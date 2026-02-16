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

class DocumentosController
{
    private static array $tiposPermitidos = ['contrato', 'incapacidad', 'colilla', 'otro'];
    private static array $extensionesPermitidas = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx'];
    private static array $rolesPrivilegiados = ['admin', 'rrhh', 'gerente'];

    public static function handle($method, $parts)
    {
        $user = AuthMiddleware::check();
        $esPrivilegiado = self::esRolPrivilegiado($user);
        $empleadoIdUsuario = self::resolverEmpleadoIdUsuario($user);
        $usuarioId = (int) ($user['id'] ?? 0);

        if (!$esPrivilegiado && $empleadoIdUsuario <= 0) {
            Response::json(['error' => 'Tu usuario no esta vinculado a un empleado'], 403);
        }

        switch ($method) {
            case 'GET':
                if (isset($parts[1]) && $parts[1] === 'auditoria') {
                    self::listarAuditoria($esPrivilegiado);
                }

                if (isset($parts[1], $parts[2]) && $parts[2] === 'descargar') {
                    self::descargar((int) $parts[1], $esPrivilegiado, $empleadoIdUsuario, $usuarioId);
                }

                self::listar($esPrivilegiado, $empleadoIdUsuario);
                break;

            case 'POST':
                if (!$esPrivilegiado) {
                    Response::json(['error' => 'No autorizado para subir documentos'], 403);
                }
                self::subir();
                break;

            case 'DELETE':
                if (!$esPrivilegiado) {
                    Response::json(['error' => 'No autorizado para eliminar documentos'], 403);
                }
                if (!isset($parts[1])) {
                    Response::json(['error' => 'ID de documento requerido'], 400);
                }
                self::eliminar((int) $parts[1]);
                break;

            default:
                Response::json(['error' => 'Metodo no permitido'], 405);
        }
    }

    private static function esRolPrivilegiado(array $user): bool
    {
        $rol = strtolower((string) ($user['rol'] ?? ''));
        return in_array($rol, self::$rolesPrivilegiados, true);
    }

    private static function resolverEmpleadoIdUsuario(array $user): int
    {
        if (isset($user['empleado_id']) && (int) $user['empleado_id'] > 0) {
            return (int) $user['empleado_id'];
        }

        $usuarioId = (int) ($user['id'] ?? 0);
        if ($usuarioId <= 0) {
            return 0;
        }

        global $pdo;
        $stmt = $pdo->prepare("SELECT empleado_id FROM usuarios WHERE id = ? LIMIT 1");
        $stmt->execute([$usuarioId]);
        $dbUser = $stmt->fetch();

        return (int) ($dbUser['empleado_id'] ?? 0);
    }

    private static function listar(bool $esPrivilegiado, int $empleadoIdUsuario): void
    {
        global $pdo;

        $empleadoId = isset($_GET['empleado_id']) ? (int) $_GET['empleado_id'] : null;

        $sql = "
            SELECT
                d.id,
                d.empleado_id,
                d.tipo,
                d.nombre_archivo,
                d.url,
                d.periodo,
                d.fecha_subida,
                e.nombre AS empleado_nombre,
                e.cedula AS empleado_cedula
            FROM documentos_empleado d
            INNER JOIN empleados e ON e.id = d.empleado_id
        ";

        $where = [];
        $params = [];

        if (!$esPrivilegiado) {
            $where[] = "d.tipo = 'colilla'";
            $where[] = "d.empleado_id = ?";
            $params[] = $empleadoIdUsuario;
        }

        if (!empty($empleadoId)) {
            $where[] = "d.empleado_id = ?";
            $params[] = $empleadoId;
        }

        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }

