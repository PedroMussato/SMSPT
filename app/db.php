<?php
function db(): PDO {
    static $pdo;
    if ($pdo) return $pdo;
    $pdo = new PDO('sqlite:' . __DIR__ . '/../storage/database.sqlite', null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY, username TEXT NOT NULL UNIQUE, password_hash TEXT NOT NULL, email TEXT);
    CREATE TABLE IF NOT EXISTS login_attempts (ip TEXT NOT NULL, at INTEGER NOT NULL);
    CREATE TABLE IF NOT EXISTS password_resets (
        id INTEGER PRIMARY KEY, user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        token_hash TEXT NOT NULL, expires_at INTEGER NOT NULL);
    CREATE TABLE IF NOT EXISTS kanban_columns (
        id INTEGER PRIMARY KEY, user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        name TEXT NOT NULL, position INTEGER NOT NULL DEFAULT 0, created_at TEXT NOT NULL, updated_at TEXT NOT NULL);
    CREATE TABLE IF NOT EXISTS tasks (
        id INTEGER PRIMARY KEY, user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        column_id INTEGER NOT NULL REFERENCES kanban_columns(id) ON DELETE RESTRICT,
        course_id INTEGER REFERENCES courses(id) ON DELETE SET NULL,
        student_id INTEGER REFERENCES students(id) ON DELETE SET NULL,
        title TEXT NOT NULL, description TEXT NOT NULL DEFAULT '', position INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL, updated_at TEXT NOT NULL);
    CREATE TABLE IF NOT EXISTS task_updates (
        id INTEGER PRIMARY KEY, task_id INTEGER NOT NULL REFERENCES tasks(id) ON DELETE CASCADE,
        content TEXT NOT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL);
    CREATE TABLE IF NOT EXISTS attachments (
        id INTEGER PRIMARY KEY, user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        kind TEXT NOT NULL, ref_id INTEGER NOT NULL, original_name TEXT NOT NULL, stored_name TEXT NOT NULL,
        size INTEGER NOT NULL, created_at TEXT NOT NULL);
    CREATE TABLE IF NOT EXISTS courses (
        id INTEGER PRIMARY KEY,
        user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        name TEXT NOT NULL, description TEXT NOT NULL DEFAULT '',
        created_at TEXT NOT NULL, updated_at TEXT NOT NULL);
    CREATE TABLE IF NOT EXISTS students (
        id INTEGER PRIMARY KEY,
        user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        course_id INTEGER NOT NULL REFERENCES courses(id) ON DELETE RESTRICT,
        first_name TEXT NOT NULL, last_name TEXT NOT NULL,
        email TEXT, price REAL, linkedin TEXT,
        created_at TEXT NOT NULL, updated_at TEXT NOT NULL);
    CREATE TABLE IF NOT EXISTS observations (
        id INTEGER PRIMARY KEY,
        student_id INTEGER NOT NULL REFERENCES students(id) ON DELETE CASCADE,
        content TEXT NOT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL);
    CREATE TABLE IF NOT EXISTS updates (
        id INTEGER PRIMARY KEY,
        student_id INTEGER NOT NULL REFERENCES students(id) ON DELETE CASCADE,
        content TEXT NOT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL);
    ");
    // Upgrade from the single-user version: existing data goes to the first account.
    if ((int)$pdo->query('PRAGMA user_version')->fetchColumn() < 2) {
        foreach (['courses', 'students'] as $t) {
            $cols = array_column($pdo->query("PRAGMA table_info($t)")->fetchAll(), 'name');
            if (!in_array('user_id', $cols, true)) $pdo->exec("ALTER TABLE $t ADD COLUMN user_id INTEGER REFERENCES users(id) ON DELETE CASCADE");
            $pdo->exec("UPDATE $t SET user_id = (SELECT MIN(id) FROM users) WHERE user_id IS NULL");
        }
        $pdo->exec('PRAGMA user_version = 2');
    }
    // Upgrade: users get an email address (needed for password recovery).
    if ((int)$pdo->query('PRAGMA user_version')->fetchColumn() < 3) {
        $cols = array_column($pdo->query('PRAGMA table_info(users)')->fetchAll(), 'name');
        if (!in_array('email', $cols, true)) $pdo->exec('ALTER TABLE users ADD COLUMN email TEXT');
        $pdo->exec('PRAGMA user_version = 3');
    }
    // Upgrade: every existing user gets the default board phases.
    if ((int)$pdo->query('PRAGMA user_version')->fetchColumn() < 4) {
        foreach ($pdo->query('SELECT id FROM users')->fetchAll(PDO::FETCH_COLUMN) as $uid0)
            if (!$pdo->query('SELECT 1 FROM kanban_columns WHERE user_id = ' . (int)$uid0)->fetch()) seed_columns((int)$uid0);
        $pdo->exec('PRAGMA user_version = 4');
    }
    $pdo->exec('
    CREATE UNIQUE INDEX IF NOT EXISTS idx_users_lower ON users(lower(username));
    CREATE UNIQUE INDEX IF NOT EXISTS idx_users_email ON users(lower(email));
    CREATE UNIQUE INDEX IF NOT EXISTS idx_resets_token ON password_resets(token_hash);
    CREATE INDEX IF NOT EXISTS idx_cols_user ON kanban_columns(user_id, position);
    CREATE INDEX IF NOT EXISTS idx_tasks_user ON tasks(user_id, column_id, position);
    CREATE INDEX IF NOT EXISTS idx_task_updates ON task_updates(task_id, created_at);
    CREATE INDEX IF NOT EXISTS idx_attach_ref ON attachments(kind, ref_id);
    CREATE INDEX IF NOT EXISTS idx_courses_user ON courses(user_id);
    CREATE INDEX IF NOT EXISTS idx_students_user ON students(user_id);
    CREATE INDEX IF NOT EXISTS idx_students_course ON students(course_id);
    CREATE INDEX IF NOT EXISTS idx_updates_student ON updates(student_id, created_at);
    CREATE INDEX IF NOT EXISTS idx_obs_student ON observations(student_id);
    ');
    return $pdo;
}
function q(string $sql, array $p = []): PDOStatement { $s = db()->prepare($sql); $s->execute($p); return $s; }
function row(string $sql, array $p = []) { return q($sql, $p)->fetch() ?: null; }
function rows(string $sql, array $p = []): array { return q($sql, $p)->fetchAll(); }
/** Fetch one record that belongs to the logged-in user, or 404. Other users' records look like they don't exist. */
function find(string $table, int $id): array { // $table is always an internal constant
    $via = ['observations' => ['students', 'student_id'], 'updates' => ['students', 'student_id'], 'task_updates' => ['tasks', 'task_id']];
    if (isset($via[$table])) { [$parent, $fk] = $via[$table]; $sql = "SELECT n.* FROM $table n JOIN $parent p ON p.id = n.$fk WHERE n.id = ? AND p.user_id = ?"; }
    else $sql = "SELECT * FROM $table WHERE id = ? AND user_id = ?";
    return row($sql, [$id, uid()]) ?? not_found();
}
function seed_columns(int $userId): void {
    foreach (['Backlog', 'To-do', 'Doing', 'Done'] as $i => $n)
        q('INSERT INTO kanban_columns(user_id, name, position, created_at, updated_at) VALUES(?,?,?,?,?)', [$userId, $n, $i, now(), now()]);
}
