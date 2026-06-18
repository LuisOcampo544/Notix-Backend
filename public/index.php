<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../src/config/database.php';
require_once __DIR__ . '/../src/utils/helpers.php';
require_once __DIR__ . '/../src/controllers/AuthController.php';
require_once __DIR__ . '/../src/controllers/NoteController.php';
require_once __DIR__ . '/../src/controllers/StripeController.php';
require_once __DIR__ . '/../src/controllers/UserController.php';

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

$routes = [
    // Autenticación
    'POST /api/register'               => ['AuthController', 'register'],
    'POST /api/login'                  => ['AuthController', 'login'],

    // Usuario actual
    'GET /api/me'                      => ['AuthController', 'me'],
    'PUT /api/user/profile'            => ['UserController', 'updateProfile'],

    // Notas
    'GET /api/notes'                   => ['NoteController', 'index'],
    'POST /api/notes'                  => ['NoteController', 'store'],
    'PUT /api/notes/{id}'              => ['NoteController', 'update'],
    'DELETE /api/notes/{id}'           => ['NoteController', 'destroy'],
    'GET /api/notes/{id}/pdf'          => ['NoteController', 'exportPdf'],

    // Stripe
    'POST /api/stripe/create-session'  => ['StripeController', 'createSession'],
    'POST /api/stripe/webhook'         => ['StripeController', 'webhook'],
];

$matched = false;
foreach ($routes as $route => $action) {
    [$routeMethod, $routePath] = explode(' ', $route, 2);
    $pattern = preg_replace('/\{[a-zA-Z]+\}/', '([0-9]+)', $routePath);
    $pattern = '#^' . $pattern . '$#';
    if ($method === $routeMethod && preg_match($pattern, $uri, $matches)) {
        array_shift($matches);
        $controller = new $action[0]();
        call_user_func_array([$controller, $action[1]], $matches);
        $matched = true;
        break;
    }
}

if (!$matched) {
    errorResponse('Ruta no encontrada', 404);
}