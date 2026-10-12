<?php
/* ---------- Kanban board: phases (columns) and tasks ---------- */
function board_columns(): array { return rows('SELECT * FROM kanban_columns WHERE user_id = ? ORDER BY position, id', [uid()]); }
function next_position(int $col): int { return (int)row('SELECT COALESCE(MAX(position), -1) + 1 AS p FROM tasks WHERE column_id = ?', [$col])['p']; }

function kanban_board() {
    $cols = board_columns(); $by = [];
    foreach (rows("SELECT t.*, c.name AS course_name, TRIM(s.first_name || ' ' || s.last_name) AS student_name
                   FROM tasks t LEFT JOIN courses c ON c.id = t.course_id LEFT JOIN students s ON s.id = t.student_id
                   WHERE t.user_id = ? ORDER BY t.position, t.id", [uid()]) as $t) $by[$t['column_id']][] = $t;
    render('kanban', compact('cols', 'by'));
}
function column_add() {
    $name = trim((string)($_POST['name'] ?? ''));
    if ($name === '' || mb_strlen($name) > 40) flash('Give the phase a name of up to 40 characters.', 'error');
    else {
        $pos = (int)row('SELECT COALESCE(MAX(position), -1) + 1 AS p FROM kanban_columns WHERE user_id = ?', [uid()])['p'];
        q('INSERT INTO kanban_columns(user_id, name, position, created_at, updated_at) VALUES(?,?,?,?,?)', [uid(), $name, $pos, now(), now()]);
    }
    redirect('/kanban');
}
function column_edit_form(int $id, ?string $name = null, ?string $error = null) {
    $c = find('kanban_columns', $id);
    render('column_form', ['id' => $id, 'name' => $name ?? $c['name'], 'error' => $error]);
}
function column_edit_save(int $id) {
    find('kanban_columns', $id); $name = trim((string)($_POST['name'] ?? ''));
    if ($name === '' || mb_strlen($name) > 40) return column_edit_form($id, $name, 'Enter a name of up to 40 characters.');
    q('UPDATE kanban_columns SET name = ?, updated_at = ? WHERE id = ? AND user_id = ?', [$name, now(), $id, uid()]);
    flash('Phase renamed.'); redirect('/kanban');
}
function column_move(int $id, int $dir) {
    find('kanban_columns', $id);
    $ids = array_map('intval', array_column(board_columns(), 'id'));
    $i = array_search($id, $ids, true); $j = $i + $dir;
    if ($i !== false && isset($ids[$j])) {
        [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
        foreach ($ids as $pos => $cid) q('UPDATE kanban_columns SET position = ? WHERE id = ? AND user_id = ?', [$pos, $cid, uid()]);
    }
    redirect('/kanban');
}
function column_task_count(int $id): int { return (int)row('SELECT COUNT(*) n FROM tasks WHERE column_id = ?', [$id])['n']; }
function column_delete_confirm(int $id) {
    $c = find('kanban_columns', $id); $n = column_task_count($id);
    render('confirm', ['title' => 'Delete phase “' . $c['name'] . '”?',
        'text' => $n ? "This phase has $n task" . ($n > 1 ? 's' : '') . '. Move them to another phase before deleting it.' : 'This action cannot be undone.',
        'action' => "/columns/$id/delete", 'back' => '/kanban', 'blocked' => $n > 0]);
}
function column_delete(int $id) {
    find('kanban_columns', $id);
    if (column_task_count($id)) { flash('This phase still has tasks. Move them first.', 'error'); redirect('/kanban'); }
    q('DELETE FROM kanban_columns WHERE id = ? AND user_id = ?', [$id, uid()]);
    flash('Phase deleted.'); redirect('/kanban');
}

/* ---- tasks ---- */
function task_form(int $id = 0, ?array $task = null, ?array $errors = null) {
    $cols = board_columns();
    if (!$cols) { flash('Add a phase to the board first.', 'error'); redirect('/kanban'); }
    if ($task === null) {
        if ($id) { $r = find('tasks', $id); $task = $r + ['link' => $r['student_id'] ? 's:' . $r['student_id'] : ($r['course_id'] ? 'c:' . $r['course_id'] : '')]; }
        else $task = ['title' => '', 'description' => '', 'column_id' => (int)($_GET['column'] ?? $cols[0]['id']), 'link' => ''];
    }
    $courses = rows('SELECT id, name FROM courses WHERE user_id = ? ORDER BY name COLLATE NOCASE', [uid()]);
    $students = rows("SELECT s.id, s.first_name || ' ' || s.last_name AS name, c.name AS course FROM students s JOIN courses c ON c.id = s.course_id
                      WHERE s.user_id = ? ORDER BY s.first_name COLLATE NOCASE, s.last_name COLLATE NOCASE", [uid()]);
    render('task_form', compact('id', 'task', 'cols', 'courses', 'students', 'errors'));
}
function task_save(int $id = 0) {
    $old = $id ? find('tasks', $id) : null;
    $t = ['title' => trim((string)($_POST['title'] ?? '')), 'description' => trim((string)($_POST['description'] ?? '')),
          'column_id' => (int)($_POST['column_id'] ?? 0), 'link' => (string)($_POST['link'] ?? '')];
    $err = [];
    if ($t['title'] === '' || mb_strlen($t['title']) > 200) $err['title'] = 'Enter a title (up to 200 characters).';
    if (!row('SELECT 1 FROM kanban_columns WHERE id = ? AND user_id = ?', [$t['column_id'], uid()])) $err['column_id'] = 'Choose a phase.';
    $cid = $sid = null;
    if ($t['link'] !== '') {
        if (preg_match('/^([cs]):(\d+)$/', $t['link'], $m) && row($m[1] === 'c' ? 'SELECT 1 FROM courses WHERE id = ? AND user_id = ?' : 'SELECT 1 FROM students WHERE id = ? AND user_id = ?', [(int)$m[2], uid()])) {
            if ($m[1] === 'c') $cid = (int)$m[2]; else $sid = (int)$m[2];
        } else $err['link'] = 'Choose a course or a student from the list.';
    }
    if ($err) return task_form($id, $t, $err);
    if ($old) {
        $pos = (int)$old['column_id'] === $t['column_id'] ? (int)$old['position'] : next_position($t['column_id']);
        q('UPDATE tasks SET column_id=?, course_id=?, student_id=?, title=?, description=?, position=?, updated_at=? WHERE id=? AND user_id=?',
          [$t['column_id'], $cid, $sid, $t['title'], $t['description'], $pos, now(), $id, uid()]);
        flash('Task saved.');
    } else {
        q('INSERT INTO tasks(user_id, column_id, course_id, student_id, title, description, position, created_at, updated_at) VALUES(?,?,?,?,?,?,?,?,?)',
          [uid(), $t['column_id'], $cid, $sid, $t['title'], $t['description'], next_position($t['column_id']), now(), now()]);
        $id = (int)db()->lastInsertId(); flash('Task created.');
    }
    redirect("/tasks/$id");
}
function task_show(int $id) {
    $task = row("SELECT t.*, k.name AS column_name, c.name AS course_name, TRIM(s.first_name || ' ' || s.last_name) AS student_name
                 FROM tasks t JOIN kanban_columns k ON k.id = t.column_id LEFT JOIN courses c ON c.id = t.course_id LEFT JOIN students s ON s.id = t.student_id
                 WHERE t.id = ? AND t.user_id = ?", [$id, uid()]) ?? not_found();
    $updates = rows('SELECT * FROM task_updates WHERE task_id = ? ORDER BY created_at DESC, id DESC', [$id]);
    $files = attachments_for('task_update', array_column($updates, 'id'));
    render('task', ['task' => $task, 'updates' => $updates, 'files' => $files, 'cols' => board_columns()]);
}
function task_move(int $id) {
    $t = find('tasks', $id); $col = (int)($_POST['column_id'] ?? 0);
    if (!row('SELECT 1 FROM kanban_columns WHERE id = ? AND user_id = ?', [$col, uid()])) { http_response_code(400); exit('Invalid phase'); }
    if ((int)$t['column_id'] !== $col) q('UPDATE tasks SET column_id = ?, position = ?, updated_at = ? WHERE id = ? AND user_id = ?', [$col, next_position($col), now(), $id, uid()]);
    if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch') { http_response_code(204); exit; }
    redirect(($_POST['back'] ?? '') === 'task' ? "/tasks/$id" : '/kanban');
}
function task_delete_confirm(int $id) {
    $t = find('tasks', $id);
    render('confirm', ['title' => 'Delete this task?', 'text' => 'This also deletes its updates and attached files. This action cannot be undone.',
        'quote' => $t['title'], 'action' => "/tasks/$id/delete", 'back' => "/tasks/$id", 'blocked' => false]);
}
function task_delete(int $id) {
    find('tasks', $id);
    $names = attachments_take('task_update', 'SELECT id FROM task_updates WHERE task_id = ?', [$id]);
    q('DELETE FROM tasks WHERE id = ? AND user_id = ?', [$id, uid()]);
    files_unlink($names); flash('Task deleted.'); redirect('/kanban');
}
