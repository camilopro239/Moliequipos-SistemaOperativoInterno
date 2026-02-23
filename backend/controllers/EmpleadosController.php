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

class EmpleadosController
{

    public static function handle($method, $parts)
    {

        switch ($method) {

            case 'GET':
                AuthMiddleware::check(['admin', 'gerente', 'propietario', 'recursos_humanos']);
                self::listar();
                break;

            case 'POST':
                AuthMiddleware::check(['admin', 'propietario', 'gerente', 'recursos_humanos']);
                self::crear();
                break;

            case 'PUT':
                AuthMiddleware::check(['admin', 'propietario', 'gerente', 'recursos_humanos']);
                if (!isset($parts[1])) {
                    Response::json(['error' => 'ID requerido'], 400);
                }
                self::actualizar($parts[1]);
                break;

            case 'DELETE':
                AuthMiddleware::check(['admin']);
                if (!isset($parts[1])) {
                    Response::json(['error' => 'ID requerido'], 400);
                }
                self::eliminar($parts[1]);
                break;

            default:
                Response::json(['error' => 'Método no permitido'], 405);
        }
    }


    private static function listar()
    {
        global $pdo;

        $stmt = $pdo->query("SELECT * FROM empleados ORDER BY id DESC");
        $empleados = $stmt->fetchAll();

        Response::json($empleados);
    }

    private static function crear()
    {
        global $pdo;

        $data = json_decode(file_get_contents('php://input'), true);

        $stmt = $pdo->prepare("
            INSERT INTO empleados
            (nombre, cedula, cargo, telefono, correo, fecha_ingreso, estado)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $data['nombre'],
            $data['cedula'],
            $data['cargo'] ?? null,
            $data['telefono'] ?? null,
            $data['correo'] ?? null,
            $data['fecha_ingreso'] ?? null,
            $data['estado'] ?? 'activo'
        ]);

        Response::json(['ok' => true, 'message' => 'Empleado creado']);
    }

    private static function actualizar($id)
    {
        global $pdo;

        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) {
            Response::json(['error' => 'Datos inválidos'], 400);
        }

        $stmt = $pdo->prepare("
            UPDATE empleados SET
                nombre = ?,
                cedula = ?,
                cargo = ?,
                correo = ?,
                estado = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $data['nombre'] ?? null,
            $data['cedula'] ?? null,
            $data['cargo'] ?? null,
            $data['correo'] ?? null,
            $data['estado'] ?? 'activo',
            $id
        ]);

        if ($stmt->rowCount() === 0) {
            Response::json([
                'ok' => true,
                'message' => 'Sin cambios (datos iguales)'
            ]);
        }

        Response::json([
            'ok' => true,
            'message' => 'Empleado actualizado'
        ]);
    }


    private static function eliminar($id)
    {
        global $pdo;

        $stmt = $pdo->prepare("DELETE FROM empleados WHERE id = ?");
        $stmt->execute([$id]);

        Response::json(['ok' => true, 'message' => 'Empleado eliminado']);
    }
}
