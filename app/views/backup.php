<?php $title = 'Backup'; ?>
<h1>Backup</h1>
<?php if ($error): ?><p class="flash error"><?= e($error) ?></p><?php endif; ?>

<h2>Export</h2>
<p>Download your data as one JSON file: <?= $counts['courses'] ?> courses, <?= $counts['students'] ?> students,
<?= $counts['observations'] ?> observations, <?= $counts['updates'] ?> updates, <?= $counts['columns'] ?> board phases,
<?= $counts['tasks'] ?> tasks and <?= $counts['task_updates'] ?> task updates. This is only your own data; login accounts are not included.</p>
<p class="muted"><strong>Attached files are not in the JSON file</strong> (you have <?= $counts['files'] ?>). To move everything to another server, also copy the <code>storage/uploads</code> folder.</p>
<p><a class="btn primary" href="/export">Download backup</a></p>

<h2>Import</h2>
<p>Restore from a backup file, for example on a new installation or another account. The import <strong>replaces all your current data</strong>,
including the files attached to your current updates. It is checked first, and if anything is wrong nothing is changed.</p>
<form method="post" action="/import" enctype="multipart/form-data" class="narrow">
  <?= csrf_field() ?>
  <label>Backup file <input type="file" name="file" accept=".json,application/json" required></label>
  <label class="check"><input type="checkbox" name="confirm" value="1" required> I understand this deletes my current students, courses, observations, updates, tasks and attached files.</label>
  <button class="btn danger">Import backup</button>
</form>
