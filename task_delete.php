<?php
// GET  = "Are you sure?" confirmation page      POST = actually delete
require __DIR__ . '/includes/bootstrap.php';
$user = require_login();

$id = (int)($_REQUEST['id'] ?? 0);
$task = task_find_with_owner($id);

// Owner may delete; admin may delete anything (moderation). Everyone else gets the same 404.
if ($task === null || !can_delete_task($task, $user)) {
    abort(404, 'That task does not exist, or it is not yours.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_with_csrf();
    task_delete($id);
    flash('success', 'Task “' . $task['title'] . '” was deleted.');
    $back = (string)($_POST['back'] ?? '');
    redirect(in_array($back, ['admin.php', 'board.php'], true) ? $back : 'board.php');
}

$back = ((int)$task['user_id'] !== (int)$user['id']) ? 'admin.php' : 'board.php';
render_header('Delete task');
?>
<section class="card narrow center danger-card">
  <p class="big-code">🗑</p>
  <h1>Delete this task?</h1>
  <p><strong><?= e($task['title']) ?></strong></p>
  <?php if ($back === 'admin.php'): ?><p class="muted small">Owned by <?= e($task['owner_name']) ?> (you are deleting this as an administrator).</p><?php endif; ?>
  <p class="muted">This cannot be undone.</p>
  <form method="post" class="actions center-actions">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int)$task['id'] ?>">
    <input type="hidden" name="back" value="<?= e($back) ?>">
    <button class="btn danger" type="submit">Yes, delete it</button>
    <a class="btn" href="<?= e($back) ?>">Keep it</a>
  </form>
</section>
<?php render_footer();
