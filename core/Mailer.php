<?php
/**
 * Mailer — Envio de e-mail via SMTP (sem dependências externas)
 * Usa as configurações de config.php: MAIL_HOST, MAIL_PORT, MAIL_USER, MAIL_PASS
 *
 * Uso:
 *   Mailer::send('dest@email.com', 'Nome Destino', 'Assunto', '<p>HTML</p>');
 */
class Mailer
{
    public static function send(
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody
    ): bool {
        // Texto simples como fallback
        $textBody = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</div>'], "\n", $htmlBody));
        $textBody = html_entity_decode($textBody, ENT_QUOTES, 'UTF-8');
        $textBody = preg_replace('/\n{3,}/', "\n\n", trim($textBody));

        $boundary = '----=_Part_' . md5(uniqid('', true));
        $from     = MAIL_USER;
        $fromName = MAIL_FROM_NAME;
        $port     = (int) MAIL_PORT;

        // Headers
        $headers  = "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <$from>\r\n";
        $headers .= "To: =?UTF-8?B?" . base64_encode($toName) . "?= <$toEmail>\r\n";
        $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n";
        $headers .= "X-Mailer: GestorPro\r\n";

        $body  = "--$boundary\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($textBody)) . "\r\n";
        $body .= "--$boundary\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($htmlBody)) . "\r\n";
        $body .= "--$boundary--\r\n";

        try {
            // Conecta SMTP com TLS/STARTTLS
            $ctx    = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
            $socket = ($port === 465)
                ? stream_socket_client("ssl://" . MAIL_HOST . ":$port", $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx)
                : stream_socket_client("tcp://" . MAIL_HOST . ":$port", $errno, $errstr, 15);

            if (!$socket) {
                error_log("Mailer: conexão falhou — $errstr ($errno)");
                return false;
            }

            $read = fgets($socket, 512);
            if (!str_starts_with($read, '220')) { fclose($socket); return false; }

            // EHLO
            self::cmd($socket, "EHLO " . gethostname());
            $ehlo = self::readAll($socket);

            // STARTTLS para porta 587
            if ($port === 587) {
                self::cmd($socket, "STARTTLS");
                $tls = fgets($socket, 512);
                if (!str_starts_with($tls, '220')) { fclose($socket); return false; }
                stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                self::cmd($socket, "EHLO " . gethostname());
                self::readAll($socket);
            }

            // AUTH LOGIN
            self::cmd($socket, "AUTH LOGIN");
            fgets($socket, 512);
            self::cmd($socket, base64_encode(MAIL_USER));
            fgets($socket, 512);
            self::cmd($socket, base64_encode(MAIL_PASS));
            $auth = fgets($socket, 512);
            if (!str_starts_with($auth, '235')) { fclose($socket); return false; }

            // MAIL FROM / RCPT TO / DATA
            self::cmd($socket, "MAIL FROM:<$from>");
            fgets($socket, 512);
            self::cmd($socket, "RCPT TO:<$toEmail>");
            fgets($socket, 512);
            self::cmd($socket, "DATA");
            fgets($socket, 512);
            fwrite($socket, $headers . "\r\n" . $body . "\r\n.\r\n");
            $sent = fgets($socket, 512);
            self::cmd($socket, "QUIT");
            fclose($socket);

            return str_starts_with($sent, '250');
        } catch (\Throwable $e) {
            error_log("Mailer exception: " . $e->getMessage());
            return false;
        }
    }

    // ── Template padrão de e-mail ────────────────────────────
    public static function template(string $title, string $body, string $btnText = '', string $btnUrl = ''): string
    {
        $siteName = defined('APP_NAME') ? APP_NAME : 'GestorPro';
        $btn = $btnText ? "
            <div style='text-align:center;margin:28px 0'>
                <a href='$btnUrl' style='background:#5B8DEF;color:#fff;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:700;font-size:15px'>$btnText</a>
            </div>" : '';
        return "
        <div style='font-family:-apple-system,BlinkMacSystemFont,Segoe UI,sans-serif;background:#0d1117;min-height:100vh;padding:40px 16px'>
          <div style='max-width:480px;margin:0 auto;background:#161b22;border:1px solid #30363d;border-radius:12px;overflow:hidden'>
            <div style='background:#1c2128;padding:24px 32px;border-bottom:1px solid #30363d'>
              <div style='font-size:20px;font-weight:800;color:#e6edf3;letter-spacing:-0.5px'>$siteName</div>
            </div>
            <div style='padding:32px'>
              <h2 style='color:#e6edf3;font-size:18px;margin:0 0 16px;font-weight:700'>$title</h2>
              <div style='color:#8b949e;font-size:14px;line-height:1.7'>$body</div>
              $btn
            </div>
            <div style='padding:16px 32px;border-top:1px solid #30363d;text-align:center;font-size:11px;color:#484f58'>
              © $siteName · Este e-mail foi enviado automaticamente, não responda.
            </div>
          </div>
        </div>";
    }

    private static function cmd($socket, string $cmd): void
    {
        fwrite($socket, $cmd . "\r\n");
    }

    private static function readAll($socket): string
    {
        $out = '';
        while (($line = fgets($socket, 512)) !== false) {
            $out .= $line;
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        return $out;
    }
}
