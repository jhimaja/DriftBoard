<?php
require __DIR__ . '/includes/bootstrap.php';
$user = require_login();
$uid = (int)$user['id'];

$f = read_filters();
$view = (($_GET['view'] ?? 'board') === 'list') ? 'list' : 'board';
$stats = task_stats($uid);
$filtered = ($f['q'] !== '' || $f['status'] !== '' || $f['priority'] !== '');
$donePct = $stats['total'] > 0 ? (int)round($stats['completed'] / $stats['total'] * 100) : 0;

if ($view === 'list') {
    $total = tasks_count($uid, $f);
    $pages = max(1, (int)ceil($total / TASKS_PER_PAGE));
    $page = min(max(1, (int)($_GET['page'] ?? 1)), $pages);
    $tasks = tasks_search($uid, $f, TASKS_PER_PAGE, ($page - 1) * TASKS_PER_PAGE);
} else {
    $tasks = tasks_search($uid, $f, 300);
    $byStatus = array_fill_keys(array_keys(STATUSES), []);
    foreach ($tasks as $t) {
        $byStatus[$t['status']][] = $t;
    }
}

/** Little ← / → buttons that work even without JavaScript. */
function move_buttons(array $t): string
{
    $keys = array_keys(STATUSES);
    $i = array_search($t['status'], $keys, true);
    $out = '';
    foreach ([-1 => ['←', 'Move back'], 1 => ['→', 'Move forward']] as $dir => [$sym, $label]) {
        if (isset($keys[$i + $dir])) {
            $target = $keys[$i + $dir];
            $out .= '<form method="post" action="task_status.php" class="inline">' . csrf_field()
                . '<input type="hidden" name="id" value="' . (int)$t['id'] . '">'
                . '<input type="hidden" name="status" value="' . e($target) . '">'
                . '<input type="hidden" name="back" value="' . e(url_with([])) . '">'
                . '<button class="icon-btn" type="submit" title="' . e($label . ' to ' . STATUSES[$target]) . '" aria-label="' . e($label . ' to ' . STATUSES[$target]) . '">' . $sym . '</button></form>';
        }
    }
    return $out;
}

function due_chip(array $t): string
{
    if (!$t['due_date']) {
        return '';
    }
    $s = due_state($t['due_date'], $t['status']);
    $label = ['overdue' => 'Overdue · ', 'today' => 'Due today · ', 'soon' => 'Due soon · '][$s] ?? 'Due ';
    return '<span class="chip due ' . e($s) . '">' . e($label . fmt_date($t['due_date'])) . '</span>';
}

function task_card(array $t): void
{ ?>
  <article class="task prio-<?= e($t['priority']) ?>" draggable="true" data-id="<?= (int)$t['id'] ?>" data-status="<?= e($t['status']) ?>">
    <div class="task-top">
      <h3><?= e($t['title']) ?></h3>
      <span class="chip prio"><?= e(PRIORITIES[$t['priority']]) ?></span>
    </div>
    <?php if ($t['description'] !== ''): ?><p class="task-desc"><?= e(excerpt($t['description'])) ?></p><?php endif; ?>
    <div class="task-meta">
      <?= due_chip($t) ?>
      <span class="muted small">Added <?= e(fmt_date($t['created_at'])) ?></span>
    </div>
    <div class="task-actions">
      <?= move_buttons($t) ?>
      <span class="spacer"></span>
      <a class="icon-btn" href="task_form.php?id=<?= (int)$t['id'] ?>" title="Edit" aria-label="Edit task">✎</a>
      <a class="icon-btn danger-text" href="task_delete.php?id=<?= (int)$t['id'] ?>" title="Delete" aria-label="Delete task">🗑</a>
    </div>
  </article>
<?php }

render_header('Board', 'board-page');
?>
<section class="hero">
  <div>
    <p class="eyebrow"><?= e(date('l, d F')) ?></p>
    <h1>Hi <?= e(explode(' ', $user['name'])[0]) ?>, here’s your drift.</h1>
  </div>
  <a class="btn primary" href="task_form.php">+ New task</a>
</section>

<section class="stats" aria-label="Summary">
  <div class="stat"><span class="num"><?= $stats['total'] ?></span><span class="lbl">Total</span></div>
  <div class="stat s-pending"><span class="num"><?= $stats['pending'] ?></span><span class="lbl">Pending</span></div>
  <div class="stat s-progress"><span class="num"><?= $stats['in_progress'] ?></span><span class="lbl">In progress</span></div>
  <div class="stat s-done"><span class="num"><?= $stats['completed'] ?></span><span class="lbl">Completed</span></div>
  <div class="stat s-over"><span class="num"><?= $stats['overdue'] ?></span><span class="lbl">Overdue</span></div>
  <div class="stat progress-stat">
    <span class="lbl">Overall progress · <?= $donePct ?>%</span>
    <progress max="100" value="<?= $donePct ?>"><?= $donePct ?>%</progress>
  </div>
