# Backend Detallado

Este documento explica todo el backend del proyecto de forma practica: que hace cada archivo, como fluye una peticion y donde vive cada regla de negocio.

## 1. Vision general

El backend esta en `backend/` y usa PHP sin framework. La arquitectura es simple:

- `index.php` funciona como front controller y router.
- `controllers/` contiene la logica por modulo.
- `helpers/` centraliza respuestas JSON, JWT y middleware de autorizacion.
- `config/db.php` crea la conexion PDO.
- `sql/` tiene scripts de evolucion de base de datos.
- `uploads/` guarda archivos cargados (documentos por empleado).

La idea del diseno es mantener bajo acoplamiento y facilitar cambios rapidos.

## 2. Flujo de una peticion HTTP

### Paso 1: Entrada por `backend/index.php`

Archivo: `backend/index.php`

- Define CORS para `http://localhost:4200`.
- Responde `OPTIONS` con `200` para preflight.
- Carga `config/db.php` y `helpers/Response.php`.
- Parsea URL y metodo.
- Enruta por primer segmento:
  - `auth` -> `AuthController`
  - `empleados` -> `EmpleadosController`
  - `documentos` -> `DocumentosController`
  - `usuarios` -> `UsuariosController`
- Si no coincide ningun modulo, devuelve 404 JSON.

### Paso 2: Controlador correspondiente

Cada controlador valida:

- Metodo HTTP.
- Permisos por rol (cuando aplica).
- Parametros requeridos.
- Integridad basica antes de tocar base de datos.

### Paso 3: Respuesta estandarizada

`helpers/Response.php` retorna JSON y corta ejecucion (`exit`), para evitar salidas mezcladas.

## 3. Configuracion de base de datos

Archivo: `backend/config/db.php`

- Declara host, base, usuario y password.
- Crea `PDO` con:
  - `ERRMODE_EXCEPTION` para detectar errores SQL.
  - `FETCH_ASSOC` por defecto.
- En caso de fallo, responde 500 con error de conexion.

Observacion:

- El archivo hoy contiene credenciales locales directas, correcto para entorno local. Para produccion conviene mover estas variables a entorno.

## 4. Helpers y seguridad

### 4.1 `backend/helpers/Response.php`

Clase `Response`:

- `json($data, $status = 200)`
  - Define status HTTP.
  - Define `Content-Type: application/json`.
  - Hace `echo json_encode`.
  - Finaliza ejecucion.

Es el punto unico de salida JSON.

### 4.2 `backend/helpers/JWT.php`

Clase `JWT`:

- Usa firma HMAC SHA256 con secreto interno.
- `generate(array $payload, int $expSeconds = 3600): string`
  - Construye header JWT.
  - Agrega expiracion (`exp`) al payload.
  - Firma y devuelve token.
- `validate(string $token): ?array`
  - Separa token en 3 partes.
  - Recalcula firma esperada.
  - Compara con `hash_equals`.
  - Verifica expiracion.
  - Devuelve payload o `null`.

Observaciones tecnicas:

- Usa `base64_encode` simple, no base64url estandar JWT.
- Para el sistema actual funciona porque frontend y backend usan misma implementacion.

### 4.3 `backend/helpers/AuthMiddleware.php`

Clase `AuthMiddleware`:

- `check(array $rolesPermitidos = []): array`
  - Permite `OPTIONS` (preflight).
  - Lee header `Authorization` con 3 estrategias:
    - `getallheaders()`
    - `$_SERVER['HTTP_AUTHORIZATION']`
    - `$_SERVER['REDIRECT_HTTP_AUTHORIZATION']`
  - Valida que exista token.
  - Valida formato `Bearer ...`.
  - Valida JWT.
  - Si recibe `rolesPermitidos`, valida rol.
  - Devuelve payload autenticado.

Este helper es la base real del control de acceso del backend.

## 5. Controladores

## 5.1 `backend/controllers/AuthController.php`

Responsabilidad: autenticacion.

Rutas:

- `POST /auth/login`

Metodos:

- `handle(string $method, array $parts): void`
  - Discrimina ruta y metodo.
  - Si no coincide, responde 404 con detalle debug.

- `login(): void`
  - Lee `email` y `password` del body JSON.
  - Valida campos obligatorios.
  - Busca usuario en tabla `usuarios`.
  - Compara password con `password_verify`.
  - Genera JWT con:
    - `id`
    - `rol`
    - `name`
    - `empleado_id`
  - Devuelve:
    - `token`
    - objeto `user` (datos basicos de sesion)

Resultado funcional:

- El frontend guarda `token` y `user` en `localStorage`.

## 5.2 `backend/controllers/EmpleadosController.php`

