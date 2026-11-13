<?php
require __DIR__ . '/includes/bootstrap.php';
if (current_user()) {
    redirect('board.php');
}

$errors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_with_csrf();
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $password = (string)($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $errors['form'] = 'Please enter your email and password.';
    } elseif (too_many_failed_logins($email)) {
        $errors['form'] = 'Too many failed attempts. Please wait ' . LOGIN_WINDOW_MINUTES . ' minutes and try again.';
    } else {
        $stmt = db()->prepare('SELECT id, name, email, password_hash, role, is_active FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Always run password_verify (even for unknown emails) so response time doesn't reveal which emails exist.
        $hash = $user['password_hash'] ?? '$2y$10$/D/3UH/z.IlgHnfp8dciWeP0XAy9QN/.m5DkhrjoTO1boVjIXhCvy';
        $ok = password_verify($password, $hash) && $user !== false;

        if ($ok && (int)$user['is_active'] === 1) {
            clear_failed_logins($email);
            if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
                db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                    ->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
            }
            login_user($user);
            flash('success', 'Welcome back, ' . $user['name'] . '!');
            redirect('board.php');
        } elseif ($ok) {
            $errors['form'] = 'This account has been disabled. Please contact an administrator.';
        } else {
            record_failed_login($email);
            $errors['form'] = 'Email or password is incorrect.'; // same message either way
        }
    }
}

render_header('Log in', 'auth-page');
?>
<section class="auth-wrap">
  <div class="auth-art">
    <h2>Keep work moving,<br>one card at a time.</h2>
    <p>Drag tasks across <strong>Pending → In&nbsp;Progress → Completed</strong> and watch the board drift toward done.</p>
  </div>
  <div class="card auth-card">
    <h1>Log in</h1>
    <?php if (isset($errors['form'])): ?><div class="flash flash-error"><?= e($errors['form']) ?></div><?php endif; ?>
    <form method="post" novalidate data-validate>
      <?= csrf_field() ?>
      <label>Email
        <input type="email" name="email" value="<?= e($email) ?>" autocomplete="email" required data-label="Email">
      </label>
      <label>Password
        <input type="password" name="password" autocomplete="current-password" required data-label="Password">
      </label>
      <button class="btn primary block" type="submit">Log in</button>
    </form>
    <p class="muted small">New here? <a href="register.php">Create an account</a></p>
  </div>
</section>
<?php render_footer();
