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
        'id_horario',
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

    $id_horario = (int)$data['id_horario'];

    $db = getConnection();
    $db->beginTransaction();

    // Validar si ya tiene marcaciones asociadas
    $stmtCheckMarcaciones = $db->prepare("SELECT COUNT(*) FROM marcacion WHERE id_horario_resuelto = :id_horario");
    $stmtCheckMarcaciones->execute(['id_horario' => $id_horario]);
    $hayMarcaciones = (int)$stmtCheckMarcaciones->fetchColumn() > 0;
    if ($hayMarcaciones) {
        sendError('No se puede editar el horario porque ya tiene marcaciones registradas', 400);
    }

    $sql = "
        UPDATE horario
        SET nombre = :nombre,
            entrada = :entrada,
            inicio_refri = :inicio_refri,
            fin_refri = :fin_refri,
            salida = :salida,
            tol_entrada_min = :tol_entrada_min,
            tol_salida_min = :tol_salida_min,
            tol_refri_min = :tol_refri_min,
            color = :color
        WHERE id_horario = :id_horario
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
        'id_horario' => $id_horario,
    ]);

    // reset dias
    $stmtDel = $db->prepare("DELETE FROM horario_dia WHERE id_horario = :id_horario");
    $stmtDel->execute(['id_horario' => $id_horario]);

    if (!is_array($data['dias']) || count($data['dias']) === 0) {
        sendError('Se requiere al menos un dia aplicable', 400);
    }

    $sqlDia = "INSERT INTO horario_dia (id_horario, dia_semana) VALUES (:id_horario, :dia)";
    $stmtDia = $db->prepare($sqlDia);
    foreach ($data['dias'] as $dia) {
        $stmtDia->execute([
            'id_horario' => $id_horario,
            'dia' => (int)$dia,
        ]);
    }

    $db->commit();

    sendSuccess(null, 'Horario actualizado');
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
