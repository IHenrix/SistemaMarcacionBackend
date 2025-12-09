<?php

require_once '../../includes/config.php';
require_once '../../includes/database.php';
require_once '../../includes/cors.php';
require_once '../../includes/response.php';
require_once '../../includes/jwt.php';

cors();
validateMethod('POST');

try {
    requireAuth();
    $data = getRequestBody();

    validateRequired($data, [
        'nombre',
        'entrada',
        'inicio_refri',
        'fin_refri',
        'salida',
        'tol_entrada_min',
        'tol_salida_min',
        'tol_refri_min',
        'dias'
    ]);

    if (!is_array($data['dias']) || count($data['dias']) === 0) {
        sendError('Se requiere al menos un dia aplicable', 400);
    }

    $db = getConnection();
    $db->beginTransaction();

    $sql = "
        INSERT INTO horario
        (nombre, entrada, inicio_refri, fin_refri, salida, tol_entrada_min, tol_salida_min, tol_refri_min, color, estado)
        VALUES
        (:nombre, :entrada, :inicio_refri, :fin_refri, :salida, :tol_entrada_min, :tol_salida_min, :tol_refri_min, :color, 1)
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute([
        'nombre' => trim($data['nombre']),
        'entrada' => $data['entrada'],
        'inicio_refri' => $data['inicio_refri'],
        'fin_refri' => $data['fin_refri'],
        'salida' => $data['salida'],
        'tol_entrada_min' => (int)$data['tol_entrada_min'],
        'tol_salida_min' => (int)$data['tol_salida_min'],
        'tol_refri_min' => (int)$data['tol_refri_min'],
        'color' => isset($data['color']) ? trim($data['color']) : null,
    ]);

    $id_horario = $db->lastInsertId();

    $sqlDia = "INSERT INTO horario_dia (id_horario, dia_semana) VALUES (:id_horario, :dia)";
    $stmtDia = $db->prepare($sqlDia);
    foreach ($data['dias'] as $dia) {
        $stmtDia->execute([
            'id_horario' => $id_horario,
            'dia' => (int)$dia,
        ]);
    }

    $db->commit();

    sendSuccess(['id_horario' => (int)$id_horario], 'Horario creado', 201);
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