Responsabilidad: CRUD de empleados.

Permisos:

- `GET /empleados`: `admin`, `rrhh`
- `POST /empleados`: `admin`
- `PUT /empleados/{id}`: `admin`, `rrhh`
- `DELETE /empleados/{id}`: `admin`

Metodos:

- `handle($method, $parts)`
  - Router interno por metodo HTTP.

- `listar()`
  - `SELECT * FROM empleados ORDER BY id DESC`.

- `crear()`
  - Inserta empleado con nombre, cedula, cargo, telefono, correo, fecha_ingreso y estado.

- `actualizar($id)`
  - Actualiza campos principales.
  - Si `rowCount` es 0, devuelve "Sin cambios".

- `eliminar($id)`
  - Borra empleado por id.

Notas funcionales:

- No incluye paginacion todavia.
- No hay validaciones profundas de formato de cedula/telefono.

## 5.3 `backend/controllers/DocumentosController.php`

Responsabilidad: listar, subir, eliminar, descargar documentos y consultar auditoria.

Constantes clave:

- `tiposPermitidos`: `contrato`, `incapacidad`, `colilla`, `otro`.
- `extensionesPermitidas`: `pdf`, `jpg`, `jpeg`, `png`, `doc`, `docx`, `xls`, `xlsx`.
- `rolesPrivilegiados`: `admin`, `rrhh`, `gerente`.

Regla principal de seguridad:

- Usuarios privilegiados ven y gestionan todo.
- Usuarios no privilegiados:
  - solo ven `colilla`.
  - solo de su propio `empleado_id`.
  - no pueden subir ni eliminar.

Metodos:

- `handle($method, $parts)`
  - Autentica usuario.
  - Calcula si es privilegiado.
  - Resuelve `empleado_id` vinculado al usuario.
  - Enruta:
    - `GET /documentos` -> `listar`
    - `GET /documentos/auditoria` -> `listarAuditoria`
    - `GET /documentos/{id}/descargar` -> `descargar`
    - `POST /documentos` -> `subir` (solo privilegiado)
    - `DELETE /documentos/{id}` -> `eliminar` (solo privilegiado)

- `esRolPrivilegiado(array $user): bool`
  - Evalua rol contra lista privilegiada.

- `resolverEmpleadoIdUsuario(array $user): int`
  - Usa `empleado_id` del token si existe.
  - Si no, consulta tabla `usuarios`.

- `listar(bool $esPrivilegiado, int $empleadoIdUsuario): void`
  - Lista documentos con join a empleados.
  - Si no privilegiado, fuerza filtro de seguridad:
    - `d.tipo = 'colilla'`
    - `d.empleado_id = empleado del usuario`
  - Permite filtro opcional `empleado_id` por query string.

- `listarAuditoria(bool $esPrivilegiado): void`
  - Solo roles privilegiados.
  - Valida existencia de tabla de auditoria.
  - Valida filtros:
    - `empleado_id`
    - `tipo_documento`
    - `fecha_desde`
    - `fecha_hasta`
    - `limit` (max 500)
  - Devuelve listado con joins a `usuarios`, `empleados`, `documentos_empleado`.

- `asegurarTablaAuditoria(): void`
  - Si no existe tabla, devuelve error explicando script a ejecutar.

- `fechaEsValida(string $fecha): bool`
  - Valida formato `YYYY-MM-DD` y fecha real.

- `subir(): void`
  - Valida `empleado_id`, `tipo` y archivo.
  - Verifica empleado existente.
  - Valida extension.
  - Crea directorio destino si no existe.
  - Sanitiza nombre de archivo.
  - Genera nombre unico (`timestamp + random + base`).
  - Mueve archivo a disco.
  - Inserta registro en `documentos_empleado`.
  - Retorna 201 con id nuevo.

- `eliminar(int $id): void`
  - Valida id.
  - Busca documento.
  - Elimina registro en DB.
  - Intenta eliminar archivo fisico de `uploads`.

- `descargar(int $id, bool $esPrivilegiado, int $empleadoIdUsuario, int $usuarioId): void`
  - Valida existencia de documento.
  - Aplica reglas de acceso para no privilegiados.
  - Registra descarga en auditoria.
  - Resuelve ruta segura del archivo.
  - Envia headers de descarga.
  - Hace `readfile` y termina.

- `registrarDescarga(int $usuarioId, int $documentoId, int $empleadoId, string $tipo): void`
  - Inserta evento en `auditoria_descargas_documentos`.
  - Si falla (por ejemplo tabla inexistente), no bloquea descarga.

## 5.4 `backend/controllers/UsuariosController.php`

Responsabilidad: administracion de usuarios.

