<?php

require_once __DIR__ . '/env.php';

env_load(__DIR__ . '/../.env');

$DB_HOST = env('DB_HOST');
$DB_NAME = env('DB_NAME');
$DB_USER = env('DB_USER');
$DB_PASS = env('DB_PASS', '');
$APP_ENV = env('APP_ENV', 'local');

if (!$DB_HOST || !$DB_NAME || !$DB_USER) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'error' => 'Configuracion de base de datos incompleta en el servidor',
    ]);
    exit;
}

try {
    $pdo = new PDO(
        "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');

    $response = [
        'error' => 'Error de conexion a la base de datos',
    ];

    if ($APP_ENV === 'local') {
        $response['detalle'] = $e->getMessage();
    }

    echo json_encode($response);
    exit;
}
