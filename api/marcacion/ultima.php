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

    $db = getConnection();

    // obtener id_persona
    $stmtPersona = $db->prepare("SELECT id_persona FROM usuario WHERE id_usuario = :id");
    $stmtPersona->execute(['id' => $userId]);
    $id_persona = $stmtPersona->fetchColumn();
    if (!$id_persona) {
        sendError('Usuario sin persona asociada', 400);
    }

    // tomar la fecha más reciente con marcaciones
    $stmtFecha = $db->prepare("
        SELECT fecha
        FROM marcacion
        WHERE id_persona = :id_persona
        ORDER BY fecha DESC
        LIMIT 1
    ");
    $stmtFecha->execute(['id_persona' => $id_persona]);
    $fechaUltima = $stmtFecha->fetchColumn();

    if (!$fechaUltima) {
        sendSuccess(['fecha' => null, 'marcaciones' => [], 'completado' => false], 'Sin marcaciones previas');
    }

    $stmtMarc = $db->prepare("
        SELECT tipo, hora
        FROM marcacion
        WHERE id_persona = :id_persona AND fecha = :fecha
        ORDER BY hora ASC
    ");
    $stmtMarc->execute(['id_persona' => $id_persona, 'fecha' => $fechaUltima]);
    $marcaciones = $stmtMarc->fetchAll();

    $tipos = array_column($marcaciones, 'tipo');
    $completado = in_array('ENTRADA', $tipos, true)
        && in_array('INICIO_REFRI', $tipos, true)
        && in_array('FIN_REFRI', $tipos, true)
        && in_array('SALIDA', $tipos, true);

    sendSuccess(
        [
            'fecha' => $fechaUltima,
            'marcaciones' => $marcaciones,
            'completado' => $completado,
        ],
        'Ultima marcacion'
    );
} catch (PDOException $e) {
    sendError('Error en la base de datos: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    sendError('Error interno del servidor: ' . $e->getMessage(), 500);
}
