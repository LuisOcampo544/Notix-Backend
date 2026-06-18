<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/helpers.php';
require_once __DIR__ . '/../middleware/auth.php';

class NoteController
{
    public function index()
    {
        $userId = authenticate();
        $db = getConnection();
        $search = trim($_GET['q'] ?? '');

        $sql = 'SELECT id, title, content, tag, color, created_at, updated_at FROM notes WHERE user_id = :uid';
        $params = ['uid' => $userId];
        
        if (!empty($search)) {
            $sql .= ' AND (title LIKE :search OR content LIKE :search2)';
            $like = '%' . $search . '%';
            $params['search'] = $like;
            $params['search2'] = $like;
        }
        $sql .= ' ORDER BY updated_at DESC';

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $notes = $stmt->fetchAll();
        
        jsonResponse(['notes' => $notes]);
    }

    public function store()
    {
        $userId = authenticate();
        $user = findUserById($userId);
        $this->checkNoteLimit($userId, $user['is_premium']);

        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $title = trim($data['title'] ?? '');
        $content = trim($data['content'] ?? '');
        $tag = trim($data['tag'] ?? 'general');
        $color = ($user['is_premium'] && !empty($data['color'])) ? trim($data['color']) : '#ffffff';

        if (empty($title)) {
            errorResponse('El título es obligatorio', 422);
        }

        $db = getConnection();
        $stmt = $db->prepare(
            'INSERT INTO notes (user_id, title, content, tag, color) VALUES (:uid, :title, :content, :tag, :color)'
        );
        $stmt->execute(['uid' => $userId, 'title' => $title, 'content' => $content, 'tag' => $tag, 'color' => $color]);
        $noteId = (int)$db->lastInsertId();

        $note = $this->findNoteById($noteId, $userId);
        jsonResponse(['message' => 'Nota creada correctamente', 'note' => $note], 201);
    }

    public function update($id)
    {
        $userId = authenticate();
        $user = findUserById($userId);
        $note = $this->findNoteById((int)$id, $userId);
        
        if (!$note) {
            errorResponse('Nota no encontrada', 404);
        }

        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $title = trim($data['title'] ?? $note['title']);
        $content = trim($data['content'] ?? $note['content']);
        $tag = trim($data['tag'] ?? $note['tag']);
        $color = ($user['is_premium'] && isset($data['color'])) ? trim($data['color']) : $note['color'];

        if (empty($title)) {
            errorResponse('El título es obligatorio', 422);
        }

        $db = getConnection();
        $stmt = $db->prepare(
            'UPDATE notes SET title = :title, content = :content, tag = :tag, color = :color WHERE id = :id AND user_id = :uid'
        );
        $stmt->execute(['title' => $title, 'content' => $content, 'tag' => $tag, 'color' => $color, 'id' => (int)$id, 'uid' => $userId]);

        $updated = $this->findNoteById((int)$id, $userId);
        jsonResponse(['message' => 'Nota actualizada', 'note' => $updated]);
    }

    public function destroy($id)
    {
        $userId = authenticate();
        $note = $this->findNoteById((int)$id, $userId);
        if (!$note) {
            errorResponse('Nota no encontrada', 404);
        }
        
        $db = getConnection();
        $stmt = $db->prepare('DELETE FROM notes WHERE id = :id AND user_id = :uid');
        $stmt->execute(['id' => (int)$id, 'uid' => $userId]);
        
        jsonResponse(['message' => 'Nota eliminada correctamente']);
    }

    public function exportPdf($id)
    {
        $userId = authenticate();
        $user = findUserById($userId);
        
        if (!$user['is_premium']) {
            errorResponse('Necesitas ser premium para esta función', 403);
        }

        $note = $this->findNoteById((int)$id, $userId);
        if (!$note) {
            errorResponse('Nota no encontrada', 404);
        }

        $title = htmlspecialchars($note['title']);
        $content = nl2br(htmlspecialchars($note['content']));
        $tag = htmlspecialchars($note['tag']);

        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Nota</title>
        <style>body{font-family:DejaVu Sans,sans-serif;padding:20px;}h1{color:#333;}.tag{display:inline-block;background:#eee;padding:2px 8px;border-radius:4px;}</style>
        </head><body>
        <h1>' . $title . '</h1>
        <p class="tag">' . $tag . '</p>
        <p>' . $content . '</p>
        </body></html>';

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="nota_' . (int)$id . '.pdf"');
        echo $dompdf->output();
        exit;
    }

    private function findNoteById(int $id, int $userId): ?array
    {
        $db = getConnection();
        $stmt = $db->prepare('SELECT id, title, content, tag, color, created_at, updated_at FROM notes WHERE id = :id AND user_id = :uid LIMIT 1');
        $stmt->execute(['id' => $id, 'uid' => $userId]);
        return $stmt->fetch() ?: null;
    }

    private function checkNoteLimit(int $userId, bool $isPremium): void
    {
        if ($isPremium) return;
        
        $db = getConnection();
        $stmt = $db->prepare('SELECT COUNT(*) as total FROM notes WHERE user_id = :uid');
        $stmt->execute(['uid' => $userId]);
        $count = (int)$stmt->fetch()['total'];
        
        if ($count >= 20) {
            errorResponse('Límite de 20 notas alcanzado. Actualízate a Premium.', 403);
        }
    }
}