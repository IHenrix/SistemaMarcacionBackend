<?php

function sendMailGmail(array $params): bool
{
    $smtpUser = SMTP_GMAIL_USER;
    $smtpPass = SMTP_GMAIL_PASS;

    if (empty($smtpUser) || empty($smtpPass)) {
        return false; // sin credenciales, no intentamos
    }

    $to = $params['to'] ?? $smtpUser;
    $subject = $params['subject'] ?? '';
    $html = $params['html'] ?? '';
    $from = $params['from'] ?? $smtpUser;
    $fromName = $params['from_name'] ?? 'Contacto';
    $attachmentName = $params['attachment_name'] ?? null;
    $attachmentType = $params['attachment_type'] ?? null;
    $attachmentContent = $params['attachment_content'] ?? null;

    $host = 'smtp.gmail.com';
    $port = 587;
    $timeout = 30;

    $socket = stream_socket_client("tcp://$host:$port", $errno, $errstr, $timeout);
    if (!$socket) {
        throw new Exception("SMTP connect error: $errstr ($errno)");
    }

    $read = function () use ($socket) {
        $data = '';
        while ($line = fgets($socket, 515)) {
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return $data;
    };

    $write = function (string $cmd) use ($socket, $read) {
        fwrite($socket, $cmd . "\r\n");
        return $read();
    };

    $read(); // banner
    $write('EHLO localhost');
    $write('STARTTLS');
    if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
        throw new Exception('No se pudo iniciar TLS');
    }
    $write('EHLO localhost');
    $write('AUTH LOGIN');
    $write(base64_encode($smtpUser));
    $resp = $write(base64_encode($smtpPass));
    if (strpos($resp, '235') !== 0) {
        throw new Exception('Credenciales SMTP no aceptadas');
    }

    $write('MAIL FROM: <' . $from . '>');
    $write('RCPT TO: <' . $to . '>');
    $write('DATA');

    $boundary = 'b' . bin2hex(random_bytes(8));
    $headers = [];
    $headers[] = 'From: ' . ($fromName ? "\"{$fromName}\" <{$from}>" : $from);
    $headers[] = 'To: ' . $to;
    $headers[] = 'Subject: ' . mb_encode_mimeheader($subject, 'UTF-8');
    $headers[] = 'MIME-Version: 1.0';

    $body = '';
    if ($attachmentContent !== null && $attachmentName && $attachmentType) {
        $headers[] = 'Content-Type: multipart/mixed; boundary="' . $boundary . '"';
        $body .= '--' . $boundary . "\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($html)) . "\r\n";
        $body .= '--' . $boundary . "\r\n";
        $body .= 'Content-Type: ' . $attachmentType . '; name="' . $attachmentName . "\"\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n";
        $body .= 'Content-Disposition: attachment; filename="' . $attachmentName . "\"\r\n\r\n";
        $body .= chunk_split(base64_encode($attachmentContent)) . "\r\n";
        $body .= '--' . $boundary . "--\r\n";
    } else {
        $headers[] = 'Content-Type: text/html; charset=UTF-8';
        $headers[] = 'Content-Transfer-Encoding: base64';
        $body .= chunk_split(base64_encode($html));
    }

    $message = implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n.";
    $write($message);
    $write('QUIT');
    fclose($socket);
    return true;
}
