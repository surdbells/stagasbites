import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { AuthStore } from '../../core/auth.store';
import { SeoService } from '../../core/seo.service';

@Component({
  selector: 'app-forgot-password',
  imports: [ReactiveFormsModule, RouterLink],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <section class="auth">
      <form class="card auth__card" [formGroup]="form" (ngSubmit)="submit()" novalidate>
        <h1>Reset your password</h1>
        @if (done()) {
          <div class="alert alert--success" role="status">If that email has an account, a reset link is on its way. It’s valid for one hour.</div>
        } @else {
          <p class="text-soft">Enter your email and we’ll send you a link.</p>
          <div class="field">
            <label for="email">Email</label>
            <input id="email" type="email" formControlName="email" autocomplete="email" />
          </div>
          <button type="submit" class="btn btn--block" [disabled]="busy()">{{ busy() ? 'Sending…' : 'Send reset link' }}</button>
        }
        <p class="auth__links"><a routerLink="/account/login">← Back to sign in</a></p>
      </form>
    </section>
  `,
  styleUrl: './auth-shell.scss',
})
export class ForgotPassword {
  private readonly auth = inject(AuthStore);
  protected readonly busy = signal(false);
  protected readonly done = signal(false);
  protected readonly form = inject(FormBuilder).nonNullable.group({ email: ['', [Validators.required, Validators.email]] });

  constructor() {
    inject(SeoService).set({ title: 'Reset password', description: 'Reset your password.', noindex: true });
  }

  submit(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }
    this.busy.set(true);
    // Same outcome either way, so the form can't be used to discover which emails have accounts.
    const finish = () => {
      this.busy.set(false);
      this.done.set(true);
    };
    this.auth.forgotPassword(this.form.getRawValue().email).subscribe({ next: finish, error: finish });
  }
}
