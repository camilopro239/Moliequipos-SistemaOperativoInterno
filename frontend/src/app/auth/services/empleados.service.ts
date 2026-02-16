import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, map } from 'rxjs';

@Injectable({ providedIn: 'root' })
export class EmpleadosService {

  private http = inject(HttpClient);
  private API_URL = 'http://localhost:8000/empleados';

  getEmpleados(): Observable<any[]> {
  return this.http.get<any[]>(this.API_URL);
}

crearEmpleado(data:any){
  return this.http.post(this.API_URL, data)
}

actualizarEmpleado(id: number, data: any) {
  return this.http.put(
    `${this.API_URL}/${id}`,
    data
  );
}

eliminarEmpleado(id: number) {
  return this.http.delete(
    `${this.API_URL}/${id}`
  );
}


}
