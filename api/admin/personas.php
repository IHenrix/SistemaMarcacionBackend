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
 * GET - Listar personas (con filtros opcionales) u obtener una persona por ID
 */
function handleGet($db) {
    // Si hay ID en la URL, obtener persona específica
    if (isset($_GET['id'])) {
        obtenerPersona($db, $_GET['id']);
        return;
    }

    // Construir query con filtros
    $sql = "
        SELECT
            p.id_persona,
            p.dni,
            p.nombres,
            p.apellidos,
            p.telefono,
            p.correo,
            p.id_area,
            p.estado,
            p.fecha_creacion,
            a.nombre as area_nombre,
            a.descripcion as area_descripcion,
            u.id_usuario,
            u.username
        FROM persona p
        LEFT JOIN area a ON p.id_area = a.id_area
        LEFT JOIN usuario u ON u.id_persona = p.id_persona
        WHERE 1=1
    ";

    $params = [];

    // Filtro por nombres
    if (!empty($_GET['nombres'])) {
        $sql .= " AND UPPER(p.nombres) LIKE UPPER(:nombres)";
        $params['nombres'] = '%' . $_GET['nombres'] . '%';
    }

    // Filtro por apellidos
    if (!empty($_GET['apellidos'])) {
        $sql .= " AND UPPER(p.apellidos) LIKE UPPER(:apellidos)";
        $params['apellidos'] = '%' . $_GET['apellidos'] . '%';
    }

    // Filtro por estado
    if (isset($_GET['estado']) && $_GET['estado'] !== '') {
        $sql .= " AND p.estado = :estado";
        $params['estado'] = $_GET['estado'];
    }

    // Filtro "sin usuario"
    if (isset($_GET['sinUsuario']) && $_GET['sinUsuario'] === 'true') {
        $sql .= " AND u.id_usuario IS NULL";
    }

    $sql .= " ORDER BY p.fecha_creacion DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $personas = $stmt->fetchAll();

    // Formatear respuesta
    $result = array_map(function($persona) {
        return [
            'id_persona' => (int)$persona['id_persona'],
            'dni' => $persona['dni'],
            'nombres' => $persona['nombres'],
            'apellidos' => $persona['apellidos'],
            'telefono' => $persona['telefono'],
            'correo' => $persona['correo'],
            'id_area' => $persona['id_area'] ? (int)$persona['id_area'] : null,
            'estado' => (int)$persona['estado'],
            'fecha_creacion' => $persona['fecha_creacion'],
            'area' => $persona['id_area'] ? [
                'id_area' => (int)$persona['id_area'],
                'nombre' => $persona['area_nombre'],
                'descripcion' => $persona['area_descripcion']
            ] : null,
            'tiene_usuario' => $persona['id_usuario'] !== null,
            'username' => $persona['username']
        ];
    }, $personas);

    sendSuccess($result, 'Personas obtenidas exitosamente');
}

/**
 * Obtener una persona por ID
 */
function obtenerPersona($db, $id) {
    $sql = "
        SELECT
            p.id_persona,
            p.dni,
            p.nombres,
            p.apellidos,
            p.telefono,
            p.correo,
            p.id_area,
            p.estado,
            p.fecha_creacion,
            a.nombre as area_nombre,
            u.id_usuario,
            u.username
        FROM persona p
        LEFT JOIN area a ON p.id_area = a.id_area
        LEFT JOIN usuario u ON u.id_persona = p.id_persona
        WHERE p.id_persona = :id
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute(['id' => $id]);
    $persona = $stmt->fetch();

    if (!$persona) {
        sendError('Persona no encontrada', 404);
    }

    $result = [
        'id_persona' => (int)$persona['id_persona'],
        'dni' => $persona['dni'],
        'nombres' => $persona['nombres'],
        'apellidos' => $persona['apellidos'],
        'telefono' => $persona['telefono'],
        'correo' => $persona['correo'],
        'id_area' => $persona['id_area'] ? (int)$persona['id_area'] : null,
        'estado' => (int)$persona['estado'],
        'fecha_creacion' => $persona['fecha_creacion'],
        'tiene_usuario' => $persona['id_usuario'] !== null,
        'username' => $persona['username']
    ];

    sendSuccess($result, 'Persona obtenida exitosamente');
}

/**
 * POST - Crear nueva persona
 */
