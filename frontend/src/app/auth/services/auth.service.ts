import { Injectable, inject, PLATFORM_ID } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { isPlatformBrowser } from '@angular/common';
import { Observable, tap } from 'rxjs';

@Injectable({ providedIn: 'root' })
export class AuthService {

  private http = inject(HttpClient);
  private platformId = inject(PLATFORM_ID);

  // ✅ BASE URL REAL DEL BACKEND (php -S)
  private API_URL = 'http://localhost:8000/auth';

 login(email: string, password: string) {
  return this.http.post<any>(`${this.API_URL}/login`, { email, password })
    .pipe(
      tap(res => {
        localStorage.setItem('token', res.token);
        localStorage.setItem('user', JSON.stringify(res.user));
      })
    );
}


  logout(): void {
    if (isPlatformBrowser(this.platformId)) {
      localStorage.clear();
    }
  }

  isAuthenticated(): boolean {
    if (!isPlatformBrowser(this.platformId)) return false;
    return !!localStorage.getItem('token');
  }
}
