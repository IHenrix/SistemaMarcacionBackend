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
            p.id_persona,
            p.dni,
            p.nombres,
            p.apellidos,
            p.telefono,
            p.correo,
            p.id_area,
            a.nombre AS area_nombre,
            u.id_usuario,
            u.username,
            GROUP_CONCAT(r.nombre) AS roles
        FROM persona p
        LEFT JOIN area a ON p.id_area = a.id_area
        LEFT JOIN usuario u ON u.id_persona = p.id_persona
        LEFT JOIN usuario_rol ur ON ur.id_usuario = u.id_usuario
        LEFT JOIN rol r ON r.id_rol = ur.id_rol
        WHERE p.estado = 1
        GROUP BY
            p.id_persona,
            p.dni,
            p.nombres,
            p.apellidos,
            p.telefono,
            p.correo,
            p.id_area,
            a.nombre,
            u.id_usuario,
            u.username
        ORDER BY p.nombres, p.apellidos
    ";

    $stmt = $db->query($sql);
    $rows = $stmt->fetchAll();

    // Separar roles en array
    $data = array_map(function ($row) {
        $row['roles'] = $row['roles'] ? explode(',', $row['roles']) : [];
        return $row;
    }, $rows);

    sendSuccess($data, 'Usuarios obtenidos');
} catch (PDOException $e) {
    sendError('Error en la base de datos: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    sendError('Error interno del servidor: ' . $e->getMessage(), 500);
}