function handlePost($db) {
    $data = getRequestBody();

    // Validaciones
    validateRequired($data, ['dni', 'nombres', 'apellidos', 'correo']);

    // Validar formato de email
    if (!filter_var($data['correo'], FILTER_VALIDATE_EMAIL)) {
        sendError('Formato de correo inválido', 400);
    }

    // Verificar que el DNI no exista
    $sqlCheck = "SELECT id_persona FROM persona WHERE dni = :dni";
    $stmtCheck = $db->prepare($sqlCheck);
    $stmtCheck->execute(['dni' => $data['dni']]);

    if ($stmtCheck->fetch()) {
        sendError('Ya existe una persona con ese DNI', 400);
    }

    // Verificar que el correo no exista
    $sqlCheckEmail = "SELECT id_persona FROM persona WHERE correo = :correo";
    $stmtCheckEmail = $db->prepare($sqlCheckEmail);
    $stmtCheckEmail->execute(['correo' => $data['correo']]);

    if ($stmtCheckEmail->fetch()) {
        sendError('Ya existe una persona con ese correo', 400);
    }

    // Insertar persona
    $sql = "
        INSERT INTO persona (dni, nombres, apellidos, telefono, correo, id_area, estado)
        VALUES (:dni, :nombres, :apellidos, :telefono, :correo, :id_area, 1)
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute([
        'dni' => strtoupper(trim($data['dni'])),
        'nombres' => strtoupper(trim($data['nombres'])),
        'apellidos' => strtoupper(trim($data['apellidos'])),
        'telefono' => isset($data['telefono']) ? strtoupper(trim($data['telefono'])) : null,
        'correo' => strtolower(trim($data['correo'])),
        'id_area' => isset($data['id_area']) ? $data['id_area'] : null
    ]);

    $id = $db->lastInsertId();

    sendSuccess(['id_persona' => (int)$id], 'Persona creada exitosamente', 201);
}

/**
 * PUT - Actualizar persona
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
    validateRequired($data, ['dni', 'nombres', 'apellidos', 'correo', 'estado']);

    // Validar formato de email
    if (!filter_var($data['correo'], FILTER_VALIDATE_EMAIL)) {
        sendError('Formato de correo inválido', 400);
    }

    // Verificar que la persona existe
    $sqlCheck = "SELECT id_persona FROM persona WHERE id_persona = :id";
    $stmtCheck = $db->prepare($sqlCheck);
    $stmtCheck->execute(['id' => $id]);

    if (!$stmtCheck->fetch()) {
        sendError('Persona no encontrada', 404);
    }

    // Verificar que el DNI no esté duplicado (excepto la misma persona)
    $sqlCheckDni = "SELECT id_persona FROM persona WHERE dni = :dni AND id_persona != :id";
    $stmtCheckDni = $db->prepare($sqlCheckDni);
    $stmtCheckDni->execute(['dni' => $data['dni'], 'id' => $id]);

    if ($stmtCheckDni->fetch()) {
        sendError('Ya existe otra persona con ese DNI', 400);
    }

    // Verificar que el correo no esté duplicado
    $sqlCheckEmail = "SELECT id_persona FROM persona WHERE correo = :correo AND id_persona != :id";
    $stmtCheckEmail = $db->prepare($sqlCheckEmail);
    $stmtCheckEmail->execute(['correo' => $data['correo'], 'id' => $id]);

    if ($stmtCheckEmail->fetch()) {
        sendError('Ya existe otra persona con ese correo', 400);
    }

    // Actualizar persona
    $sql = "
        UPDATE persona
        SET dni = :dni,
            nombres = :nombres,
            apellidos = :apellidos,
            telefono = :telefono,
            correo = :correo,
            id_area = :id_area,
            estado = :estado
        WHERE id_persona = :id
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute([
        'dni' => strtoupper(trim($data['dni'])),
        'nombres' => strtoupper(trim($data['nombres'])),
        'apellidos' => strtoupper(trim($data['apellidos'])),
        'telefono' => isset($data['telefono']) ? strtoupper(trim($data['telefono'])) : null,
        'correo' => strtolower(trim($data['correo'])),
        'id_area' => isset($data['id_area']) ? $data['id_area'] : null,
        'estado' => $data['estado'],
        'id' => $id
    ]);

    sendSuccess(null, 'Persona actualizada exitosamente');
}

/**
 * DELETE - Desactivar persona (y su usuario si tiene)
 */
function handleDelete($db) {
    // Obtener ID de la URL
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $pathParts = explode('/', trim($path, '/'));
    $id = end($pathParts);

    if (!is_numeric($id)) {
        sendError('ID inválido', 400);
    }

    // Verificar que la persona existe
    $sqlCheck = "SELECT id_persona FROM persona WHERE id_persona = :id";
    $stmtCheck = $db->prepare($sqlCheck);
    $stmtCheck->execute(['id' => $id]);

    if (!$stmtCheck->fetch()) {
        sendError('Persona no encontrada', 404);
    }

    // Iniciar transacción
    $db->beginTransaction();

    try {
        // Desactivar persona
        $sqlPersona = "UPDATE persona SET estado = 0 WHERE id_persona = :id";
        $stmtPersona = $db->prepare($sqlPersona);
        $stmtPersona->execute(['id' => $id]);

        // Desactivar usuario asociado si existe
        $sqlUsuario = "UPDATE usuario SET estado = 'I' WHERE id_persona = :id";
        $stmtUsuario = $db->prepare($sqlUsuario);
        $stmtUsuario->execute(['id' => $id]);

        $db->commit();

        sendSuccess(null, 'Persona desactivada exitosamente');

    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}
