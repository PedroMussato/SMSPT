<?php
declare(strict_types=1);
require __DIR__ . '/../config/config.php';
require __DIR__ . '/../app/helpers.php';
require __DIR__ . '/../app/db.php';
require __DIR__ . '/../app/mail.php';
require __DIR__ . '/../app/files.php';
require __DIR__ . '/../app/kanban.php';
require __DIR__ . '/../app/controllers.php';

$path = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/', '/') ?: '/';
if (PHP_SAPI === 'cli-server' && is_file(__DIR__ . $path)) return false; // php -S support

session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS'])]);
session_start();
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer'); // keeps reset tokens out of referrers

$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'POST' && !$_POST && !$_FILES && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    http_response_code(413); exit('The upload is larger than the server allows (post_max_size). Go back and try smaller files.');
}
if ($method === 'POST') csrf_check();

// [method, pattern, handler, public?]
$routes = [
  ['GET',  '/register', 'register_form', 1], ['POST', '/register', 'register_post', 1],
  ['GET',  '/setup', fn() => redirect('/register'), 1],
  ['GET',  '/forgot', 'forgot_form', 1], ['POST', '/forgot', 'forgot_post', 1],
  ['GET',  '/reset', 'reset_form', 1], ['POST', '/reset', 'reset_post', 1],
  ['GET',  '/login', 'login_form', 1], ['POST', '/login', 'login_post', 1],
  ['POST', '/logout', 'logout'],

  ['GET', '/', 'students_index'], ['GET', '/students', 'students_index'],
  ['GET', '/students/create', 'student_form'], ['POST', '/students/create', 'student_save'],
  ['GET', '/students/(\d+)', 'student_show'],
  ['GET', '/students/(\d+)/edit', 'student_form'], ['POST', '/students/(\d+)/edit', 'student_save'],
  ['GET', '/students/(\d+)/delete', 'student_delete_confirm'], ['POST', '/students/(\d+)/delete', 'student_delete'],

  ['GET', '/profile', 'profile_page'], ['POST', '/profile/email', 'profile_email'], ['POST', '/profile/password', 'profile_password'],
  ['GET', '/files/(\d+)', 'file_serve'],

  ['GET', '/kanban', 'kanban_board'], ['POST', '/kanban/columns', 'column_add'],
  ['GET', '/columns/(\d+)/edit', 'column_edit_form'], ['POST', '/columns/(\d+)/edit', 'column_edit_save'],
  ['POST', '/columns/(\d+)/left', fn($id) => column_move($id, -1)], ['POST', '/columns/(\d+)/right', fn($id) => column_move($id, 1)],
  ['GET', '/columns/(\d+)/delete', 'column_delete_confirm'], ['POST', '/columns/(\d+)/delete', 'column_delete'],
  ['GET', '/tasks/create', 'task_form'], ['POST', '/tasks/create', 'task_save'],
  ['GET', '/tasks/(\d+)', 'task_show'],
  ['GET', '/tasks/(\d+)/edit', 'task_form'], ['POST', '/tasks/(\d+)/edit', 'task_save'],
  ['POST', '/tasks/(\d+)/move', 'task_move'],
  ['GET', '/tasks/(\d+)/delete', 'task_delete_confirm'], ['POST', '/tasks/(\d+)/delete', 'task_delete'],

  ['GET', '/backup', 'backup_page'], ['GET', '/export', 'backup_export'], ['POST', '/import', 'backup_import'],

  ['GET', '/courses', 'courses_index'],
  ['GET', '/courses/create', 'course_form'], ['POST', '/courses/create', 'course_save'],
  ['GET', '/courses/(\d+)', fn($id) => redirect('/students?course=' . $id)],
  ['GET', '/courses/(\d+)/edit', 'course_form'], ['POST', '/courses/(\d+)/edit', 'course_save'],
  ['GET', '/courses/(\d+)/delete', 'course_delete_confirm'], ['POST', '/courses/(\d+)/delete', 'course_delete'],
];
foreach (['observations', 'updates', 'task_updates'] as $t) {
  $routes[] = ['POST', ($t === 'task_updates' ? '/tasks/(\d+)/updates' : '/students/(\d+)/' . $t), fn($sid) => note_add($t, (int)$sid)];
  $routes[] = ['GET',  "/$t/(\\d+)/edit", fn($id) => note_edit_form($t, (int)$id)];
  $routes[] = ['POST', "/$t/(\\d+)/edit", fn($id) => note_edit_save($t, (int)$id)];
  $routes[] = ['GET',  "/$t/(\\d+)/delete", fn($id) => note_delete_confirm($t, (int)$id)];
  $routes[] = ['POST', "/$t/(\\d+)/delete", fn($id) => note_delete($t, (int)$id)];
}

foreach ($routes as $r) {
  if ($r[0] === $method && preg_match('#^' . $r[1] . '$#', $path, $m)) {
    if (empty($r[3])) require_login();
    array_shift($m);
    $r[2](...array_map('intval', $m));
    exit;
  }
}
not_found();
