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
    $db = getConnection();

    $sql = "
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
            h.estado,
            h.creado_en
        FROM horario h
        ORDER BY h.id_horario DESC
    ";

    $stmt = $db->query($sql);
    $horarios = $stmt->fetchAll();

    // Map dias
    $sqlDias = "SELECT id_horario, dia_semana FROM horario_dia";
    $diasStmt = $db->query($sqlDias);
    $diasRows = $diasStmt->fetchAll();
    $diasMap = [];
    foreach ($diasRows as $row) {
        $diasMap[$row['id_horario']][] = (int)$row['dia_semana'];
    }

    $data = array_map(function ($h) use ($diasMap) {
        $h['dias'] = $diasMap[$h['id_horario']] ?? [];
        return $h;
    }, $horarios);

    sendSuccess($data, 'Horarios obtenidos');
} catch (PDOException $e) {
    sendError('Error en la base de datos: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    sendError('Error interno del servidor: ' . $e->getMessage(), 500);
}
