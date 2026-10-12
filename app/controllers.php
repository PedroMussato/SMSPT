<?php
/* ---------- Auth ---------- */
function registration_open(): bool { return !row('SELECT 1 FROM users') || !defined('ALLOW_REGISTRATION') || ALLOW_REGISTRATION; }
function register_form() {
    if (uid()) redirect('/');
    if (!registration_open()) { flash('Registration is closed.', 'error'); redirect('/login'); }
    render('auth', ['mode' => 'register', 'error' => null]);
}
function register_post() {
    if (!registration_open()) redirect('/login');
    $u = trim($_POST['username'] ?? ''); $email = trim($_POST['email'] ?? ''); $p = (string)($_POST['password'] ?? '');
    if (!preg_match('/^[\w.@+-]{3,64}$/', $u)) $err = 'Username must be 3 to 64 characters: letters, numbers and . _ @ + -';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $err = 'Enter a valid email address.';
    elseif (strlen($p) < 8) $err = 'Password must be at least 8 characters.';
    elseif ($p !== (string)($_POST['password2'] ?? '')) $err = 'The passwords do not match.';
    elseif (row('SELECT 1 FROM users WHERE lower(username) = lower(?)', [$u])) $err = 'That username is already taken.';
    elseif (find_user_by_email($email)) $err = 'That email is already registered.';
    else $err = null;
    if (!$err) {
        try { q('INSERT INTO users(username, email, password_hash) VALUES(?, ?, ?)', [$u, $email, password_hash($p, PASSWORD_DEFAULT)]); }
        catch (PDOException) { $err = 'That username or email is already registered.'; }
    }
    if ($err) return render('auth', ['mode' => 'register', 'error' => $err]);
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)db()->lastInsertId(); $_SESSION['uname'] = $u;
    seed_columns(uid());
    flash('Welcome! Start by creating a course.'); redirect('/courses');
}
function login_form() { if (uid()) redirect('/'); render('auth', ['mode' => 'login', 'error' => null]); }
function login_post() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    q('DELETE FROM login_attempts WHERE at < ?', [time() - 900]);
    if (row('SELECT COUNT(*) n FROM login_attempts WHERE ip = ?', [$ip])['n'] >= 10)
        return render('auth', ['mode' => 'login', 'error' => 'Too many failed attempts. Try again in 15 minutes.']);
    $u = row('SELECT * FROM users WHERE lower(username) = lower(?)', [trim($_POST['username'] ?? '')]);
    if ($u && password_verify((string)($_POST['password'] ?? ''), $u['password_hash'])) {
        session_regenerate_id(true); $_SESSION['uid'] = (int)$u['id']; $_SESSION['uname'] = $u['username'];
        if (empty($u['email'])) flash('Add your email on the Profile page so you can recover your password.');
        redirect('/');
    }
    q('INSERT INTO login_attempts(ip, at) VALUES(?, ?)', [$ip, time()]);
    usleep(300000);
    render('auth', ['mode' => 'login', 'error' => 'Wrong username or password.']);
}
function logout() { $_SESSION = []; session_destroy(); redirect('/login'); }

