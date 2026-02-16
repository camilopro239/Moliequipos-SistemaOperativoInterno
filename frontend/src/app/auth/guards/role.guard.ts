import { inject, PLATFORM_ID } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { isPlatformBrowser } from '@angular/common';

export const roleGuard: CanActivateFn = (route) => {
  const router = inject(Router);
  const platformId = inject(PLATFORM_ID);

  if (!isPlatformBrowser(platformId)) {
    return true;
  }

  const userRaw = localStorage.getItem('user');
  if (!userRaw) {
    router.navigate(['/login']);
    return false;
  }

  const user = JSON.parse(userRaw);
  const allowedRoles = route.data['roles'] as string[];

  if (!allowedRoles?.includes(user.rol)) {
    router.navigate(['/documentos']);
    return false;
  }

  return true;
};
