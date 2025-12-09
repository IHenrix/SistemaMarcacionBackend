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

    $personal = isset($_GET['personal']) ? (int)$_GET['personal'] : 0;
    $idPersonaFiltro = isset($_GET['id_persona']) ? (int)$_GET['id_persona'] : null;
    $desde = isset($_GET['desde']) ? $_GET['desde'] : null;
    $hasta = isset($_GET['hasta']) ? $_GET['hasta'] : null;
    $tardanzaFiltro = isset($_GET['tardanza']) ? $_GET['tardanza'] : null; // '1' | '0'

    $db = getConnection();

    // Si es reporte personal, resolver id_persona desde el usuario autenticado
    if ($personal === 1) {
        $stmtPersona = $db->prepare("SELECT id_persona FROM usuario WHERE id_usuario = :id");
        $stmtPersona->execute(['id' => $userId]);
        $idPersonaFiltro = $stmtPersona->fetchColumn();
        if (!$idPersonaFiltro) {
            sendError('Usuario sin persona asociada', 400);
        }
    }

    $where = [];
    $params = [];

    if ($idPersonaFiltro) {
        $where[] = 'm.id_persona = :id_persona';
        $params['id_persona'] = $idPersonaFiltro;
    }
    if ($desde) {
        $where[] = 'm.fecha >= :desde';
        $params['desde'] = $desde;
    }
    if ($hasta) {
        $where[] = 'm.fecha <= :hasta';
        $params['hasta'] = $hasta;
    }

    $sql = "
        SELECT
            m.id_marcacion,
            m.id_persona,
            m.id_horario_resuelto,
            m.tipo,
            m.fecha,
            m.hora,
            m.creado_en,
            p.nombres,
            p.apellidos,
            p.dni,
            h.nombre AS horario_nombre,
            h.entrada,
            h.inicio_refri,
            h.fin_refri,
            h.salida,
            h.tol_entrada_min
        FROM marcacion m
        LEFT JOIN persona p ON m.id_persona = p.id_persona
        LEFT JOIN horario h ON m.id_horario_resuelto = h.id_horario
    ";

    if (!empty($where)) {
        $sql .= " WHERE " . implode(' AND ', $where);
    }

    $sql .= " ORDER BY m.fecha DESC, m.hora DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    // Agregar por persona + fecha para mostrar en una sola línea sus eventos del día
    $agrupado = [];
    foreach ($rows as $row) {
        $key = $row['id_persona'] . '_' . $row['fecha'];
        if (!isset($agrupado[$key])) {
            $agrupado[$key] = [
                'id_persona' => (int)$row['id_persona'],
                'nombres' => $row['nombres'],
                'apellidos' => $row['apellidos'],
                'dni' => $row['dni'],
                'fecha' => $row['fecha'],
                'horario_nombre' => $row['horario_nombre'],
                'entrada' => null,
                'inicio_refri' => null,
                'fin_refri' => null,
                'salida' => null,
                'tardanza' => false,
                'minutos_tarde' => null,
                'tol_entrada_min' => $row['tol_entrada_min'],
                'entrada_objetivo' => $row['entrada']
            ];
        }

        switch ($row['tipo']) {
            case 'ENTRADA':
                $agrupado[$key]['entrada'] = $row['hora'];
                // calcular tardanza
                if (!empty($row['entrada'])) {
                    $tol = isset($row['tol_entrada_min']) ? (int)$row['tol_entrada_min'] : 0;
                    $esperado = timeToMinutes($row['entrada']) + $tol;
                    $real = timeToMinutes($row['hora']);
                    if ($real > $esperado) {
                        $agrupado[$key]['tardanza'] = true;
                        $agrupado[$key]['minutos_tarde'] = $real - $esperado;
                    }
                }
                break;
            case 'INICIO_REFRI':
                $agrupado[$key]['inicio_refri'] = $row['hora'];
                break;
            case 'FIN_REFRI':
                $agrupado[$key]['fin_refri'] = $row['hora'];
                break;
            case 'SALIDA':
                $agrupado[$key]['salida'] = $row['hora'];
                break;
        }
    }

    // filtrar por tardanza si se solicitó
    $resultado = array_values($agrupado);
    if ($tardanzaFiltro === '1') {
        $resultado = array_values(array_filter($resultado, fn($r) => $r['tardanza'] === true));
    } elseif ($tardanzaFiltro === '0') {
        $resultado = array_values(array_filter($resultado, fn($r) => $r['tardanza'] === false));
    }

    // limpieza de campos internos
    foreach ($resultado as &$r) {
        unset($r['tol_entrada_min'], $r['entrada_objetivo']);
    }

    sendSuccess($resultado, 'Reporte generado');
} catch (PDOException $e) {
    sendError('Error en la base de datos: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    sendError('Error interno del servidor: ' . $e->getMessage(), 500);
}

function timeToMinutes(string $hhmm): int
{
    [$h, $m] = array_map('intval', explode(':', $hhmm));
    return ($h * 60) + $m;
}
