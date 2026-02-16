<?php
require_once __DIR__ . '/JWT.php';
require_once __DIR__ . '/Response.php';

class AuthMiddleware
{
    public static function check(array $rolesPermitidos = []): array
    {
        // ✅ Permitir preflight CORS
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }

        $authHeader = null;

        // 1️⃣ Intento estándar
        if (function_exists('getallheaders')) {
            foreach (getallheaders() as $key => $value) {
                if (strtolower($key) === 'authorization') {
                    $authHeader = $value;
                    break;
                }
            }
        }

        // 2️⃣ Fallback PHP built-in server / Windows
        if (!$authHeader && isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
        }

        // 3️⃣ Último fallback
        if (!$authHeader && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }

        if (!$authHeader) {
            Response::json(['error' => 'Token requerido'], 401);
        }

        // ✅ Validar formato
        if (!str_starts_with($authHeader, 'Bearer ')) {
            Response::json(['error' => 'Formato de token inválido'], 401);
        }

        // ✅ Validar token
        $token = str_replace('Bearer ', '', $authHeader);
        $payload = JWT::validate($token);

        if (!$payload) {
            Response::json(['error' => 'Token inválido o expirado'], 401);
        }

        // ✅ Validar roles (si aplica)
        if (!empty($rolesPermitidos) && !in_array($payload['rol'], $rolesPermitidos)) {
            Response::json(['error' => 'Acceso no autorizado'], 403);
        }

        return $payload;
    }
}
