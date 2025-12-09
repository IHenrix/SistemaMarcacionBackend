<?php

require_once '../../includes/config.php';
require_once '../../includes/database.php';
require_once '../../includes/cors.php';
require_once '../../includes/response.php';
require_once '../../includes/jwt.php';

cors();
validateMethod('POST');

try {
    requireAuth();
    $data = getRequestBody();
    validateRequired($data, ['id_asignacion', 'estado']);

    $id = (int)$data['id_asignacion'];
    $estado = (int)$data['estado'] === 1 ? 1 : 0;

    $db = getConnection();
    $stmt = $db->prepare("UPDATE asignacion_horario SET estado = :estado WHERE id_asignacion = :id");
    $stmt->execute(['estado' => $estado, 'id' => $id]);

    sendSuccess(null, 'Asignacion actualizada');
} catch (PDOException $e) {
    sendError('Error en la base de datos: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    sendError('Error interno del servidor: ' . $e->getMessage(), 500);
}
