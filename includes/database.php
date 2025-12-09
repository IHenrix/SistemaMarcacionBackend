<?php


require_once __DIR__ . '/config.php';

function getConnection() {
    static $connection = null;

    if ($connection === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST .
                   ";port=" . DB_PORT .
                   ";dbname=" . DB_NAME .
                   ";charset=" . DB_CHARSET;

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            $connection = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error de conexión a la base de datos'
            ]);
            exit;
        }
    }

    return $connection;
}


function callProcedure($procedureName, $params = []) {
    $db = getConnection();

    $placeholders = str_repeat('?,', count($params) - 1) . '?';
    $sql = "CALL $procedureName($placeholders)";

    $stmt = $db->prepare($sql);
    return $stmt->execute($params);
}
