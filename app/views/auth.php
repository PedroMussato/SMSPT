<?php
$reg = $mode === 'register'; $title = $reg ? 'Create account' : 'Log in';
?>
<section class="narrow">
  <h1><?= $reg ? 'Create your account' : 'Log in' ?></h1>
  <?php if ($reg): ?><p class="muted">Your students, courses and notes are private to your account.</p><?php endif; ?>
  <?php if ($error): ?><p class="flash error"><?= e($error) ?></p><?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <label>Username <input name="username" required autofocus autocomplete="username" value="<?= e($_POST['username'] ?? '') ?>"></label>
    <?php if ($reg): ?>
    <label>Email <span class="muted">(used to recover your password)</span> <input type="email" name="email" required autocomplete="email" value="<?= e($_POST['email'] ?? '') ?>"></label>
    <?php endif; ?>
    <label>Password <input type="password" name="password" required autocomplete="<?= $reg ? 'new-password' : 'current-password' ?>"></label>
    <?php if ($reg): ?>
    <label>Repeat password <input type="password" name="password2" required autocomplete="new-password"></label>
    <?php endif; ?>
    <button class="btn primary"><?= $reg ? 'Create account' : 'Log in' ?></button>
  </form>
  <p class="muted">
    <?php if ($reg): ?>Already have an account? <a href="/login">Log in</a>
    <?php else: ?><a href="/forgot">Forgot your password?</a><?php if (registration_open()): ?> · <a href="/register">Create an account</a><?php endif; ?>
    <?php endif; ?>
  </p>
</section>
