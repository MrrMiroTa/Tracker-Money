<?php
/**
 * report-filter.php - shared date filter for pdf.php and export-csv.php
 *
 *   ?period=day   &date=2026-10-03
 *   ?period=week  &week=2026-W40          (ISO week, Monday-Sunday)
 *   ?period=month &month=2026-10
 *   ?period=range &from=2026-10-01&to=2026-10-15   (both days included)
 *   (no period)   -> everything.   Old "?date=YYYY-MM-DD" links still work.
 *
 * Uses  t.date >= :rs AND t.date < :re  (NOT DATE(t.date)=...) so MySQL can use the
 * index idx_user_deleted_date and stays fast with hundreds of thousands of rows.
 *
 * @return string human readable label of the filter ('' = no filter)
 */
function applyReportFilter(string &$sql, array &$params, array $q): string
{
    $period = $q['period'] ?? (isset($q['date']) ? 'day' : 'all');
    $fail = function (string $msg) {
        http_response_code(400);
        header('Content-Type: text/plain; charset=UTF-8');
        exit($msg);
    };
    $isDate = fn($s) => is_string($s) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $s) && checkdate((int)substr($s, 5, 2), (int)substr($s, 8, 2), (int)substr($s, 0, 4));

    switch ($period) {
        case 'day':
            if (!$isDate($q['date'] ?? null)) $fail('Invalid date (use YYYY-MM-DD)');
            $s = new DateTime($q['date']);
            $e = (clone $s)->modify('+1 day');
            break;
        case 'week':
            if (!preg_match('/^(\d{4})-W(\d{2})$/', $q['week'] ?? '', $m) || (int)$m[2] < 1 || (int)$m[2] > 53) $fail('Invalid week (use YYYY-Www)');
            $s = (new DateTime())->setISODate((int)$m[1], (int)$m[2])->setTime(0, 0);
            $e = (clone $s)->modify('+7 day');
            break;
        case 'month':
            if (!preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $q['month'] ?? '')) $fail('Invalid month (use YYYY-MM)');
            $s = new DateTime($q['month'] . '-01');
            $e = (clone $s)->modify('+1 month');
            break;
        case 'range':
            if (!$isDate($q['from'] ?? null) || !$isDate($q['to'] ?? null)) $fail('Invalid range (use from/to = YYYY-MM-DD)');
            $s = new DateTime($q['from']);
            $e = (new DateTime($q['to']))->modify('+1 day');
            if ($e <= $s) $fail('"to" must not be before "from"');
            break;
        case 'all':
            return '';
        default:
            $fail('Unknown period');
    }

    $sql .= ' AND t.date >= :rs AND t.date < :re';
    $params[':rs'] = $s->format('Y-m-d 00:00:00');
    $params[':re'] = $e->format('Y-m-d 00:00:00');
    $last = (clone $e)->modify('-1 day')->format('Y-m-d');
    return $s->format('Y-m-d') . ($last !== $s->format('Y-m-d') ? ' → ' . $last : '');
}