/* ---------- Courses ---------- */
function courses_index() {
    $courses = rows('SELECT c.*, (SELECT COUNT(*) FROM students s WHERE s.course_id = c.id) AS n
                     FROM courses c WHERE c.user_id = ? ORDER BY c.name COLLATE NOCASE', [uid()]);
    render('courses', compact('courses'));
}
function course_form(int $id = 0, ?array $course = null, ?string $error = null) {
    $course ??= $id ? find('courses', $id) : ['name' => '', 'description' => ''];
    render('course_form', compact('id', 'course', 'error'));
}
function course_save(int $id = 0) {
    if ($id) find('courses', $id);
    $c = ['name' => trim($_POST['name'] ?? ''), 'description' => trim($_POST['description'] ?? '')];
    if ($c['name'] === '') return course_form($id, $c, 'Course name is required.');
    if ($id) q('UPDATE courses SET name=?, description=?, updated_at=? WHERE id=?', [$c['name'], $c['description'], now(), $id]);
    else q('INSERT INTO courses(user_id, name, description, created_at, updated_at) VALUES(?,?,?,?,?)', [uid(), $c['name'], $c['description'], now(), now()]);
    flash('Course saved.'); redirect('/courses');
}
function course_student_count(int $id): int { return (int)row('SELECT COUNT(*) n FROM students WHERE course_id=?', [$id])['n']; }
function course_delete_confirm(int $id) {
    $c = find('courses', $id); $n = course_student_count($id);
    render('confirm', [
        'title' => 'Delete course “' . $c['name'] . '”?',
        'text' => $n ? "This course has $n student" . ($n > 1 ? 's' : '') . '. You must move or delete the students before deleting this course.' : 'This action cannot be undone.',
        'action' => "/courses/$id/delete", 'back' => '/courses', 'blocked' => $n > 0,
    ]);
}
function course_delete(int $id) {
    find('courses', $id);
    if (course_student_count($id)) { flash('This course still has students. Move or delete them first.', 'error'); redirect('/courses'); }
    q('DELETE FROM courses WHERE id=?', [$id]); flash('Course deleted.'); redirect('/courses');
}

/* ---------- Students ---------- */
function students_index() {
    $search = trim($_GET['q'] ?? ''); $cid = (int)($_GET['course'] ?? 0);
    $where = ['s.user_id = ?']; $p = [uid()];
    if ($search !== '') {
        $like = '%' . addcslashes($search, '%_\\') . '%';
        $where[] = "(s.first_name LIKE ? ESCAPE '\\' OR s.last_name LIKE ? ESCAPE '\\'
                     OR (s.first_name || ' ' || s.last_name) LIKE ? ESCAPE '\\' OR s.email LIKE ? ESCAPE '\\')";
        array_push($p, $like, $like, $like, $like);
    }
    if ($cid) { $where[] = 's.course_id = ?'; $p[] = $cid; }
    $students = rows('SELECT s.*, c.name AS course_name, (SELECT MAX(u.created_at) FROM updates u WHERE u.student_id = s.id) AS last_update
                      FROM students s JOIN courses c ON c.id = s.course_id' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . '
                      ORDER BY COALESCE(last_update, s.created_at) DESC, s.id DESC', $p);
    $courses = rows('SELECT id, name FROM courses WHERE user_id = ? ORDER BY name COLLATE NOCASE', [uid()]);
    $total = (int)row('SELECT COUNT(*) n FROM students WHERE user_id = ?', [uid()])['n'];
    render('students', compact('students', 'courses', 'search', 'cid', 'total'));
}
function student_form(int $id = 0, ?array $student = null, ?array $errors = null) {
    $courses = rows('SELECT id, name FROM courses WHERE user_id = ? ORDER BY name COLLATE NOCASE', [uid()]);
    if (!$courses) { flash('Create a course before adding students.'); redirect('/courses/create'); }
    $student ??= $id ? find('students', $id)
        : ['first_name' => '', 'last_name' => '', 'email' => '', 'price' => '', 'linkedin' => '', 'course_id' => (int)($_GET['course'] ?? 0)];
    render('student_form', compact('id', 'student', 'courses', 'errors'));
}
function student_save(int $id = 0) {
    if ($id) find('students', $id);
    $s = [];
    foreach (['first_name', 'last_name', 'email', 'price', 'linkedin'] as $f) $s[$f] = trim((string)($_POST[$f] ?? ''));
    $s['course_id'] = (int)($_POST['course_id'] ?? 0);
    $err = [];
    if ($s['first_name'] === '') $err['first_name'] = 'First name is required.';
    if ($s['last_name'] === '') $err['last_name'] = 'Last name is required.';
    if (!row('SELECT 1 FROM courses WHERE id=? AND user_id=?', [$s['course_id'], uid()])) $err['course_id'] = 'Choose a course.';
    if ($s['email'] !== '' && !filter_var($s['email'], FILTER_VALIDATE_EMAIL)) $err['email'] = 'Enter a valid email address.';
    $price = null;
    if ($s['price'] !== '') {
        $n = str_replace(',', '.', ltrim($s['price'], '$ '));
        if (is_numeric($n) && (float)$n >= 0) $price = (float)$n; else $err['price'] = 'Enter a number, like 16 or 16.50.';
    }
    if ($s['linkedin'] !== '') {
        if (!preg_match('#^https?://#i', $s['linkedin'])) $s['linkedin'] = 'https://' . $s['linkedin'];
        if (!filter_var($s['linkedin'], FILTER_VALIDATE_URL)) $err['linkedin'] = 'Enter a valid URL.';
    }
    if ($err) return student_form($id, $s, $err);
    $vals = [$s['course_id'], $s['first_name'], $s['last_name'], $s['email'] ?: null, $price, $s['linkedin'] ?: null];
    if ($id) { q('UPDATE students SET course_id=?, first_name=?, last_name=?, email=?, price=?, linkedin=?, updated_at=? WHERE id=?', [...$vals, now(), $id]); flash('Student saved.'); }
    else { q('INSERT INTO students(user_id, course_id, first_name, last_name, email, price, linkedin, created_at, updated_at) VALUES(?,?,?,?,?,?,?,?,?)', [uid(), ...$vals, now(), now()]); $id = (int)db()->lastInsertId(); flash('Student created.'); }
    redirect("/students/$id");
}
function student_show(int $id) {
    $student = row('SELECT s.*, c.name AS course_name FROM students s JOIN courses c ON c.id = s.course_id WHERE s.id=? AND s.user_id=?', [$id, uid()]) ?? not_found();
    $observations = rows('SELECT * FROM observations WHERE student_id=? ORDER BY created_at, id', [$id]);
    $updates = rows('SELECT * FROM updates WHERE student_id=? ORDER BY created_at DESC, id DESC', [$id]);
    $files = attachments_for('update', array_column($updates, 'id'));
    render('student', compact('student', 'observations', 'updates', 'files'));
}
function student_delete_confirm(int $id) {
    $s = find('students', $id);
    render('confirm', ['title' => 'Delete ' . $s['first_name'] . ' ' . $s['last_name'] . '?',
        'text' => 'This also deletes all their observations and updates. This action cannot be undone.',
        'action' => "/students/$id/delete", 'back' => "/students/$id", 'blocked' => false]);
}
function student_delete(int $id) {
    find('students', $id);
    $names = attachments_take('update', 'SELECT id FROM updates WHERE student_id = ?', [$id]);
    q('DELETE FROM students WHERE id=?', [$id]);
    files_unlink($names); flash('Student deleted.'); redirect('/');
}

/* ---------- Observations & updates (with optional file attachments) ---------- */
const NOTE_TYPES = [
    'observations' => ['parent' => 'students', 'label' => 'observation', 'back' => '/students/', 'files' => null],
    'updates'      => ['parent' => 'students', 'label' => 'update',      'back' => '/students/', 'files' => 'update'],
    'task_updates' => ['parent' => 'tasks',    'label' => 'update',      'back' => '/tasks/',    'files' => 'task_update'],
];
function note_cfg(string $t): array { return NOTE_TYPES[$t] ?? not_found(); }
function note_add(string $t, int $pid) {
    $cfg = note_cfg($t); find($cfg['parent'], $pid);
    $c = trim((string)($_POST['content'] ?? '')); $files = $cfg['files'] ? uploaded_files() : [];
    if ($c === '' && !$files) flash('The ' . $cfg['label'] . ' cannot be empty.', 'error');
    elseif ($err = validate_uploads($files)) flash($err, 'error');
    else {
        $fk = $t === 'task_updates' ? 'task_id' : 'student_id';
        q("INSERT INTO $t($fk, content, created_at, updated_at) VALUES(?,?,?,?)", [$pid, $c, now(), now()]);
        if ($files) save_uploads($files, $cfg['files'], (int)db()->lastInsertId());
        flash(ucfirst($cfg['label']) . ' added.');
    }
    redirect($cfg['back'] . $pid);
}
function note_parent_id(string $t, array $n): int { return (int)$n[$t === 'task_updates' ? 'task_id' : 'student_id']; }
function note_edit_form(string $t, int $id, ?string $content = null, ?string $error = null) {
    $cfg = note_cfg($t); $n = find($t, $id);
    $files = $cfg['files'] ? (attachments_for($cfg['files'], [$id])[$id] ?? []) : [];
    render('note_form', ['label' => $cfg['label'], 'content' => $content ?? $n['content'], 'error' => $error, 'files' => $files, 'allow_files' => (bool)$cfg['files'],
        'action' => "/$t/$id/edit", 'back' => $cfg['back'] . note_parent_id($t, $n)]);
}
function note_edit_save(string $t, int $id) {
    $cfg = note_cfg($t); $n = find($t, $id); $c = trim((string)($_POST['content'] ?? ''));
    $existing = $cfg['files'] ? (attachments_for($cfg['files'], [$id])[$id] ?? []) : [];
    $remove = array_map('intval', (array)($_POST['remove'] ?? []));
    $keep = array_filter($existing, fn($f) => !in_array((int)$f['id'], $remove, true));
    $new = $cfg['files'] ? uploaded_files() : [];
    if ($c === '' && !$keep && !$new) return note_edit_form($t, $id, $c, 'The ' . $cfg['label'] . ' cannot be empty.');
    if ($err = validate_uploads($new)) return note_edit_form($t, $id, $c, $err);
    $names = [];
    foreach ($existing as $f) if (in_array((int)$f['id'], $remove, true)) {
        q('DELETE FROM attachments WHERE id = ? AND user_id = ?', [$f['id'], uid()]); $names[] = $f['stored_name'];
    }
    files_unlink($names);
    if ($new) save_uploads($new, $cfg['files'], $id);
    q("UPDATE $t SET content=?, updated_at=? WHERE id=?", [$c, now(), $id]); // created_at stays unchanged
    flash('Changes saved.'); redirect($cfg['back'] . note_parent_id($t, $n));
}
function note_delete_confirm(string $t, int $id) {
    $cfg = note_cfg($t); $n = find($t, $id);
    render('confirm', ['title' => 'Delete this ' . $cfg['label'] . '?', 'text' => 'This action cannot be undone.' . ($cfg['files'] ? ' Attached files are deleted too.' : ''),
        'quote' => $n['content'], 'action' => "/$t/$id/delete", 'back' => $cfg['back'] . note_parent_id($t, $n), 'blocked' => false]);
}
function note_delete(string $t, int $id) {
    $cfg = note_cfg($t); $n = find($t, $id);
    $names = $cfg['files'] ? attachments_take($cfg['files'], 'SELECT ?', [$id]) : [];
    q("DELETE FROM $t WHERE id=?", [$id]);
    files_unlink($names);
    flash(ucfirst($cfg['label']) . ' deleted.'); redirect($cfg['back'] . note_parent_id($t, $n));
}

/* ---------- Backup: export / import (JSON), always limited to the logged-in user ---------- */
function user_data(): array {
    $u = [uid()];
    return [
        'courses' => rows('SELECT id, name, description, created_at, updated_at FROM courses WHERE user_id = ? ORDER BY id', $u),
        'students' => rows('SELECT id, course_id, first_name, last_name, email, price, linkedin, created_at, updated_at FROM students WHERE user_id = ? ORDER BY id', $u),
        'observations' => rows('SELECT n.id, n.student_id, n.content, n.created_at, n.updated_at FROM observations n JOIN students s ON s.id = n.student_id WHERE s.user_id = ? ORDER BY n.id', $u),
        'updates' => rows('SELECT n.id, n.student_id, n.content, n.created_at, n.updated_at FROM updates n JOIN students s ON s.id = n.student_id WHERE s.user_id = ? ORDER BY n.id', $u),
        'columns' => rows('SELECT id, name, position, created_at, updated_at FROM kanban_columns WHERE user_id = ? ORDER BY position, id', $u),
        'tasks' => rows('SELECT id, column_id, course_id, student_id, title, description, position, created_at, updated_at FROM tasks WHERE user_id = ? ORDER BY id', $u),
        'task_updates' => rows('SELECT n.id, n.task_id, n.content, n.created_at, n.updated_at FROM task_updates n JOIN tasks t ON t.id = n.task_id WHERE t.user_id = ? ORDER BY n.id', $u),
    ];
}
function backup_page(?string $error = null) {
    $counts = array_map('count', user_data());
    $counts['files'] = (int)row('SELECT COUNT(*) n FROM attachments WHERE user_id = ?', [uid()])['n'];
    render('backup', compact('error', 'counts'));
}
function backup_export() {
    $data = ['app' => 'student-manager', 'version' => 2, 'exported_at' => gmdate('c')] + user_data();
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="student-manager-backup-' . gmdate('Y-m-d') . '.json"');
    header('Cache-Control: no-store');
    echo $json; exit;
}
function backup_validate(&$d): void {
    $bad = fn(string $m) => new InvalidArgumentException($m);
    if (!is_array($d) || ($d['app'] ?? null) !== 'student-manager') throw $bad('This is not a Student Manager backup file.');
    if (!in_array($d['version'] ?? null, [1, 2], true)) throw $bad('This backup version is not supported.');
    foreach (['columns', 'tasks', 'task_updates'] as $t) $d[$t] ??= [];   // version 1 backups have no board
    $ts = fn($v) => is_string($v) && preg_match('/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/', $v);
    $spec = ['courses' => ['name'], 'students' => ['first_name', 'last_name'], 'observations' => ['content'], 'updates' => [],
             'columns' => ['name'], 'tasks' => ['title'], 'task_updates' => []];
    $ids = [];
    foreach ($spec as $t => $req) {
        if (!is_array($d[$t] ?? null)) throw $bad("The backup has no “{$t}” list.");
        foreach ($d[$t] as $r) {
            if (!is_array($r) || !is_int($r['id'] ?? null) || $r['id'] < 1 || isset($ids[$t][$r['id']])) throw $bad("The “{$t}” list has an invalid or duplicate id.");
            foreach ($req as $k) if (!is_string($r[$k] ?? null) || trim($r[$k]) === '') throw $bad("A record in “{$t}” is missing “{$k}”.");
            if (in_array($t, ['updates', 'task_updates'], true) && !is_string($r['content'] ?? null)) throw $bad("A record in “{$t}” has no text.");
            if (!$ts($r['created_at'] ?? null) || !$ts($r['updated_at'] ?? null)) throw $bad("A record in “{$t}” has an invalid date.");
            $ids[$t][$r['id']] = 1;
        }
    }
    foreach ($d['students'] as $r) {
        if (!is_int($r['course_id'] ?? null) || !isset($ids['courses'][$r['course_id']])) throw $bad('A student points to a course that is not in the backup.');
        foreach (['email', 'linkedin'] as $k) if (isset($r[$k]) && !is_string($r[$k])) throw $bad("A student has an invalid $k.");
        if (!empty($r['linkedin']) && !preg_match('#^https?://#i', $r['linkedin'])) throw $bad('A student has an invalid LinkedIn URL.');
        if (isset($r['price']) && !is_numeric($r['price'])) throw $bad('A student has an invalid price.');
    }
    foreach (['observations', 'updates'] as $t) foreach ($d[$t] as $r)
        if (!is_int($r['student_id'] ?? null) || !isset($ids['students'][$r['student_id']])) throw $bad("A record in “{$t}” points to a student that is not in the backup.");
    foreach ($d['columns'] as $r) if (!is_int($r['position'] ?? null)) throw $bad('A board phase has an invalid position.');
    foreach ($d['tasks'] as $r) {
        if (!is_int($r['column_id'] ?? null) || !isset($ids['columns'][$r['column_id']])) throw $bad('A task points to a phase that is not in the backup.');
        if (!is_string($r['description'] ?? null) || !is_int($r['position'] ?? null)) throw $bad('A task has invalid data.');
        if (($r['course_id'] ?? null) !== null && !isset($ids['courses'][$r['course_id']])) throw $bad('A task points to a course that is not in the backup.');
        if (($r['student_id'] ?? null) !== null && !isset($ids['students'][$r['student_id']])) throw $bad('A task points to a student that is not in the backup.');
    }
    foreach ($d['task_updates'] as $r)
        if (!is_int($r['task_id'] ?? null) || !isset($ids['tasks'][$r['task_id']])) throw $bad('A task update points to a task that is not in the backup.');
}
function backup_import() {
    if (empty($_POST['confirm'])) return backup_page('Tick the box to confirm that the import replaces all your current data.');
    $f = $_FILES['file'] ?? null;
    if (!$f || $f['error'] !== UPLOAD_ERR_OK) return backup_page('Choose a backup file. The upload failed or the file is larger than the server allows.');
    try { $d = json_decode((string)file_get_contents($f['tmp_name']), true, 16, JSON_THROW_ON_ERROR); }
    catch (Throwable) { return backup_page('That file is not valid JSON.'); }
    try { backup_validate($d); } catch (InvalidArgumentException $e) { return backup_page($e->getMessage()); }

    $pdo = db(); $me = uid(); $pdo->beginTransaction();
    try {
        // Only this user's rows are replaced. New ids are assigned, because ids are shared by all users.
        // Attached files belong to the old updates, so they are removed with them (after the commit).
        $names = array_merge(
            attachments_take('update', 'SELECT u.id FROM updates u JOIN students s ON s.id = u.student_id WHERE s.user_id = ?', [$me]),
            attachments_take('task_update', 'SELECT u.id FROM task_updates u JOIN tasks t ON t.id = u.task_id WHERE t.user_id = ?', [$me]));
        $pdo->prepare('DELETE FROM task_updates WHERE task_id IN (SELECT id FROM tasks WHERE user_id = ?)')->execute([$me]);
        foreach (['tasks', 'kanban_columns'] as $t) $pdo->prepare("DELETE FROM $t WHERE user_id = ?")->execute([$me]);
        foreach (['observations', 'updates'] as $t)
            $pdo->prepare("DELETE FROM $t WHERE student_id IN (SELECT id FROM students WHERE user_id = ?)")->execute([$me]);
        foreach (['students', 'courses'] as $t) $pdo->prepare("DELETE FROM $t WHERE user_id = ?")->execute([$me]);

        $cmap = $smap = $kmap = $tmap = [];
        $c = $pdo->prepare('INSERT INTO courses(user_id, name, description, created_at, updated_at) VALUES(?,?,?,?,?)');
        foreach ($d['courses'] as $r) { $c->execute([$me, $r['name'], (string)($r['description'] ?? ''), $r['created_at'], $r['updated_at']]); $cmap[$r['id']] = (int)$pdo->lastInsertId(); }
        $s = $pdo->prepare('INSERT INTO students(user_id, course_id, first_name, last_name, email, price, linkedin, created_at, updated_at) VALUES(?,?,?,?,?,?,?,?,?)');
        foreach ($d['students'] as $r) {
            $s->execute([$me, $cmap[$r['course_id']], $r['first_name'], $r['last_name'], ($r['email'] ?? '') ?: null,
                isset($r['price']) ? (float)$r['price'] : null, ($r['linkedin'] ?? '') ?: null, $r['created_at'], $r['updated_at']]);
            $smap[$r['id']] = (int)$pdo->lastInsertId();
        }
        foreach (['observations', 'updates'] as $t) {
            $n = $pdo->prepare("INSERT INTO $t(student_id, content, created_at, updated_at) VALUES(?,?,?,?)");
            foreach ($d[$t] as $r) $n->execute([$smap[$r['student_id']], $r['content'], $r['created_at'], $r['updated_at']]);
        }
        $k = $pdo->prepare('INSERT INTO kanban_columns(user_id, name, position, created_at, updated_at) VALUES(?,?,?,?,?)');
        foreach ($d['columns'] as $r) { $k->execute([$me, $r['name'], $r['position'], $r['created_at'], $r['updated_at']]); $kmap[$r['id']] = (int)$pdo->lastInsertId(); }
        if (!$d['columns']) seed_columns($me);
        $tk = $pdo->prepare('INSERT INTO tasks(user_id, column_id, course_id, student_id, title, description, position, created_at, updated_at) VALUES(?,?,?,?,?,?,?,?,?)');
        foreach ($d['tasks'] as $r) {
            $tk->execute([$me, $kmap[$r['column_id']], isset($r['course_id']) ? $cmap[$r['course_id']] : null, isset($r['student_id']) ? $smap[$r['student_id']] : null,
                $r['title'], $r['description'], $r['position'], $r['created_at'], $r['updated_at']]);
            $tmap[$r['id']] = (int)$pdo->lastInsertId();
        }
        $tu = $pdo->prepare('INSERT INTO task_updates(task_id, content, created_at, updated_at) VALUES(?,?,?,?)');
        foreach ($d['task_updates'] as $r) $tu->execute([$tmap[$r['task_id']], $r['content'], $r['created_at'], $r['updated_at']]);
        $pdo->commit();
    } catch (Throwable) {
        $pdo->rollBack();
        return backup_page('The import failed and nothing was changed.');
    }
    files_unlink($names);
    flash(sprintf('Import complete: %d courses, %d students, %d observations, %d updates, %d tasks.',
        count($d['courses']), count($d['students']), count($d['observations']), count($d['updates']), count($d['tasks'])));
    redirect('/');
}

/* ---------- Password recovery & profile ---------- */
function find_user_by_email(string $email) { return row('SELECT * FROM users WHERE lower(email) = lower(?)', [$email]); }
function forgot_form() { if (uid()) redirect('/profile'); render('forgot', ['sent' => false, 'error' => null]); }
function forgot_post() {
    $ip = 'forgot:' . ($_SERVER['REMOTE_ADDR'] ?? '');
    q('DELETE FROM login_attempts WHERE at < ?', [time() - 900]);
    if (row('SELECT COUNT(*) n FROM login_attempts WHERE ip = ?', [$ip])['n'] >= 5)
        return render('forgot', ['sent' => false, 'error' => 'Too many requests. Try again in 15 minutes.']);
    q('INSERT INTO login_attempts(ip, at) VALUES(?, ?)', [$ip, time()]);
    $email = trim($_POST['email'] ?? '');
    $u = filter_var($email, FILTER_VALIDATE_EMAIL) ? find_user_by_email($email) : null;
    if ($u) {
        $token = bin2hex(random_bytes(32));
        q('DELETE FROM password_resets WHERE user_id = ?', [$u['id']]);
        q('INSERT INTO password_resets(user_id, token_hash, expires_at) VALUES(?, ?, ?)', [$u['id'], hash('sha256', $token), time() + 3600]);
        try {
            $name = mail_config()['from_name'] ?? 'Student Manager';
            send_mail($u['email'], "Reset your $name password",
                "Hi {$u['username']},\n\nSomeone asked to reset the password for your $name account.\n"
                . "Use this link to choose a new password (valid for 1 hour):\n\n" . app_url() . "/reset?token=$token\n\n"
                . "If you did not ask for this, ignore this email. Your password stays the same.\n");
        } catch (Throwable $e) { error_log('Password reset email failed: ' . $e->getMessage()); }
    }
    // Same answer whether or not the email exists, so accounts can't be discovered.
    render('forgot', ['sent' => true, 'error' => null]);
}
function reset_valid(string $token) {
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) return null;
    return row('SELECT * FROM password_resets WHERE token_hash = ? AND expires_at > ?', [hash('sha256', $token), time()]);
}
function reset_form() {
    $t = (string)($_GET['token'] ?? '');
    if (!reset_valid($t)) return render('forgot', ['sent' => false, 'error' => 'This reset link is invalid or has expired. Request a new one.']);
    render('reset', ['token' => $t, 'error' => null]);
}
function reset_post() {
    $t = (string)($_POST['token'] ?? ''); $r = reset_valid($t);
    if (!$r) return render('forgot', ['sent' => false, 'error' => 'This reset link is invalid or has expired. Request a new one.']);
    $p = (string)($_POST['password'] ?? '');
    if (strlen($p) < 8) return render('reset', ['token' => $t, 'error' => 'Password must be at least 8 characters.']);
    if ($p !== (string)($_POST['password2'] ?? '')) return render('reset', ['token' => $t, 'error' => 'The passwords do not match.']);
    q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($p, PASSWORD_DEFAULT), $r['user_id']]);
    q('DELETE FROM password_resets WHERE user_id = ?', [$r['user_id']]);
    flash('Password updated. Log in with your new password.'); redirect('/login');
}
function profile_page(?string $emailErr = null, ?string $pwErr = null) {
    $user = row('SELECT username, email FROM users WHERE id = ?', [uid()]) ?? not_found();
    render('profile', compact('user', 'emailErr', 'pwErr'));
}
function current_password_ok(): bool {
    $u = row('SELECT password_hash FROM users WHERE id = ?', [uid()]);
    return $u && password_verify((string)($_POST['current'] ?? ''), $u['password_hash']);
}
function profile_email() {
    $email = trim($_POST['email'] ?? '');
    if (!current_password_ok()) return profile_page('Your current password is wrong.');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return profile_page('Enter a valid email address.');
    $o = find_user_by_email($email);
    if ($o && (int)$o['id'] !== uid()) return profile_page('That email is already used by another account.');
    q('UPDATE users SET email = ? WHERE id = ?', [$email, uid()]);
    flash('Email updated.'); redirect('/profile');
}
function profile_password() {
    $new = (string)($_POST['password'] ?? '');
    if (!current_password_ok()) return profile_page(null, 'Your current password is wrong.');
    if (strlen($new) < 8) return profile_page(null, 'The new password must be at least 8 characters.');
    if ($new !== (string)($_POST['password2'] ?? '')) return profile_page(null, 'The new passwords do not match.');
    q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), uid()]);
    session_regenerate_id(true);
    flash('Password changed.'); redirect('/profile');
}
