import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

@Injectable({ providedIn: 'root' })
export class UsuariosService {
  private http = inject(HttpClient);
  private API_URL = 'http://localhost:8000/usuarios';

  getUsuarios(): Observable<any[]> {
    return this.http.get<any[]>(this.API_URL);
  }

  getEmpleadosParaVincular(): Observable<any[]> {
    return this.http.get<any[]>(`${this.API_URL}/empleados`);
  }

  crearUsuario(data: {
    empleado_id: number;
    email: string;
    password: string;
    rol: string;
  }) {
    return this.http.post(this.API_URL, data);
  }

  actualizarRol(usuarioId: number, rol: string) {
    return this.http.put(`${this.API_URL}/${usuarioId}/rol`, { rol });
  }

  resetPassword(usuarioId: number, password: string) {
    return this.http.put(`${this.API_URL}/${usuarioId}/reset-password`, { password });
  }
}
