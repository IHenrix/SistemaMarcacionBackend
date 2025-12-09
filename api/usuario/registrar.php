<?php
/**
 * API: Registrar nuevo usuario
 * Método: POST
 * Body: {
 *   "dni": "70000005",
 *   "nombres": "Juan",
 *   "apellidos": "Pérez García",
 *   "telefono": "987654321",
 *   "correo": "juan.perez@empresa.com",
 *   "id_area": 1,
 *   "username": "70000005",
 *   "password": "password123",
 *   "roles": [1, 2]
 * }
 */

require_once '../../includes/config.php';
require_once '../../includes/database.php';
require_once '../../includes/cors.php';
require_once '../../includes/response.php';
require_once '../../includes/jwt.php';

cors();

validateMethod('POST');

try {
    // Obtener datos del body
    $data = getRequestBody();

    validateRequired($data, [
        'dni',
        'nombres',
        'apellidos',
        'username',
        'password'
    ]);

    $dni = trim($data['dni']);
    $nombres = trim($data['nombres']);
    $apellidos = trim($data['apellidos']);
    $telefono = isset($data['telefono']) ? trim($data['telefono']) : null;
    $correo = isset($data['correo']) ? trim($data['correo']) : null;
    $id_area = isset($data['id_area']) ? (int)$data['id_area'] : null;
    $username = trim($data['username']);
    $password = $data['password'];
    $roles = isset($data['roles']) && is_array($data['roles']) ? $data['roles'] : [];

    if (strlen($dni) < 8) {
        sendError('El DNI debe tener al menos 8 caracteres', 400);
    }

    if (strlen($password) < 6) {
        sendError('La contraseña debe tener al menos 6 caracteres', 400);
    }

    if (!empty($correo) && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        sendError('El correo electrónico no es válido', 400);
    }

    $db = getConnection();
    $db->beginTransaction();

    $sqlCheck = "SELECT id_persona FROM persona WHERE dni = :dni";
    $stmtCheck = $db->prepare($sqlCheck);
    $stmtCheck->execute(['dni' => $dni]);
    if ($stmtCheck->fetch()) {
        $db->rollBack();
        sendError('El DNI ya está registrado', 409);
    }

    $sqlCheckUser = "SELECT id_usuario FROM usuario WHERE username = :username";
    $stmtCheckUser = $db->prepare($sqlCheckUser);
    $stmtCheckUser->execute(['username' => $username]);
    if ($stmtCheckUser->fetch()) {
        $db->rollBack();
        sendError('El username ya está registrado', 409);
    }

    if ($id_area !== null) {
        $sqlCheckArea = "SELECT id_area FROM area WHERE id_area = :id_area AND estado = 1";
        $stmtCheckArea = $db->prepare($sqlCheckArea);
        $stmtCheckArea->execute(['id_area' => $id_area]);
        if (!$stmtCheckArea->fetch()) {
            $db->rollBack();
            sendError('El área especificada no existe o está inactiva', 400);
        }
    }

    $sqlInsertPersona = "
        INSERT INTO persona (dni, nombres, apellidos, telefono, correo, id_area, estado, fecha_creacion)
        VALUES (:dni, :nombres, :apellidos, :telefono, :correo, :id_area, 1, NOW())
    ";

    $stmtInsertPersona = $db->prepare($sqlInsertPersona);
    $stmtInsertPersona->execute([
        'dni' => $dni,
        'nombres' => $nombres,
        'apellidos' => $apellidos,
        'telefono' => $telefono,
        'correo' => $correo,
        'id_area' => $id_area
    ]);

    $id_persona = $db->lastInsertId();

    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

    // Insertar usuario
    $sqlInsertUsuario = "
        INSERT INTO usuario (id_persona, username, password, intentos_fallidos, bloqueado)
        VALUES (:id_persona, :username, :password, 0, 0)
    ";

    $stmtInsertUsuario = $db->prepare($sqlInsertUsuario);
    $stmtInsertUsuario->execute([
        'id_persona' => $id_persona,
        'username' => $username,
        'password' => $hashedPassword
    ]);

    $id_usuario = $db->lastInsertId();

    $rolesAsignados = [];
    if (!empty($roles)) {
        $sqlInsertRole = "INSERT INTO usuario_rol (id_usuario, id_rol) VALUES (:id_usuario, :id_rol)";
        $stmtInsertRole = $db->prepare($sqlInsertRole);

        foreach ($roles as $id_rol) {
            // Verificar que el rol existe
            $sqlCheckRole = "SELECT nombre FROM rol WHERE id_rol = :id_rol";
            $stmtCheckRole = $db->prepare($sqlCheckRole);
            $stmtCheckRole->execute(['id_rol' => $id_rol]);
            $role = $stmtCheckRole->fetch();

            if ($role) {
                $stmtInsertRole->execute([
                    'id_usuario' => $id_usuario,
                    'id_rol' => $id_rol
                ]);
                $rolesAsignados[] = [
                    'id_rol' => $id_rol,
                    'nombre' => $role['nombre']
                ];
            }
        }
    }

    $db->commit();

    $areaNombre = null;
    if ($id_area) {
        $sqlArea = "SELECT nombre, descripcion FROM area WHERE id_area = :id_area";
        $stmtArea = $db->prepare($sqlArea);
        $stmtArea->execute(['id_area' => $id_area]);
        $area = $stmtArea->fetch();
        $areaNombre = $area ? $area['nombre'] : null;
    }

    $responseData = [
        'persona' => [
            'id_persona' => $id_persona,
            'dni' => $dni,
            'nombres' => $nombres,
            'apellidos' => $apellidos,
            'nombre_completo' => "$nombres $apellidos",
            'telefono' => $telefono,
            'correo' => $correo,
            'area' => $areaNombre,
            'id_area' => $id_area,
            'estado' => 'activo'
        ],
        'usuario' => [
            'id_usuario' => $id_usuario,
            'username' => $username,
            'password_hasheado' => true
        ],
        'roles' => $rolesAsignados
    ];

    sendSuccess($responseData, 'Usuario registrado exitosamente', 201);

} catch (PDOException $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    sendError('Error en la base de datos: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    sendError('Error interno del servidor: ' . $e->getMessage(), 500);
}