Permisos globales:

- Todo el controlador exige `admin` o `gerente`.

Rutas:

- `GET /usuarios`
- `GET /usuarios/empleados`
- `POST /usuarios`
- `PUT /usuarios/{id}/rol`
- `PUT /usuarios/{id}/reset-password` (tambien acepta `/password`)

Metodos:

- `handle(string $method, array $parts): void`
  - Aplica middleware de rol.
  - Verifica esquema (`empleado_id` en `usuarios`).
  - Enruta por metodo y segmentos.

- `asegurarEsquemaUsuarios(): void`
  - Si falta columna `empleado_id`, devuelve SQL requerido.

- `listarEmpleados(): void`
  - Lista empleados con `LEFT JOIN` a usuarios para saber si ya tienen cuenta.

- `listarUsuarios(): void`
  - Lista usuarios con datos de empleado vinculado.

- `crearUsuario(): void`
  - Valida `empleado_id`, `email`, `password`, `rol`.
  - Verifica formato email.
  - Exige password minimo 6.
  - Valida patron de rol (`^[a-z0-9_]+$`).
  - Verifica que empleado exista.
  - Verifica email unico.
  - Verifica que empleado no tenga usuario previo.
  - Hashea password con bcrypt.
  - Inserta usuario y responde 201.

- `actualizarRol(int $usuarioId): void`
  - Valida id y rol.
  - Verifica usuario.
  - Actualiza rol.

- `resetPassword(int $usuarioId): void`
  - Valida id.
  - Exige password minimo 6.
  - Verifica usuario.
  - Reemplaza hash de contrasena.

- `normalizarRol(string $rol): string`
  - Trim + lowercase.

- `rolEsValido(string $rol): bool`
  - Solo letras minusculas, numeros y guion bajo.

## 6. Modelo

Archivo: `backend/models/Usuario.php`

- `findByEmail(PDO $pdo, string $email): ?array`
  - Busca usuario por email con campos base.

Nota:

- En el estado actual del proyecto, el login consulta DB directo en `AuthController`; este modelo no es obligatorio para el flujo actual.

## 7. Scripts SQL de evolucion

Archivo: `backend/sql/001_usuarios_empleado.sql`

- Agrega columna `empleado_id` a `usuarios`.
- Crea FK `usuarios.empleado_id -> empleados.id`.
- Agrega indice unico para mantener relacion 1:1 usuario-empleado.

Archivo: `backend/sql/002_auditoria_descargas_documentos.sql`

- Crea tabla `auditoria_descargas_documentos` con:
  - ids de usuario, documento, empleado
  - tipo de documento
  - ip
  - user_agent
  - fecha_descarga
- Crea claves foraneas y indices para filtros.

## 8. Archivo auxiliar

Archivo: `backend/tempo.php`

- Script temporal para generar un hash bcrypt de prueba.
- No participa del flujo principal de la API.

## 9. Mapa de endpoints y permisos

Auth:

- `POST /auth/login`: publico.

Empleados:

- `GET /empleados`: admin, rrhh.
- `POST /empleados`: admin.
- `PUT /empleados/{id}`: admin, rrhh.
- `DELETE /empleados/{id}`: admin.

Documentos:

- `GET /documentos`: autenticado (con filtros por rol).
- `POST /documentos`: admin, rrhh, gerente.
- `DELETE /documentos/{id}`: admin, rrhh, gerente.
- `GET /documentos/{id}/descargar`: autenticado (con validacion estricta por rol).
- `GET /documentos/auditoria`: admin, rrhh, gerente.

Usuarios:

- `GET /usuarios`: admin, gerente.
- `GET /usuarios/empleados`: admin, gerente.
- `POST /usuarios`: admin, gerente.
- `PUT /usuarios/{id}/rol`: admin, gerente.
- `PUT /usuarios/{id}/reset-password`: admin, gerente.

## 10. Riesgos tecnicos actuales (con enfoque didactico)

- CORS esta fijo a localhost y no usa variables de entorno.
- Secreto JWT esta hardcoded.
- No hay paginacion backend en listados masivos.
- No hay rate limiting.
- Existe algo de texto con codificacion rota en comentarios o mensajes (problema de encoding), no afecta logica pero si mantenimiento.

## 11. Recomendaciones de mantenimiento

- Mantener reglas de permisos en backend como fuente de verdad.
- Cuando agregues un nuevo rol:
  - actualizar listas de rol en backend y frontend.
  - actualizar esta documentacion.
- Cuando crees un nuevo endpoint:
  - documentar request, response, permisos y validaciones.
- No confiar en controles de frontend para seguridad: toda regla sensible debe existir en backend (como ya pasa en modulo de documentos).