        $sql .= " ORDER BY d.fecha_subida DESC, d.id DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        Response::json($stmt->fetchAll());
    }

    private static function listarAuditoria(bool $esPrivilegiado): void
    {
        global $pdo;

        if (!$esPrivilegiado) {
            Response::json(['error' => 'No autorizado para ver auditoria'], 403);
        }

        self::asegurarTablaAuditoria();

        $empleadoId = isset($_GET['empleado_id']) ? (int) $_GET['empleado_id'] : 0;
        $tipoDocumento = trim((string) ($_GET['tipo_documento'] ?? ''));
        $fechaDesde = trim((string) ($_GET['fecha_desde'] ?? ''));
        $fechaHasta = trim((string) ($_GET['fecha_hasta'] ?? ''));
        $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 100;

        if ($tipoDocumento !== '' && !in_array($tipoDocumento, self::$tiposPermitidos, true)) {
            Response::json(['error' => 'Tipo de documento invalido'], 400);
        }

        if ($fechaDesde !== '' && !self::fechaEsValida($fechaDesde)) {
            Response::json(['error' => 'fecha_desde invalida. Formato esperado: YYYY-MM-DD'], 400);
        }

        if ($fechaHasta !== '' && !self::fechaEsValida($fechaHasta)) {
            Response::json(['error' => 'fecha_hasta invalida. Formato esperado: YYYY-MM-DD'], 400);
        }

        if ($limit <= 0) {
            $limit = 100;
        }
        if ($limit > 500) {
            $limit = 500;
        }

        $sql = "
            SELECT
                a.id,
                a.usuario_id,
                a.documento_id,
                a.empleado_id,
                a.tipo_documento,
                a.ip,
                a.user_agent,
                a.fecha_descarga,
                u.nombre AS usuario_nombre,
                u.email AS usuario_email,
                u.rol AS usuario_rol,
                e.nombre AS empleado_nombre,
                e.cedula AS empleado_cedula,
                d.nombre_archivo,
                d.periodo
            FROM auditoria_descargas_documentos a
            INNER JOIN usuarios u ON u.id = a.usuario_id
            INNER JOIN empleados e ON e.id = a.empleado_id
            INNER JOIN documentos_empleado d ON d.id = a.documento_id
        ";

        $where = [];
        $params = [];

        if ($empleadoId > 0) {
            $where[] = 'a.empleado_id = ?';
            $params[] = $empleadoId;
        }

        if ($tipoDocumento !== '') {
            $where[] = 'a.tipo_documento = ?';
            $params[] = $tipoDocumento;
        }

        if ($fechaDesde !== '') {
            $where[] = 'a.fecha_descarga >= ?';
            $params[] = $fechaDesde . ' 00:00:00';
        }

        if ($fechaHasta !== '') {
            $where[] = 'a.fecha_descarga < DATE_ADD(?, INTERVAL 1 DAY)';
            $params[] = $fechaHasta;
        }

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY a.fecha_descarga DESC, a.id DESC LIMIT ' . $limit;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        Response::json($stmt->fetchAll());
    }

    private static function asegurarTablaAuditoria(): void
    {
        global $pdo;

        $stmt = $pdo->query("SHOW TABLES LIKE 'auditoria_descargas_documentos'");
        $tabla = $stmt->fetch();

        if ($tabla) {
            return;
        }

        Response::json([
            'error' => 'Falta la tabla auditoria_descargas_documentos',
            'accion_requerida' => [
                'Ejecuta el script backend/sql/002_auditoria_descargas_documentos.sql'
            ]
        ], 500);
    }

    private static function fechaEsValida(string $fecha): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            return false;
        }

        [$anio, $mes, $dia] = array_map('intval', explode('-', $fecha));
        return checkdate($mes, $dia, $anio);
    }

    private static function subir(): void
    {
        global $pdo;

        $empleadoId = (int) ($_POST['empleado_id'] ?? 0);
        $tipo = trim((string) ($_POST['tipo'] ?? ''));
        $periodo = trim((string) ($_POST['periodo'] ?? ''));

        if ($empleadoId <= 0 || $tipo === '') {
            Response::json(['error' => 'empleado_id y tipo son obligatorios'], 400);
        }

        if (!in_array($tipo, self::$tiposPermitidos, true)) {
            Response::json(['error' => 'Tipo de documento invalido'], 400);
        }

        if (!isset($_FILES['archivo']) || !is_array($_FILES['archivo'])) {
            Response::json(['error' => 'Archivo requerido'], 400);
        }

        $archivo = $_FILES['archivo'];
        if (($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            Response::json(['error' => 'Error al subir el archivo'], 400);
        }

        $stmtEmpleado = $pdo->prepare("SELECT id FROM empleados WHERE id = ? LIMIT 1");
        $stmtEmpleado->execute([$empleadoId]);
        if (!$stmtEmpleado->fetch()) {
            Response::json(['error' => 'Empleado no encontrado'], 404);
        }

        $nombreOriginal = basename((string) ($archivo['name'] ?? 'archivo'));
        $extension = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));

        if (!in_array($extension, self::$extensionesPermitidas, true)) {
            Response::json(['error' => 'Extension de archivo no permitida'], 400);
        }

        $dirDestino = __DIR__ . '/../uploads/empleados/documentos';
        if (!is_dir($dirDestino) && !mkdir($dirDestino, 0777, true) && !is_dir($dirDestino)) {
            Response::json(['error' => 'No se pudo crear el directorio de destino'], 500);
        }

        $base = pathinfo($nombreOriginal, PATHINFO_FILENAME);
        $base = preg_replace('/[^a-zA-Z0-9_-]/', '_', $base);
        $base = trim((string) $base, '_');
        if ($base === '') {
            $base = 'archivo';
        }

        try {
            $random = bin2hex(random_bytes(4));
        } catch (Exception $e) {
            $random = (string) mt_rand(100000, 999999);
        }

        $nombreGuardado = time() . '_' . $random . '_' . $base . '.' . $extension;
        $rutaAbsoluta = $dirDestino . DIRECTORY_SEPARATOR . $nombreGuardado;

        if (!move_uploaded_file((string) $archivo['tmp_name'], $rutaAbsoluta)) {
            Response::json(['error' => 'No se pudo guardar el archivo'], 500);
        }

        $url = '/uploads/empleados/documentos/' . $nombreGuardado;

        $stmt = $pdo->prepare("
            INSERT INTO documentos_empleado
                (empleado_id, tipo, nombre_archivo, url, periodo)
            VALUES
                (?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $empleadoId,
            $tipo,
            $nombreOriginal,
            $url,
            $periodo !== '' ? $periodo : null,
        ]);

        Response::json([
            'ok' => true,
            'message' => 'Documento cargado correctamente',
            'id' => (int) $pdo->lastInsertId(),
        ], 201);
    }

    private static function eliminar(int $id): void
    {
        global $pdo;

        if ($id <= 0) {
            Response::json(['error' => 'ID de documento invalido'], 400);
        }

        $stmt = $pdo->prepare("SELECT id, url FROM documentos_empleado WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $documento = $stmt->fetch();

        if (!$documento) {
            Response::json(['error' => 'Documento no encontrado'], 404);
        }

        $stmtDelete = $pdo->prepare("DELETE FROM documentos_empleado WHERE id = ?");
        $stmtDelete->execute([$id]);

        $rutaRelativa = parse_url((string) ($documento['url'] ?? ''), PHP_URL_PATH);
        if (is_string($rutaRelativa) && str_starts_with($rutaRelativa, '/uploads/')) {
            $baseBackend = realpath(__DIR__ . '/..');
            if ($baseBackend !== false) {
                $rutaArchivo = $baseBackend . str_replace('/', DIRECTORY_SEPARATOR, $rutaRelativa);
                if (is_file($rutaArchivo)) {
                    @unlink($rutaArchivo);
                }
            }
        }

        Response::json([
            'ok' => true,
            'message' => 'Documento eliminado',
        ]);
    }

    private static function descargar(int $id, bool $esPrivilegiado, int $empleadoIdUsuario, int $usuarioId): void
    {
        global $pdo;

        if ($id <= 0) {
            Response::json(['error' => 'ID de documento invalido'], 400);
        }

        $stmt = $pdo->prepare("
            SELECT id, empleado_id, tipo, nombre_archivo, url
            FROM documentos_empleado
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$id]);
        $documento = $stmt->fetch();

        if (!$documento) {
            Response::json(['error' => 'Documento no encontrado'], 404);
        }

        if (!$esPrivilegiado) {
            if (($documento['tipo'] ?? '') !== 'colilla') {
                Response::json(['error' => 'Solo puedes descargar colillas de pago'], 403);
            }

            if ((int) ($documento['empleado_id'] ?? 0) !== $empleadoIdUsuario) {
                Response::json(['error' => 'Solo puedes descargar tus propias colillas'], 403);
            }
        }

        self::registrarDescarga(
            $usuarioId,
            (int) $documento['id'],
            (int) $documento['empleado_id'],
            (string) $documento['tipo']
        );

        $rutaRelativa = parse_url((string) ($documento['url'] ?? ''), PHP_URL_PATH);
        if (!is_string($rutaRelativa) || !str_starts_with($rutaRelativa, '/uploads/')) {
            Response::json(['error' => 'Ruta de archivo invalida'], 400);
        }

        $baseBackend = realpath(__DIR__ . '/..');
        if ($baseBackend === false) {
            Response::json(['error' => 'No se encontro la ruta base del backend'], 500);
        }

        $rutaArchivo = $baseBackend . str_replace('/', DIRECTORY_SEPARATOR, $rutaRelativa);
        if (!is_file($rutaArchivo)) {
            Response::json(['error' => 'Archivo no encontrado en disco'], 404);
        }

        $mimeType = mime_content_type($rutaArchivo) ?: 'application/octet-stream';
        $nombreArchivo = basename((string) ($documento['nombre_archivo'] ?? 'documento'));

        header('Content-Description: File Transfer');
        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
        header('Content-Length: ' . filesize($rutaArchivo));
        header('Cache-Control: no-cache, must-revalidate');

        readfile($rutaArchivo);
        exit;
    }

    private static function registrarDescarga(int $usuarioId, int $documentoId, int $empleadoId, string $tipo): void
    {
        global $pdo;

        if ($usuarioId <= 0 || $documentoId <= 0) {
            return;
        }

        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        $userAgent = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);

        try {
            $stmt = $pdo->prepare("
                INSERT INTO auditoria_descargas_documentos
                    (usuario_id, documento_id, empleado_id, tipo_documento, ip, user_agent)
                VALUES
                    (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$usuarioId, $documentoId, $empleadoId, $tipo, $ip, $userAgent]);
        } catch (Throwable $e) {
            // Si no existe la tabla de auditoria, no bloquea la descarga.
        }
    }
}
