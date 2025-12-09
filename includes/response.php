<?php

function sendSuccess($data = null, $message = 'Operación exitosa', $code = 200) {
    http_response_code($code);
    echo json_encode([
        'success' => true,
        'data' => $data,
        'message' => $message
    ], JSON_UNESCAPED_UNICODE);
    exit;
}


function sendError($message = 'Ha ocurrido un error', $code = 400, $errors = null) {
    http_response_code($code);
    $response = [
        'success' => false,
        'message' => $message
    ];

    if ($errors !== null) {
        $response['errors'] = $errors;
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

function validateMethod($method) {
    if ($_SERVER['REQUEST_METHOD'] !== $method) {
        sendError("Método no permitido. Se esperaba $method", 405);
    }
}

function getRequestBody() {
    $body = file_get_contents('php://input');
    $data = json_decode($body, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        sendError('JSON inválido en el body de la petición', 400);
    }

    return $data ?? [];
}


function validateRequired($data, $required) {
    $missing = [];

    foreach ($required as $field) {
        if (!isset($data[$field]) || $data[$field] === '' || $data[$field] === null) {
            $missing[] = $field;
        }
    }

    if (!empty($missing)) {
        sendError('Campos requeridos faltantes', 400, [
            'missing_fields' => $missing
        ]);
    }
}
