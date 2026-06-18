<?php
require_once __DIR__ . '/../config/database.php';

function jsonResponse($data, int $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function errorResponse(string $message, int $code = 400) {
    jsonResponse(['error' => $message], $code);
}

function findUserById(int $userId): array {
    $db = getConnection();
    $stmt = $db->prepare('SELECT id, name, email, is_premium, created_at FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $userId]);
    $user = $stmt->fetch();

    if (!$user) {
        errorResponse('Usuario no encontrado', 404);
    }
    
    $user['id'] = (int)$user['id'];
    $user['is_premium'] = (bool)$user['is_premium'];
    
    return $user;
}

function generateJWT(int $userId): string {
    $payload = [
        'iss' => 'notesapp',
        'sub' => $userId,
        'iat' => time(),
        'exp' => time() + 86400
    ];
    return \Firebase\JWT\JWT::encode($payload, $_ENV['JWT_SECRET'], 'HS256');
}