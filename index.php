<?php
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'message' => 'API Backend is running',
    'version' => '1.0.0',
    'php_version' => phpversion(),
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'
]);
