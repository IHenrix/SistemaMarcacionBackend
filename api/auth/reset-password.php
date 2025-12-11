<?php

require_once '../../includes/config.php';
require_once '../../includes/database.php';
require_once '../../includes/cors.php';
require_once '../../includes/response.php';
require_once '../../includes/token-recovery.php';

cors();

validateMethod('POST');

try {
    $data = getRequestBody();
    validateRequired($data, ['token', 'newPassword']);

    $token = $data['token'];
    $newPassword = $data['newPassword'];

    // Validación de longitud de contraseña
    if (strlen($newPassword) < 6) {
        sendError('La contraseña debe tener al menos 6 caracteres', 400);
    }

    $db = getConnection();

    // 1. BUSCAR Y VALIDAR TOKEN
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

    // 4. INICIAR TRANSACCIÓN (atomicidad)
    $db->beginTransaction();

    try {
        // 5. ENCRIPTAR NUEVA CONTRASEÑA CON BCRYPT
        $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);

        // 6. ACTUALIZAR CONTRASEÑA DEL USUARIO
        $sqlUpdate = "
            UPDATE usuario
            SET password = :password
            WHERE id_usuario = :id_usuario
        ";

        $stmtUpdate = $db->prepare($sqlUpdate);
        $stmtUpdate->execute([
            'password' => $passwordHash,
            'id_usuario' => $tokenData['id_usuario']
        ]);

        // 7. MARCAR TOKEN COMO USADO
        $ipUso = obtenerIPCliente();

        $sqlMarkUsed = "
            UPDATE token_recuperacion_password
            SET usado = 1, fecha_uso = NOW(), ip_uso = :ip_uso
            WHERE id_token = :id_token
        ";

        $stmtMarkUsed = $db->prepare($sqlMarkUsed);
        $stmtMarkUsed->execute([
            'ip_uso' => $ipUso,
            'id_token' => $tokenData['id_token']
        ]);

        // 8. RESETEAR INTENTOS FALLIDOS (opcional pero recomendado)
        $sqlResetAttempts = "
            UPDATE usuario
            SET intentos_fallidos = 0, bloqueado = 0, fecha_bloqueo = NULL
            WHERE id_usuario = :id_usuario
        ";

        $stmtResetAttempts = $db->prepare($sqlResetAttempts);
        $stmtResetAttempts->execute(['id_usuario' => $tokenData['id_usuario']]);

        // 9. COMMIT DE LA TRANSACCIÓN
        $db->commit();

        sendSuccess(null, 'Contraseña actualizada exitosamente');

    } catch (Exception $e) {
        // ROLLBACK en caso de error
        $db->rollBack();
        throw $e;
    }

} catch (PDOException $e) {
    sendError('Error en la base de datos', 500);
} catch (Exception $e) {
    sendError($e->getMessage(), 500);
}
