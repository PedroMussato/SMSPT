<?php $title = $task['title']; $tid = (int)$task['id']; ?>
<p class="back"><a href="/kanban">← Board</a></p>
<header class="profile-head">
  <div>
    <h1><?= e($task['title']) ?></h1>
    <p class="muted">Phase: <strong><?= e($task['column_name']) ?></strong>
      <?php if ($task['student_id'] && $task['student_name']): ?> · Student: <a href="/students/<?= (int)$task['student_id'] ?>"><?= e($task['student_name']) ?></a>
      <?php elseif ($task['course_id'] && $task['course_name']): ?> · Course: <a href="/?course=<?= (int)$task['course_id'] ?>"><?= e($task['course_name']) ?></a><?php endif; ?></p>
  </div>
  <div class="actions"><a class="btn" href="/tasks/<?= $tid ?>/edit">Edit task</a><a class="btn danger-outline" href="/tasks/<?= $tid ?>/delete">Delete</a></div>
</header>
<form method="post" action="/tasks/<?= $tid ?>/move" class="filters"><?= csrf_field() ?>
  <input type="hidden" name="back" value="task">
  <select name="column_id" aria-label="Phase"><?php foreach ($cols as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)$task['column_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select>
  <button class="btn">Move</button>
</form>

<section>
  <h2>Description</h2>
  <?php if ($task['description'] !== ''): ?><div class="text"><?= e($task['description']) ?></div><?php else: ?><p class="muted">No description.</p><?php endif; ?>
</section>

<section>
  <h2>Updates</h2>
  <details class="add"><summary class="btn primary">+ Add update</summary>
    <form method="post" action="/tasks/<?= $tid ?>/updates" enctype="multipart/form-data"><?= csrf_field() ?>
      <textarea name="content" rows="5" aria-label="Update"></textarea>
      <label class="file">Attach files <input type="file" name="files[]" multiple></label>
      <div class="actions"><button class="btn primary">Save</button></div>
    </form>
  </details>
  <?php if (!$updates): ?>
    <p class="muted">No updates yet. Add the first update to start this task's history.</p>
  <?php else: ?>
    <ol class="timeline">
    <?php foreach ($updates as $u): ?>
      <li>
        <time datetime="<?= e(str_replace(' ', 'T', $u['created_at'])) ?>Z"><?= e(fmt_dt($u['created_at'])) ?></time>
        <?php if ($u['content'] !== ''): ?><div class="text"><?= e($u['content']) ?></div><?php endif; ?>
        <?= files_html($files[$u['id']] ?? []) ?>
        <span class="row-actions"><a href="/task_updates/<?= (int)$u['id'] ?>/edit">Edit</a><a class="danger-link" href="/task_updates/<?= (int)$u['id'] ?>/delete">Delete</a></span>
      </li>
    <?php endforeach; ?>
    </ol>
  <?php endif; ?>
</section>
