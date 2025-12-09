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

    $params = [];
    $where = [];

    if (isset($_GET['id_persona'])) {
        $where[] = 'm.id_persona = :id_persona';
        $params['id_persona'] = (int)$_GET['id_persona'];
    }
    if (isset($_GET['fecha'])) {
        $where[] = 'm.fecha = :fecha';
        $params['fecha'] = $_GET['fecha'];
    }

    $sql = "
        SELECT
            m.id_marcacion,
            m.id_persona,
            m.id_horario_resuelto,
            m.tipo,
            m.fecha,
            m.hora,
            m.creado_en,
            p.nombres,
            p.apellidos,
            h.nombre as horario_nombre
        FROM marcacion m
        LEFT JOIN persona p ON m.id_persona = p.id_persona
        LEFT JOIN horario h ON m.id_horario_resuelto = h.id_horario
    ";

    if (!empty($where)) {
        $sql .= " WHERE " . implode(' AND ', $where);
    }

    $sql .= " ORDER BY m.fecha DESC, m.hora DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    sendSuccess($rows, 'Marcaciones obtenidas');
} catch (PDOException $e) {
    sendError('Error en la base de datos: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    sendError('Error interno del servidor: ' . $e->getMessage(), 500);
}
