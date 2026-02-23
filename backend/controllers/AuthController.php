<?php
header("Access-Control-Allow-Origin: http://localhost:4200");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../helpers/JWT.php';

class AuthController {

    public static function handle(string $method, array $parts): void {

        if ($method === 'POST' && isset($parts[1]) && $parts[1] === 'login') {
            self::login();
            return;
        }

        Response::json([
            'error' => 'Ruta no valida en auth',
            'debug' => [
                'method' => $method,
                'parts' => $parts
            ]
        ], 404);
    }

    private static function login(): void {
        global $pdo;

        if (!JWT::isConfigured()) {
            Response::json(['error' => 'Configuracion JWT incompleta en el servidor'], 500);
        }

        $data = json_decode(file_get_contents('php://input'), true);

        if (!$data || empty($data['email']) || empty($data['password'])) {
            Response::json(['error' => 'Datos incompletos'], 400);
        }

        $stmt = $pdo->prepare("
            SELECT id, nombre, email, clave, rol, empleado_id
            FROM usuarios
            WHERE email = ?
            LIMIT 1
        ");
        $stmt->execute([$data['email']]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($data['password'], $user['clave'])) {
            Response::json(['error' => 'Credenciales invalidas'], 401);
        }

        try {
            $token = JWT::generate([
                'id' => (int) $user['id'],
                'rol' => (string) $user['rol'],
                'name' => (string) $user['nombre'],
                'empleado_id' => isset($user['empleado_id']) ? (int) $user['empleado_id'] : null,
            ]);
        } catch (Throwable $e) {
            Response::json(['error' => 'No se pudo generar el token de sesion'], 500);
        }

        Response::json([
            'token' => $token,
            'user' => [
                'id' => (int) $user['id'],
                'nombre' => $user['nombre'],
                'email' => $user['email'],
                'rol' => $user['rol'],
                'empleado_id' => isset($user['empleado_id']) ? (int) $user['empleado_id'] : null,
            ]
        ]);
    }
}