</section>

<form class="filters" method="get" action="board.php" role="search">
  <input type="hidden" name="view" value="<?= e($view) ?>">
  <input type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Search task titles…" aria-label="Search by title" maxlength="100">
  <select name="status" aria-label="Filter by status">
    <option value="">All statuses</option>
    <?php foreach (STATUSES as $k => $label): ?><option value="<?= e($k) ?>" <?= $f['status'] === $k ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
  </select>
  <select name="priority" aria-label="Filter by priority">
    <option value="">Any priority</option>
    <?php foreach (PRIORITIES as $k => $label): ?><option value="<?= e($k) ?>" <?= $f['priority'] === $k ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
  </select>
  <button class="btn" type="submit">Apply</button>
  <?php if ($filtered): ?><a class="btn ghost" href="<?= e('board.php?view=' . $view) ?>">Clear</a><?php endif; ?>
  <span class="spacer"></span>
  <div class="seg" role="group" aria-label="View">
    <a class="<?= $view === 'board' ? 'on' : '' ?>" href="<?= e(url_with(['view' => 'board', 'page' => ''])) ?>">Board</a>
    <a class="<?= $view === 'list' ? 'on' : '' ?>" href="<?= e(url_with(['view' => 'list', 'page' => ''])) ?>">List</a>
  </div>
</form>

<?php if ($stats['total'] === 0): ?>
  <section class="card center empty">
    <p class="big-code">✦</p><h2>Your board is empty</h2>
    <p class="muted">Add your first task and it will appear in the Pending column.</p>
    <a class="btn primary" href="task_form.php">Add a task</a>
  </section>

<?php elseif ($view === 'board'): ?>
  <?php if ($filtered && !$tasks): ?><p class="muted notice">No tasks match your search or filters.</p><?php endif; ?>
  <section class="board" data-csrf="<?= e(csrf_token()) ?>">
    <?php foreach (STATUSES as $key => $label):
        if ($f['status'] !== '' && $f['status'] !== $key) { continue; } ?>
      <div class="column col-<?= e($key) ?>" data-status="<?= e($key) ?>">
        <header><h2><?= e($label) ?></h2><span class="count"><?= count($byStatus[$key]) ?></span></header>
        <div class="dropzone">
          <?php foreach ($byStatus[$key] as $t) { task_card($t); } ?>
          <?php if (!$byStatus[$key]): ?><p class="col-empty">Nothing here</p><?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </section>
  <p class="muted small tip">Tip: drag a card to another column to change its status.</p>

<?php else: ?>
  <?php if (!$tasks): ?>
    <p class="muted notice">No tasks match your search or filters.</p>
  <?php else: ?>
  <div class="table-wrap">
    <table class="list">
      <thead><tr><th>Title</th><th>Status</th><th>Priority</th><th>Due</th><th>Created</th><th><span class="sr">Actions</span></th></tr></thead>
      <tbody>
      <?php foreach ($tasks as $t): ?>
        <tr>
          <td><strong><?= e($t['title']) ?></strong><?php if ($t['description'] !== ''): ?><br><span class="muted small"><?= e(excerpt($t['description'], 80)) ?></span><?php endif; ?></td>
          <td><span class="chip st-<?= e($t['status']) ?>"><?= e(STATUSES[$t['status']]) ?></span></td>
          <td><span class="chip prio p-<?= e($t['priority']) ?>"><?= e(PRIORITIES[$t['priority']]) ?></span></td>
          <td><?= $t['due_date'] ? due_chip($t) : '<span class="muted">—</span>' ?></td>
          <td class="muted small"><?= e(fmt_date($t['created_at'])) ?></td>
          <td class="row-actions"><?= move_buttons($t) ?>
            <a class="icon-btn" href="task_form.php?id=<?= (int)$t['id'] ?>" aria-label="Edit task" title="Edit">✎</a>
            <a class="icon-btn danger-text" href="task_delete.php?id=<?= (int)$t['id'] ?>" aria-label="Delete task" title="Delete">🗑</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pages > 1): ?>
  <nav class="pager" aria-label="Pages">
    <?php if ($page > 1): ?><a class="btn" href="<?= e(url_with(['page' => $page - 1])) ?>">← Prev</a><?php endif; ?>
    <span class="muted">Page <?= $page ?> of <?= $pages ?> · <?= $total ?> tasks</span>
    <?php if ($page < $pages): ?><a class="btn" href="<?= e(url_with(['page' => $page + 1])) ?>">Next →</a><?php endif; ?>
  </nav>
  <?php endif; ?>
  <?php endif; ?>
<?php endif; ?>
<?php render_footer();
