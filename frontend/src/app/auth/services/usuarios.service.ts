import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../environments/environment';

@Injectable({ providedIn: 'root' })
export class UsuariosService {
  private http = inject(HttpClient);
  private API_URL = `${environment.apiBaseUrl}/usuarios`;

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
