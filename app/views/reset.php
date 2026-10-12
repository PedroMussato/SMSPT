<?php $title = 'Choose a new password'; ?>
<section class="narrow">
  <h1>Choose a new password</h1>
  <?php if ($error): ?><p class="flash error"><?= e($error) ?></p><?php endif; ?>
  <form method="post" action="/reset">
    <?= csrf_field() ?>
    <input type="hidden" name="token" value="<?= e($token) ?>">
    <label>New password <input type="password" name="password" required autofocus autocomplete="new-password"></label>
    <label>Repeat new password <input type="password" name="password2" required autocomplete="new-password"></label>
    <button class="btn primary">Save new password</button>
  </form>
</section>
