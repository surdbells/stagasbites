import { HttpErrorResponse } from '@angular/common/http';
import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { AuthStore } from '../../core/auth.store';
import { ApiResponse } from '../../core/models';
import { SeoService } from '../../core/seo.service';

@Component({
  selector: 'app-register',
  imports: [ReactiveFormsModule, RouterLink],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <section class="auth">
      <form class="card auth__card" [formGroup]="form" (ngSubmit)="submit()" novalidate>
        <h1>Create your account</h1>
        <p class="text-soft">Track orders and re-order your favourites in a couple of taps.</p>
        @if (error(); as message) {
          <div class="alert alert--error" role="alert">{{ message }}</div>
        }
        <div class="form-row">
          <div class="field">
            <label for="first_name">First name</label>
            <input id="first_name" formControlName="first_name" autocomplete="given-name" />
          </div>
          <div class="field">
            <label for="last_name">Last name</label>
            <input id="last_name" formControlName="last_name" autocomplete="family-name" />
          </div>
        </div>
        <div class="field">
          <label for="email">Email</label>
          <input id="email" type="email" formControlName="email" autocomplete="email" />
          @if (fieldErrors()['email']; as e) { <span class="error">{{ e[0] }}</span> }
        </div>
        <div class="field">
          <label for="phone">Mobile number</label>
          <input id="phone" type="tel" formControlName="phone" autocomplete="tel" />
        </div>
        <div class="field">
          <label for="password">Password</label>
          <input id="password" type="password" formControlName="password" autocomplete="new-password" />
          <span class="hint">At least 10 characters, with an uppercase letter and a number.</span>
          @if (fieldErrors()['password']; as e) { <span class="error">{{ e.join(' ') }}</span> }
        </div>
        <button type="submit" class="btn btn--block" [disabled]="busy()">{{ busy() ? 'Creating…' : 'Create account' }}</button>
        <p class="auth__links"><span>Already have an account? <a routerLink="/account/login">Sign in</a></span></p>
      </form>
    </section>
  `,
  styleUrl: './auth-shell.scss',
})
export class Register {
  private readonly auth = inject(AuthStore);
  private readonly router = inject(Router);
  private readonly next = inject(ActivatedRoute).snapshot.queryParamMap.get('next') ?? '/account';
  protected readonly busy = signal(false);
  protected readonly error = signal<string | null>(null);
  protected readonly fieldErrors = signal<Record<string, string[]>>({});

  protected readonly form = inject(FormBuilder).nonNullable.group({
    first_name: ['', Validators.required],
    last_name: ['', Validators.required],
    email: ['', [Validators.required, Validators.email]],
    phone: [''],
    password: ['', [Validators.required, Validators.minLength(10)]],
  });

  constructor() {
    inject(SeoService).set({ title: 'Create an account', description: "Create your Staga's Bites account.", noindex: true });
  }

  submit(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }
    this.busy.set(true);
    this.error.set(null);
    this.fieldErrors.set({});
    this.auth.register(this.form.getRawValue()).subscribe({
      next: () => this.router.navigateByUrl(this.next.startsWith('/') && !this.next.startsWith('//') ? this.next : '/account'),
      error: (err: HttpErrorResponse) => {
        const body = err.error as ApiResponse<unknown>;
        this.busy.set(false);
        this.fieldErrors.set(body?.errors ?? {});
        this.error.set(body?.message ?? 'Could not create your account. Please try again.');
      },
    });
  }
}
