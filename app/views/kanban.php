<?php $title = 'Board'; ?>
<div class="bar"><h1>Board</h1><a class="btn primary" href="/tasks/create">+ Add task</a></div>
<?php if (!$cols): ?><div class="empty"><p><strong>No phases yet.</strong></p><p>Add your first phase, for example “To-do”.</p></div><?php endif; ?>
<div class="board" data-csrf="<?= e(csrf()) ?>">
<?php foreach ($cols as $i => $c): $prev = $cols[$i - 1] ?? null; $next = $cols[$i + 1] ?? null; $list = $by[$c['id']] ?? []; ?>
  <section class="kcol" data-id="<?= (int)$c['id'] ?>">
    <header>
      <h2><?= e($c['name']) ?> <span class="muted"><?= count($list) ?></span></h2>
      <span class="row-actions">
        <?php if ($prev): ?><form method="post" action="/columns/<?= (int)$c['id'] ?>/left"><?= csrf_field() ?><button class="link" title="Move phase left" aria-label="Move phase left">◀</button></form><?php endif; ?>
        <?php if ($next): ?><form method="post" action="/columns/<?= (int)$c['id'] ?>/right"><?= csrf_field() ?><button class="link" title="Move phase right" aria-label="Move phase right">▶</button></form><?php endif; ?>
        <a href="/columns/<?= (int)$c['id'] ?>/edit">Edit</a><a class="danger-link" href="/columns/<?= (int)$c['id'] ?>/delete">Delete</a>
      </span>
    </header>
    <div class="klist">
    <?php foreach ($list as $t): ?>
      <article class="task-card" draggable="true" data-id="<?= (int)$t['id'] ?>">
        <a href="/tasks/<?= (int)$t['id'] ?>"><?= e($t['title']) ?></a>
        <?php if ($t['student_name']): ?><div class="muted small">Student: <?= e($t['student_name']) ?></div>
        <?php elseif ($t['course_name']): ?><div class="muted small">Course: <?= e($t['course_name']) ?></div><?php endif; ?>
        <?php if ($prev || $next): ?>
        <form method="post" action="/tasks/<?= (int)$t['id'] ?>/move" class="mv"><?= csrf_field() ?>
          <?php if ($prev): ?><button class="link" name="column_id" value="<?= (int)$prev['id'] ?>" title="Move to <?= e($prev['name']) ?>" aria-label="Move to <?= e($prev['name']) ?>">◀</button><?php endif; ?>
          <?php if ($next): ?><button class="link" name="column_id" value="<?= (int)$next['id'] ?>" title="Move to <?= e($next['name']) ?>" aria-label="Move to <?= e($next['name']) ?>">▶</button><?php endif; ?>
        </form>
        <?php endif; ?>
      </article>
    <?php endforeach; ?>
    </div>
    <a class="add-task" href="/tasks/create?column=<?= (int)$c['id'] ?>">+ Add task</a>
  </section>
<?php endforeach; ?>
  <section class="kcol newcol">
    <details class="add"><summary class="btn">+ Add phase</summary>
      <form method="post" action="/kanban/columns"><?= csrf_field() ?>
        <input name="name" placeholder="Phase name" required maxlength="40" aria-label="Phase name">
        <div class="actions"><button class="btn primary">Add</button></div>
      </form>
    </details>
  </section>
</div>
<script src="/assets/kanban.js"></script>
