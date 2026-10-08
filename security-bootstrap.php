<?php
/**
 * security-bootstrap.php
 * Load this FIRST in every entry point (before any output). It replaces the
 * scattered `session_start()` calls and gives every page the same protections:
 *
 *   1. Secure session cookie (HttpOnly, Secure on HTTPS, SameSite=Strict)
 *   2. Idle + absolute session timeouts
 *   3. Security headers (CSP, HSTS, anti-clickjacking, nosniff ...)
 *   4. Same-origin check on every state-changing request (CSRF stop-gap)
 *   5. Helpers: sec_client_ip(), sec_login_session(), sec_destroy_session()
 */

if (defined('SECURITY_BOOTSTRAP_LOADED')) {
    return;
}
define('SECURITY_BOOTSTRAP_LOADED', true);

if (!defined('SESSION_IDLE_TTL'))     define('SESSION_IDLE_TTL', 1800);      // 30 min without activity
if (!defined('SESSION_ABSOLUTE_TTL')) define('SESSION_ABSOLUTE_TTL', 28800); // 8 hours max

function sec_is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    // Behind a reverse proxy (Wasmer, Cloudflare ...)
    return strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}

/** Real client IP. We deliberately do NOT trust X-Forwarded-For (it can be forged). */
function sec_client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function sec_destroy_session(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $p['path'],
            'domain'   => $p['domain'],
            'secure'   => $p['secure'],
            'httponly' => $p['httponly'],
            'samesite' => $p['samesite'] ?? 'Strict',
        ]);
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

/**
 * Call right after a successful login. Issues a NEW session id so an attacker
 * who planted a known id before login (session fixation) gets nothing.
 */
function sec_login_session(array $user): void
{
    session_regenerate_id(true);
    $_SESSION = [
        'user_id'  => (int)$user['id'],
        'username' => $user['username'],
        'role'     => $user['role'],
        '_created' => time(),
        '_last'    => time(),
    ];
}

/** Reject cross-site POST/PUT/DELETE. Primary CSRF defence is SameSite=Strict; this is a second layer. */
function sec_require_same_origin(): void
{
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
        return;
    }

    $allowed = [strtolower($_SERVER['HTTP_HOST'] ?? '')];
    $extra = getenv('APP_ALLOWED_HOSTS'); // optional: "example.com,www.example.com"
    if ($extra) {
        foreach (explode(',', $extra) as $h) {
            $allowed[] = strtolower(trim($h));
        }
    }

    foreach (['HTTP_ORIGIN', 'HTTP_REFERER'] as $header) {
        if (empty($_SERVER[$header])) {
            continue;
        }
        $parts = parse_url($_SERVER[$header]);
        $host  = strtolower($parts['host'] ?? '') . (isset($parts['port']) ? ':' . $parts['port'] : '');
        if (!in_array($host, $allowed, true)) {
            http_response_code(403);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['status' => 'error', 'message' => 'Forbidden: cross-site request blocked.']);
            exit;
        }
        return; // header present and matches
    }
    // Neither header present (old browser / privacy tool): allowed, SameSite cookie still protects us.
}

// ---- CLI scripts (e.g. create-admin.php) need none of the web-only parts ----
if (PHP_SAPI === 'cli') {
    return;
}

// 1. Never show PHP errors to visitors; log them instead.
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// 2. Security headers
if (!headers_sent()) {
    header_remove('X-Powered-By');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'self'; "
        . "script-src 'self' 'unsafe-inline'; "
        . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
        . "font-src 'self' https://fonts.gstatic.com data:; "
        . "img-src 'self' data: https://api.qrserver.com; "
        . "connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; "
        . "form-action 'self'; object-src 'none'");
    if (sec_is_https()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

// 3. Hardened session
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');   // refuse session ids the server did not create
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    session_name('TRKSESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => sec_is_https(),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();

    $now = time();
    if (isset($_SESSION['_created'])) {
        $expired = ($now - $_SESSION['_created'] > SESSION_ABSOLUTE_TTL)
                || ($now - ($_SESSION['_last'] ?? $now) > SESSION_IDLE_TTL);
        if ($expired) {
            sec_destroy_session();
            session_start();
        }
    }
    $_SESSION['_created'] = $_SESSION['_created'] ?? $now;
    $_SESSION['_last']    = $now;
}

// 4. CSRF stop-gap for every state-changing request
sec_require_same_origin();
