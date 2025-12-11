<?php

function cors() {
    $allowedOrigins = [
        'http://localhost:4200',
        'https://abril-delicias-helados-h5grfde5c9csgxhq.brazilsouth-01.azurewebsites.net'
    ];

    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

    if (in_array($origin, $allowedOrigins)) {
        header('Access-Control-Allow-Origin: ' . $origin);
    }

    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');

    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

    header('Access-Control-Allow-Credentials: true');

    header('Content-Type: application/json; charset=UTF-8');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit(0);
    }
}
