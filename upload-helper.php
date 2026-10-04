<?php
/**
 * upload-helper.php - Safe receipt upload
 *
 * What the old code did wrong: it kept the extension the CLIENT chose, so an
 * attacker could upload "shell.php" and run it. This helper:
 *   - ignores the client's filename and MIME type completely
 *   - detects the REAL type from file contents (finfo)
 *   - allows only JPG / PNG / WEBP / PDF, max 5 MB
 *   - builds the extension itself from the detected type
 *   - uses an unguessable random filename
 *   - drops an .htaccess in uploads/ that blocks script execution (defence in depth)
 */

class UploadRejected extends RuntimeException {}

const RECEIPT_MAX_BYTES = 5 * 1024 * 1024;

/**
 * @param array|null $file one entry of $_FILES
 * @return string|null relative path like "uploads/receipt_ab12....jpg", or null if no file was sent
 * @throws UploadRejected when a file was sent but is not acceptable
 */
function saveReceiptUpload(?array $file): ?string
{
    if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $reject = 'ឯកសារមិនត្រឹមត្រូវ។ អនុញ្ញាតតែ JPG, PNG, WEBP ឬ PDF ហើយទំហំមិនលើស 5MB។';

    if (!is_int($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        throw new UploadRejected($reject);
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        throw new UploadRejected($reject);
    }
    $size = (int)($file['size'] ?? 0);
    if ($size <= 0 || $size > RECEIPT_MAX_BYTES) {
        throw new UploadRejected($reject);
    }

    $allowed = [
        'image/jpeg'      => 'jpg',
        'image/png'       => 'png',
        'image/webp'      => 'webp',
        'application/pdf' => 'pdf',
    ];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!is_string($mime) || !isset($allowed[$mime])) {
        throw new UploadRejected($reject);
    }
    if (strpos($mime, 'image/') === 0 && @getimagesize($file['tmp_name']) === false) {
        throw new UploadRejected($reject); // claims to be an image but is not decodable
    }

    $dir = __DIR__ . '/uploads/';
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        error_log('Cannot create uploads directory');
        throw new UploadRejected($reject);
    }
    $htaccess = $dir . '.htaccess';
    if (!is_file($htaccess)) {
        @file_put_contents($htaccess,
            "# Block script execution in the uploads folder\n" .
            "<IfModule mod_authz_core.c>\n" .
            "  <FilesMatch \"(?i)\\.(php[0-9]?|phtml|phar|pl|py|cgi|sh)$\">\n    Require all denied\n  </FilesMatch>\n" .
            "</IfModule>\n");
    }

    $name = 'receipt_' . bin2hex(random_bytes(12)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], $dir . $name)) {
        throw new UploadRejected($reject);
    }
    @chmod($dir . $name, 0644);

    return 'uploads/' . $name;
}
