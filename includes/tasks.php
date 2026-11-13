<?php
declare(strict_types=1);

/* =====================================================================
 * Task data layer. EVERY function that touches a member's task takes the
 * user id and puts "user_id = ?" in the SQL itself, so ownership is
 * enforced by the database query - not by hiding buttons in the HTML.
 * ===================================================================== */

/** Validate + clean task form input. Returns [errors, cleanValues]. */
function validate_task(array $in): array
{
    $errors = [];
    $title = trim((string)($in['title'] ?? ''));
    $desc  = trim((string)($in['description'] ?? ''));
    $status = (string)($in['status'] ?? 'pending');
    $priority = (string)($in['priority'] ?? 'medium');
    $due = trim((string)($in['due_date'] ?? ''));

    if ($title === '') {
        $errors['title'] = 'Please give the task a title.';
    } elseif (mb_strlen($title) > 150) {
        $errors['title'] = 'The title can be at most 150 characters.';
    }
    if (mb_strlen($desc) > 2000) {
        $errors['description'] = 'The description can be at most 2000 characters.';
    }
    if (!isset(STATUSES[$status])) {
        $errors['status'] = 'Please choose a valid status.';
    }
    if (!isset(PRIORITIES[$priority])) {
        $errors['priority'] = 'Please choose a valid priority.';
    }
    if ($due !== '') {
        $d = DateTime::createFromFormat('Y-m-d', $due);
        if (!$d || $d->format('Y-m-d') !== $due) {
            $errors['due_date'] = 'Please enter a valid date.';
        }
    }
    return [$errors, [
        'title' => $title, 'description' => $desc, 'status' => $status,
        'priority' => $priority, 'due_date' => $due === '' ? null : $due,
    ]];
}

function task_create(int $userId, array $t): int
{
    $stmt = db()->prepare(
        'INSERT INTO tasks (user_id, title, description, status, priority, due_date) VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$userId, $t['title'], $t['description'], $t['status'], $t['priority'], $t['due_date']]);
    return (int)db()->lastInsertId();
}

/** Returns true only if the task exists AND belongs to $userId. */
function task_update(int $id, int $userId, array $t): bool
{
    $stmt = db()->prepare(
        'UPDATE tasks SET title = ?, description = ?, status = ?, priority = ?, due_date = ?
          WHERE id = ? AND user_id = ?'
    );
    $stmt->execute([$t['title'], $t['description'], $t['status'], $t['priority'], $t['due_date'], $id, $userId]);
    // rowCount() is 0 when nothing changed, so confirm ownership separately
    return task_find_owned($id, $userId) !== null;
}

function task_set_status(int $id, int $userId, string $status): bool
{
    if (!isset(STATUSES[$status])) {
        return false;
    }
    $stmt = db()->prepare('UPDATE tasks SET status = ? WHERE id = ? AND user_id = ?');
    $stmt->execute([$status, $id, $userId]);
    return task_find_owned($id, $userId) !== null;
}

function task_find_owned(int $id, int $userId): ?array
{
    $stmt = db()->prepare('SELECT * FROM tasks WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, $userId]);
    return $stmt->fetch() ?: null;
}

/** Admin / delete-confirmation lookup: includes owner's name. Callers must check permission. */
function task_find_with_owner(int $id): ?array
{
    $stmt = db()->prepare('SELECT t.*, u.name AS owner_name FROM tasks t JOIN users u ON u.id = t.user_id WHERE t.id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function can_delete_task(array $task, array $user): bool
{
    return (int)$task['user_id'] === (int)$user['id'] || is_admin($user);
}

function task_delete(int $id): void
{
    // Permission is decided by the caller via can_delete_task().
    db()->prepare('DELETE FROM tasks WHERE id = ?')->execute([$id]);
}

/** Reads ?q= &status= &priority= from the URL and sanitises them. */
function read_filters(): array
{
    $q = trim((string)($_GET['q'] ?? ''));
    $status = (string)($_GET['status'] ?? '');
    $priority = (string)($_GET['priority'] ?? '');
    return [
        'q'        => mb_substr($q, 0, 100),
        'status'   => isset(STATUSES[$status]) ? $status : '',
        'priority' => isset(PRIORITIES[$priority]) ? $priority : '',
    ];
}

/** Builds the shared WHERE clause (always scoped to one user). */
function filter_sql(int $userId, array $f): array
{
    $where = ['user_id = ?'];
    $params = [$userId];
    if ($f['q'] !== '') {
        $where[] = "title LIKE ? ESCAPE '\\\\'";
        $params[] = '%' . addcslashes($f['q'], '%_\\') . '%';
    }
    if ($f['status'] !== '') {
        $where[] = 'status = ?';
        $params[] = $f['status'];
    }
    if ($f['priority'] !== '') {
        $where[] = 'priority = ?';
        $params[] = $f['priority'];
    }
    return [implode(' AND ', $where), $params];
}

const TASK_ORDER = "FIELD(priority,'high','medium','low'), (due_date IS NULL), due_date, created_at DESC";

function tasks_search(int $userId, array $f, int $limit, int $offset = 0): array
{
    [$where, $params] = filter_sql($userId, $f);
    $sql = "SELECT * FROM tasks WHERE $where ORDER BY " . TASK_ORDER . ' LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset;
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function tasks_count(int $userId, array $f): int
{
    [$where, $params] = filter_sql($userId, $f);
    $stmt = db()->prepare("SELECT COUNT(*) FROM tasks WHERE $where");
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}

/** Dashboard numbers for one user. */
function task_stats(int $userId): array
{
    $stmt = db()->prepare(
        "SELECT COUNT(*) AS total,
                COALESCE(SUM(status='pending'),0)     AS pending,
                COALESCE(SUM(status='in_progress'),0) AS in_progress,
                COALESCE(SUM(status='completed'),0)   AS completed,
                COALESCE(SUM(status <> 'completed' AND due_date < CURDATE()),0) AS overdue
           FROM tasks WHERE user_id = ?"
    );
    $stmt->execute([$userId]);
    return array_map('intval', $stmt->fetch());
}
