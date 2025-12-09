<?php

require_once '../../includes/config.php';
require_once '../../includes/database.php';
require_once '../../includes/cors.php';
require_once '../../includes/response.php';
require_once '../../includes/jwt.php';

cors();
validateMethod('GET');

try {
    $payload = requireAuth();
    $userId = $payload['user_id'];
    $fecha = date('Y-m-d');

    $db = getConnection();

    $stmtPersona = $db->prepare("SELECT id_persona FROM usuario WHERE id_usuario = :id");
    $stmtPersona->execute(['id' => $userId]);
    $id_persona = $stmtPersona->fetchColumn();
    if (!$id_persona) {
        sendError('Usuario sin persona asociada', 400);
    }

    $stmt = $db->prepare("
        SELECT tipo, hora
        FROM marcacion
        WHERE id_persona = :id_persona AND fecha = :fecha
        ORDER BY hora ASC
    ");
    $stmt->execute(['id_persona' => $id_persona, 'fecha' => $fecha]);
    $rows = $stmt->fetchAll();

    sendSuccess([
        'fecha' => $fecha,
        'marcaciones' => $rows,
    ], 'Marcaciones de hoy');
} catch (PDOException $e) {
    sendError('Error en la base de datos: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    sendError('Error interno del servidor: ' . $e->getMessage(), 500);
}
