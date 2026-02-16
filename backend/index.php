<?php

header('Access-Control-Allow-Origin: http://localhost:4200');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Credentials: true');

// 🔥 Manejo del preflight (ESTO ES CLAVE)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}


    require_once __DIR__ . '/config/db.php';
    require_once __DIR__ . '/helpers/Response.php';

    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $method = $_SERVER['REQUEST_METHOD'];
    $parts = explode('/', trim($uri, '/'));

    switch ($parts[0]) {
        case 'auth':
            require_once __DIR__ . '/controllers/AuthController.php';
            AuthController::handle($method, $parts);
            break;

        case 'empleados':
            require_once __DIR__ . '/controllers/EmpleadosController.php';
            EmpleadosController::handle($method, $parts);
            break;

        case 'documentos':
            require_once __DIR__ . '/controllers/DocumentosController.php';
            DocumentosController::handle($method, $parts);
            break;

        case 'usuarios':
            require_once __DIR__ . '/controllers/UsuariosController.php';
            UsuariosController::handle($method, $parts);
            break;

        default:
            Response::json(['error' => 'Endpoint no encontrado'], 404);
    }
