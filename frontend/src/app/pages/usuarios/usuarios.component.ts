import { Component, OnInit, PLATFORM_ID, inject } from '@angular/core';
import { CommonModule, isPlatformBrowser } from '@angular/common';
import {
  FormBuilder,
  FormsModule,
  ReactiveFormsModule,
  Validators,
} from '@angular/forms';
import { UsuariosService } from '../../auth/services/usuarios.service';
import { ToastService } from '../../core/services/toast.service';

@Component({
  standalone: true,
  selector: 'app-usuarios',
  imports: [CommonModule, ReactiveFormsModule, FormsModule],
  templateUrl: './usuarios.component.html',
  styleUrls: ['./usuarios.component.scss'],
})
export class UsuariosComponent implements OnInit {
  private fb = inject(FormBuilder);
  private usuariosService = inject(UsuariosService);
  private toast = inject(ToastService);
  private platformId = inject(PLATFORM_ID);

  loading = true;
  guardando = false;
  actualizandoRolId: number | null = null;
  reseteandoPasswordId: number | null = null;

  empleados: any[] = [];
  usuarios: any[] = [];

  rolesDisponibles = ['admin', 'gerente', 'rrhh', 'operador_patio', 'operador'];
  rolEditadoPorUsuario: Record<number, string> = {};

  modalResetAbierto = false;
  usuarioResetObjetivo: any | null = null;
  mostrarPasswordReset = false;
  mostrarConfirmPasswordReset = false;

  form = this.fb.group({
    empleado_id: [null as number | null, Validators.required],
    email: ['', [Validators.required, Validators.email]],
    password: ['', [Validators.required, Validators.minLength(6)]],
    rol: ['operador_patio', Validators.required],
  });

  resetForm = this.fb.group({
    password: ['', [Validators.required, Validators.minLength(6)]],
    confirmPassword: ['', [Validators.required]],
  });

  ngOnInit(): void {
    if (!isPlatformBrowser(this.platformId)) {
      this.loading = false;
      return;
    }

    this.cargarDatos();
  }

  retornar() {
    if (!isPlatformBrowser(this.platformId)) return;
    window.history.back();
  }

  get empleadosDisponibles(): any[] {
    return this.empleados.filter((emp) => !emp.usuario_id);
  }

  get passwordsNoCoinciden(): boolean {
    const password = String(this.resetForm.value.password || '');
    const confirmPassword = String(this.resetForm.value.confirmPassword || '');

    if (!confirmPassword) return false;
    return password !== confirmPassword;
  }

  cargarDatos() {
    this.loading = true;

    this.usuariosService.getEmpleadosParaVincular().subscribe({
      next: (empleados) => {
        this.empleados = empleados;

        this.usuariosService.getUsuarios().subscribe({
          next: (usuarios) => {
            this.usuarios = usuarios;
            this.rolEditadoPorUsuario = {};
            for (const user of this.usuarios) {
              this.rolEditadoPorUsuario[user.id] = user.rol;
            }
            this.loading = false;
          },
          error: (err) => {
            this.loading = false;
            this.toast.error(err?.error?.error || 'No se pudo cargar la lista de usuarios');
          },
        });
      },
      error: (err) => {
        this.loading = false;
        this.toast.error(err?.error?.error || 'No se pudo cargar la lista de empleados');
      },
    });
  }

  crearUsuario() {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    const data = this.form.getRawValue();
    const payload = {
      empleado_id: Number(data.empleado_id),
      email: String(data.email).trim().toLowerCase(),
      password: String(data.password),
      rol: String(data.rol).trim().toLowerCase(),
    };

    this.guardando = true;
    this.usuariosService.crearUsuario(payload).subscribe({
      next: () => {
        this.guardando = false;
        this.toast.success('Usuario creado correctamente');
        this.form.patchValue({
          empleado_id: null,
          email: '',
          password: '',
          rol: 'operador_patio',
        });
        this.cargarDatos();
      },
      error: (err) => {
        this.guardando = false;
        this.toast.error(err?.error?.error || 'No se pudo crear el usuario');
      },
    });
  }

  actualizarRol(user: any) {
    const nuevoRol = String(this.rolEditadoPorUsuario[user.id] || '').trim().toLowerCase();
    if (!nuevoRol) {
      this.toast.warning('Debes seleccionar un rol valido');
      return;
    }

    if (nuevoRol === String(user.rol || '').toLowerCase()) {
      this.toast.info('No hay cambios en el rol');
      return;
    }

    this.actualizandoRolId = user.id;
    this.usuariosService.actualizarRol(user.id, nuevoRol).subscribe({
      next: () => {
        this.actualizandoRolId = null;
        this.toast.success('Rol actualizado correctamente');
        this.cargarDatos();
      },
      error: (err) => {
        this.actualizandoRolId = null;
        this.toast.error(err?.error?.error || 'No se pudo actualizar el rol');
      },
    });
  }

  abrirModalResetPassword(user: any) {
    this.usuarioResetObjetivo = user;
    this.modalResetAbierto = true;
    this.mostrarPasswordReset = false;
    this.mostrarConfirmPasswordReset = false;
    this.resetForm.reset({ password: '', confirmPassword: '' });
  }

  cerrarModalResetPassword() {
    if (this.reseteandoPasswordId !== null) {
      return;
    }

    this.modalResetAbierto = false;
    this.usuarioResetObjetivo = null;
    this.mostrarPasswordReset = false;
    this.mostrarConfirmPasswordReset = false;
    this.resetForm.reset({ password: '', confirmPassword: '' });
  }

  toggleMostrarPasswordReset() {
    this.mostrarPasswordReset = !this.mostrarPasswordReset;
  }

  toggleMostrarConfirmPasswordReset() {
    this.mostrarConfirmPasswordReset = !this.mostrarConfirmPasswordReset;
  }

  confirmarResetPassword() {
    if (!this.usuarioResetObjetivo) {
      return;
    }

    if (this.resetForm.invalid) {
      this.resetForm.markAllAsTouched();
      return;
    }

    const password = String(this.resetForm.value.password || '').trim();
    const confirmPassword = String(this.resetForm.value.confirmPassword || '').trim();

    if (password !== confirmPassword) {
      this.toast.warning('Las contrasenas no coinciden');
      return;
    }

    this.reseteandoPasswordId = this.usuarioResetObjetivo.id;
    this.usuariosService.resetPassword(this.usuarioResetObjetivo.id, password).subscribe({
      next: () => {
        this.reseteandoPasswordId = null;
        this.toast.success('Contrasena restablecida correctamente');
        this.cerrarModalResetPassword();
      },
      error: (err) => {
        this.reseteandoPasswordId = null;
        this.toast.error(err?.error?.error || 'No se pudo restablecer la contrasena');
      },
    });
  }
}
