import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { AuthStore } from './auth.store';

export const authGuard: CanActivateFn = (_route, state) => {
  const auth = inject(AuthStore);
  return auth.isLoggedIn()
    ? true
    : inject(Router).createUrlTree(['/account/login'], { queryParams: { next: state.url } });
};

export const adminGuard: CanActivateFn = (_route, state) => {
  const auth = inject(AuthStore);
  return auth.isAdmin()
    ? true
    : inject(Router).createUrlTree(['/account/login'], { queryParams: { next: state.url } });
};
