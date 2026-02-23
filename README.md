# Moliequipos - Sistema Operativo Interno

Sistema web interno para la gestion de empleados, usuarios y documentos laborales de Moliequipos.

## Tecnologias

- Frontend: Angular 18 (standalone components)
- Backend: PHP (controladores simples + JWT)
- Base de datos: MySQL

## Modulos actuales

- Login con JWT
- Empleados
  - Crear, listar, editar y eliminar
  - Filtro por nombre/cedula/cargo
- Documentos
  - Carga de documentos por empleado (`contrato`, `incapacidad`, `colilla`, `otro`)
  - Descarga de archivos
  - Eliminacion (roles privilegiados)
- Usuarios
  - Crear usuario vinculado a empleado
  - Editar rol
  - Reset de contrasena (modal)
- Auditoria de descargas
  - Registro automatico de descargas
  - Panel con filtros (empleado, tipo, fechas, limite)
- Dashboard en layout
  - Resumen rapido por rol
  - Actividad reciente
  - Accesos rapidos

## Roles y permisos

- `admin`, `gerente`, `rrhh`
  - Acceso completo a documentos y auditoria
- Otros roles
  - Solo pueden ver/descargar sus propias colillas

## Estructura del proyecto

```text
backend/
  config/
  controllers/
  helpers/
  models/
  sql/
  uploads/
frontend/
  src/app/
docs/
```

## Documentacion detallada

Para revisar el sistema completo de forma tecnica y ordenada:

- `docs/00-indice-documentacion.md`
- `docs/01-backend-detallado.md`
- `docs/02-frontend-detallado.md`
- `docs/03-base-datos-y-permisos.md`

## Configuracion local

### 1) Base de datos

Crear la base de datos `chatarreria` y ejecutar los scripts necesarios (segun tu estado actual):

- `backend/sql/001_usuarios_empleado.sql`
- `backend/sql/002_auditoria_descargas_documentos.sql`

### 2) Backend

Configura entorno en `backend/.env` (puedes partir de `backend/.env.example`):

```env
APP_ENV=local
DB_HOST=127.0.0.1
DB_NAME=chatarreria
DB_USER=root
DB_PASS=
JWT_SECRET=tu_clave_larga_y_aleatoria
```

Iniciar servidor PHP desde la carpeta `backend`:

```bash
php -S localhost:8000
```

### 3) Frontend

Configura URL base de API en:

- `frontend/src/environments/environment.ts` (desarrollo)
- `frontend/src/environments/environment.prod.ts` (produccion)

Desde `frontend`:

```bash
npm install
npm run start
```

Aplicacion en: `http://localhost:4200`

### 4) Build para GitHub Pages

Si publicas el frontend en GitHub Pages (project page), usa:

```bash
cd frontend
npm run build:gh
```

Ese script genera el build con `base-href` y `deploy-url` del repo para que los assets (por ejemplo logos) carguen correctamente en produccion.

## Endpoints principales

- Auth
  - `POST /auth/login`
- Empleados
  - `GET /empleados`
  - `POST /empleados`
  - `PUT /empleados/{id}`
  - `DELETE /empleados/{id}`
- Documentos
  - `GET /documentos`
  - `POST /documentos`
  - `DELETE /documentos/{id}`
  - `GET /documentos/{id}/descargar`
  - `GET /documentos/auditoria`
- Usuarios
  - `GET /usuarios`
  - `GET /usuarios/empleados`
  - `POST /usuarios`
  - `PUT /usuarios/{id}/rol`
  - `PUT /usuarios/{id}/reset-password`

## Notas

- `backend/uploads/` esta ignorado en git para no versionar archivos subidos.
- El frontend ya incluye interceptor JWT para enviar `Authorization: Bearer <token>`.

## Estado del repo

Version inicial etiquetada: `v1.0.0`
