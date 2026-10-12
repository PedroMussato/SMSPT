<?php $name = $student['first_name'] . ' ' . $student['last_name']; $title = $name; $sid = $student['id']; ?>
<p class="back"><a href="/">← Students</a></p>
<header class="profile-head">
  <div><h1><?= e($name) ?></h1><p class="muted"><a href="/?course=<?= $student['course_id'] ?>"><?= e($student['course_name']) ?></a></p></div>
  <div class="actions"><a class="btn" href="/students/<?= $sid ?>/edit">Edit student</a><a class="btn danger-outline" href="/students/<?= $sid ?>/delete">Delete</a></div>
</header>

<section>
  <h2>Student data</h2>
  <dl class="data">
    <dt>First name</dt><dd><?= e($student['first_name']) ?></dd>
    <dt>Last name</dt><dd><?= e($student['last_name']) ?></dd>
    <dt>Email</dt><dd><?= $student['email'] ? '<a href="mailto:' . e($student['email']) . '">' . e($student['email']) . '</a>' : '<span class="muted">—</span>' ?></dd>
    <dt>Price</dt><dd><?= $student['price'] !== null ? e(money($student['price'])) : '<span class="muted">—</span>' ?></dd>
    <dt>LinkedIn</dt><dd><?= $student['linkedin'] ? '<a href="' . e($student['linkedin']) . '" target="_blank" rel="noopener noreferrer">' . e(short_url($student['linkedin'])) . '</a>' : '<span class="muted">—</span>' ?></dd>
    <dt>Course</dt><dd><?= e($student['course_name']) ?></dd>
  </dl>
</section>

<section>
  <h2>Observations</h2>
  <?php if (!$observations): ?>
    <p class="muted">No observations yet.</p>
  <?php else: ?>
    <ul class="obs">
    <?php foreach ($observations as $o): ?>
      <li><span class="text"><?= e($o['content']) ?></span>
        <span class="row-actions"><a href="/observations/<?= $o['id'] ?>/edit">Edit</a><a class="danger-link" href="/observations/<?= $o['id'] ?>/delete">Delete</a></span></li>
    <?php endforeach; ?>
    </ul>
  <?php endif; ?>
  <details class="add"><summary class="btn">+ Add observation</summary>
    <form method="post" action="/students/<?= $sid ?>/observations"><?= csrf_field() ?>
      <textarea name="content" rows="3" required aria-label="Observation"></textarea>
      <div class="actions"><button class="btn primary">Save</button></div>
    </form>
  </details>
</section>

<section>
  <h2>Updates</h2>
  <details class="add"><summary class="btn primary">+ Add update</summary>
    <form method="post" action="/students/<?= $sid ?>/updates" enctype="multipart/form-data"><?= csrf_field() ?>
      <textarea name="content" rows="5" aria-label="Update"></textarea>
      <label class="file">Attach files <input type="file" name="files[]" multiple></label>
      <div class="actions"><button class="btn primary">Save</button></div>
    </form>
  </details>
  <?php if (!$updates): ?>
    <p class="muted">No updates yet. Add the first update to start the student's history.</p>
  <?php else: ?>
    <ol class="timeline">
    <?php foreach ($updates as $u): ?>
      <li>
        <time datetime="<?= e(str_replace(' ', 'T', $u['created_at'])) ?>Z"><?= e(fmt_dt($u['created_at'])) ?></time>
        <?php if ($u['content'] !== ''): ?><div class="text"><?= e($u['content']) ?></div><?php endif; ?>
        <?= files_html($files[$u['id']] ?? []) ?>
        <span class="row-actions"><a href="/updates/<?= $u['id'] ?>/edit">Edit</a><a class="danger-link" href="/updates/<?= $u['id'] ?>/delete">Delete</a></span>
      </li>
    <?php endforeach; ?>
    </ol>
  <?php endif; ?>
</section>
