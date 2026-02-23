import { Component, inject, OnInit, PLATFORM_ID } from '@angular/core';
import { CommonModule, isPlatformBrowser } from '@angular/common';
import { EmpleadosService } from '../../auth/services/empleados.service';
import { ToastService } from '../../core/services/toast.service';

import {
  FormBuilder,
  FormGroup,
  FormsModule,
  Validators,
  ReactiveFormsModule,
} from '@angular/forms';
import { Router } from '@angular/router';

@Component({
  standalone: true,
  selector: 'app-empleados',
  imports: [CommonModule, ReactiveFormsModule, FormsModule],
  templateUrl: './empleados.component.html',
  styleUrls: ['./empleados.component.scss'],
})
export class EmpleadosComponent implements OnInit {

  private fb = inject(FormBuilder);
  private empleadosService = inject(EmpleadosService);
  private toast = inject(ToastService);
  private platformId = inject(PLATFORM_ID);
  private router = inject(Router);

  form!: FormGroup;

  empleados: any[] = [];
  filtroTexto = '';
  filtroEstado = 'todos';
  loading = true;
  error = '';

  showModal = false;
  modoEdicion = false;
  empleadoEditando: any = null;

  ngOnInit(): void {

    // =========================
    // 📋 FORMULARIO
    // =========================
    this.form = this.fb.group({
      id: [null],
      nombre: ['', Validators.required],
      cedula: ['', Validators.required],
      cargo: [''],
      telefono: [''],
      correo: ['', [Validators.required, Validators.email]],
      fecha_ingreso: [''],
      estado: ['activo', Validators.required],
    });

    // ⛔ SSR
    if (!isPlatformBrowser(this.platformId)) {
      this.loading = false;
      return;
    }

    this.cargarEmpleados();
  }

  // =========================
  // 📥 GET EMPLEADOS
  // =========================
  cargarEmpleados() {
    this.loading = true;

    this.empleadosService.getEmpleados().subscribe({
      next: (data) => {
        this.empleados = data;
        this.loading = false;
      },
      error: () => {
        this.toast.error('No se pudieron cargar los empleados');
        this.loading = false;
      },
    });
  }

  get empleadosFiltrados(): any[] {
    const texto = this.normalizarTexto(this.filtroTexto.trim());
    const estado = this.filtroEstado;

    return this.empleados.filter((emp) => {
      const coincideEstado = estado === 'todos' || emp.estado === estado;

      if (!texto) return coincideEstado;

      const campos = [
        emp.nombre,
        emp.cedula,
        emp.cargo,
        emp.correo,
      ]
        .map((valor) => this.normalizarTexto(valor))
        .join(' ');

      return coincideEstado && campos.includes(texto);
    });
  }

  limpiarFiltros() {
    this.filtroTexto = '';
    this.filtroEstado = 'todos';
  }

  private normalizarTexto(valor: any): string {
    return String(valor ?? '')
      .toLowerCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '');
  }

  retornar() {
    if (!isPlatformBrowser(this.platformId)) return;
    window.history.back();
  }

  verDocumentos(empleado: any) {
    if (!empleado?.id) return;
    this.router.navigate(['/documentos'], {
      queryParams: { empleado_id: empleado.id },
    });
  }

  // =========================
  // 🗑️ ELIMINAR
  // =========================
  confirmarEliminar(empleado: any) {
    const ok = confirm(
      `¿Seguro que deseas eliminar a ${empleado.nombre}?`
    );

    if (!ok) return;

    this.eliminarEmpleado(empleado.id);
  }

  eliminarEmpleado(id: number) {
    this.empleadosService.eliminarEmpleado(id).subscribe({
      next: () => {
        this.toast.success('Empleado eliminado correctamente');
        this.cargarEmpleados();
      },
      error: () => {
        this.toast.error('No se pudo eliminar el empleado');
      },
    });
  }

  // =========================
  // 🪟 MODAL
  // =========================
  openModalCrear() {
    this.modoEdicion = false;
    this.empleadoEditando = null;
    this.form.reset({ telefono: '', fecha_ingreso: '', estado: 'activo' });
    this.showModal = true;
  }

  openModalEditar(empleado: any) {
    this.modoEdicion = true;
    this.empleadoEditando = empleado;
    this.form.patchValue(empleado);
    this.showModal = true;
  }

  closeModal() {
    this.showModal = false;
    this.modoEdicion = false;
    this.empleadoEditando = null;
    this.form.reset({ telefono: '', fecha_ingreso: '', estado: 'activo' });
  }

  // =========================
  // 💾 SUBMIT (CREAR / EDITAR)
  // =========================
  submit() {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    const data = this.form.value;

    if (this.modoEdicion) {
      // ✏️ EDITAR
      this.empleadosService.actualizarEmpleado(data.id, data).subscribe({
        next: () => {
          this.toast.success('Empleado actualizado correctamente');
          this.closeModal();
          this.cargarEmpleados();
        },
        error: (err: any) => {
          console.error(err);
          this.toast.error(
            err?.error?.message || 'Error al actualizar el empleado'
          );
        },
      });

    } else {
      // ➕ CREAR
      this.empleadosService.crearEmpleado(data).subscribe({
        next: () => {
          this.toast.success('Empleado creado correctamente');
          this.closeModal();
          this.cargarEmpleados();
        },
        error: () => {
          this.toast.error('Error al crear el empleado');
        },
      });
    }
  }
}
