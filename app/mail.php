<?php
/** Minimal SMTP client (no libraries needed). Supports SSL (465), STARTTLS (587) and plain. Throws on failure. */
function mail_config(): array {
    static $c;
    if ($c === null) {
        $f = __DIR__ . '/../mail.config.php';
        if (!is_file($f)) throw new RuntimeException('mail.config.php is missing');
        $c = require $f;
    }
    return $c;
}
function app_url(): string {
    $u = rtrim((string)(mail_config()['app_url'] ?? ''), '/');
    if (!preg_match('#^https?://[^\s]+$#i', $u)) throw new RuntimeException('app_url in mail.config.php is not a valid URL');
    return $u;
}
function send_mail(string $to, string $subject, string $body): void {
    $c = mail_config();
    $from = (string)($c['from_email'] ?? '');
    if (!filter_var($to, FILTER_VALIDATE_EMAIL) || !filter_var($from, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Invalid sender or recipient address');
    $enc = strtolower((string)($c['encryption'] ?? 'tls'));
    $host = (string)$c['host']; $port = (int)$c['port'];
    $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => $host]]);
    $fp = @stream_socket_client(($enc === 'ssl' ? 'ssl://' : 'tcp://') . "$host:$port", $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) throw new RuntimeException("SMTP connect failed: $errstr ($errno)");
    stream_set_timeout($fp, 15);
    $read = function () use ($fp): string {
        $out = '';
        while (($l = fgets($fp, 1024)) !== false) { $out .= $l; if (strlen($l) < 4 || $l[3] !== '-') break; }
        return $out;
    };
    $cmd = function (string $line, array $ok, string $label = '') use ($fp, $read): string {
        if ($line !== '') fwrite($fp, $line . "\r\n");
        $r = $read();
        if (!in_array((int)substr($r, 0, 3), $ok, true)) throw new RuntimeException('SMTP error' . ($label !== '' ? " after $label" : '') . ': ' . trim($r));
        return $r;
    };
    try {
        $helo = parse_url(app_url(), PHP_URL_HOST) ?: 'localhost';
        $cmd('', [220], 'connect');
        $cmd("EHLO $helo", [250], 'EHLO');
        if ($enc === 'tls') {
            $cmd('STARTTLS', [220], 'STARTTLS');
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) throw new RuntimeException('Could not start TLS');
            $cmd("EHLO $helo", [250], 'EHLO (TLS)');
        }
        if (($c['username'] ?? '') !== '') {
            $cmd('AUTH LOGIN', [334], 'AUTH');
            $cmd(base64_encode((string)$c['username']), [334], 'username');
            $cmd(base64_encode((string)$c['password']), [235], 'password');
        }
        $cmd("MAIL FROM:<$from>", [250], 'MAIL FROM');
        $cmd("RCPT TO:<$to>", [250, 251], 'RCPT TO');
        $cmd('DATA', [354], 'DATA');
        $name = trim(str_replace(["\r", "\n"], ' ', (string)($c['from_name'] ?? '')));
        $msg = implode("\r\n", [
            'Date: ' . date('r'),
            'From: ' . ($name !== '' ? '=?UTF-8?B?' . base64_encode($name) . "?= <$from>" : $from),
            "To: <$to>",
            'Subject: =?UTF-8?B?' . base64_encode(str_replace(["\r", "\n"], ' ', $subject)) . '?=',
            'Message-ID: <' . bin2hex(random_bytes(8)) . '@' . substr(strrchr($from, '@'), 1) . '>',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
            '',
            rtrim(chunk_split(base64_encode($body), 76, "\r\n")),
        ]);
        fwrite($fp, $msg . "\r\n.\r\n");
        $cmd('', [250], 'message body');
        @fwrite($fp, "QUIT\r\n");
    } finally {
        fclose($fp);
    }
}
