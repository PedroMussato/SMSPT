# Student Manager

Courses → Students → Observations + chronological Updates. PHP 8.1+, SQLite (PDO), Apache.

## Run locally
    php -S localhost:8000 -t public public/index.php
Open http://localhost:8000 — the first visit lets you register. Anyone can register; each account only sees its own data.

## Apache
Point the DocumentRoot at `public/` (mod_rewrite enabled, `AllowOverride All`).
`storage/` must be writable by the web server user; the SQLite file is created automatically
on first request and lives outside the web root. Requires the `pdo_sqlite` PHP extension.

## Settings
`config/config.php` — `APP_TZ` is your timezone (timestamps are stored in UTC); `ALLOW_REGISTRATION = false` closes sign-up.
Upgrading from the single-user version: existing data is assigned to the first account automatically on the first request.
Serve over HTTPS in production.

## Email (password recovery)
Copy `mail.config.example.php` to `mail.config.php` (project root) and edit it: SMTP host, port, encryption (`tls`/`ssl`/`none`), login, sender and
`app_url` (the public address used in the reset link). Keep this file private, and don't overwrite it when upgrading.
If a reset email doesn't arrive, check the PHP/Apache error log for a line starting with "Password reset email failed".

## Files and the Board
- Updates (on students and on tasks) accept any number of files of any type. They are stored in `storage/uploads/`,
  outside the web root, and are only served to the account that owns them. PDFs, images, audio and video open in the
  browser (video/audio can seek); everything else downloads.
- Upload limits come from PHP: `upload_max_filesize` and `post_max_size` (see `public/.user.ini`, which works with
  PHP-FPM/CGI; with mod_php set them in `php.ini`). The app also caps each file at 100 MB; change it with
  `const MAX_UPLOAD_MB = 200;` in `config/config.php`.
- Backups (JSON) do not contain attached files. To move a whole installation, copy `storage/database.sqlite` and
  `storage/uploads/`, or import the JSON and copy `storage/uploads/` only if you keep the same database.
- Board: each user has their own phases (Backlog, To-do, Doing, Done to start), which can be added, renamed, reordered
  and deleted (a phase with tasks can't be deleted). Tasks can be linked to one course or one student.
