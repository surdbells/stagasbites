import { HttpErrorResponse, HttpInterceptorFn, HttpRequest } from '@angular/common/http';
import { inject } from '@angular/core';
import { Observable, catchError, finalize, shareReplay, switchMap, throwError } from 'rxjs';
import { environment } from '../../environments/environment';
import { AuthStore } from './auth.store';
import { AuthTokens } from './models';

let refreshInFlight: Observable<AuthTokens> | null = null;

const withToken = (req: HttpRequest<unknown>, token: string | null) =>
  token ? req.clone({ setHeaders: { Authorization: `Bearer ${token}` } }) : req;

export const authInterceptor: HttpInterceptorFn = (req, next) => {
  const auth = inject(AuthStore);
  const isApi = req.url.startsWith(environment.apiUrl);
  const isAuthCall = req.url.includes('/auth/');

  if (!isApi) {
    return next(req);
  }

  return next(withToken(req, auth.accessToken)).pipe(
    catchError((err: HttpErrorResponse) => {
      if (err.status !== 401 || isAuthCall || !auth.refreshToken) {
        return throwError(() => err);
      }
      refreshInFlight ??= auth.refresh().pipe(
        shareReplay(1),
        finalize(() => (refreshInFlight = null)),
      );
      return refreshInFlight.pipe(
        switchMap((tokens) => next(withToken(req, tokens.access_token))),
        catchError((refreshErr) => {
          auth.logout(false);
          return throwError(() => refreshErr);
        }),
      );
    }),
  );
};
