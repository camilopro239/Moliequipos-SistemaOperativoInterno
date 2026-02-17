# Documentacion Detallada del Proyecto

Este indice organiza la documentacion tecnica completa del sistema de gestion interna de Moliequipos.

## Como leer esta documentacion

1. Empieza por la guia de backend para entender rutas, seguridad y base de negocio.
2. Sigue con la guia de frontend para ver flujo de pantallas, guards y servicios.
3. Revisa la guia de base de datos y permisos para ver como se conecta todo.

## Documentos incluidos

- `docs/01-backend-detallado.md`
  - Explica la arquitectura PHP, middlewares, controladores, validaciones y respuestas.
- `docs/02-frontend-detallado.md`
  - Explica arquitectura Angular, rutas, guards, servicios, componentes y flujos de UI.
- `docs/03-base-datos-y-permisos.md`
  - Explica esquema de tablas, relaciones, SQL de evolucion y matriz de permisos por rol.

## Alcance

Esta documentacion cubre:

- Backend completo (`backend/`).
- Frontend completo (`frontend/src/app` y arranque en `frontend/src`).
- Scripts SQL usados por el sistema (`backend/sql`).
- Reglas funcionales de acceso que hoy aplica la aplicacion.

## Nota de mantenimiento

Cada vez que agregues un endpoint, cambies un guard o toques permisos de rol, actualiza tambien estos 3 documentos para que el conocimiento del sistema no se pierda.

