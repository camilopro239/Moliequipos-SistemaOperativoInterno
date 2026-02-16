import { Routes } from '@angular/router';
import { authGuard } from './auth/guards/auth.guard';
import { roleGuard } from './auth/guards/role.guard';

export const routes: Routes = [
  {
    path: 'login',
    loadComponent: () =>
      import('./auth/pages/login/login.component').then((m) => m.LoginComponent),
  },

  {
    path: 'empleados',
    canActivate: [authGuard, roleGuard],
    data: { roles: ['admin', 'rrhh'] },
    loadComponent: () =>
      import('./pages/empleados/empleados.component').then((m) => m.EmpleadosComponent),
  },

  {
    path: 'documentos',
    canActivate: [authGuard],
    loadComponent: () =>
      import('./pages/documentos/documentos.component').then((m) => m.DocumentosComponent),
  },

  {
    path: 'usuarios',
    canActivate: [authGuard, roleGuard],
    data: { roles: ['admin', 'gerente'] },
    loadComponent: () =>
      import('./pages/usuarios/usuarios.component').then((m) => m.UsuariosComponent),
  },

  {
    path: 'auditoria-descargas',
    canActivate: [authGuard, roleGuard],
    data: { roles: ['admin', 'rrhh', 'gerente'] },
    loadComponent: () =>
      import('./pages/auditoria-descargas/auditoria-descargas.component').then(
        (m) => m.AuditoriaDescargasComponent,
      ),
  },

  {
    path: '',
    canActivate: [authGuard],
    loadComponent: () => import('./layout/layout.component').then((m) => m.LayoutComponent),
  },
];
