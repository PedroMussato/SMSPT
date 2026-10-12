<?php
$title = $id ? 'Edit student' : 'Add student';
$errors = $errors ?? [];
$field = function ($name, $label, $type = 'text', $req = false, $hint = '') use ($student, $errors) { ?>
  <label><?= e($label) ?> <?= $req ? '' : '<span class="muted">(optional)</span>' ?>
    <input type="<?= $type ?>" name="<?= $name ?>" value="<?= e($student[$name] ?? '') ?>" <?= $req ? 'required' : '' ?> <?= $name === 'first_name' ? 'autofocus' : '' ?> <?= $name === 'price' ? 'inputmode="decimal"' : '' ?>>
    <?php if (isset($errors[$name])): ?><span class="err"><?= e($errors[$name]) ?></span><?php endif; ?>
  </label>
<?php }; ?>
<section class="narrow">
  <h1><?= e($title) ?></h1>
  <form method="post">
    <?= csrf_field() ?>
    <?php $field('first_name', 'First name', 'text', true); $field('last_name', 'Last name', 'text', true); ?>
    <?php $field('email', 'Email', 'text'); $field('price', 'Price', 'text'); $field('linkedin', 'LinkedIn', 'text'); ?>
    <label>Course
      <select name="course_id" required>
        <option value="">Choose a course</option>
        <?php foreach ($courses as $c): ?><option value="<?= $c['id'] ?>" <?= (int)$student['course_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
      </select>
      <?php if (isset($errors['course_id'])): ?><span class="err"><?= e($errors['course_id']) ?></span><?php endif; ?>
    </label>
    <div class="actions"><a class="btn" href="<?= $id ? "/students/$id" : '/' ?>">Cancel</a><button class="btn primary"><?= $id ? 'Save changes' : 'Create student' ?></button></div>
  </form>
</section>
