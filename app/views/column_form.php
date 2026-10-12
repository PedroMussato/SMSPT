<?php $title = 'Rename phase'; ?>
<section class="narrow">
  <h1>Rename phase</h1>
  <?php if ($error): ?><p class="flash error"><?= e($error) ?></p><?php endif; ?>
  <form method="post" action="/columns/<?= (int)$id ?>/edit">
    <?= csrf_field() ?>
    <label>Name <input name="name" value="<?= e($name) ?>" required maxlength="40" autofocus></label>
    <div class="actions"><a class="btn" href="/kanban">Cancel</a><button class="btn primary">Save changes</button></div>
  </form>
</section>
