<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/helpers.php';
require_once __DIR__ . '/../middleware/auth.php';

class UserController
{
    public function updateProfile()
    {
        $userId = authenticate();
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $db = getConnection();

        $name = trim($data['name'] ?? '');
        $email = trim($data['email'] ?? '');
        $currentPassword = $data['current_password'] ?? '';
        $newPassword = $data['new_password'] ?? '';

        if (empty($name) || empty($email)) {
            errorResponse('Nombre y email son obligatorios', 422);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            errorResponse('Email no válido', 422);
        }

        $stmt = $db->prepare('SELECT id FROM users WHERE email = :email AND id != :uid LIMIT 1');
        $stmt->execute(['email' => $email, 'uid' => $userId]);
        if ($stmt->fetch()) {
            errorResponse('El email ya está en uso por otro usuario', 409);
        }

        if (!empty($newPassword)) {
            if (strlen($newPassword) < 6) {
                errorResponse('La nueva contraseña debe tener al menos 6 caracteres', 422);
            }
            $stmt = $db->prepare('SELECT password FROM users WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $userId]);
            $user = $stmt->fetch();
            
            if (!$user || !password_verify($currentPassword, $user['password'])) {
                errorResponse('Contraseña actual incorrecta', 403);
            }
            $hashed = password_hash($newPassword, PASSWORD_BCRYPT);
            $stmt = $db->prepare('UPDATE users SET name = :name, email = :email, password = :pass WHERE id = :id');
            $stmt->execute(['name' => $name, 'email' => $email, 'pass' => $hashed, 'id' => $userId]);
        } else {
            $stmt = $db->prepare('UPDATE users SET name = :name, email = :email WHERE id = :id');
            $stmt->execute(['name' => $name, 'email' => $email, 'id' => $userId]);
        }

        $updatedUser = findUserById($userId);

        jsonResponse(['message' => 'Perfil actualizado', 'user' => $updatedUser]);
    }
}