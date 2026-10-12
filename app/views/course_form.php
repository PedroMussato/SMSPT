<?php $title = $id ? 'Edit course' : 'Add course'; ?>
<section class="narrow">
  <h1><?= e($title) ?></h1>
  <?php if ($error): ?><p class="flash error"><?= e($error) ?></p><?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <label>Course name <input name="name" value="<?= e($course['name']) ?>" required autofocus></label>
    <label>Description <span class="muted">(optional)</span> <textarea name="description" rows="3"><?= e($course['description']) ?></textarea></label>
    <div class="actions"><a class="btn" href="/courses">Cancel</a><button class="btn primary"><?= $id ? 'Save changes' : 'Create course' ?></button></div>
  </form>
</section>
