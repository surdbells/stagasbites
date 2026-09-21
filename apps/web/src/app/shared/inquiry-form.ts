import { HttpErrorResponse } from '@angular/common/http';
import { ChangeDetectionStrategy, Component, inject, input, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ApiService } from '../core/api.service';
import { ApiResponse } from '../core/models';

@Component({
  selector: 'app-inquiry-form',
  imports: [ReactiveFormsModule],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    @if (sent()) {
      <div class="alert alert--success" role="status">
        <strong>Message received.</strong> Thank you — we’ll reply within one business day.
      </div>
    } @else {
      <form [formGroup]="form" (ngSubmit)="submit()" novalidate>
        @if (error(); as message) {
          <div class="alert alert--error" role="alert">{{ message }}</div>
        }
        <div class="form-row">
          <div class="field">
            <label [for]="type() + '-name'">Your name</label>
            <input [id]="type() + '-name'" formControlName="name" autocomplete="name" />
          </div>
          <div class="field">
            <label [for]="type() + '-email'">Email</label>
            <input [id]="type() + '-email'" type="email" formControlName="email" autocomplete="email" />
          </div>
        </div>
        <div class="form-row">
          <div class="field">
            <label [for]="type() + '-phone'">Phone (optional)</label>
            <input [id]="type() + '-phone'" type="tel" formControlName="phone" autocomplete="tel" />
          </div>
          @if (type() === 'catering') {
            <div class="field">
              <label for="event_type">Type of event</label>
              <input id="event_type" formControlName="event_type" placeholder="Birthday, wedding, office lunch…" />
            </div>
          }
        </div>
        @if (type() === 'catering') {
          <div class="form-row">
            <div class="field">
              <label for="event_date">Event date</label>
              <input id="event_date" type="date" formControlName="event_date" [min]="today" />
            </div>
            <div class="field">
              <label for="guest_count">Number of guests</label>
              <input id="guest_count" type="number" min="1" formControlName="guest_count" />
            </div>
          </div>
        }
        <div class="field">
          <label [for]="type() + '-message'">{{ type() === 'catering' ? 'Tell us about your event' : 'How can we help?' }}</label>
          <textarea [id]="type() + '-message'" formControlName="message" rows="5"></textarea>
          @if (form.controls.message.touched && form.controls.message.invalid) {
            <span class="error">Please add a few details (at least 10 characters).</span>
          }
        </div>
        <!-- Honeypot: hidden from people, irresistible to bots. -->
        <div class="hp" aria-hidden="true">
          <label>Website <input formControlName="website" tabindex="-1" autocomplete="off" /></label>
        </div>
        <button type="submit" class="btn" [disabled]="sending()">
          {{ sending() ? 'Sending…' : type() === 'catering' ? 'Request my quote' : 'Send message' }}
        </button>
      </form>
    }
  `,
  styles: `
    .hp {
      position: absolute;
      left: -9999px;
      width: 1px;
      height: 1px;
      overflow: hidden;
    }
  `,
})
export class InquiryForm {
  readonly type = input<'contact' | 'catering'>('contact');

  private readonly api = inject(ApiService);
  protected readonly sending = signal(false);
  protected readonly sent = signal(false);
  protected readonly error = signal<string | null>(null);
  protected readonly today = new Date().toISOString().slice(0, 10);

  protected readonly form = inject(FormBuilder).nonNullable.group({
    name: ['', [Validators.required, Validators.maxLength(160)]],
    email: ['', [Validators.required, Validators.email]],
    phone: [''],
    event_type: [''],
    event_date: [''],
    guest_count: [null as number | null],
    message: ['', [Validators.required, Validators.minLength(10), Validators.maxLength(4000)]],
    website: [''],
  });

  submit(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      this.error.set('Please fill in your name, a valid email and a short message.');
      return;
    }
    this.sending.set(true);
    this.error.set(null);
    const v = this.form.getRawValue();
    this.api.post('/inquiries', { ...v, type: this.type(), guest_count: v.guest_count ? Number(v.guest_count) : null }).subscribe({
      next: () => {
        this.sent.set(true);
        this.sending.set(false);
      },
      error: (err: HttpErrorResponse) => {
        this.sending.set(false);
        this.error.set((err.error as ApiResponse<unknown>)?.message ?? 'We couldn’t send that. Please try again or call us.');
      },
    });
  }
}
