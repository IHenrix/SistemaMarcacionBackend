<?php

require_once '../../includes/config.php';
require_once '../../includes/database.php';
require_once '../../includes/cors.php';
require_once '../../includes/response.php';
require_once '../../includes/jwt.php';

cors();
validateMethod('GET');

try {
    requireAuth();
    if (!isset($_GET['id_persona'])) {
        sendError('id_persona requerido', 400);
    }
    $id_persona = (int)$_GET['id_persona'];
    $fecha = isset($_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d');
    $diaSemana = (int)date('w', strtotime($fecha)); // 0 domingo

    $db = getConnection();

    // 1. Excepcion persona/area
    $sqlExc = "
        SELECT e.id_horario_aplica
        FROM excepcion_horario e
        LEFT JOIN persona p ON e.id_persona = p.id_persona
        WHERE (e.id_persona = :id_persona OR (e.id_area = p.id_area AND e.id_area IS NOT NULL))
          AND e.fecha = :fecha
        LIMIT 1
    ";
    $stmtExc = $db->prepare($sqlExc);
    $stmtExc->execute(['id_persona' => $id_persona, 'fecha' => $fecha]);
    $exc = $stmtExc->fetchColumn();
    if ($exc) {
        sendSuccess(['id_horario' => (int)$exc, 'origen' => 'excepcion'], 'Horario resuelto');
    }

    // 2. Asignacion persona
    $sqlPer = "
        SELECT ah.id_horario
        FROM asignacion_horario ah
        WHERE ah.id_persona = :id_persona
          AND ah.estado = 1
          AND ah.fecha_inicio <= :fecha
          AND (ah.fecha_fin IS NULL OR ah.fecha_fin >= :fecha)
        ORDER BY ah.fecha_inicio DESC, ah.id_asignacion DESC
        LIMIT 1
    ";
    $stmtPer = $db->prepare($sqlPer);
    $stmtPer->execute(['id_persona' => $id_persona, 'fecha' => $fecha]);
    $horarioPer = $stmtPer->fetchColumn();
    if ($horarioPer) {
        sendSuccess(['id_horario' => (int)$horarioPer, 'origen' => 'persona'], 'Horario resuelto');
    }

    // 3. Asignacion area
    $sqlArea = "
        SELECT p.id_area, ah.id_horario
        FROM persona p
        JOIN asignacion_horario ah ON ah.id_area = p.id_area
        WHERE p.id_persona = :id_persona
          AND ah.estado = 1
          AND ah.fecha_inicio <= :fecha
          AND (ah.fecha_fin IS NULL OR ah.fecha_fin >= :fecha)
        ORDER BY ah.fecha_inicio DESC, ah.id_asignacion DESC
        LIMIT 1
    ";
    $stmtArea = $db->prepare($sqlArea);
    $stmtArea->execute(['id_persona' => $id_persona, 'fecha' => $fecha]);
    $rowArea = $stmtArea->fetch();
    if ($rowArea) {
        sendSuccess(['id_horario' => (int)$rowArea['id_horario'], 'origen' => 'area'], 'Horario resuelto');
    }

    // 4. Ninguno encontrado
    sendError('No hay horario asignado para la fecha indicada', 404);
} catch (PDOException $e) {
    sendError('Error en la base de datos: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    sendError('Error interno del servidor: ' . $e->getMessage(), 500);
}
