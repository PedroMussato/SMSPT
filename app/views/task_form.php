<?php
$title = $id ? 'Edit task' : 'Add task'; $errors = $errors ?? [];
$err = fn($k) => isset($errors[$k]) ? '<span class="err">' . e($errors[$k]) . '</span>' : '';
?>
<section class="narrow">
  <h1><?= e($title) ?></h1>
  <form method="post" action="<?= $id ? "/tasks/$id/edit" : '/tasks/create' ?>">
    <?= csrf_field() ?>
    <label>Title <input name="title" value="<?= e($task['title']) ?>" required maxlength="200" autofocus><?= $err('title') ?></label>
    <label>Description <span class="muted">(optional)</span> <textarea name="description" rows="5"><?= e($task['description']) ?></textarea></label>
    <label>Phase
      <select name="column_id" required>
        <?php foreach ($cols as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)$task['column_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
      </select><?= $err('column_id') ?>
    </label>
    <label>Linked to <span class="muted">(optional)</span>
      <select name="link">
        <option value="">Nothing</option>
        <?php if ($courses): ?><optgroup label="Courses">
          <?php foreach ($courses as $c): $v = 'c:' . $c['id']; ?><option value="<?= e($v) ?>" <?= $task['link'] === $v ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
        </optgroup><?php endif; ?>
        <?php if ($students): ?><optgroup label="Students">
          <?php foreach ($students as $s): $v = 's:' . $s['id']; ?><option value="<?= e($v) ?>" <?= $task['link'] === $v ? 'selected' : '' ?>><?= e($s['name'] . ' — ' . $s['course']) ?></option><?php endforeach; ?>
        </optgroup><?php endif; ?>
      </select><?= $err('link') ?>
    </label>
    <div class="actions"><a class="btn" href="<?= $id ? "/tasks/$id" : '/kanban' ?>">Cancel</a><button class="btn primary"><?= $id ? 'Save changes' : 'Create task' ?></button></div>
  </form>
</section>
