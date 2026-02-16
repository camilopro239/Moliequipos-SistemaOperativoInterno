import { Component, OnInit, PLATFORM_ID, inject } from '@angular/core';
import { CommonModule, isPlatformBrowser } from '@angular/common';
import {
  FormBuilder,
  FormsModule,
  ReactiveFormsModule,
  Validators,
} from '@angular/forms';
import { ActivatedRoute } from '@angular/router';
import { EmpleadosService } from '../../auth/services/empleados.service';
import { DocumentosService } from '../../auth/services/documentos.service';
import { ToastService } from '../../core/services/toast.service';

@Component({
  standalone: true,
  selector: 'app-documentos',
  imports: [CommonModule, ReactiveFormsModule, FormsModule],
  templateUrl: './documentos.component.html',
  styleUrls: ['./documentos.component.scss'],
})
export class DocumentosComponent implements OnInit {
  private fb = inject(FormBuilder);
  private empleadosService = inject(EmpleadosService);
  private documentosService = inject(DocumentosService);
  private toast = inject(ToastService);
  private platformId = inject(PLATFORM_ID);
  private route = inject(ActivatedRoute);

  empleados: any[] = [];
  documentos: any[] = [];
  esPrivilegiado = false;

  loading = true;
  subiendo = false;
  filtroEmpleadoId = '';

  archivoSeleccionado: File | null = null;

  form = this.fb.group({
    empleado_id: [null as number | null, Validators.required],
    tipo: ['contrato', Validators.required],
    periodo: [''],
  });

  ngOnInit(): void {
    if (!isPlatformBrowser(this.platformId)) {
      this.loading = false;
      return;
    }

    this.inicializarPermisos();
    this.cargarEmpleados();
    this.route.queryParamMap.subscribe((params) => {
      const empleadoIdParam = params.get('empleado_id');
      const empleadoId = Number(empleadoIdParam);
      this.filtroEmpleadoId = empleadoId > 0 ? String(empleadoId) : '';
      this.form.patchValue({ empleado_id: empleadoId > 0 ? empleadoId : null });
      this.cargarDocumentos();
    });
  }

  private inicializarPermisos() {
    const userRaw = localStorage.getItem('user');
    if (!userRaw) {
      this.esPrivilegiado = false;
      return;
    }

    try {
      const user = JSON.parse(userRaw);
      const rol = (user?.rol || '').toLowerCase();
      this.esPrivilegiado = ['admin', 'rrhh', 'gerente'].includes(rol);
    } catch {
      this.esPrivilegiado = false;
    }
  }

  retornar() {
    if (!isPlatformBrowser(this.platformId)) return;
    window.history.back();
  }

  cargarEmpleados() {
    if (!this.esPrivilegiado) {
      this.empleados = [];
      return;
    }

    this.empleadosService.getEmpleados().subscribe({
      next: (data) => {
        this.empleados = data;
      },
      error: () => {
        this.toast.error('No se pudieron cargar los empleados');
      },
    });
  }

  cargarDocumentos() {
    this.loading = true;

    const empleadoId =
      this.filtroEmpleadoId && Number(this.filtroEmpleadoId) > 0
        ? Number(this.filtroEmpleadoId)
        : null;

    this.documentosService.getDocumentos(empleadoId).subscribe({
      next: (data) => {
        this.documentos = data;
        this.loading = false;
      },
      error: () => {
        this.toast.error('No se pudieron cargar los documentos');
        this.loading = false;
      },
    });
  }

  onArchivoSeleccionado(event: Event) {
    const input = event.target as HTMLInputElement;
    this.archivoSeleccionado = input.files && input.files.length ? input.files[0] : null;
  }

  subirDocumento() {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    if (!this.esPrivilegiado) {
      this.toast.error('No tienes permisos para subir documentos');
      return;
    }

    if (!this.archivoSeleccionado) {
      this.toast.warning('Debes seleccionar un archivo');
      return;
    }

    const valores = this.form.getRawValue();
    const formData = new FormData();

    formData.append('empleado_id', String(valores.empleado_id));
    formData.append('tipo', String(valores.tipo));
    formData.append('archivo', this.archivoSeleccionado);

    const periodo = (valores.periodo ?? '').trim();
    if (periodo) {
      formData.append('periodo', periodo);
    }

    this.subiendo = true;

    this.documentosService.subirDocumento(formData).subscribe({
      next: () => {
        this.toast.success('Documento cargado correctamente');
        this.subiendo = false;
        this.archivoSeleccionado = null;
        this.form.patchValue({ tipo: 'contrato', periodo: '' });
        this.cargarDocumentos();
      },
      error: (err) => {
        this.subiendo = false;
        this.toast.error(err?.error?.error || 'No se pudo cargar el documento');
      },
    });
  }

  descargarDocumento(doc: any) {
    if (!isPlatformBrowser(this.platformId)) return;

    this.documentosService.descargarDocumento(doc.id).subscribe({
      next: (archivo) => {
        const blobUrl = URL.createObjectURL(archivo);
        const a = document.createElement('a');
        a.href = blobUrl;
        a.download = doc.nombre_archivo || 'documento';
        document.body.appendChild(a);
        a.click();
        a.remove();
        URL.revokeObjectURL(blobUrl);
      },
      error: (err) => {
        this.toast.error(err?.error?.error || 'No se pudo descargar el documento');
      },
    });
  }

  eliminarDocumento(doc: any) {
    if (!this.esPrivilegiado) {
      this.toast.error('No tienes permisos para eliminar documentos');
      return;
    }

    const ok = confirm(`Deseas eliminar el documento "${doc.nombre_archivo}"?`);
    if (!ok) return;

    this.documentosService.eliminarDocumento(doc.id).subscribe({
      next: () => {
        this.toast.success('Documento eliminado');
        this.cargarDocumentos();
      },
      error: (err) => {
        this.toast.error(err?.error?.error || 'No se pudo eliminar el documento');
      },
    });
  }
}
