import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, map } from 'rxjs';
import { environment } from '../../environments/environment';
import { ApiResponse } from './models';

type Query = Record<string, string | number | boolean | null | undefined>;

/** Thin wrapper that unwraps the API's `{ success, data, message }` envelope. */
@Injectable({ providedIn: 'root' })
export class ApiService {
  private readonly http = inject(HttpClient);
  private readonly base = environment.apiUrl;

  get<T>(path: string, query?: Query): Observable<T> {
    return this.http
      .get<ApiResponse<T>>(this.base + path, { params: this.params(query) })
      .pipe(map((r) => r.data));
  }

  getPage<T>(path: string, query?: Query): Observable<ApiResponse<T>> {
    return this.http.get<ApiResponse<T>>(this.base + path, { params: this.params(query) });
  }

  post<T>(path: string, body: unknown = {}): Observable<T> {
    return this.http.post<ApiResponse<T>>(this.base + path, body).pipe(map((r) => r.data));
  }

  put<T>(path: string, body: unknown): Observable<T> {
    return this.http.put<ApiResponse<T>>(this.base + path, body).pipe(map((r) => r.data));
  }

  patch<T>(path: string, body: unknown): Observable<T> {
    return this.http.patch<ApiResponse<T>>(this.base + path, body).pipe(map((r) => r.data));
  }

  delete<T>(path: string): Observable<T> {
    return this.http.delete<ApiResponse<T>>(this.base + path).pipe(map((r) => r.data));
  }

  private params(query?: Query): HttpParams {
    let params = new HttpParams();
    for (const [key, value] of Object.entries(query ?? {})) {
      if (value !== null && value !== undefined && value !== '') {
        params = params.set(key, String(value));
      }
    }
    return params;
  }
}
