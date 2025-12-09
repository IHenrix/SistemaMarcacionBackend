<?php

require_once '../../includes/config.php';
require_once '../../includes/database.php';
require_once '../../includes/cors.php';
require_once '../../includes/response.php';
require_once '../../includes/jwt.php';

cors();
validateMethod('GET');

function obtenerHorarioCompleto($db, $idHorario) {
    // Obtener datos del horario
    $sqlHorario = "
        SELECT
            h.id_horario,
            h.nombre,
            h.entrada,
            h.inicio_refri,
            h.fin_refri,
            h.salida,
            h.tol_entrada_min,
            h.tol_salida_min,
            h.tol_refri_min,
            h.color,
            h.estado
        FROM horario h
        WHERE h.id_horario = :id_horario
    ";
    $stmtHorario = $db->prepare($sqlHorario);
    $stmtHorario->execute(['id_horario' => $idHorario]);
    $horario = $stmtHorario->fetch();

    if (!$horario) {
        return null;
    }

    // Obtener días del horario
    $sqlDias = "SELECT dia_semana FROM horario_dia WHERE id_horario = :id_horario";
    $stmtDias = $db->prepare($sqlDias);
    $stmtDias->execute(['id_horario' => $idHorario]);
    $diasRows = $stmtDias->fetchAll();
    $horario['dias'] = array_map(function($row) {
        return (int)$row['dia_semana'];
    }, $diasRows);

    return $horario;
}

try {
    $payload = requireAuth();
    $userId = $payload['user_id'];

    $db = getConnection();

    // Obtener id_persona del usuario autenticado
    $stmtPersona = $db->prepare("SELECT id_persona FROM usuario WHERE id_usuario = :id");
    $stmtPersona->execute(['id' => $userId]);
    $id_persona = $stmtPersona->fetchColumn();
    if (!$id_persona) {
        sendError('Usuario sin persona asociada', 400);
    }

    $fecha = isset($_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d');
    $diaSemana = (int)date('w', strtotime($fecha)); // 0 domingo

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
    $idHorario = $stmtExc->fetchColumn();
    if ($idHorario) {
        $horario = obtenerHorarioCompleto($db, $idHorario);
        sendSuccess(['horario' => $horario, 'origen' => 'excepcion'], 'Horario resuelto');
    }

    // 2. Asignacion persona
    $sqlPer = "
        SELECT ah.id_horario
        FROM asignacion_horario ah
        WHERE ah.id_persona = :id_persona
          AND ah.estado = 1
          AND ah.fecha_inicio <= :fecha1
          AND (ah.fecha_fin IS NULL OR ah.fecha_fin >= :fecha2)
        ORDER BY ah.fecha_inicio DESC, ah.id_asignacion DESC
        LIMIT 1
    ";
    $stmtPer = $db->prepare($sqlPer);
    $stmtPer->execute(['id_persona' => $id_persona, 'fecha1' => $fecha, 'fecha2' => $fecha]);
    $idHorario = $stmtPer->fetchColumn();
    if ($idHorario) {
        $horario = obtenerHorarioCompleto($db, $idHorario);
        sendSuccess(['horario' => $horario, 'origen' => 'persona'], 'Horario resuelto');
    }

    // 3. Asignacion area
    $sqlArea = "
        SELECT p.id_area, ah.id_horario
        FROM persona p
        JOIN asignacion_horario ah ON ah.id_area = p.id_area
        WHERE p.id_persona = :id_persona
          AND ah.estado = 1
          AND ah.fecha_inicio <= :fecha1
          AND (ah.fecha_fin IS NULL OR ah.fecha_fin >= :fecha2)
        ORDER BY ah.fecha_inicio DESC, ah.id_asignacion DESC
        LIMIT 1
    ";
    $stmtArea = $db->prepare($sqlArea);
    $stmtArea->execute(['id_persona' => $id_persona, 'fecha1' => $fecha, 'fecha2' => $fecha]);
    $rowArea = $stmtArea->fetch();
    if ($rowArea) {
        $horario = obtenerHorarioCompleto($db, $rowArea['id_horario']);
        sendSuccess(['horario' => $horario, 'origen' => 'area'], 'Horario resuelto');
    }

    // 4. Ninguno encontrado - devolver success con data vacío
    sendSuccess(['horario' => null, 'origen' => null], 'No hay horario asignado');
} catch (PDOException $e) {
    sendError('Error en la base de datos: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    sendError('Error interno del servidor: ' . $e->getMessage(), 500);
}
