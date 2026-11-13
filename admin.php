<?php
// Administrator console: users + moderation of all tasks.
require __DIR__ . '/includes/bootstrap.php';
$admin = require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_with_csrf();
    $targetId = (int)($_POST['user_id'] ?? 0);
    if ($targetId === (int)$admin['id']) {
        flash('error', 'You cannot disable your own account.');
    } else {
        $stmt = db()->prepare('UPDATE users SET is_active = 1 - is_active WHERE id = ?');
        $stmt->execute([$targetId]);
        flash('success', 'User status updated.');
    }
    redirect('admin.php');
}

$users = db()->query(
    "SELECT u.id, u.name, u.email, u.role, u.is_active, u.created_at, COUNT(t.id) AS task_count
       FROM users u LEFT JOIN tasks t ON t.user_id = u.id
   GROUP BY u.id ORDER BY u.created_at"
)->fetchAll();

$all = db()->query(
    "SELECT t.*, u.name AS owner_name FROM tasks t JOIN users u ON u.id = t.user_id
  ORDER BY t.created_at DESC LIMIT 50"
)->fetchAll();

render_header('Admin');
?>
<section class="hero"><div><p class="eyebrow">Administrator</p><h1>Control room</h1></div></section>

<section class="card">
  <h2>Users</h2>
  <div class="table-wrap"><table class="list">
    <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Tasks</th><th>Joined</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($users as $u): ?>
      <tr>
        <td><strong><?= e($u['name']) ?></strong></td>
        <td><?= e($u['email']) ?></td>
        <td><span class="chip"><?= e($u['role']) ?></span></td>
        <td><?= (int)$u['task_count'] ?></td>
        <td class="muted small"><?= e(fmt_date($u['created_at'])) ?></td>
        <td>
          <?php if ((int)$u['id'] === (int)$admin['id']): ?>
            <span class="muted small">You</span>
          <?php else: ?>
          <form method="post" class="inline"><?= csrf_field() ?>
            <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
            <button class="btn small <?= $u['is_active'] ? '' : 'primary' ?>" type="submit"><?= $u['is_active'] ? 'Disable' : 'Enable' ?></button>
            <span class="chip <?= $u['is_active'] ? 'st-completed' : 'due overdue' ?>"><?= $u['is_active'] ? 'Active' : 'Disabled' ?></span>
          </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
</section>

<section class="card">
  <h2>Latest tasks (all users)</h2>
  <p class="muted small">Admins can review and remove any task, but cannot edit another person’s task content.</p>
  <div class="table-wrap"><table class="list">
    <thead><tr><th>Title</th><th>Owner</th><th>Status</th><th>Created</th><th><span class="sr">Actions</span></th></tr></thead>
    <tbody>
    <?php foreach ($all as $t): ?>
      <tr>
        <td><?= e($t['title']) ?></td>
        <td><?= e($t['owner_name']) ?></td>
        <td><span class="chip st-<?= e($t['status']) ?>"><?= e(STATUSES[$t['status']]) ?></span></td>
        <td class="muted small"><?= e(fmt_date($t['created_at'])) ?></td>
        <td class="row-actions"><a class="icon-btn danger-text" href="task_delete.php?id=<?= (int)$t['id'] ?>" aria-label="Delete task" title="Delete">🗑</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
</section>
<?php render_footer();
