<?php
require __DIR__ . '/includes/bootstrap.php';
if (current_user()) {
    redirect('board.php');
}

$errors = [];
$name = $email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_with_csrf();
    $name = trim((string)($_POST['name'] ?? ''));
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['confirm'] ?? '');

    if ($name === '' || mb_strlen($name) < 2 || mb_strlen($name) > 60) {
        $errors['name'] = 'Please enter your name (2 to 60 characters).';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 120) {
        $errors['email'] = 'Please enter a valid email address.';
    }
    if (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        $errors['password'] = 'Use at least 8 characters, with a letter and a number.';
    } elseif (strlen($password) > 72) {
        $errors['password'] = 'Password can be at most 72 characters.';
    }
    if ($password !== $confirm) {
        $errors['confirm'] = 'The two passwords do not match.';
    }

    if (!$errors) {
        try {
            // Role is NEVER read from the request: everyone who registers is a plain "member".
            db()->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, \'member\')')
                ->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
            flash('success', 'Account created! You can log in now.');
            redirect('login.php');
        } catch (PDOException $ex) {
            if ($ex->getCode() === '23000') { // duplicate key on the UNIQUE email index
                $errors['email'] = 'That email is already registered. Try logging in instead.';
            } else {
                throw $ex;
            }
        }
    }
}

render_header('Create account', 'auth-page');
?>
<section class="auth-wrap">
  <div class="auth-art">
    <h2>Your tasks.<br>Your board.<br>Nobody else's.</h2>
    <p>Every account gets a private board. Other users can never see or change your cards.</p>
  </div>
  <div class="card auth-card">
    <h1>Create account</h1>
    <form method="post" novalidate data-validate>
      <?= csrf_field() ?>
      <label>Name
        <input name="name" value="<?= e($name) ?>" maxlength="60" required data-label="Name" data-min="2">
        <?= field_error($errors, 'name') ?>
      </label>
      <label>Email
        <input type="email" name="email" value="<?= e($email) ?>" maxlength="120" required data-label="Email">
        <?= field_error($errors, 'email') ?>
      </label>
      <label>Password
        <input type="password" name="password" autocomplete="new-password" required data-label="Password" data-password>
        <?= field_error($errors, 'password') ?>
        <span class="hint">At least 8 characters, with a letter and a number.</span>
      </label>
      <label>Confirm password
        <input type="password" name="confirm" autocomplete="new-password" required data-label="Confirmation" data-match="password">
        <?= field_error($errors, 'confirm') ?>
      </label>
      <button class="btn primary block" type="submit">Create account</button>
    </form>
    <p class="muted small">Already registered? <a href="login.php">Log in</a></p>
  </div>
</section>
<?php render_footer();
