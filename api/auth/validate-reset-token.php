<?php

require_once '../../includes/config.php';
require_once '../../includes/database.php';
require_once '../../includes/cors.php';
require_once '../../includes/response.php';
require_once '../../includes/token-recovery.php';

cors();

validateMethod('GET');

try {
    if (!isset($_GET['token']) || empty($_GET['token'])) {
        sendError('Token no proporcionado', 400);
    }

    $token = $_GET['token'];
    $db = getConnection();

    // 1. BUSCAR TOKEN EN BD
    $sql = "
        SELECT id_token, id_usuario, usado, fecha_expiracion
        FROM token_recuperacion_password
        WHERE token = :token
        LIMIT 1
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute(['token' => $token]);
    $tokenData = $stmt->fetch();

    if (!$tokenData) {
        sendError('Token inválido o no encontrado', 400);
    }

    // 2. VALIDAR QUE NO ESTÉ USADO
    if ($tokenData['usado'] == 1) {
        sendError('Este token ya ha sido utilizado', 400);
    }

    // 3. VALIDAR EXPIRACIÓN
    $ahora = new DateTime();
    $expiracion = new DateTime($tokenData['fecha_expiracion']);

    if ($ahora > $expiracion) {
        sendError('Este token ha expirado', 400);
    }

    sendSuccess(null, 'Token válido');

} catch (Exception $e) {
    sendError($e->getMessage(), 500);
}
