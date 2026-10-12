<?php
function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function redirect(string $to): never { header('Location: ' . $to); exit; }
function now(): string { return gmdate('Y-m-d H:i:s'); }
function csrf(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="' . e(csrf()) . '">'; }
function csrf_check(): void {
    if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['_csrf'] ?? ''))) {
        http_response_code(419); exit('Session expired. Go back, reload the page and try again.');
    }
}
function flash(string $msg, string $type = 'ok'): void { $_SESSION['flash'] = [$msg, $type]; }
function flash_get(): ?array { $f = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $f; }
function uid(): int { return (int)($_SESSION['uid'] ?? 0); }
function require_login(): void { if (!uid()) redirect('/login'); }
function render(string $view, array $d = []): void {
    extract($d);
    ob_start(); require __DIR__ . "/views/$view.php"; $content = ob_get_clean();
    require __DIR__ . '/views/layout.php';
}
function not_found(): never { http_response_code(404); render('message', ['title' => 'Not found', 'text' => 'That page does not exist.']); exit; }
function fmt_dt(string $ts): string {
    $d = new DateTime($ts, new DateTimeZone('UTC')); $d->setTimezone(new DateTimeZone(APP_TZ));
    return $d->format('M j, Y · g:i A');
}
function fmt_day(?string $ts): string { return $ts ? explode(' · ', fmt_dt($ts))[0] : '—'; }
function money($p): string { return $p === null || $p === '' ? '' : '$' . rtrim(rtrim(number_format((float)$p, 2, '.', ''), '0'), '.'); }
function short_url(string $u): string { return preg_replace('#^https?://(www\.)?#', '', rtrim($u, '/')); }
function fmt_size(int $b): string {
    $u = ['B', 'KB', 'MB', 'GB']; $i = 0; $x = (float)$b;
    while ($x >= 1024 && $i < 3) { $x /= 1024; $i++; }
    return ($i === 0 ? (string)$b : number_format($x, $x < 10 ? 1 : 0)) . ' ' . $u[$i];
}
function files_html(array $list): string {
    if (!$list) return '';
    $h = '<ul class="files">';
    foreach ($list as $f) {
        $id = (int)$f['id'];
        $h .= '<li><a href="/files/' . $id . '" target="_blank" rel="noopener">' . e($f['original_name']) . '</a> <span class="muted">' . e(fmt_size((int)$f['size']))
            . '</span> <a class="muted" href="/files/' . $id . '?dl=1" title="Download" aria-label="Download ' . e($f['original_name']) . '">↓</a></li>';
    }
    return $h . '</ul>';
}
