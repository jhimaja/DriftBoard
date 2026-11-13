<?php
// One page for BOTH "add task" and "edit task".  task_form.php  = new,  task_form.php?id=5  = edit
require __DIR__ . '/includes/bootstrap.php';
$user = require_login();

$id = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;
$task = null;
if ($id > 0) {
    // Ownership is checked in SQL. Someone else's id and a non-existent id look identical (404).
    $task = task_find_owned($id, (int)$user['id']);
    if ($task === null) {
        abort(404, 'That task does not exist, or it is not yours.');
    }
}

$errors = [];
$values = $task ?? ['title' => '', 'description' => '', 'status' => 'pending', 'priority' => 'medium', 'due_date' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_with_csrf();
    [$errors, $clean] = validate_task($_POST);
    $values = $clean;
    if (!$errors) {
        if ($task) {
            task_update($id, (int)$user['id'], $clean);
            flash('success', 'Task “' . $clean['title'] . '” was updated.');
        } else {
            task_create((int)$user['id'], $clean);
            flash('success', 'Task “' . $clean['title'] . '” was added to your board.');
        }
        redirect('board.php');
    }
}

render_header($task ? 'Edit task' : 'New task');
?>
<section class="card narrow">
  <h1><?= $task ? 'Edit task' : 'Add a task' ?></h1>
  <form method="post" novalidate data-validate>
    <?= csrf_field() ?>
    <?php if ($task): ?><input type="hidden" name="id" value="<?= (int)$task['id'] ?>"><?php endif; ?>

    <label>Title <span class="req">*</span>
      <input name="title" value="<?= e($values['title']) ?>" maxlength="150" required data-label="Title" autofocus>
      <?= field_error($errors, 'title') ?>
    </label>

    <label>Description
      <textarea name="description" rows="5" maxlength="2000" data-counter="2000"><?= e($values['description']) ?></textarea>
      <span class="hint counter"></span>
      <?= field_error($errors, 'description') ?>
    </label>

    <div class="grid3">
      <label>Status
        <select name="status">
          <?php foreach (STATUSES as $k => $label): ?>
            <option value="<?= e($k) ?>" <?= $values['status'] === $k ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
        <?= field_error($errors, 'status') ?>
      </label>
      <label>Priority
        <select name="priority">
          <?php foreach (PRIORITIES as $k => $label): ?>
            <option value="<?= e($k) ?>" <?= $values['priority'] === $k ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
        <?= field_error($errors, 'priority') ?>
      </label>
      <label>Due date
        <input type="date" name="due_date" value="<?= e($values['due_date'] ?? '') ?>">
        <?= field_error($errors, 'due_date') ?>
      </label>
    </div>

    <div class="actions">
      <button class="btn primary" type="submit"><?= $task ? 'Save changes' : 'Add task' ?></button>
      <a class="btn" href="board.php">Cancel</a>
    </div>
  </form>
</section>
<?php render_footer();
