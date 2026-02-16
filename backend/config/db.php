<?php

$DB_HOST = '127.0.0.1';
$DB_NAME = 'chatarreria';
$DB_USER = 'root';
$DB_PASS = '';

try {
    $pdo = new PDO(
        "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Error de conexión a la base de datos',
        'detalle' => $e->getMessage()
    ]);
    exit;
}
