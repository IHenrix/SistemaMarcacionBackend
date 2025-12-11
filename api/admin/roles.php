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
    $sql = "SELECT id_rol, nombre FROM rol ORDER BY nombre";
    $stmt = $db->query($sql);
    $roles = $stmt->fetchAll();

    $result = array_map(function($rol) {
        return [
            'id_rol' => (int)$rol['id_rol'],
            'nombre' => $rol['nombre']
        ];
    }, $roles);

    sendSuccess($result, 'Roles obtenidos exitosamente');

} catch (PDOException $e) {
    sendError('Error en la base de datos: ' . $e->getMessage(), 500);
}
