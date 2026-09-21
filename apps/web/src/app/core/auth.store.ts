import { Injectable, computed, inject, signal } from '@angular/core';
import { Router } from '@angular/router';
import { Observable, tap } from 'rxjs';
import { ApiService } from './api.service';
import { AuthTokens, User } from './models';

const STORAGE_KEY = 'stagas.auth.v1';

interface Session {
  access_token: string;
  refresh_token: string;
  user: User;
}

@Injectable({ providedIn: 'root' })
export class AuthStore {
  private readonly api = inject(ApiService);
  private readonly router = inject(Router);
  private readonly session = signal<Session | null>(this.restore());

  readonly user = computed(() => this.session()?.user ?? null);
  readonly isLoggedIn = computed(() => this.session() !== null);
  readonly isAdmin = computed(() => this.session()?.user.role === 'admin');

  get accessToken(): string | null {
    return this.session()?.access_token ?? null;
  }

  get refreshToken(): string | null {
    return this.session()?.refresh_token ?? null;
  }

  login(email: string, password: string): Observable<AuthTokens> {
    return this.api
      .post<AuthTokens>('/auth/login', { email, password })
      .pipe(tap((t) => this.persist(t)));
  }

  register(payload: {
    first_name: string;
    last_name: string;
    email: string;
    phone: string;
    password: string;
  }): Observable<AuthTokens> {
    return this.api.post<AuthTokens>('/auth/register', payload).pipe(tap((t) => this.persist(t)));
  }

  refresh(): Observable<AuthTokens> {
    return this.api
      .post<AuthTokens>('/auth/refresh', { refresh_token: this.refreshToken })
      .pipe(tap((t) => this.persist(t)));
  }

  forgotPassword(email: string): Observable<unknown> {
    return this.api.post('/auth/forgot-password', { email });
  }

  resetPassword(token: string, password: string): Observable<unknown> {
    return this.api.post('/auth/reset-password', { token, password });
  }

  logout(redirect = true): void {
    const refresh_token = this.refreshToken;
    if (refresh_token) {
      this.api.post('/auth/logout', { refresh_token }).subscribe({ error: () => undefined });
    }
    this.session.set(null);
    localStorage.removeItem(STORAGE_KEY);
    if (redirect) {
      this.router.navigateByUrl('/');
    }
  }

  private persist(tokens: AuthTokens): void {
    const session: Session = {
      access_token: tokens.access_token,
      refresh_token: tokens.refresh_token,
      user: tokens.user,
    };
    this.session.set(session);
    localStorage.setItem(STORAGE_KEY, JSON.stringify(session));
  }

  private restore(): Session | null {
    try {
      const raw = localStorage.getItem(STORAGE_KEY);
      return raw ? (JSON.parse(raw) as Session) : null;
    } catch {
      return null;
    }
  }
}
