<?php

require_once '../../includes/config.php';
require_once '../../includes/database.php';
require_once '../../includes/cors.php';
require_once '../../includes/response.php';
require_once '../../includes/jwt.php';

cors();

// Autenticación requerida
requireAuth();

$db = getConnection();
$method = $_SERVER['REQUEST_METHOD'];

try {
    // Parsear la URL para determinar la acción
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $pathParts = explode('/', trim($path, '/'));

    // Verificar si es una acción específica (activar, desbloquear)
    if (count($pathParts) >= 4) {
        $lastPart = end($pathParts);
        $idIndex = count($pathParts) - 2;
        $id = $pathParts[$idIndex];

        if ($method === 'POST' && $lastPart === 'activar' && is_numeric($id)) {
            handleActivar($db, $id);
            return;
        }

        if ($method === 'POST' && $lastPart === 'desbloquear' && is_numeric($id)) {
            handleDesbloquear($db, $id);
            return;
        }
    }

    // CRUD estándar
    switch ($method) {
        case 'GET':
            handleGet($db);
            break;
        case 'POST':
            handlePost($db);
            break;
        case 'PUT':
            handlePut($db);
            break;
        case 'DELETE':
            handleDelete($db);
            break;
        default:
            sendError('Método no permitido', 405);
    }
} catch (PDOException $e) {
    sendError('Error en la base de datos: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    sendError($e->getMessage(), 500);
}

/**
 * GET - Listar usuarios (con filtros) u obtener un usuario por ID
 */
function handleGet($db) {
    // Si hay ID en la URL, obtener usuario específico
    if (isset($_GET['id'])) {
        obtenerUsuario($db, $_GET['id']);
        return;
    }

    // Listar usuarios con filtros
    $sql = "
        SELECT
            u.id_usuario,
            u.username,
            u.estado,
            u.intentos_fallidos,
            u.fecha_ultimo_acceso,
            u.fecha_bloqueo,
            p.id_persona,
            p.dni,
            p.nombres,
            p.apellidos,
            p.correo,
            p.telefono,
            p.id_area,
            p.estado as persona_estado,
            a.nombre as area_nombre
        FROM usuario u
        INNER JOIN persona p ON u.id_persona = p.id_persona
        LEFT JOIN area a ON p.id_area = a.id_area
        WHERE 1=1
    ";

    $params = [];

    // Filtros
    if (!empty($_GET['username'])) {
        $sql .= " AND UPPER(u.username) LIKE UPPER(:username)";
        $params['username'] = '%' . $_GET['username'] . '%';
    }

    if (!empty($_GET['nombres'])) {
        $sql .= " AND UPPER(p.nombres) LIKE UPPER(:nombres)";
        $params['nombres'] = '%' . $_GET['nombres'] . '%';
    }

    if (!empty($_GET['apellidos'])) {
        $sql .= " AND UPPER(p.apellidos) LIKE UPPER(:apellidos)";
        $params['apellidos'] = '%' . $_GET['apellidos'] . '%';
    }

    if (isset($_GET['estado']) && $_GET['estado'] !== '') {
        $sql .= " AND u.estado = :estado";
        $params['estado'] = $_GET['estado'];
    }

    $sql .= " ORDER BY u.id_usuario DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $usuarios = $stmt->fetchAll();

    // Para cada usuario, obtener sus roles
    $result = array_map(function($usuario) use ($db) {
        $sqlRoles = "
            SELECT r.id_rol, r.nombre
            FROM usuario_rol ur
            INNER JOIN rol r ON ur.id_rol = r.id_rol
            WHERE ur.id_usuario = :id_usuario
        ";
        $stmtRoles = $db->prepare($sqlRoles);
        $stmtRoles->execute(['id_usuario' => $usuario['id_usuario']]);
        $roles = $stmtRoles->fetchAll();

        return [
            'id_usuario' => (int)$usuario['id_usuario'],
            'username' => $usuario['username'],
            'estado' => $usuario['estado'],
            'intentos_fallidos' => (int)$usuario['intentos_fallidos'],
            'fecha_ultimo_acceso' => $usuario['fecha_ultimo_acceso'],
            'fecha_bloqueo' => $usuario['fecha_bloqueo'],
            'persona' => [
                'id_persona' => (int)$usuario['id_persona'],
                'dni' => $usuario['dni'],
                'nombres' => $usuario['nombres'],
                'apellidos' => $usuario['apellidos'],
                'correo' => $usuario['correo'],
                'telefono' => $usuario['telefono'],
                'id_area' => $usuario['id_area'] ? (int)$usuario['id_area'] : null,
                'area_nombre' => $usuario['area_nombre'],
                'estado' => (int)$usuario['persona_estado']
            ],
            'roles' => array_map(function($rol) {
                return [
                    'id_rol' => (int)$rol['id_rol'],
                    'nombre' => $rol['nombre']
                ];
            }, $roles)
        ];
    }, $usuarios);

    sendSuccess($result, 'Usuarios obtenidos exitosamente');
}

/**
 * Obtener un usuario por ID
 */
function obtenerUsuario($db, $id) {
    $sql = "
        SELECT
            u.id_usuario,
            u.username,
            u.estado,
            u.intentos_fallidos,
            u.fecha_ultimo_acceso,
            p.id_persona,
            p.dni,
            p.nombres,
            p.apellidos,
            p.correo,
            p.telefono,
            p.id_area
        FROM usuario u
        INNER JOIN persona p ON u.id_persona = p.id_persona
        WHERE u.id_usuario = :id
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute(['id' => $id]);
    $usuario = $stmt->fetch();

    if (!$usuario) {
        sendError('Usuario no encontrado', 404);
    }

    // Obtener roles
    $sqlRoles = "
        SELECT r.id_rol, r.nombre
        FROM usuario_rol ur
        INNER JOIN rol r ON ur.id_rol = r.id_rol
        WHERE ur.id_usuario = :id_usuario
    ";
    $stmtRoles = $db->prepare($sqlRoles);
    $stmtRoles->execute(['id_usuario' => $id]);
    $roles = $stmtRoles->fetchAll();

    $result = [
        'id_usuario' => (int)$usuario['id_usuario'],
        'username' => $usuario['username'],
        'estado' => $usuario['estado'],
        'intentos_fallidos' => (int)$usuario['intentos_fallidos'],
        'fecha_ultimo_acceso' => $usuario['fecha_ultimo_acceso'],
        'persona' => [
            'id_persona' => (int)$usuario['id_persona'],
            'dni' => $usuario['dni'],
            'nombres' => $usuario['nombres'],
            'apellidos' => $usuario['apellidos'],
            'correo' => $usuario['correo'],
            'telefono' => $usuario['telefono'],
            'id_area' => $usuario['id_area'] ? (int)$usuario['id_area'] : null
        ],
        'roles' => array_map(function($rol) {
            return [
                'id_rol' => (int)$rol['id_rol'],
                'nombre' => $rol['nombre']
            ];
        }, $roles)
    ];

    sendSuccess($result, 'Usuario obtenido exitosamente');
}

/**
 * POST - Crear nuevo usuario desde persona
 */
function handlePost($db) {
    $data = getRequestBody();

    // Validaciones
    validateRequired($data, ['id_persona', 'username', 'password', 'roles']);

    if (!is_array($data['roles']) || empty($data['roles'])) {
        sendError('Debe seleccionar al menos un rol', 400);
    }

    // Verificar que la persona existe y no tiene usuario
    $sqlCheck = "
        SELECT p.id_persona, u.id_usuario
        FROM persona p
        LEFT JOIN usuario u ON u.id_persona = p.id_persona
        WHERE p.id_persona = :id_persona
    ";
    $stmtCheck = $db->prepare($sqlCheck);
    $stmtCheck->execute(['id_persona' => $data['id_persona']]);
    $personaCheck = $stmtCheck->fetch();

    if (!$personaCheck) {
        sendError('Persona no encontrada', 404);
    }

    if ($personaCheck['id_usuario'] !== null) {
        sendError('Esta persona ya tiene un usuario asociado', 400);
    }

    // Verificar que el username no exista
    $sqlCheckUser = "SELECT id_usuario FROM usuario WHERE username = :username";
    $stmtCheckUser = $db->prepare($sqlCheckUser);
    $stmtCheckUser->execute(['username' => $data['username']]);

    if ($stmtCheckUser->fetch()) {
        sendError('El nombre de usuario ya existe', 400);
    }

    // Validar longitud de contraseña
    if (strlen($data['password']) < 6) {
        sendError('La contraseña debe tener al menos 6 caracteres', 400);
    }

    // Iniciar transacción
    $db->beginTransaction();

    try {
        // Encriptar contraseña
        $passwordHash = password_hash($data['password'], PASSWORD_BCRYPT);

        // Insertar usuario
        $sql = "
            INSERT INTO usuario (id_persona, username, password, estado)
            VALUES (:id_persona, :username, :password, 'A')
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute([
            'id_persona' => $data['id_persona'],
            'username' => strtoupper(trim($data['username'])),
            'password' => $passwordHash
        ]);

        $idUsuario = $db->lastInsertId();

        // Insertar roles
        $sqlRol = "INSERT INTO usuario_rol (id_usuario, id_rol) VALUES (:id_usuario, :id_rol)";
        $stmtRol = $db->prepare($sqlRol);

        foreach ($data['roles'] as $idRol) {
            $stmtRol->execute([
                'id_usuario' => $idUsuario,
                'id_rol' => $idRol
            ]);
        }

        $db->commit();

        sendSuccess(['id_usuario' => (int)$idUsuario], 'Usuario creado exitosamente', 201);

    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

/**
 * PUT - Actualizar usuario
 */
function handlePut($db) {
    // Obtener ID de la URL
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $pathParts = explode('/', trim($path, '/'));
    $id = end($pathParts);

    if (!is_numeric($id)) {
        sendError('ID inválido', 400);
    }

    $data = getRequestBody();

    // Validaciones
    validateRequired($data, ['username', 'roles', 'estado']);

    if (!is_array($data['roles']) || empty($data['roles'])) {
        sendError('Debe seleccionar al menos un rol', 400);
    }

    // Verificar que el usuario existe
    $sqlCheck = "SELECT id_usuario FROM usuario WHERE id_usuario = :id";
    $stmtCheck = $db->prepare($sqlCheck);
    $stmtCheck->execute(['id' => $id]);

    if (!$stmtCheck->fetch()) {
        sendError('Usuario no encontrado', 404);
    }

    // Verificar que el username no esté duplicado
    $sqlCheckUser = "SELECT id_usuario FROM usuario WHERE username = :username AND id_usuario != :id";
    $stmtCheckUser = $db->prepare($sqlCheckUser);
    $stmtCheckUser->execute(['username' => $data['username'], 'id' => $id]);

    if ($stmtCheckUser->fetch()) {
        sendError('El nombre de usuario ya existe', 400);
    }

    // Iniciar transacción
    $db->beginTransaction();

    try {
        // Actualizar usuario
        $sqlUpdate = "
            UPDATE usuario
            SET username = :username,
                estado = :estado
        ";

        $params = [
            'username' => strtoupper(trim($data['username'])),
            'estado' => $data['estado'],
            'id' => $id
        ];

        // Si hay nueva contraseña, incluirla
        if (!empty($data['password'])) {
            if (strlen($data['password']) < 6) {
                sendError('La contraseña debe tener al menos 6 caracteres', 400);
            }
            $sqlUpdate .= ", password = :password";
            $params['password'] = password_hash($data['password'], PASSWORD_BCRYPT);
        }

        $sqlUpdate .= " WHERE id_usuario = :id";

        $stmt = $db->prepare($sqlUpdate);
        $stmt->execute($params);

        // Eliminar roles existentes
        $sqlDeleteRoles = "DELETE FROM usuario_rol WHERE id_usuario = :id";
        $stmtDeleteRoles = $db->prepare($sqlDeleteRoles);
        $stmtDeleteRoles->execute(['id' => $id]);

        // Insertar nuevos roles
        $sqlRol = "INSERT INTO usuario_rol (id_usuario, id_rol) VALUES (:id_usuario, :id_rol)";
        $stmtRol = $db->prepare($sqlRol);

        foreach ($data['roles'] as $idRol) {
            $stmtRol->execute([
                'id_usuario' => $id,
                'id_rol' => $idRol
            ]);
        }

        $db->commit();

        sendSuccess(null, 'Usuario actualizado exitosamente');

    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

/**
 * DELETE - Desactivar usuario
 */
function handleDelete($db) {
    // Obtener ID de la URL
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $pathParts = explode('/', trim($path, '/'));
    $id = end($pathParts);

    if (!is_numeric($id)) {
        sendError('ID inválido', 400);
    }

    // Verificar que el usuario existe
    $sqlCheck = "SELECT id_usuario FROM usuario WHERE id_usuario = :id";
    $stmtCheck = $db->prepare($sqlCheck);
    $stmtCheck->execute(['id' => $id]);

    if (!$stmtCheck->fetch()) {
        sendError('Usuario no encontrado', 404);
    }

    // Desactivar usuario
    $sql = "UPDATE usuario SET estado = 'I' WHERE id_usuario = :id";
    $stmt = $db->prepare($sql);
    $stmt->execute(['id' => $id]);

    sendSuccess(null, 'Usuario desactivado exitosamente');
}

/**
 * POST - Activar usuario
 */
function handleActivar($db, $id) {
    // Verificar que el usuario existe
    $sqlCheck = "SELECT id_usuario FROM usuario WHERE id_usuario = :id";
    $stmtCheck = $db->prepare($sqlCheck);
    $stmtCheck->execute(['id' => $id]);

    if (!$stmtCheck->fetch()) {
        sendError('Usuario no encontrado', 404);
    }

    // Activar usuario
    $sql = "UPDATE usuario SET estado = 'A' WHERE id_usuario = :id";
    $stmt = $db->prepare($sql);
    $stmt->execute(['id' => $id]);

    sendSuccess(null, 'Usuario activado exitosamente');
}

/**
 * POST - Desbloquear usuario
 */
function handleDesbloquear($db, $id) {
    // Verificar que el usuario existe y está bloqueado
    $sqlCheck = "SELECT id_usuario, estado FROM usuario WHERE id_usuario = :id";
    $stmtCheck = $db->prepare($sqlCheck);
    $stmtCheck->execute(['id' => $id]);
    $usuario = $stmtCheck->fetch();

    if (!$usuario) {
        sendError('Usuario no encontrado', 404);
    }

    if ($usuario['estado'] !== 'B') {
        sendError('El usuario no está bloqueado', 400);
    }

    // Desbloquear usuario (resetear intentos y cambiar estado a Activo)
    $sql = "
        UPDATE usuario
        SET estado = 'A',
            intentos_fallidos = 0,
            fecha_bloqueo = NULL
        WHERE id_usuario = :id
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute(['id' => $id]);

    sendSuccess(null, 'Usuario desbloqueado exitosamente');
}
