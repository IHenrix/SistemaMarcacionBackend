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

    $sql = "
        SELECT
            u.id_usuario,
            u.id_persona,
            u.username,
            u.fecha_ultimo_acceso,
            p.dni,
            p.nombres,
            p.apellidos,
            p.telefono,
            p.correo,
            p.id_area,
            p.estado,
            p.fecha_creacion,
            a.nombre as area_nombre,
            a.descripcion as area_descripcion
        FROM usuario u
        INNER JOIN persona p ON u.id_persona = p.id_persona
        LEFT JOIN area a ON p.id_area = a.id_area
        WHERE u.id_usuario = :id_usuario
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute(['id_usuario' => $userId]);
    $user = $stmt->fetch();

    if (!$user) {
        sendError('Usuario no encontrado', 404);
    }

    $sqlRoles = "
        SELECT r.id_rol, r.nombre
        FROM usuario_rol ur
        INNER JOIN rol r ON ur.id_rol = r.id_rol
        WHERE ur.id_usuario = :id_usuario
    ";
    $stmtRoles = $db->prepare($sqlRoles);
    $stmtRoles->execute(['id_usuario' => $userId]);
    $roles = $stmtRoles->fetchAll();

    $profileData = [
        'id' => $user['id_persona'],
        'id_usuario' => $user['id_usuario'],
        'dni' => $user['dni'],
        'nombres' => $user['nombres'],
        'apellidos' => $user['apellidos'],
        'nombre_completo' => $user['nombres'] . ' ' . $user['apellidos'],
        'telefono' => $user['telefono'],
        'correo' => $user['correo'],
        'username' => $user['username'],
        'area' => $user['id_area'] ? [
            'id' => $user['id_area'],
            'nombre' => $user['area_nombre'],
            'descripcion' => $user['area_descripcion']
        ] : null,
        'roles' => $roles,
        'perfil' => !empty($roles) ? $roles[0]['nombre'] : 'Sin rol',
        'estado' => $user['estado'] == 1 ? 'Activo' : 'Inactivo',
        'fecha_creacion' => $user['fecha_creacion'],
        'fecha_ultimo_acceso' => $user['fecha_ultimo_acceso']
    ];

    sendSuccess($profileData, 'Perfil obtenido exitosamente');

} catch (PDOException $e) {
    sendError('Error en la base de datos: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    sendError('Error interno del servidor: ' . $e->getMessage(), 500);
}
