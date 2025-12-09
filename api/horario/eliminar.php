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
    validateRequired($data, ['id_horario']);

    $id = (int)$data['id_horario'];
    $db = getConnection();

    // Verificar marcaciones
    $stmtMar = $db->prepare("SELECT COUNT(*) FROM marcacion WHERE id_horario_resuelto = :id");
    $stmtMar->execute(['id' => $id]);
    if ((int)$stmtMar->fetchColumn() > 0) {
        sendError('No se puede eliminar el horario porque tiene marcaciones registradas', 400);
    }

    // Verificar asignaciones (empleados o áreas)
    $stmtAsig = $db->prepare("SELECT COUNT(*) FROM asignacion_horario WHERE id_horario = :id");
    $stmtAsig->execute(['id' => $id]);
    if ((int)$stmtAsig->fetchColumn() > 0) {
        sendError('No se puede eliminar el horario porque tiene asignaciones vigentes (empleados o áreas)', 400);
    }

    $stmtDelDias = $db->prepare("DELETE FROM horario_dia WHERE id_horario = :id");
    $stmtDelDias->execute(['id' => $id]);

    $stmtDelHor = $db->prepare("DELETE FROM horario WHERE id_horario = :id");
    $stmtDelHor->execute(['id' => $id]);

    sendSuccess(null, 'Horario eliminado');
} catch (PDOException $e) {
    sendError('Error en la base de datos: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    sendError('Error interno del servidor: ' . $e->getMessage(), 500);
}
