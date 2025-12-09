<?php

require_once '../../includes/config.php';
require_once '../../includes/database.php';
require_once '../../includes/cors.php';
require_once '../../includes/response.php';
require_once '../../includes/jwt.php';

cors();
validateMethod('POST');

function hayTraslapeActualizar($inicioA, $finA, $inicioB, $finB) {
    if ($finA === null) $finA = '9999-12-31';
    if ($finB === null) $finB = '9999-12-31';
    return !($finA < $inicioB || $finB < $inicioA);
}

try {
    requireAuth();
    $data = getRequestBody();

    validateRequired($data, ['id_asignacion', 'id_horario', 'fecha_inicio', 'prioridad']);

    $id_asignacion = (int)$data['id_asignacion'];
    $id_horario = (int)$data['id_horario'];
    $fecha_inicio = $data['fecha_inicio'];
    $fecha_fin = isset($data['fecha_fin']) && $data['fecha_fin'] !== '' ? $data['fecha_fin'] : null;
    $prioridad = $data['prioridad'];
    $id_persona = isset($data['id_persona']) ? (int)$data['id_persona'] : null;
    $id_area = isset($data['id_area']) ? (int)$data['id_area'] : null;

    if ($prioridad === 'PERSONA' && !$id_persona) {
        sendError('id_persona requerido para prioridad PERSONA', 400);
    }
    if ($prioridad === 'AREA' && !$id_area) {
        sendError('id_area requerido para prioridad AREA', 400);
    }

    $db = getConnection();
    $db->beginTransaction();

    // Validar traslapes excluyendo la propia asignación
    $condCampo = $prioridad === 'PERSONA' ? 'id_persona' : 'id_area';
    $condValor = $prioridad === 'PERSONA' ? $id_persona : $id_area;

    $sqlCheck = "
        SELECT id_asignacion, fecha_inicio, fecha_fin
        FROM asignacion_horario
        WHERE $condCampo = :valor AND estado = 1 AND id_asignacion <> :id_asignacion
    ";
    $stmtCheck = $db->prepare($sqlCheck);
    $stmtCheck->execute(['valor' => $condValor, 'id_asignacion' => $id_asignacion]);
    $rows = $stmtCheck->fetchAll();
    foreach ($rows as $row) {
        if (hayTraslapeActualizar($fecha_inicio, $fecha_fin, $row['fecha_inicio'], $row['fecha_fin'])) {
            sendError('Existe una asignación traslapada para el mismo destino', 400);
        }
    }

    $sql = "
        UPDATE asignacion_horario
        SET id_horario = :id_horario,
            id_persona = :id_persona,
            id_area = :id_area,
            fecha_inicio = :fecha_inicio,
            fecha_fin = :fecha_fin,
            prioridad = :prioridad
        WHERE id_asignacion = :id_asignacion
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute([
        'id_horario' => $id_horario,
        'id_persona' => $id_persona,
        'id_area' => $id_area,
        'fecha_inicio' => $fecha_inicio,
        'fecha_fin' => $fecha_fin,
        'prioridad' => $prioridad,
        'id_asignacion' => $id_asignacion,
    ]);

    $db->commit();

    sendSuccess(null, 'Asignación actualizada');
} catch (PDOException $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    sendError('Error en la base de datos: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    sendError('Error interno del servidor: ' . $e->getMessage(), 500);
}
