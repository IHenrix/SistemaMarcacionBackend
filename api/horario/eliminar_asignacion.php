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
    validateRequired($data, ['id_asignacion']);

    $id = (int)$data['id_asignacion'];

    $db = getConnection();

    // Obtener persona asociada a la asignación
    $stmtInfo = $db->prepare("SELECT id_persona FROM asignacion_horario WHERE id_asignacion = :id");
    $stmtInfo->execute(['id' => $id]);
    $id_persona = $stmtInfo->fetchColumn();

    if ($id_persona) {
        $stmtMar = $db->prepare("SELECT COUNT(*) FROM marcacion WHERE id_persona = :id_persona");
        $stmtMar->execute(['id_persona' => $id_persona]);
        if ((int)$stmtMar->fetchColumn() > 0) {
            sendError('No se puede eliminar la asignación: la persona tiene marcaciones registradas', 400);
        }
    }

    $stmt = $db->prepare("DELETE FROM asignacion_horario WHERE id_asignacion = :id");
    $stmt->execute(['id' => $id]);

    sendSuccess(null, 'Asignación eliminada');
} catch (PDOException $e) {
    sendError('Error en la base de datos: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    sendError('Error interno del servidor: ' . $e->getMessage(), 500);
}
