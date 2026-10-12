<section class="narrow">
  <h1><?= e($title) ?></h1>
  <?php if (!empty($quote)): ?><blockquote class="text"><?= e($quote) ?></blockquote><?php endif; ?>
  <p><?= e($text) ?></p>
  <form method="post" action="<?= e($action) ?>" class="actions">
    <?= csrf_field() ?>
    <a class="btn" href="<?= e($back) ?>"><?= $blocked ? 'Back' : 'Cancel' ?></a>
    <?php if (!$blocked): ?><button class="btn danger">Delete</button><?php endif; ?>
  </form>
</section>
