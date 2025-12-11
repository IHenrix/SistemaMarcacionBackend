<?php

require_once '../../includes/config.php';
require_once '../../includes/database.php';
require_once '../../includes/cors.php';
require_once '../../includes/response.php';
require_once '../../includes/mailer.php';
require_once '../../includes/token-recovery.php';

cors();

validateMethod('POST');

try {
    $data = getRequestBody();
    validateRequired($data, ['email']);

    $email = trim(strtolower($data['email']));

    // Validación básica de formato de email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendError('El email proporcionado no es válido', 400);
    }

    $db = getConnection();

    // 1. BUSCAR USUARIO POR EMAIL
    $sql = "
        SELECT u.id_usuario, u.id_persona, u.username, p.correo, p.nombres, p.apellidos
        FROM usuario u
        INNER JOIN persona p ON u.id_persona = p.id_persona
        WHERE LOWER(p.correo) = :email
        AND u.bloqueado = 0
        AND p.estado = 1
        LIMIT 1
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if (!$user) {
        sendError('No existe un usuario registrado con ese email', 404);
    }

    // 2. INVALIDAR TOKENS ANTERIORES
    invalidarTokensAnteriores($user['id_usuario']);

    // 3. GENERAR TOKEN COMBINADO (UUID + JWT)
    $tokenUUID = generarUUID();
    $tokenJWT = generarJWTRecuperacion($user['id_usuario'], $email);
    $tokenCombinado = $tokenUUID . ':' . $tokenJWT;

    // 4. CALCULAR EXPIRACIÓN (15 minutos)
    $fechaExpiracion = date('Y-m-d H:i:s', strtotime('+15 minutes'));

    // 5. OBTENER IP DEL CLIENTE
    $ipSolicitud = obtenerIPCliente();

    // 6. INSERTAR EN BASE DE DATOS
    $sqlInsert = "
        INSERT INTO token_recuperacion_password
        (id_usuario, token, email, fecha_solicitud, fecha_expiracion, usado, ip_solicitud)
        VALUES (:id_usuario, :token, :email, NOW(), :fecha_expiracion, 0, :ip_solicitud)
    ";

    $stmtInsert = $db->prepare($sqlInsert);
    $stmtInsert->execute([
        'id_usuario' => $user['id_usuario'],
        'token' => $tokenCombinado,
        'email' => $email,
        'fecha_expiracion' => $fechaExpiracion,
        'ip_solicitud' => $ipSolicitud
    ]);

    // 7. ENVIAR EMAIL CON HTML PROFESIONAL
    $nombreCompleto = $user['nombres'] . ' ' . $user['apellidos'];
    enviarEmailRecuperacion($email, $nombreCompleto, $tokenCombinado);

    sendSuccess(null, 'Se ha enviado un correo electrónico con instrucciones para recuperar su contraseña');

} catch (PDOException $e) {
    sendError('Error en la base de datos', 500);
} catch (Exception $e) {
    sendError($e->getMessage(), 500);
}
