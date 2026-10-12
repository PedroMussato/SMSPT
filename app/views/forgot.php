<?php $title = 'Forgot password'; ?>
<section class="narrow">
  <h1>Forgot your password?</h1>
  <?php if ($sent): ?>
    <p class="flash">If an account uses that email address, we have sent a link to reset the password. It is valid for 1 hour.</p>
    <p><a href="/login">Back to log in</a></p>
  <?php else: ?>
    <p class="muted">Enter the email address of your account and we will send you a reset link.</p>
    <?php if ($error): ?><p class="flash error"><?= e($error) ?></p><?php endif; ?>
    <form method="post" action="/forgot">
      <?= csrf_field() ?>
      <label>Email <input type="email" name="email" required autofocus autocomplete="email"></label>
      <div class="actions"><a class="btn" href="/login">Cancel</a><button class="btn primary">Send reset link</button></div>
    </form>
  <?php endif; ?>
</section>
