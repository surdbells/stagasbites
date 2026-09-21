import { HttpErrorResponse } from '@angular/common/http';
import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { AuthStore } from '../../core/auth.store';
import { ApiResponse } from '../../core/models';
import { SeoService } from '../../core/seo.service';

@Component({
  selector: 'app-reset-password',
  imports: [ReactiveFormsModule, RouterLink],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <section class="auth">
      <form class="card auth__card" [formGroup]="form" (ngSubmit)="submit()" novalidate>
        <h1>Choose a new password</h1>
        @if (done()) {
          <div class="alert alert--success" role="status">Password updated. You can now sign in.</div>
          <a routerLink="/account/login" class="btn btn--block">Sign in</a>
        } @else {
          @if (error(); as message) {
            <div class="alert alert--error" role="alert">{{ message }}</div>
          }
          <div class="field">
            <label for="password">New password</label>
            <input id="password" type="password" formControlName="password" autocomplete="new-password" />
            <span class="hint">At least 10 characters, with an uppercase letter and a number.</span>
          </div>
          <button type="submit" class="btn btn--block" [disabled]="busy() || !token">{{ busy() ? 'Saving…' : 'Update password' }}</button>
        }
      </form>
    </section>
  `,
  styleUrl: './auth-shell.scss',
})
export class ResetPassword {
  private readonly auth = inject(AuthStore);
  protected readonly token = inject(ActivatedRoute).snapshot.queryParamMap.get('token');
  protected readonly busy = signal(false);
  protected readonly done = signal(false);
  protected readonly error = signal<string | null>(this.token ? null : 'This reset link is incomplete. Please request a new one.');
  protected readonly form = inject(FormBuilder).nonNullable.group({ password: ['', [Validators.required, Validators.minLength(10)]] });

  constructor() {
    inject(SeoService).set({ title: 'Choose a new password', description: 'Choose a new password.', noindex: true });
  }

  submit(): void {
    if (this.form.invalid || !this.token) {
      this.form.markAllAsTouched();
      return;
    }
    this.busy.set(true);
    this.error.set(null);
    this.auth.resetPassword(this.token, this.form.getRawValue().password).subscribe({
      next: () => {
        this.busy.set(false);
        this.done.set(true);
      },
      error: (err: HttpErrorResponse) => {
        const body = err.error as ApiResponse<unknown>;
        this.busy.set(false);
        this.error.set(body?.errors?.['password']?.join(' ') ?? body?.message ?? 'Could not update your password.');
      },
    });
  }
}
