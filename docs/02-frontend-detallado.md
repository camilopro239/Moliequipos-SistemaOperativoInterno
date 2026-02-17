# Frontend Detallado

Este documento explica la arquitectura frontend en Angular, los flujos de cada pantalla y el papel de cada servicio/componente.

## 1. Vision general

El frontend esta en `frontend/` y usa Angular 18 con componentes standalone.

Piezas principales:

- Router con lazy loading de componentes.
- Guards para autenticacion y roles.
- Interceptor JWT para adjuntar token.
- Servicios HTTP por modulo.
- Layout principal con dashboard + menu dinamico por rol.

## 2. Arranque de la aplicacion

Archivo: `frontend/src/main.ts`

- Arranca la app con `bootstrapApplication(AppComponent, appConfig)`.

Archivo: `frontend/src/app/app.config.ts`

- Registra:
  - `provideRouter(routes)`
  - `provideHttpClient(withInterceptors([jwtInterceptor]))`

Archivo: `frontend/src/app/app.component.ts`

- Componente raiz.
- Renderiza:
  - `<router-outlet>` para pantalla activa.
  - `<app-toast>` para notificaciones globales.

Archivo: `frontend/src/app/app.component.html`

- Solo contiene outlet y toast global.

## 3. Rutas y control de acceso

Archivo: `frontend/src/app/app.routes.ts`

Rutas:

- `/login`: publica.
- `/empleados`: `authGuard + roleGuard` roles `admin`, `rrhh`.
- `/documentos`: `authGuard`.
- `/usuarios`: `authGuard + roleGuard` roles `admin`, `gerente`.
- `/auditoria-descargas`: `authGuard + roleGuard` roles `admin`, `rrhh`, `gerente`.
- `/` (root): `authGuard`, carga layout/dashboard.

Diseno aplicado:

- Las rutas sensibles siempre exigen sesion.
- Los roles restringidos se validan en frontend para UX, y en backend para seguridad real.

## 4. Guards e interceptor

## 4.1 `auth.guard.ts`

- Si no hay `user` en `localStorage`, redirige a `/login`.
- En SSR retorna `true` para evitar romper render server-side.

## 4.2 `role.guard.ts`

- Lee `user` de `localStorage`.
- Lee roles permitidos desde metadata de ruta (`route.data['roles']`).
- Si el rol no esta permitido, redirige a `/documentos`.

## 4.3 `jwt.interceptor.ts`

- Si no esta en navegador (SSR), no inyecta token.
- Lee `token` del `localStorage`.
- Si hay token, clona request con header `Authorization: Bearer <token>`.
- Incluye una condicion puntual: si no hay token y URL incluye `/empleados`, reemplaza URL por `about:blank` para evitar llamada protegida.

Nota tecnica:

- Esa condicion especial funciona como defensa adicional, aunque el guard ya evita la navegacion sin sesion.

## 5. Servicios de datos

## 5.1 `auth.service.ts`

- Base URL: `http://localhost:8000/auth`.
- `login(email, password)`
  - POST a `/auth/login`.
  - Guarda `token` y `user` en `localStorage`.
- `logout()`
  - Limpia `localStorage`.
- `isAuthenticated()`
  - Retorna true si hay token en navegador.

## 5.2 `empleados.service.ts`

- Base URL: `http://localhost:8000/empleados`.
- Metodos:
  - `getEmpleados()`
  - `crearEmpleado(data)`
  - `actualizarEmpleado(id, data)`
  - `eliminarEmpleado(id)`

## 5.3 `documentos.service.ts`

- Base URL: `http://localhost:8000/documentos`.
- Metodos:
  - `getDocumentos(empleadoId?)`
  - `getAuditoriaDescargas(filtros?)`
  - `subirDocumento(formData)`
  - `eliminarDocumento(id)`
  - `descargarDocumento(id)` con `responseType: 'blob'`

## 5.4 `usuarios.service.ts`

