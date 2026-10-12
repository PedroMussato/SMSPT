<?php $title = 'Courses'; ?>
<div class="bar"><h1>Courses</h1><a class="btn primary" href="/courses/create">+ Add course</a></div>
<?php if (!$courses): ?>
  <div class="empty"><p><strong>No courses yet.</strong></p><p>Create a course before adding students.</p><a class="btn primary" href="/courses/create">+ Add course</a></div>
<?php else: ?>
<ul class="rows">
<?php foreach ($courses as $c): ?>
  <li>
    <div>
      <a href="/courses/<?= $c['id'] ?>"><strong><?= e($c['name']) ?></strong></a>
      <?php if ($c['description'] !== ''): ?><div class="muted"><?= e($c['description']) ?></div><?php endif; ?>
    </div>
    <span class="muted"><?= $c['n'] ?> student<?= $c['n'] == 1 ? '' : 's' ?></span>
    <span class="row-actions"><a href="/courses/<?= $c['id'] ?>/edit">Edit</a><a class="danger-link" href="/courses/<?= $c['id'] ?>/delete">Delete</a></span>
  </li>
<?php endforeach; ?>
</ul>
<?php endif; ?>
