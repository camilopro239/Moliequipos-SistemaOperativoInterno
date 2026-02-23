import { Component, OnInit, PLATFORM_ID, inject } from '@angular/core';
import { RouterOutlet, RouterModule, Router } from '@angular/router';
import { CommonModule, isPlatformBrowser } from '@angular/common';
import { forkJoin, of, catchError } from 'rxjs';
import { AuthService } from '../auth/services/auth.service';
import { EmpleadosService } from '../auth/services/empleados.service';
import { UsuariosService } from '../auth/services/usuarios.service';
import { DocumentosService } from '../auth/services/documentos.service';

@Component({
  standalone: true,
  selector: 'app-layout',
  imports: [CommonModule, RouterOutlet, RouterModule],
  templateUrl: './layout.component.html',
  styleUrls: ['./layout.component.scss'],
})
export class LayoutComponent implements OnInit {
  private authService = inject(AuthService);
  private router = inject(Router);
  private empleadosService = inject(EmpleadosService);
  private usuariosService = inject(UsuariosService);
  private documentosService = inject(DocumentosService);
  private platformId = inject(PLATFORM_ID);

  private rolesPrivilegiados = [
    'admin',
    'gerente',
    'propietario',
    'recursos_humanos',
  ];
  private rolesEmpleados = [
    ...this.rolesPrivilegiados,
  ];
  private rolesGestionUsuarios = [
    ...this.rolesPrivilegiados,
  ];
  private rolesAuditoria = [
    ...this.rolesPrivilegiados,
  ];

  usuarioNombre = 'Usuario';
  usuarioRol = '';
  esPrivilegiado = false;

  dashboardLoading = true;
  totalDocumentosVisibles = 0;
  totalColillasVisibles = 0;
  totalEmpleados = 0;
  totalUsuarios = 0;
  totalDescargasRecientes = 0;

  documentosRecientes: any[] = [];
  descargasRecientes: any[] = [];

  ngOnInit(): void {
    this.inicializarSesion();
    this.cargarDashboard();
  }

  private inicializarSesion(): void {
    if (!isPlatformBrowser(this.platformId)) {
      this.usuarioNombre = 'Usuario';
      this.usuarioRol = '';
      this.esPrivilegiado = false;
      return;
    }

    const userRaw = localStorage.getItem('user');
    if (!userRaw) {
      this.usuarioNombre = 'Usuario';
      this.usuarioRol = '';
      this.esPrivilegiado = false;
      return;
    }

    try {
      const user = JSON.parse(userRaw);
      this.usuarioNombre = String(user?.nombre || user?.email || 'Usuario');
      this.usuarioRol = String(user?.rol || '').toLowerCase();
      this.esPrivilegiado = this.rolesPrivilegiados.includes(this.usuarioRol);
    } catch {
      this.usuarioNombre = 'Usuario';
      this.usuarioRol = '';
      this.esPrivilegiado = false;
    }
  }

  private formatMenuIndex(value: number): string {
    return String(value).padStart(2, '0');
  }

  get puedeVerEmpleados(): boolean {
    return this.rolesEmpleados.includes(this.usuarioRol);
  }

  get puedeGestionarUsuarios(): boolean {
    return this.rolesGestionUsuarios.includes(this.usuarioRol);
  }

  get puedeVerAuditoria(): boolean {
    return this.rolesAuditoria.includes(this.usuarioRol);
  }

  get rolLabel(): string {
    return this.usuarioRol || 'sin rol';
  }

  get indiceEmpleados(): string {
    return this.formatMenuIndex(2);
  }

  get indiceUsuarios(): string {
    let indice = 2;
    if (this.puedeVerEmpleados) {
      indice += 1;
    }
    return this.formatMenuIndex(indice);
  }

  get indiceDocumentos(): string {
    let indice = 2;
    if (this.puedeVerEmpleados) {
      indice += 1;
    }
    if (this.puedeGestionarUsuarios) {
      indice += 1;
    }
    return this.formatMenuIndex(indice);
  }

  get indiceAuditoria(): string {
    return this.formatMenuIndex(Number(this.indiceDocumentos) + 1);
  }

  cargarDashboard(): void {
    if (!isPlatformBrowser(this.platformId)) {
      this.dashboardLoading = false;
      return;
    }

    if (!this.esPrivilegiado) {
      this.limpiarDashboard();
      this.dashboardLoading = false;
      return;
    }

    this.dashboardLoading = true;

    const empleados$ = this.puedeVerEmpleados
      ? this.empleadosService.getEmpleados().pipe(catchError(() => of([])))
      : of([]);

    const usuarios$ = this.puedeGestionarUsuarios
      ? this.usuariosService.getUsuarios().pipe(catchError(() => of([])))
      : of([]);

    const documentos$ = this.documentosService
      .getDocumentos()
      .pipe(catchError(() => of([])));

    const auditoria$ = this.puedeVerAuditoria
      ? this.documentosService
          .getAuditoriaDescargas({ limit: 6 })
          .pipe(catchError(() => of([])))
      : of([]);

    forkJoin({
      empleados: empleados$,
      usuarios: usuarios$,
      documentos: documentos$,
      auditoria: auditoria$,
    }).subscribe(({ empleados, usuarios, documentos, auditoria }) => {
      const listaDocumentos = Array.isArray(documentos) ? documentos : [];
      const listaAuditoria = Array.isArray(auditoria) ? auditoria : [];

      this.totalDocumentosVisibles = listaDocumentos.length;
      this.totalColillasVisibles = listaDocumentos.filter(
        (doc) => doc?.tipo === 'colilla',
      ).length;
      this.totalEmpleados = Array.isArray(empleados) ? empleados.length : 0;
      this.totalUsuarios = Array.isArray(usuarios) ? usuarios.length : 0;
      this.totalDescargasRecientes = listaAuditoria.length;

      this.documentosRecientes = listaDocumentos.slice(0, 5);
      this.descargasRecientes = listaAuditoria.slice(0, 6);

      this.dashboardLoading = false;
    });
  }

  private limpiarDashboard(): void {
    this.totalDocumentosVisibles = 0;
    this.totalColillasVisibles = 0;
    this.totalEmpleados = 0;
    this.totalUsuarios = 0;
    this.totalDescargasRecientes = 0;
    this.documentosRecientes = [];
    this.descargasRecientes = [];
  }

  logout(): void {
    this.authService.logout();
    this.router.navigate(['/login']);
  }
}
