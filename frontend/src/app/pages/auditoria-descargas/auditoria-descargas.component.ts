import { Component, OnInit, PLATFORM_ID, inject } from '@angular/core';
import { CommonModule, isPlatformBrowser } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { EmpleadosService } from '../../auth/services/empleados.service';
import { DocumentosService } from '../../auth/services/documentos.service';
import { ToastService } from '../../core/services/toast.service';

@Component({
  standalone: true,
  selector: 'app-auditoria-descargas',
  imports: [CommonModule, FormsModule],
  templateUrl: './auditoria-descargas.component.html',
  styleUrls: ['./auditoria-descargas.component.scss'],
})
export class AuditoriaDescargasComponent implements OnInit {
  private empleadosService = inject(EmpleadosService);
  private documentosService = inject(DocumentosService);
  private toast = inject(ToastService);
  private platformId = inject(PLATFORM_ID);

  loading = true;
  empleados: any[] = [];
  registros: any[] = [];

  filtroEmpleadoId = '';
  filtroTipoDocumento = '';
  filtroFechaDesde = '';
  filtroFechaHasta = '';
  filtroLimite = '100';

  ngOnInit(): void {
    if (!isPlatformBrowser(this.platformId)) {
      this.loading = false;
      return;
    }

    this.cargarEmpleados();
    this.cargarAuditoria();
  }

  retornar() {
    if (!isPlatformBrowser(this.platformId)) return;
    window.history.back();
  }

  cargarEmpleados() {
    this.empleadosService.getEmpleados().subscribe({
      next: (data) => {
        this.empleados = data;
      },
      error: () => {
        this.toast.error('No se pudieron cargar los empleados para el filtro');
      },
    });
  }

  aplicarFiltros() {
    this.cargarAuditoria();
  }

  limpiarFiltros() {
    this.filtroEmpleadoId = '';
    this.filtroTipoDocumento = '';
    this.filtroFechaDesde = '';
    this.filtroFechaHasta = '';
    this.filtroLimite = '100';
    this.cargarAuditoria();
  }

  cargarAuditoria() {
    this.loading = true;

    const empleadoId = this.filtroEmpleadoId && Number(this.filtroEmpleadoId) > 0
      ? Number(this.filtroEmpleadoId)
      : null;

    const tipoDocumento = this.filtroTipoDocumento.trim();
    const fechaDesde = this.filtroFechaDesde.trim();
    const fechaHasta = this.filtroFechaHasta.trim();

    const limiteNumero = Number(this.filtroLimite);
    const limit = Number.isFinite(limiteNumero) && limiteNumero > 0 ? limiteNumero : 100;

    this.documentosService
      .getAuditoriaDescargas({
        empleado_id: empleadoId,
        tipo_documento: tipoDocumento || undefined,
        fecha_desde: fechaDesde || undefined,
        fecha_hasta: fechaHasta || undefined,
        limit,
      })
      .subscribe({
        next: (data) => {
          this.registros = data;
          this.loading = false;
        },
        error: (err) => {
          this.loading = false;
          this.toast.error(err?.error?.error || 'No se pudo cargar la auditoria de descargas');
        },
      });
  }
}
