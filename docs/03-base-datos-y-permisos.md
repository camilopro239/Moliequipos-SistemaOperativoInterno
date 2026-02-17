# Base de Datos y Permisos

Este documento aterriza la relacion entre tablas, reglas de negocio y permisos por rol.

## 1. Tablas principales

## 1.1 `empleados`

Campos usados por el sistema:

- `id` (PK)
- `nombre`
- `cedula`
- `cargo`
- `telefono`
- `correo`
- `fecha_ingreso`
- `fecha_vencimiento_contrato`
- `estado` (`activo` o `inactivo`)

Uso funcional:

- Es la entidad humana principal.
- Se usa para:
  - gestion de personal.
  - vincular cuentas de acceso (`usuarios.empleado_id`).
  - asociar documentos laborales.

## 1.2 `usuarios`

Campos esperados/consumidos por backend:

- `id` (PK)
- `nombre`
- `email`
- `clave` (hash bcrypt)
- `rol`
- `empleado_id` (nullable, unico, FK a empleados)

Uso funcional:

- Controla autenticacion y autorizacion por rol.
- `empleado_id` habilita la regla "usuario no privilegiado solo ve sus colillas".

## 1.3 `documentos_empleado`

Campos:

- `id` (PK)
- `empleado_id` (FK a empleados)
- `tipo` (`contrato`, `incapacidad`, `colilla`, `otro`)
- `nombre_archivo`
- `url`
- `periodo` (nullable, usado principalmente para colillas)
- `fecha_subida`

Uso funcional:

- Inventario documental por empleado.
- La seguridad de consulta/descarga depende del tipo y empleado vinculado.

## 1.4 `auditoria_descargas_documentos`

Creada por script `backend/sql/002_auditoria_descargas_documentos.sql`.

Campos:

- `id` (PK)
- `usuario_id` (FK a usuarios)
- `documento_id` (FK a documentos_empleado)
- `empleado_id` (FK a empleados)
- `tipo_documento`
- `ip`
- `user_agent`
- `fecha_descarga`

Uso funcional:

- Trazabilidad de descargas.
- Fuente del panel de auditoria.

## 2. Relaciones clave

Relaciones activas:

- `usuarios.empleado_id -> empleados.id`
  - cardinalidad practica 1:1 (por indice unico).
- `documentos_empleado.empleado_id -> empleados.id`
  - 1:N (un empleado puede tener muchos documentos).
- `auditoria_descargas_documentos.usuario_id -> usuarios.id`
  - N:1.
- `auditoria_descargas_documentos.documento_id -> documentos_empleado.id`
  - N:1.
- `auditoria_descargas_documentos.empleado_id -> empleados.id`
  - N:1.

## 3. Scripts SQL y cuando ejecutarlos

Archivo: `backend/sql/001_usuarios_empleado.sql`

Ejecutar cuando:

- la tabla `usuarios` aun no tiene `empleado_id`.

Que agrega:

- columna `empleado_id`.
- FK a `empleados`.
- indice unico para impedir dos usuarios sobre el mismo empleado.

Archivo: `backend/sql/002_auditoria_descargas_documentos.sql`

Ejecutar cuando:

- aun no existe tabla de auditoria.

Que agrega:

- tabla de eventos de descarga + indices + FKs.

## 4. Matriz de permisos por rol

Roles privilegiados:

- `admin`
- `rrhh`
- `gerente`

Roles no privilegiados:

- cualquier otro valor (`operador`, `operador_patio`, etc).

Permisos actuales:

- Login:
  - todos los usuarios con credenciales validas.
- Modulo empleados:
  - ver y editar: `admin`, `rrhh`.
  - crear y eliminar: `admin`.
- Modulo usuarios:
  - acceso completo: `admin`, `gerente`.
- Modulo documentos:
  - privilegiados: ven todo, suben, eliminan, descargan.
  - no privilegiados: solo ven/descargan `colilla` de su propio `empleado_id`.
- Auditoria de descargas:
  - `admin`, `rrhh`, `gerente`.

## 5. Reglas de negocio criticas

Regla 1: Vinculacion usuario-empleado

- Un empleado puede tener como maximo un usuario.
- Si el usuario no privilegiado no tiene `empleado_id`, se bloquea acceso a documentos.

Regla 2: Restriccion de documentos para no privilegiados

- Solo `tipo = colilla`.
- Solo si `documentos_empleado.empleado_id` coincide con usuario logueado.

Regla 3: Auditoria de descarga

- Cada descarga valida intenta registrar:
  - quien descargo
  - que descargo
  - de quien era el documento
  - cuando y desde donde

## 6. Consultas utiles para soporte

Ver usuarios sin empleado vinculado:

```sql
SELECT id, nombre, email, rol
FROM usuarios
WHERE empleado_id IS NULL;
```

Ver empleados sin usuario:

```sql
SELECT e.id, e.nombre, e.cedula
FROM empleados e
LEFT JOIN usuarios u ON u.empleado_id = e.id
WHERE u.id IS NULL;
```

Ver ultimas descargas:

```sql
SELECT
  a.fecha_descarga,
  u.nombre AS usuario,
  e.nombre AS empleado,
  a.tipo_documento,
  d.nombre_archivo
FROM auditoria_descargas_documentos a
JOIN usuarios u ON u.id = a.usuario_id
JOIN empleados e ON e.id = a.empleado_id
JOIN documentos_empleado d ON d.id = a.documento_id
ORDER BY a.fecha_descarga DESC
LIMIT 50;
```

## 7. Checklist de integridad antes de despliegue

- Existe columna `usuarios.empleado_id`.
- Existe tabla `auditoria_descargas_documentos`.
- Todos los usuarios no privilegiados tienen `empleado_id` asignado.
- Los roles estan escritos en minusculas para evitar ambiguedad.
- Se valida que documentos sensibles no queden expuestos por URL directa sin token.

