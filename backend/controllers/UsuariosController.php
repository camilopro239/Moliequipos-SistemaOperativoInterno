<?php
header("Access-Control-Allow-Origin: http://localhost:4200");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../helpers/AuthMiddleware.php';

class UsuariosController
{
    private static array $rolesPermitidos = [
        'admin',
        'gerente',
        'propietario',
        'recursos_humanos',
        'supervisor_patio',
        'moledor_pasta',
        'auxiliar_patio',
    ];

    public static function handle(string $method, array $parts): void
    {
        AuthMiddleware::check(['admin', 'gerente', 'propietario', 'recursos_humanos']);

        self::asegurarEsquemaUsuarios();

        if ($method === 'GET' && isset($parts[1]) && $parts[1] === 'empleados') {
            self::listarEmpleados();
            return;
        }

        if ($method === 'GET') {
            self::listarUsuarios();
            return;
        }

        if ($method === 'POST') {
            self::crearUsuario();
            return;
        }

        if ($method === 'PUT' && isset($parts[1], $parts[2]) && is_numeric($parts[1]) && $parts[2] === 'rol') {
            self::actualizarRol((int) $parts[1]);
            return;
        }

        if ($method === 'PUT' && isset($parts[1], $parts[2]) && is_numeric($parts[1]) && ($parts[2] === 'reset-password' || $parts[2] === 'password')) {
            self::resetPassword((int) $parts[1]);
            return;
        }

        Response::json(['error' => 'Metodo no permitido'], 405);
    }

    private static function asegurarEsquemaUsuarios(): void
    {
        global $pdo;

        $stmt = $pdo->query("SHOW COLUMNS FROM usuarios LIKE 'empleado_id'");
        $columna = $stmt->fetch();

        if ($columna) {
            return;
        }

        Response::json([
            'error' => 'Falta la columna empleado_id en usuarios.',
            'accion_requerida' => [
                "ALTER TABLE usuarios ADD COLUMN empleado_id INT(11) NULL AFTER rol;",
                "ALTER TABLE usuarios ADD CONSTRAINT fk_usuarios_empleado FOREIGN KEY (empleado_id) REFERENCES empleados(id) ON DELETE SET NULL ON UPDATE CASCADE;",
                "ALTER TABLE usuarios ADD UNIQUE KEY uk_usuarios_empleado_id (empleado_id);"
            ]
        ], 500);
    }

