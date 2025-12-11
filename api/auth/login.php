<?php

require_once '../../includes/config.php';
require_once '../../includes/database.php';
require_once '../../includes/cors.php';
require_once '../../includes/response.php';
require_once '../../includes/jwt.php';

cors();

validateMethod('POST');

try {
    $data = getRequestBody();

    validateRequired($data, ['username', 'password']);

    $username = trim($data['username']);
    $password = $data['password'];

    $db = getConnection();

    $sql = "
        SELECT
            u.id_usuario,
            u.id_persona,
            u.username,
            u.password,
            u.intentos_fallidos,
            u.estado as estado_usuario,
            u.fecha_bloqueo,
            u.fecha_ultimo_acceso,
            p.dni,
            p.nombres,
            p.apellidos,
            p.telefono,
            p.correo,
            p.id_area,
            p.estado as estado_persona,
            a.nombre as area_nombre,
            a.descripcion as area_descripcion
        FROM usuario u
        INNER JOIN persona p ON u.id_persona = p.id_persona
        LEFT JOIN area a ON p.id_area = a.id_area
        WHERE u.username = :username
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch();

    if (!$user) {
        sendError('Ha introducido un usuario o una contraseña incorrectos', 404);
    }

    if ($user['estado_usuario'] == 'B') {
        sendError('Usuario bloqueado por múltiples intentos fallidos. Contacte al administrador.', 403);
    }

    if ($user['estado_usuario'] == 'I') {
        sendError('Usuario inactivo. Contacte al administrador.', 403);
    }

    if ($user['estado_persona'] == 0) {
        sendError('Usuario inactivo. Contacte al administrador.', 403);
    }

    $passwordMatch = false;

    if (password_get_info($user['password'])['algo'] !== null) {
        $passwordMatch = password_verify($password, $user['password']);
    } else {
        $passwordMatch = ($password === $user['password']);
    }

    if (!$passwordMatch) {
        callProcedure('registrar_intento_fallido', [$username]);

        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();

        if ($user['estado_usuario'] == 'B') {
            sendError('Usuario bloqueado por múltiples intentos fallidos.', 403);
        }

        $intentosRestantes = 3 - $user['intentos_fallidos'];
        if ($intentosRestantes > 0) {
            sendError('Ha introducido un nombre de usuario o una contraseña incorrectos. Le quedan ' . $intentosRestantes . ' intentos.', 401);
        } else {
            sendError('Ha introducido un nombre de usuario o una contraseña incorrectos', 401);
        }
    }

    callProcedure('resetear_intentos_login', [$username]);

    $sqlUpdate = "UPDATE usuario SET fecha_ultimo_acceso = NOW() WHERE username = :username";
    $stmtUpdate = $db->prepare($sqlUpdate);
    $stmtUpdate->execute(['username' => $username]);

    $sqlRoles = "
        SELECT r.id_rol, r.nombre
        FROM usuario_rol ur
        INNER JOIN rol r ON ur.id_rol = r.id_rol
        WHERE ur.id_usuario = :id_usuario
    ";
    $stmtRoles = $db->prepare($sqlRoles);
    $stmtRoles->execute(['id_usuario' => $user['id_usuario']]);
    $roles = $stmtRoles->fetchAll();

    $token = generateJWT($user['id_usuario'], [
        'username' => $user['username'],
        'id_persona' => $user['id_persona'],
        'roles' => array_column($roles, 'nombre')
    ]);

    $userData = [
        'id' => $user['id_persona'],
        'id_usuario' => $user['id_usuario'],
        'dni' => $user['dni'],
        'nombre' => $user['nombres'] . ' ' . $user['apellidos'],
        'nombres' => $user['nombres'],
        'apellidos' => $user['apellidos'],
        'email' => $user['correo'],
        'telefono' => $user['telefono'],
        'area' => $user['id_area'] ? [
            'id' => $user['id_area'],
            'nombre' => $user['area_nombre'],
            'descripcion' => $user['area_descripcion']
        ] : null,
        'roles' => $roles,
        'perfil' => !empty($roles) ? $roles[0]['nombre'] : 'Sin rol'
    ];

    sendSuccess([
        'token' => $token,
        'expiresIn' => JWT_EXPIRATION,
        'usuario' => $userData
    ], 'Login exitoso');

} catch (PDOException $e) {
    sendError('Error en la base de datos: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    sendError('Error interno del servidor: ' . $e->getMessage(), 500);
}
