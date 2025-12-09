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
        $where[] = 'ah.id_persona = :id_persona';
        $params['id_persona'] = (int)$_GET['id_persona'];
    }
    if (isset($_GET['id_area'])) {
        $where[] = 'ah.id_area = :id_area';
        $params['id_area'] = (int)$_GET['id_area'];
    }

    $sql = "
        SELECT
            ah.id_asignacion,
            ah.id_horario,
            ah.id_persona,
            ah.id_area,
            ah.fecha_inicio,
            ah.fecha_fin,
            ah.prioridad,
            ah.estado,
            h.nombre as horario_nombre,
            p.nombres,
            p.apellidos,
            a.nombre as area_nombre
        FROM asignacion_horario ah
        INNER JOIN horario h ON ah.id_horario = h.id_horario
        LEFT JOIN persona p ON ah.id_persona = p.id_persona
        LEFT JOIN area a ON ah.id_area = a.id_area
    ";

    if (!empty($where)) {
        $sql .= " WHERE " . implode(' AND ', $where);
    }

    $sql .= " ORDER BY ah.fecha_inicio DESC, ah.id_asignacion DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    sendSuccess($rows, 'Asignaciones obtenidas');
} catch (PDOException $e) {
    sendError('Error en la base de datos: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    sendError('Error interno del servidor: ' . $e->getMessage(), 500);
}
