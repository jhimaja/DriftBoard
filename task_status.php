<?php
// Moves a task to another status (used by the ← → buttons and by drag & drop).
require __DIR__ . '/includes/bootstrap.php';
$user = require_login();
require_post_with_csrf();

$id = (int)($_POST['id'] ?? 0);
$status = (string)($_POST['status'] ?? '');
$wantsJson = (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch');

$ok = isset(STATUSES[$status]) && task_set_status($id, (int)$user['id'], $status);

if ($wantsJson) {
    header('Content-Type: application/json');
    http_response_code($ok ? 200 : 404);
    echo json_encode(['ok' => $ok]);
    exit;
}
if (!$ok) {
    abort(404, 'That task does not exist, or it is not yours.');
}
flash('success', 'Task moved to ' . STATUSES[$status] . '.');
$back = (string)($_POST['back'] ?? '');
// only allow going back to our own board page (prevents open-redirect tricks)
redirect(preg_match('/^board\.php(\?[A-Za-z0-9_=&%.+\-]*)?$/', $back) ? $back : 'board.php');