- Base URL: `http://localhost:8000/usuarios`.
- Metodos:
  - `getUsuarios()`
  - `getEmpleadosParaVincular()`
  - `crearUsuario(data)`
  - `actualizarRol(usuarioId, rol)`
  - `resetPassword(usuarioId, password)`

## 6. Notificaciones globales (toast)

Archivo: `frontend/src/app/core/services/toast.service.ts`

- Maneja estado de toast con `BehaviorSubject`.
- API:
  - `success`
  - `error`
  - `warning`
  - `info`
  - `clear`
- Cada toast se autocierra por `duration`.

Archivo: `frontend/src/app/core/components/toast/toast.component.ts`

- Se subscribe a `toast$`.
- Renderiza toast activo si existe.

Archivo: `frontend/src/app/core/components/toast/toast.component.html`

- Plantilla simple con clase por tipo.

Archivo: `frontend/src/app/core/components/toast/toast.component.scss`

- Estilos por tipo (success/error/warning/info) + animacion de entrada/salida.

## 7. Pantallas y componentes

## 7.1 Login

Archivos:

- `frontend/src/app/auth/pages/login/login.component.ts`
- `frontend/src/app/auth/pages/login/login.component.html`
- `frontend/src/app/auth/pages/login/login.component.scss`

Comportamiento:

- Formulario reactivo con `email` y `password`.
- Validaciones de requerido + email.
- En submit:
  - llama `AuthService.login`.
  - en exito, navega a `/`.
  - en error, muestra mensaje de credenciales incorrectas.

Observacion:

- Actualmente conserva logs de consola en submit; util para debug local, pero no recomendado para produccion.

## 7.2 Layout principal + dashboard

Archivos:

- `frontend/src/app/layout/layout.component.ts`
- `frontend/src/app/layout/layout.component.html`
- `frontend/src/app/layout/layout.component.scss`

Responsabilidad:

- Envolver todas las paginas autenticadas.
- Mostrar sidebar, header y dashboard inicial.
- Ajustar menu segun rol.

Logica clave:

- Lee usuario desde `localStorage`.
- Determina permisos visuales:
  - `puedeVerEmpleados`
  - `puedeGestionarUsuarios`
  - `puedeVerAuditoria`
- `cargarDashboard()` usa `forkJoin` para pedir:
  - empleados (si rol lo permite)
  - usuarios (si rol lo permite)
  - documentos (siempre)
  - auditoria (si rol lo permite)
- Calcula metricas:
  - total documentos visibles
  - total colillas visibles
  - total empleados
  - total usuarios
  - total descargas recientes
- `logout()` limpia sesion y redirige a login.

## 7.3 Empleados

Archivos:

- `frontend/src/app/pages/empleados/empleados.component.ts`
- `frontend/src/app/pages/empleados/empleados.component.html`
- `frontend/src/app/pages/empleados/empleados.component.scss`

Responsabilidad:

- Gestion completa de empleados (crear, listar, editar, eliminar).
- Filtro local por texto y estado.
- Acceso rapido a documentos por empleado.

Logica clave:

- Formulario reactivo para modal de crear/editar.
- `cargarEmpleados()` trae lista desde API.
- Getter `empleadosFiltrados` aplica:
  - busqueda por nombre, cedula, cargo y correo
  - normalizacion sin acentos ni mayusculas.
- `verDocumentos(empleado)` navega a `/documentos?empleado_id=<id>`.
- `confirmarEliminar` usa confirm nativo.
- `submit()` decide crear o actualizar segun `modoEdicion`.

## 7.4 Documentos

Archivos:

- `frontend/src/app/pages/documentos/documentos.component.ts`
- `frontend/src/app/pages/documentos/documentos.component.html`
- `frontend/src/app/pages/documentos/documentos.component.scss`

Responsabilidad:

- Cargar documentos por empleado.
- Descargar documentos.
- Subir/eliminar documentos para roles privilegiados.

Logica clave:

- `inicializarPermisos()` define `esPrivilegiado` por rol en localStorage (`admin`, `rrhh`, `gerente`).
- Si usuario no privilegiado:
  - no se muestra panel de subida.
  - no se muestran controles de eliminacion.
  - backend ya limita a sus colillas.
