<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/jwt.php';
require_once __DIR__ . '/database.php';

/**
 * Genera un UUID v4 (similar a Java UUID.randomUUID())
 */
function generarUUID() {
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // version 4
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // variant bits

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

/**
 * Genera JWT específico para recuperación (15 minutos de expiración)
 */
function generarJWTRecuperacion($userId, $email) {
    $header = [
        'typ' => 'JWT',
        'alg' => 'HS256'
    ];

    $payload = [
        'user_id' => $userId,
        'email' => $email,
        'type' => 'reset_password',
        'iat' => time(),
        'exp' => time() + 900  // 15 minutos = 900 segundos
    ];

    $base64UrlHeader = base64UrlEncode(json_encode($header));
    $base64UrlPayload = base64UrlEncode(json_encode($payload));

    $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, JWT_SECRET, true);
    $base64UrlSignature = base64UrlEncode($signature);

    return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
}

/**
 * Invalida todos los tokens anteriores de un usuario
 */
function invalidarTokensAnteriores($userId) {
    $db = getConnection();

    $sql = "
        UPDATE token_recuperacion_password
        SET usado = 1
        WHERE id_usuario = :id_usuario
        AND usado = 0
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute(['id_usuario' => $userId]);
}

/**
 * Obtiene la IP del cliente (soporta proxies)
 */
function obtenerIPCliente() {
    // X-Forwarded-For (proxies, load balancers)
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ips[0]);
    }

    // X-Real-IP (algunos proxies)
    if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
        return $_SERVER['HTTP_X_REAL_IP'];
    }

    // IP directa
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Envía email de recuperación con HTML profesional
 */
function enviarEmailRecuperacion($destinatario, $nombreUsuario, $token) {
    // URL del frontend según ambiente
    //$frontendUrl = 'http://localhost:4200';  // Dev
     $frontendUrl = 'https://abril-delicias-helados-h5grfde5c9csgxhq.brazilsouth-01.azurewebsites.net';  // Prod

    $resetLink = $frontendUrl . '/reset-password?token=' . urlencode($token);

    // HTML profesional (similar al de proyectoBackendBaseDatosII)
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #00A5A5; color: white; padding: 20px; text-align: center; }
            .content { background-color: #f9f9f9; padding: 30px; }
            .button { display: inline-block; padding: 12px 30px; background-color: #00A5A5;text-decoration: none; border-radius: 5px; margin: 20px 0; }
            .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
            .warning { background-color: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin: 20px 0; color: #856404; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>Recuperar Contraseña</h1>
            </div>
            <div class="content">
                <p>Hola <strong>' . htmlspecialchars($nombreUsuario) . '</strong>,</p>
                <p>Recibimos una solicitud para restablecer tu contraseña en el Sistema de Marcaciones.</p>
                <p>Haz clic en el siguiente botón para continuar:</p>
                <center>
                    <a href="' . htmlspecialchars($resetLink) . '" class="button" style=" color: #fff;">Restablecer Contraseña</a>
                </center>
                <p>O copia y pega el siguiente enlace en tu navegador:</p>
                <p style="word-break: break-all; font-size: 12px; color: #666;">' . htmlspecialchars($resetLink) . '</p>
                <div class="warning">
                    <strong>⚠️ Importante:</strong> Este enlace expira en <strong>15 minutos</strong>.
                </div>
                <p>Si no solicitaste este cambio, ignora este correo. Tu contraseña permanecerá sin cambios.</p>
            </div>
            <div class="footer">
                <p>Sistema de Marcaciones<br>Este es un correo automático, no respondas a este mensaje.</p>
            </div>
        </div>
    </body>
    </html>';

    try {
        return sendMailGmail([
            'to' => $destinatario,
            'subject' => 'Recuperar tu contraseña - Sistema de Marcaciones',
            'html' => $html,
            'from' => SMTP_GMAIL_FROM,
            'from_name' => SMTP_GMAIL_FROM_NAME
        ]);
    } catch (Exception $e) {
        throw new Exception('No se pudo enviar el correo de recuperación');
    }
}
