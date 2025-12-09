<?php


require_once __DIR__ . '/config.php';
require_once __DIR__ . '/response.php';

function generateJWT($userId, $extraData = []) {
    // Header
    $header = [
        'typ' => 'JWT',
        'alg' => 'HS256'
    ];

    $payload = array_merge([
        'user_id' => $userId,
        'iat' => time(),
        'exp' => time() + JWT_EXPIRATION
    ], $extraData);

    $base64UrlHeader = base64UrlEncode(json_encode($header));
    $base64UrlPayload = base64UrlEncode(json_encode($payload));

    $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, JWT_SECRET, true);
    $base64UrlSignature = base64UrlEncode($signature);

    return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
}

function validateJWT($jwt) {
    if (empty($jwt)) {
        return false;
    }

    $parts = explode('.', $jwt);

    if (count($parts) !== 3) {
        return false;
    }

    list($base64UrlHeader, $base64UrlPayload, $base64UrlSignature) = $parts;

    $signature = base64UrlDecode($base64UrlSignature);
    $expectedSignature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, JWT_SECRET, true);

    if (!hash_equals($expectedSignature, $signature)) {
        return false;
    }

    $payload = json_decode(base64UrlDecode($base64UrlPayload), true);

    if (isset($payload['exp']) && $payload['exp'] < time()) {
        return false;
    }

    return $payload;
}

function getTokenFromHeader() {
    $authHeader = null;

    // Try getallheaders first
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        if (isset($headers['Authorization'])) {
            $authHeader = $headers['Authorization'];
        } elseif (isset($headers['authorization'])) {
            $authHeader = $headers['authorization'];
        }
    }

    // Try $_SERVER alternatives for Apache/XAMPP
    if (!$authHeader && isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
    }

    if (!$authHeader && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    }

    // Extract token from "Bearer TOKEN" format
    if ($authHeader && preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        return $matches[1];
    }

    return null;
}

function requireAuth() {
    $token = getTokenFromHeader();

    if (!$token) {
        sendError('Token no proporcionado', 401);
    }

    $payload = validateJWT($token);

    if (!$payload) {
        sendError('Token inválido o expirado', 401);
    }

    return $payload;
}


function base64UrlEncode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}


function base64UrlDecode($data) {
    return base64_decode(strtr($data, '-_', '+/'));
}
