import { HttpErrorResponse } from '@angular/common/http';
import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { AuthStore } from '../../core/auth.store';
import { ApiResponse } from '../../core/models';
import { SeoService } from '../../core/seo.service';

@Component({
  selector: 'app-login',
  imports: [ReactiveFormsModule, RouterLink],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <section class="auth">
      <form class="card auth__card" [formGroup]="form" (ngSubmit)="submit()" novalidate>
        <h1>Welcome back</h1>
        @if (error(); as message) {
          <div class="alert alert--error" role="alert">{{ message }}</div>
        }
        <div class="field">
          <label for="email">Email</label>
          <input id="email" type="email" formControlName="email" autocomplete="email" />
        </div>
        <div class="field">
          <label for="password">Password</label>
          <input id="password" type="password" formControlName="password" autocomplete="current-password" />
        </div>
        <button type="submit" class="btn btn--block" [disabled]="busy()">{{ busy() ? 'Signing in…' : 'Sign in' }}</button>
        <p class="auth__links">
          <a routerLink="/account/forgot-password">Forgot password?</a>
          <span>New here? <a routerLink="/account/register" [queryParams]="{ next: next }">Create an account</a></span>
        </p>
      </form>
    </section>
  `,
  styleUrl: './auth-shell.scss',
})
export class Login {
  private readonly auth = inject(AuthStore);
  private readonly router = inject(Router);
  protected readonly next = inject(ActivatedRoute).snapshot.queryParamMap.get('next') ?? '/account';
  protected readonly busy = signal(false);
  protected readonly error = signal<string | null>(null);

  protected readonly form = inject(FormBuilder).nonNullable.group({
    email: ['', [Validators.required, Validators.email]],
    password: ['', Validators.required],
  });

  constructor() {
    inject(SeoService).set({ title: 'Sign in', description: "Sign in to your Staga's Bites account.", noindex: true });
  }

  submit(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }
    this.busy.set(true);
    this.error.set(null);
    const { email, password } = this.form.getRawValue();
    this.auth.login(email, password).subscribe({
      // Only follow same-site paths, so a crafted ?next= can't bounce people to another domain.
      next: (tokens) =>
        this.router.navigateByUrl(this.next.startsWith('/') && !this.next.startsWith('//') ? this.next : tokens.user.role === 'admin' ? '/admin' : '/account'),
      error: (err: HttpErrorResponse) => {
        this.busy.set(false);
        this.error.set((err.error as ApiResponse<unknown>)?.message ?? 'Could not sign in. Please try again.');
      },
    });
  }
}