- Lee query param `empleado_id` para precargar filtro desde modulo empleados.
- `subirDocumento()` valida form + archivo y envia `FormData`.
- `descargarDocumento()` recibe blob, crea URL temporal y dispara descarga local.
- `eliminarDocumento()` confirma y elimina.

## 7.5 Usuarios

Archivos:

- `frontend/src/app/pages/usuarios/usuarios.component.ts`
- `frontend/src/app/pages/usuarios/usuarios.component.html`
- `frontend/src/app/pages/usuarios/usuarios.component.scss`

Responsabilidad:

- Crear usuario vinculado a empleado.
- Editar rol.
- Resetear password.

Logica clave:

- `cargarDatos()` trae empleados y usuarios.
- `empleadosDisponibles` evita duplicar vinculaciones.
- `crearUsuario()` normaliza email y rol antes de enviar.
- `actualizarRol(user)` evita request si no hay cambios.
- Modal de reset password:
  - valida minimo 6.
  - valida coincidencia password/confirm.
  - llama endpoint de reset.

## 7.6 Auditoria de descargas

Archivos:

- `frontend/src/app/pages/auditoria-descargas/auditoria-descargas.component.ts`
- `frontend/src/app/pages/auditoria-descargas/auditoria-descargas.component.html`
- `frontend/src/app/pages/auditoria-descargas/auditoria-descargas.component.scss`

Responsabilidad:

- Consultar trazabilidad de descargas por filtros.

Filtros disponibles:

- empleado
- tipo documento
- fecha desde
- fecha hasta
- limite

Logica clave:

- `cargarEmpleados()` alimenta filtro de empleado.
- `cargarAuditoria()` arma objeto de filtros y consulta API.
- `limpiarFiltros()` restablece estado y recarga.

## 8. Estilos globales y tema visual

Archivo: `frontend/src/styles.scss`

- Define variables CSS globales:
  - colores de marca
  - tipografias
  - sombras
  - colores de estado
- Incluye fuentes:
  - Barlow
  - Rajdhani
- Define base global:
  - reset parcial de box model
  - fondo general con gradiente/patron
  - estilos de foco accesible

Resultado:

- La app mantiene identidad visual consistente en login, layout y modulos.

## 9. SSR y configuracion server

Archivo: `frontend/src/app/app.config.server.ts`

- Combina `appConfig` con `provideServerRendering()`.
- Permite usar modo SSR sin reescribir configuracion principal.

## 10. Pruebas incluidas

Archivo: `frontend/src/app/app.component.spec.ts`

- Pruebas base generadas por Angular:
  - crea componente
  - verifica `title = 'frontend'`
  - intenta render de texto hello (esta prueba puede no reflejar plantilla actual)

Nota:

- El proyecto hoy esta orientado a flujo funcional; la cobertura de tests todavia es minima.

## 11. Flujo funcional completo (ejemplo real)

Login:

1. Usuario envia credenciales en login.
2. `AuthService` guarda `token` + `user`.
3. Router entra al layout.
4. Layout lee rol y construye menu permitido.

Consulta de documentos:

1. Componente llama `DocumentosService.getDocumentos`.
2. Interceptor agrega JWT.
3. Backend valida token y rol.
4. Backend devuelve lista segun permisos.
5. UI renderiza acciones segun `esPrivilegiado`.

Descarga de colilla por usuario no privilegiado:

1. Usuario pulsa descargar.
2. Backend valida que documento sea `colilla` y de su `empleado_id`.
3. Si cumple, registra auditoria y descarga.
4. Si no cumple, responde 403.

## 12. Buenas practicas para extender frontend

- Si agregas una nueva pagina:
  - crea componente standalone.
  - registra ruta con guards necesarios.
  - conecta servicio dedicado.
- Si agregas un nuevo rol:
  - actualiza `roleGuard`.
  - actualiza layout (menu + indices).
  - actualiza validaciones en backend.
- Si agregas una accion sensible:
  - oculta boton en frontend.
  - pero valida siempre en backend.

