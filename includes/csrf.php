<?php
declare(strict_types=1);

/**
 * CSRF protection (synchroniser-token pattern):
 * a random token lives in the session, every state-changing form carries it
 * in a hidden field, and the server rejects the request if they don't match.
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/** Call at the top of every script that changes data. Also enforces POST. */
function require_post_with_csrf(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        abort(405, 'This action only accepts form submissions.');
    }
    $sent = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        abort(419, 'Your form expired or looked suspicious. Please go back, refresh the page and try again.');
    }
}
