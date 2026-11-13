<?php
declare(strict_types=1);

/**
 * ROLES & PERMISSIONS (explained in README):
 *   member - can create/read/update/delete ONLY their own tasks.
 *   admin  - everything a member can on their own tasks, PLUS:
 *            see all users, enable/disable accounts, view and delete ANY task (moderation).
 *            An admin can NOT edit someone else's task content (least privilege).
 */

/** The logged-in user row, or null. Re-read from the DB on every request so that
 *  disabling an account or changing a role takes effect immediately. */
function current_user(): ?array
{
    static $user = false;
    if ($user !== false) {
        return $user;
    }
    $user = null;
    if (!empty($_SESSION['uid'])) {
        $stmt = db()->prepare('SELECT id, name, email, role, is_active FROM users WHERE id = ?');
        $stmt->execute([(int)$_SESSION['uid']]);
        $row = $stmt->fetch();
        if ($row && (int)$row['is_active'] === 1) {
            $user = $row;
        } else {
            unset($_SESSION['uid']);
        }
    }
    return $user;
}

function is_admin(?array $user): bool
{
    return $user !== null && $user['role'] === 'admin';
}

function require_login(): array
{
    $user = current_user();
    if ($user === null) {
        flash('error', 'Please log in to continue.');
        redirect('login.php');
    }
    return $user;
}

function require_admin(): array
{
    $user = require_login();
    if (!is_admin($user)) {
        abort(403, 'Only administrators can open this page.');
    }
    return $user;
}

function login_user(array $user): void
{
    session_regenerate_id(true); // prevents session fixation
    $_SESSION['uid'] = (int)$user['id'];
    $_SESSION['last_seen'] = time();
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/* ---------- brute-force protection ---------- */
function too_many_failed_logins(string $email): bool
{
    $stmt = db()->prepare(
        'SELECT COUNT(*) FROM login_attempts
          WHERE email = ? AND ip = ? AND attempted_at > (NOW() - INTERVAL ' . (int)LOGIN_WINDOW_MINUTES . ' MINUTE)'
    );
    $stmt->execute([$email, client_ip()]);
    return (int)$stmt->fetchColumn() >= LOGIN_MAX_FAILS;
}

function record_failed_login(string $email): void
{
    db()->prepare('INSERT INTO login_attempts (email, ip) VALUES (?, ?)')->execute([$email, client_ip()]);
}

function clear_failed_logins(string $email): void
{
    db()->prepare('DELETE FROM login_attempts WHERE email = ? AND ip = ?')->execute([$email, client_ip()]);
}

function client_ip(): string
{
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 0, 45);
}
