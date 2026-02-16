import { HttpInterceptorFn } from '@angular/common/http';
import { inject, PLATFORM_ID } from '@angular/core';
import { isPlatformBrowser } from '@angular/common';

export const jwtInterceptor: HttpInterceptorFn = (req, next) => {

  const platformId = inject(PLATFORM_ID);

  // ⛔ SSR NO hace requests protegidas
  if (!isPlatformBrowser(platformId)) {
    return next(req);
  }

  const token = localStorage.getItem('token');

  // ⛔ Sin token → NO llamar al backend protegido
  if (!token && req.url.includes('/empleados')) {
    return next(req.clone({ url: 'about:blank' }));
  }

  if (!token) {
    return next(req);
  }

  const authReq = req.clone({
    setHeaders: {
      Authorization: `Bearer ${token}`
    }
  });

  return next(authReq);
};