    private static function listarEmpleados(): void
    {
        global $pdo;

        $stmt = $pdo->query("
            SELECT
                e.id,
                e.nombre,
                e.cedula,
                e.correo,
                e.estado,
                u.id AS usuario_id
            FROM empleados e
            LEFT JOIN usuarios u ON u.empleado_id = e.id
            ORDER BY e.nombre ASC
        ");

        Response::json($stmt->fetchAll());
    }

    private static function listarUsuarios(): void
    {
        global $pdo;

        $stmt = $pdo->query("
            SELECT
                u.id,
                u.nombre,
                u.email,
                u.rol,
                u.empleado_id,
                e.nombre AS empleado_nombre,
                e.cedula AS empleado_cedula
            FROM usuarios u
            LEFT JOIN empleados e ON e.id = u.empleado_id
            ORDER BY u.id DESC
        ");

        Response::json($stmt->fetchAll());
    }

    private static function crearUsuario(): void
    {
        global $pdo;

        $data = json_decode(file_get_contents('php://input'), true);

        $empleadoId = (int) ($data['empleado_id'] ?? 0);
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $password = (string) ($data['password'] ?? '');
        $rol = self::normalizarRol((string) ($data['rol'] ?? ''));

        if ($empleadoId <= 0 || $email === '' || $password === '' || $rol === '') {
            Response::json(['error' => 'empleado_id, email, password y rol son obligatorios'], 400);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::json(['error' => 'Email invalido'], 400);
        }

        if (strlen($password) < 6) {
            Response::json(['error' => 'La contraseña debe tener al menos 6 caracteres'], 400);
        }

        if (!self::rolEsValido($rol)) {
            Response::json([
                'error' => 'Rol invalido',
                'roles_permitidos' => self::$rolesPermitidos
            ], 400);
        }

        $stmtEmpleado = $pdo->prepare("
            SELECT id, nombre
            FROM empleados
            WHERE id = ?
            LIMIT 1
        ");
        $stmtEmpleado->execute([$empleadoId]);
        $empleado = $stmtEmpleado->fetch();

        if (!$empleado) {
            Response::json(['error' => 'Empleado no encontrado'], 404);
        }

        $stmtEmail = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? LIMIT 1");
        $stmtEmail->execute([$email]);
        if ($stmtEmail->fetch()) {
            Response::json(['error' => 'Ya existe un usuario con ese email'], 409);
        }

        $stmtEmpleadoUsuario = $pdo->prepare("SELECT id FROM usuarios WHERE empleado_id = ? LIMIT 1");
        $stmtEmpleadoUsuario->execute([$empleadoId]);
        if ($stmtEmpleadoUsuario->fetch()) {
            Response::json(['error' => 'Este empleado ya tiene un usuario vinculado'], 409);
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);

        $stmt = $pdo->prepare("
            INSERT INTO usuarios
                (nombre, email, clave, rol, empleado_id)
            VALUES
                (?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $empleado['nombre'],
            $email,
            $hash,
            $rol,
            $empleadoId
        ]);

        Response::json([
            'ok' => true,
            'message' => 'Usuario creado correctamente',
            'usuario' => [
                'id' => (int) $pdo->lastInsertId(),
                'nombre' => $empleado['nombre'],
                'email' => $email,
                'rol' => $rol,
                'empleado_id' => $empleadoId
            ]
        ], 201);
    }

    private static function actualizarRol(int $usuarioId): void
    {
        global $pdo;

        if ($usuarioId <= 0) {
            Response::json(['error' => 'ID de usuario invalido'], 400);
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $rol = self::normalizarRol((string) ($data['rol'] ?? ''));

        if ($rol === '') {
            Response::json(['error' => 'El rol es obligatorio'], 400);
        }

        if (!self::rolEsValido($rol)) {
            Response::json([
                'error' => 'Rol invalido',
                'roles_permitidos' => self::$rolesPermitidos
            ], 400);
        }

        $stmtUser = $pdo->prepare("SELECT id, rol FROM usuarios WHERE id = ? LIMIT 1");
        $stmtUser->execute([$usuarioId]);
        $user = $stmtUser->fetch();

        if (!$user) {
            Response::json(['error' => 'Usuario no encontrado'], 404);
        }

        $stmt = $pdo->prepare("UPDATE usuarios SET rol = ? WHERE id = ?");
        $stmt->execute([$rol, $usuarioId]);

        Response::json([
            'ok' => true,
            'message' => 'Rol actualizado correctamente',
            'usuario_id' => $usuarioId,
            'rol' => $rol
        ]);
    }

    private static function resetPassword(int $usuarioId): void
    {
        global $pdo;

        if ($usuarioId <= 0) {
            Response::json(['error' => 'ID de usuario invalido'], 400);
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $password = (string) ($data['password'] ?? '');

        if (strlen($password) < 6) {
            Response::json(['error' => 'La contrasena debe tener al menos 6 caracteres'], 400);
        }

        $stmtUser = $pdo->prepare("SELECT id FROM usuarios WHERE id = ? LIMIT 1");
        $stmtUser->execute([$usuarioId]);
        if (!$stmtUser->fetch()) {
            Response::json(['error' => 'Usuario no encontrado'], 404);
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("UPDATE usuarios SET clave = ? WHERE id = ?");
        $stmt->execute([$hash, $usuarioId]);

        Response::json([
            'ok' => true,
            'message' => 'Contrasena restablecida correctamente',
            'usuario_id' => $usuarioId
        ]);
    }

    private static function normalizarRol(string $rol): string
    {
        return strtolower(trim($rol));
    }

    private static function rolEsValido(string $rol): bool
    {
        return in_array($rol, self::$rolesPermitidos, true);
    }
}
