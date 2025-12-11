<?php

require_once '../../includes/config.php';
require_once '../../includes/database.php';
require_once '../../includes/cors.php';
require_once '../../includes/response.php';
require_once '../../includes/jwt.php';

cors();

// Autenticación requerida
requireAuth();

validateMethod('GET');

$db = getConnection();

try {
    $sql = "SELECT id_area, nombre, descripcion, estado FROM area WHERE estado = 1 ORDER BY nombre";
    $stmt = $db->query($sql);
    $areas = $stmt->fetchAll();

    $result = array_map(function($area) {
        return [
            'id_area' => (int)$area['id_area'],
            'nombre' => $area['nombre'],
            'descripcion' => $area['descripcion'],
            'estado' => (int)$area['estado']
        ];
    }, $areas);

    sendSuccess($result, 'Áreas obtenidas exitosamente');

} catch (PDOException $e) {
    sendError('Error en la base de datos: ' . $e->getMessage(), 500);
}
