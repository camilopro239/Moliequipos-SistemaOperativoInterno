import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

@Injectable({ providedIn: 'root' })
export class DocumentosService {
  private http = inject(HttpClient);
  private API_URL = 'http://localhost:8000/documentos';

  getDocumentos(empleadoId?: number | null): Observable<any[]> {
    if (empleadoId && empleadoId > 0) {
      return this.http.get<any[]>(`${this.API_URL}?empleado_id=${empleadoId}`);
    }
    return this.http.get<any[]>(this.API_URL);
  }

  getAuditoriaDescargas(filtros?: {
    empleado_id?: number | null;
    tipo_documento?: string;
    fecha_desde?: string;
    fecha_hasta?: string;
    limit?: number;
  }): Observable<any[]> {
    const params = new URLSearchParams();

    if (filtros?.empleado_id && filtros.empleado_id > 0) {
      params.set('empleado_id', String(filtros.empleado_id));
    }

    if (filtros?.tipo_documento) {
      params.set('tipo_documento', filtros.tipo_documento);
    }

    if (filtros?.fecha_desde) {
      params.set('fecha_desde', filtros.fecha_desde);
    }

    if (filtros?.fecha_hasta) {
      params.set('fecha_hasta', filtros.fecha_hasta);
    }

    if (filtros?.limit && filtros.limit > 0) {
      params.set('limit', String(filtros.limit));
    }

    const query = params.toString();
    const url = query ? `${this.API_URL}/auditoria?${query}` : `${this.API_URL}/auditoria`;

    return this.http.get<any[]>(url);
  }

  subirDocumento(formData: FormData) {
    return this.http.post(this.API_URL, formData);
  }

  eliminarDocumento(id: number) {
    return this.http.delete(`${this.API_URL}/${id}`);
  }

  descargarDocumento(id: number): Observable<Blob> {
    return this.http.get(`${this.API_URL}/${id}/descargar`, {
      responseType: 'blob',
    });
  }
}
