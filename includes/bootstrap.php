<?php
declare(strict_types=1);
/** Loaded first by every page: config, safe error handling, session, security headers. */

require __DIR__ . '/config.php';

error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? '1' : '0'); // never leak paths / SQL / credentials
ini_set('log_errors', '1');

require __DIR__ . '/helpers.php';
require __DIR__ . '/layout.php';
require __DIR__ . '/db.php';
require __DIR__ . '/csrf.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/tasks.php';

// Any uncaught error (e.g. database down) -> friendly page, details only go to the server log.
set_exception_handler(function (Throwable $e): void {
    error_log('[Driftboard] ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
    }
    echo '<!doctype html><meta charset="utf-8"><title>Oops</title>'
        . '<body style="font-family:Georgia,serif;max-width:32rem;margin:4rem auto;padding:0 1rem">'
        . '<h1>Something went wrong on our side</h1>'
        . '<p>The page could not be loaded. Please try again in a moment. '
        . 'If you are setting this up: check the README (database import and config).</p></body>';
    exit;
});

// Security headers (CSP blocks injected scripts even if an escape is ever missed).
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');

session_name('driftboard_sid');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'httponly' => true,       // JavaScript cannot read the session cookie
    'samesite' => 'Lax',      // extra CSRF defence
    'secure'   => is_https(),
]);
session_start();

// Idle timeout
if (!empty($_SESSION['uid'])) {
    if (time() - (int)($_SESSION['last_seen'] ?? 0) > SESSION_IDLE_SECONDS) {
        logout_user();
        session_start();
        flash('error', 'You were logged out after a period of inactivity.');
    } else {
        $_SESSION['last_seen'] = time();
    }
}
