<?php
/* ---------- File attachments (stored outside the web root in storage/uploads) ---------- */
function upload_dir(): string {
    $d = __DIR__ . '/../storage/uploads';
    if (!is_dir($d)) mkdir($d, 0750, true);
    return $d;
}
function max_upload_bytes(): int { return (defined('MAX_UPLOAD_MB') ? (int)MAX_UPLOAD_MB : 100) * 1048576; }
function ini_size(string $key): int {
    $v = trim((string)ini_get($key)); $n = (int)$v;
    return match (strtolower(substr($v, -1))) { 'g' => $n * 1073741824, 'm' => $n * 1048576, 'k' => $n * 1024, default => $n ?: PHP_INT_MAX };
}
/** Files posted in the "files[]" field, without the empty slots. */
function uploaded_files(): array {
    $f = $_FILES['files'] ?? null; $out = [];
    if (!$f || !is_array($f['name'])) return [];
    foreach ($f['name'] as $i => $name) {
        if ($f['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
        $out[] = ['name' => (string)$name, 'tmp' => $f['tmp_name'][$i], 'size' => (int)$f['size'][$i], 'error' => $f['error'][$i]];
    }
    return $out;
}
function validate_uploads(array $files): ?string {
    foreach ($files as $f) {
        if (in_array($f['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || $f['size'] > max_upload_bytes())
            return '“' . $f['name'] . '” is larger than the allowed size (' . fmt_size(min(max_upload_bytes(), ini_size('upload_max_filesize'))) . ').';
        if ($f['error'] !== UPLOAD_ERR_OK) return 'Uploading “' . $f['name'] . '” failed. Please try again.';
    }
    return null;
}
function save_uploads(array $files, string $kind, int $ref): void {
    $dir = upload_dir();
    foreach ($files as $f) {
        $stored = bin2hex(random_bytes(16));
        if (!move_uploaded_file($f['tmp'], "$dir/$stored")) throw new RuntimeException('Could not store the uploaded file');
        $name = (string)preg_replace('/[\x00-\x1F\x7F\/\\\\]/u', '_', basename(str_replace('\\', '/', $f['name'])));
        $name = mb_substr($name, 0, 200); if ($name === '') $name = 'file';
        q('INSERT INTO attachments(user_id, kind, ref_id, original_name, stored_name, size, created_at) VALUES(?,?,?,?,?,?,?)',
          [uid(), $kind, $ref, $name, $stored, $f['size'], now()]);
    }
}
/** Attachments of the given notes, grouped by note id. */
function attachments_for(string $kind, array $ids): array {
    if (!$ids) return [];
    $in = implode(',', array_fill(0, count($ids), '?')); $out = [];
    foreach (rows("SELECT * FROM attachments WHERE user_id = ? AND kind = ? AND ref_id IN ($in) ORDER BY id", [uid(), $kind, ...array_map('intval', $ids)]) as $f)
        $out[$f['ref_id']][] = $f;
    return $out;
}
/** Deletes the attachment rows and returns the stored file names; call files_unlink() once the delete is safe (after commit). $idsSql is internal SQL. */
function attachments_take(string $kind, string $idsSql, array $p): array {
    $names = array_column(rows("SELECT stored_name FROM attachments WHERE kind = ? AND ref_id IN ($idsSql)", [$kind, ...$p]), 'stored_name');
    q("DELETE FROM attachments WHERE kind = ? AND ref_id IN ($idsSql)", [$kind, ...$p]);
    return $names;
}
function files_unlink(array $names): void {
    foreach ($names as $n) if (preg_match('/^[a-f0-9]{32}$/', (string)$n)) @unlink(upload_dir() . '/' . $n);
}
function file_serve(int $id) {
    $f = row('SELECT * FROM attachments WHERE id = ? AND user_id = ?', [$id, uid()]) ?? not_found();
    $path = upload_dir() . '/' . $f['stored_name'];
    if (!is_file($path)) not_found();
    $ext = strtolower(pathinfo($f['original_name'], PATHINFO_EXTENSION));
    $inline = ['pdf' => 'application/pdf', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp',
               'mp3' => 'audio/mpeg', 'm4a' => 'audio/mp4', 'wav' => 'audio/wav', 'ogg' => 'audio/ogg', 'mp4' => 'video/mp4', 'webm' => 'video/webm'];
    $view = isset($inline[$ext]) && empty($_GET['dl']);   // anything else is always downloaded, never rendered by the browser
    $size = filesize($path); $start = 0; $end = $size - 1;
    session_write_close();
    if ($size > 0 && isset($_SERVER['HTTP_RANGE']) && preg_match('/^bytes=(\d*)-(\d*)$/', $_SERVER['HTTP_RANGE'], $m) && ($m[1] !== '' || $m[2] !== '')) {
        if ($m[1] === '') $start = max(0, $size - (int)$m[2]);
        else { $start = (int)$m[1]; if ($m[2] !== '') $end = min($end, (int)$m[2]); }
        if ($start > $end || $start >= $size) { http_response_code(416); header("Content-Range: bytes */$size"); exit; }
        http_response_code(206); header("Content-Range: bytes $start-$end/$size");
    }
    $ascii = str_replace(['"', '\\'], '_', (string)preg_replace('/[^\x20-\x7E]/', '_', $f['original_name']));
    header('Content-Type: ' . ($view ? $inline[$ext] : 'application/octet-stream'));
    header('Content-Disposition: ' . ($view ? 'inline' : 'attachment') . "; filename=\"$ascii\"; filename*=UTF-8''" . rawurlencode($f['original_name']));
    if ($ext !== 'pdf') header('Content-Security-Policy: sandbox');
    header('Accept-Ranges: bytes'); header('Cache-Control: private, max-age=0');
    $len = $size ? $end - $start + 1 : 0;
    header('Content-Length: ' . $len);
    $h = fopen($path, 'rb'); fseek($h, $start); $left = $len;
    while ($left > 0 && !feof($h)) { $chunk = fread($h, min(131072, $left)); echo $chunk; $left -= strlen($chunk); flush(); }
    fclose($h); exit;
}
