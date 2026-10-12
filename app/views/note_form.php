<?php $title = 'Edit ' . $label; ?>
<section class="narrow">
  <h1>Edit <?= e($label) ?></h1>
  <?php if ($error): ?><p class="flash error"><?= e($error) ?></p><?php endif; ?>
  <form method="post" action="<?= e($action) ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <label>Text <textarea name="content" rows="8" <?= $allow_files ? '' : 'required' ?> autofocus><?= e($content) ?></textarea></label>
    <?php if ($allow_files): ?>
      <?php if ($files): ?>
        <p>Attached files</p>
        <ul class="files">
        <?php foreach ($files as $f): ?>
          <li><label class="check"><input type="checkbox" name="remove[]" value="<?= (int)$f['id'] ?>"> Remove “<?= e($f['original_name']) ?>” <span class="muted">(<?= e(fmt_size((int)$f['size'])) ?>)</span></label></li>
        <?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <label class="file">Add files <input type="file" name="files[]" multiple></label>
    <?php endif; ?>
    <div class="actions"><a class="btn" href="<?= e($back) ?>">Cancel</a><button class="btn primary">Save changes</button></div>
  </form>
</section>
