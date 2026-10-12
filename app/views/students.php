<?php $title = 'Students'; ?>
<div class="bar"><h1>Students</h1><a class="btn primary" href="/students/create<?= $cid ? '?course=' . $cid : '' ?>">+ Add student</a></div>
<?php if (!$total): ?>
  <div class="empty"><p><strong>No students yet.</strong></p><p>Add your first student.</p><a class="btn primary" href="/students/create">+ Add student</a></div>
<?php else: ?>
<form class="filters" method="get" action="/">
  <input type="search" name="q" value="<?= e($search) ?>" placeholder="Search students..." aria-label="Search students">
  <select name="course" aria-label="Course" onchange="this.form.submit()">
    <option value="0">All courses</option>
    <?php foreach ($courses as $c): ?><option value="<?= $c['id'] ?>" <?= $cid == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
  </select>
  <button class="btn">Search</button>
  <?php if ($search !== '' || $cid): ?><a href="/">Clear</a><?php endif; ?>
</form>
<?php if (!$students): ?>
  <p class="empty">No students match your search.</p>
<?php else: ?>
<table class="list">
  <thead><tr><th>Student</th><th>Course</th><th>Last update</th></tr></thead>
  <tbody>
  <?php foreach ($students as $s): ?>
    <tr>
      <td><a href="/students/<?= $s['id'] ?>"><strong><?= e($s['first_name'] . ' ' . $s['last_name']) ?></strong></a></td>
      <td><?= e($s['course_name']) ?></td>
      <td class="muted"><?= e(fmt_day($s['last_update'])) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; endif; ?>
