<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/helpers.php';
require_once __DIR__ . '/../middleware/auth.php';

class AuthController {
    
    public function register() {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        $name = trim($data['name'] ?? '');
        $email = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';

        if (empty($name) || empty($email) || empty($password)) {
            errorResponse('Todos los campos son obligatorios', 422);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            errorResponse('Email no válido', 422);
        }
        if (strlen($password) < 6) {
            errorResponse('La contraseña debe tener al menos 6 caracteres', 422);
        }

        $db = getConnection();

        $stmt = $db->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        if ($stmt->fetch()) {
            errorResponse('El email ya está registrado', 409);
        }

        $hashed = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $db->prepare('INSERT INTO users (name, email, password) VALUES (:name, :email, :password)');
        $stmt->execute([
            'name' => $name,
            'email' => $email,
            'password' => $hashed
        ]);

        $userId = (int)$db->lastInsertId();
        $token = generateJWT($userId);

        jsonResponse([
            'message' => 'Usuario registrado correctamente',
            'token' => $token,
            'user' => [
                'id' => $userId,
                'name' => $name,
                'email' => $email,
                'is_premium' => false
            ]
        ], 201);
    }

    public function login() {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        $email = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';

        if (empty($email) || empty($password)) {
            errorResponse('Email y contraseña son obligatorios', 422);
        }

        $db = getConnection();
        $stmt = $db->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            errorResponse('Credenciales incorrectas', 401);
        }

        $token = generateJWT($user['id']);

        jsonResponse([
            'message' => 'Inicio de sesión exitoso',
            'token' => $token,
            'user' => [
                'id' => (int)$user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'is_premium' => (bool)$user['is_premium']
            ]
        ]);
    }

    public function me() {
        $userId = authenticate();
        $user = findUserById($userId);
        jsonResponse(['user' => $user]);
    }
}