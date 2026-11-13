<?php
declare(strict_types=1);

function render_header(string $title, string $bodyClass = ''): void
{
    $user = function_exists('current_user') ? current_user() : null;
    ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> · <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="assets/css/style.css">
<script src="assets/js/app.js" defer></script>
</head>
<body class="<?= e($bodyClass) ?>">
<header class="topbar">
  <a class="brand" href="index.php"><span class="brand-mark" aria-hidden="true">◐</span> <?= e(APP_NAME) ?></a>
  <?php if ($user): ?>
  <nav class="nav" aria-label="Main">
    <a href="board.php">Board</a>
    <a href="task_form.php">+ New task</a>
    <?php if (is_admin($user)): ?><a href="admin.php">Admin</a><?php endif; ?>
  </nav>
  <div class="who">
    <span class="avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($user['name'], 0, 1))) ?></span>
    <span class="who-name"><?= e($user['name']) ?><?php if (is_admin($user)): ?> <em class="role-tag">admin</em><?php endif; ?></span>
    <form method="post" action="logout.php" class="inline"><?= csrf_field() ?><button class="link-btn" type="submit">Log out</button></form>
  </div>
  <?php endif; ?>
</header>
<main class="page">
<?php foreach (pull_flashes() as $f): ?>
  <div class="flash flash-<?= e($f['type']) ?>" role="status"><?= e($f['message']) ?><button class="flash-x" type="button" aria-label="Dismiss">×</button></div>
<?php endforeach;
}

function render_footer(): void
{
    ?>
</main>
<footer class="foot">Driftboard · a task board built with PHP &amp; MySQL</footer>
</body>
</html>
<?php
}

/** Renders a small inline error under a form field. */
function field_error(array $errors, string $key): string
{
    return isset($errors[$key]) ? '<p class="field-error" data-for="' . e($key) . '">' . e($errors[$key]) . '</p>' : '';
}
