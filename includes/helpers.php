<?php
declare(strict_types=1);


/* Fallbacks so the app still works if the PHP "mbstring" extension is switched off. */
if (!function_exists('mb_strlen')) {
    function mb_strlen(string $s): int { return preg_match_all('/./us', $s) ?: 0; }
}
if (!function_exists('mb_substr')) {
    function mb_substr(string $s, int $start, ?int $len = null): string
    {
        preg_match_all('/./us', $s, $m);
        return implode('', array_slice($m[0], $start, $len));
    }
}
if (!function_exists('mb_strtoupper')) {
    function mb_strtoupper(string $s): string { return strtoupper($s); }
}

const STATUSES = [
    'pending'     => 'Pending',
    'in_progress' => 'In Progress',
    'completed'   => 'Completed',
];
const PRIORITIES = ['low' => 'Low', 'medium' => 'Medium', 'high' => 'High'];

/** Escape anything shown in HTML (XSS protection). Use e() on EVERY user-supplied value. */
function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
}

/* ---------- one-time flash messages ---------- */
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function pull_flashes(): array
{
    $all = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $all;
}

/* ---------- small formatting helpers ---------- */
function fmt_date(?string $date): string
{
    if (!$date) {
        return '';
    }
    $ts = strtotime($date);
    return $ts ? date('d M Y', $ts) : '';
}

/** Returns: 'overdue' | 'today' | 'soon' | '' for a due date of an unfinished task. */
function due_state(?string $due, string $status): string
{
    if (!$due || $status === 'completed') {
        return '';
    }
    $today = date('Y-m-d');
    if ($due < $today) {
        return 'overdue';
    }
    if ($due === $today) {
        return 'today';
    }
    if ($due <= date('Y-m-d', strtotime('+3 days'))) {
        return 'soon';
    }
    return '';
}

function excerpt(string $text, int $len = 110): string
{
    $text = trim(preg_replace('/\s+/', ' ', $text));
    return mb_strlen($text) > $len ? mb_substr($text, 0, $len - 1) . '…' : $text;
}

/** Keeps the current filters when building links, e.g. url_with(['page' => 2]). */
function url_with(array $changes, string $base = 'board.php'): string
{
    $params = array_merge($_GET, $changes);
    $params = array_filter($params, fn($v) => $v !== '' && $v !== null);
    return $base . ($params ? '?' . http_build_query($params) : '');
}

/** Stops the request with a friendly error page (403, 404, 405, 419 ...). */
function abort(int $code, string $message): never
{
    http_response_code($code);
    $titles = [403 => 'Not allowed', 404 => 'Not found', 405 => 'Wrong request type', 419 => 'Session expired'];
    render_header($titles[$code] ?? 'Error');
    echo '<section class="card narrow center"><p class="big-code">' . (int)$code . '</p>'
        . '<h1>' . e($titles[$code] ?? 'Something went wrong') . '</h1>'
        . '<p class="muted">' . e($message) . '</p>'
        . '<p><a class="btn" href="index.php">Back to safety</a></p></section>';
    render_footer();
    exit;
}
