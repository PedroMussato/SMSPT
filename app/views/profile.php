<?php $title = 'Profile'; ?>
<h1>Profile</h1>
<p class="muted">Logged in as <strong><?= e($user['username']) ?></strong></p>

<h2>Email</h2>
<p><?= $user['email'] ? e($user['email']) : '<span class="muted">No email set. Add one so you can recover your password.</span>' ?></p>
<form method="post" action="/profile/email" class="narrow">
  <?= csrf_field() ?>
  <?php if ($emailErr): ?><p class="flash error"><?= e($emailErr) ?></p><?php endif; ?>
  <label>New email <input type="email" name="email" required autocomplete="email" value="<?= e($_POST['email'] ?? '') ?>"></label>
  <label>Current password <input type="password" name="current" required autocomplete="current-password"></label>
  <button class="btn primary">Change email</button>
</form>

<h2>Password</h2>
<form method="post" action="/profile/password" class="narrow">
  <?= csrf_field() ?>
  <?php if ($pwErr): ?><p class="flash error"><?= e($pwErr) ?></p><?php endif; ?>
  <label>Current password <input type="password" name="current" required autocomplete="current-password"></label>
  <label>New password <input type="password" name="password" required autocomplete="new-password"></label>
  <label>Repeat new password <input type="password" name="password2" required autocomplete="new-password"></label>
  <button class="btn primary">Change password</button>
</form>
