<?php $nav = $nav ?? ''; $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH); $f = flash_get(); ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(($title ?? '') ? $title . ' · Student Manager' : 'Student Manager') ?></title>
<link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<?php if (!empty($_SESSION['uid'])): ?>
<header class="top">
  <a class="brand" href="/">Student Manager</a>
  <nav>
    <a href="/" <?= ($uri === '/' || str_starts_with($uri, '/students')) ? 'aria-current="page"' : '' ?>>Students</a>
    <a href="/kanban" <?= (str_starts_with($uri, '/kanban') || str_starts_with($uri, '/tasks') || str_starts_with($uri, '/columns')) ? 'aria-current="page"' : '' ?>>Board</a>
    <a href="/courses" <?= str_starts_with($uri, '/courses') ? 'aria-current="page"' : '' ?>>Courses</a>
    <a href="/backup" <?= str_starts_with($uri, '/backup') ? 'aria-current="page"' : '' ?>>Backup</a>
  </nav>
  <a class="muted" href="/profile" <?= str_starts_with($uri, '/profile') ? 'aria-current="page"' : '' ?>><?= e($_SESSION['uname'] ?? 'Profile') ?></a>
  <form method="post" action="/logout"><?= csrf_field() ?><button class="link">Log out</button></form>
</header>
<?php endif; ?>
<main>
<?php if ($f): ?><p class="flash <?= e($f[1]) ?>" role="status"><?= e($f[0]) ?></p><?php endif; ?>
<?= $content ?>
</main>
<script>
document.querySelectorAll('details.add').forEach(d => d.addEventListener('toggle', () => {
  if (d.open) d.querySelector('textarea, input')?.focus();
}));
</script>
</body>
</html>
