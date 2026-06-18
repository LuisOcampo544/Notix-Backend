<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/helpers.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

function authenticate(): int {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

    if (empty($authHeader) || !preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
        errorResponse('Token no proporcionado o formato inválido', 401);
    }

    $token = $matches[1];

    try {
        $decoded = JWT::decode($token, new Key($_ENV['JWT_SECRET'], 'HS256'));
        
        $db = getConnection();
        $stmt = $db->prepare('SELECT id FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $decoded->sub]);
        $user = $stmt->fetch();
        
        if (!$user) {
            errorResponse('Usuario no encontrado', 401);
        }
        
        return (int)$user['id'];
    } catch (Exception $e) {
        errorResponse('Token inválido', 401);
    }
}