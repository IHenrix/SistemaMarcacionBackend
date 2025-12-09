<?php

require_once '../../includes/config.php';
require_once '../../includes/database.php';
require_once '../../includes/cors.php';
require_once '../../includes/response.php';
require_once '../../includes/jwt.php';

cors();
validateMethod('POST');

try {
    $payload = requireAuth();
    $userId = $payload['user_id']; // id_usuario

    $data = getRequestBody();
    validateRequired($data, ['tipo', 'fecha', 'hora']);

    $tipo = $data['tipo'];
    $fecha = $data['fecha'];
    $hora = $data['hora'];

    $validTipos = ['ENTRADA', 'INICIO_REFRI', 'FIN_REFRI', 'SALIDA'];
    if (!in_array($tipo, $validTipos, true)) {
        sendError('Tipo de marcacion invalido', 400);
    }

    $db = getConnection();

    // obtener id_persona desde id_usuario
    $stmtPersona = $db->prepare("SELECT id_persona FROM usuario WHERE id_usuario = :id");
    $stmtPersona->execute(['id' => $userId]);
    $id_persona = $stmtPersona->fetchColumn();
    if (!$id_persona) {
        sendError('Usuario sin persona asociada', 400);
    }

    // resolver horario vigente
    $resolverUrl = '../horario/resolver.php';
    // Como estamos en el mismo proceso, replicamos la logica basica inline:
    $diaSemana = (int)date('w', strtotime($fecha));

    // excepcion
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
    $horarioResuelto = $stmtExc->fetchColumn();

    if (!$horarioResuelto) {
        // persona
    $sqlPer = "
        SELECT ah.id_horario
        FROM asignacion_horario ah
        WHERE ah.id_persona = :id_persona
          AND ah.estado = 1
          AND ah.fecha_inicio <= :fecha_ini
          AND (ah.fecha_fin IS NULL OR ah.fecha_fin >= :fecha_fin)
        ORDER BY ah.fecha_inicio DESC, ah.id_asignacion DESC
        LIMIT 1
    ";
    $stmtPer = $db->prepare($sqlPer);
    $stmtPer->execute([
        'id_persona' => $id_persona,
        'fecha_ini' => $fecha,
        'fecha_fin' => $fecha,
    ]);
    $horarioResuelto = $stmtPer->fetchColumn();
}

    if (!$horarioResuelto) {
        // area
    $sqlArea = "
        SELECT ah.id_horario
        FROM persona p
        JOIN asignacion_horario ah ON ah.id_area = p.id_area
        WHERE p.id_persona = :id_persona
          AND ah.estado = 1
          AND ah.fecha_inicio <= :fecha_ini
          AND (ah.fecha_fin IS NULL OR ah.fecha_fin >= :fecha_fin)
        ORDER BY ah.fecha_inicio DESC, ah.id_asignacion DESC
        LIMIT 1
    ";
    $stmtArea = $db->prepare($sqlArea);
    $stmtArea->execute([
        'id_persona' => $id_persona,
        'fecha_ini' => $fecha,
        'fecha_fin' => $fecha,
    ]);
    $horarioResuelto = $stmtArea->fetchColumn();
}

    if (!$horarioResuelto) {
        sendError('No hay horario asignado para registrar la marcacion', 404);
    }

    // insert marcacion
    $sqlIns = "
        INSERT INTO marcacion (id_persona, id_horario_resuelto, tipo, fecha, hora)
        VALUES (:id_persona, :id_horario_resuelto, :tipo, :fecha, :hora)
    ";
    $stmtIns = $db->prepare($sqlIns);
    $stmtIns->execute([
        'id_persona' => $id_persona,
        'id_horario_resuelto' => $horarioResuelto,
        'tipo' => $tipo,
        'fecha' => $fecha,
        'hora' => $hora,
    ]);

    sendSuccess(['id_marcacion' => (int)$db->lastInsertId()], 'Marcacion registrada', 201);
} catch (PDOException $e) {
    sendError('Error en la base de datos: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    sendError('Error interno del servidor: ' . $e->getMessage(), 500);
}
