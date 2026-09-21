import { HttpErrorResponse } from '@angular/common/http';
import { ChangeDetectionStrategy, Component, computed, effect, inject, signal } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { catchError, of } from 'rxjs';
import { ApiService } from '../../core/api.service';
import { AuthStore } from '../../core/auth.store';
import { CartStore } from '../../core/cart.store';
import { CatalogService } from '../../core/catalog.service';
import { ApiResponse, CheckoutPayload, FulfilmentMethod } from '../../core/models';
import { SeoService } from '../../core/seo.service';
import { MoneyPipe } from '../../shared/money.pipe';

interface Quote {
  subtotal: number;
  discount: number;
  delivery_fee: number;
  tax: number;
  total: number;
  currency: string;
  tax_label: string;
  max_lead_hours: number;
  coupon_message: string | null;
}

interface DateChoice {
  value: string;
  weekday: string;
  day: string;
  month: string;
}

@Component({
  selector: 'app-checkout',
  imports: [ReactiveFormsModule, RouterLink, MoneyPipe],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './checkout.html',
  styleUrl: './checkout.scss',
})
export class Checkout {
  private readonly fb = inject(FormBuilder);
  private readonly api = inject(ApiService);
  private readonly auth = inject(AuthStore);
  protected readonly cart = inject(CartStore);

  protected readonly settings = toSignal(inject(CatalogService).settings().pipe(catchError(() => of(null))), { initialValue: null });
  protected readonly cancelled = inject(ActivatedRoute).snapshot.queryParamMap.has('cancelled');

  protected readonly method = signal<FulfilmentMethod>('pickup');
  protected readonly couponCode = signal('');
  protected readonly quote = signal<Quote | null>(null);
  protected readonly submitting = signal(false);
  protected readonly error = signal<string | null>(null);
  protected readonly fieldErrors = signal<Record<string, string[]>>({});

  protected readonly form = this.fb.nonNullable.group({
    first_name: ['', [Validators.required, Validators.maxLength(80)]],
    last_name: ['', [Validators.required, Validators.maxLength(80)]],
    email: ['', [Validators.required, Validators.email]],
    phone: ['', [Validators.required, Validators.minLength(7)]],
    date: ['', Validators.required],
    time_slot: ['', Validators.required],
    address_line1: [''],
    address_line2: [''],
    city: [''],
    postal_code: [''],
    notes: ['', Validators.maxLength(2000)],
    coupon: [''],
  });

  /** The next dates we actually cook on, far enough out to respect the longest lead time in the cart. */
  protected readonly dates = computed<DateChoice[]>(() => {
    const settings = this.settings();
    if (!settings) {
      return [];
    }
    const leadHours = Math.max(settings.min_lead_hours, this.quote()?.max_lead_hours ?? 0);
    const earliest = Date.now() + leadHours * 3_600_000;
    const choices: DateChoice[] = [];
    const cursor = new Date();
    cursor.setHours(23, 59, 0, 0);
    for (let i = 0; i < 60 && choices.length < 8; i++, cursor.setDate(cursor.getDate() + 1)) {
      const isoWeekday = cursor.getDay() === 0 ? 7 : cursor.getDay();
      if (cursor.getTime() < earliest || !settings.pickup_days.includes(isoWeekday)) {
        continue;
      }
      choices.push({
        value: `${cursor.getFullYear()}-${String(cursor.getMonth() + 1).padStart(2, '0')}-${String(cursor.getDate()).padStart(2, '0')}`,
        weekday: cursor.toLocaleDateString('en-CA', { weekday: 'short' }),
        day: String(cursor.getDate()),
        month: cursor.toLocaleDateString('en-CA', { month: 'short' }),
      });
    }
    return choices;
  });

  constructor() {
    inject(SeoService).set({ title: 'Checkout', description: 'Choose your slot and pay securely.', noindex: true });

    const user = this.auth.user();
    if (user) {
      this.form.patchValue({ first_name: user.first_name, last_name: user.last_name, email: user.email, phone: user.phone ?? '' });
    }

    // Live totals: re-priced by the server whenever the cart, method or coupon changes.
    effect(() => {
      const items = this.cart.lines().map((l) => ({ product_id: l.product_id, option_id: l.option_id, quantity: l.quantity }));
      const method = this.method();
      const coupon_code = this.couponCode();
      if (items.length === 0) {
        this.quote.set(null);
        return;
      }
      this.api.post<Quote>('/checkout/quote', { items, method, coupon_code }).subscribe({
        next: (q) => this.quote.set(q),
        error: (err: HttpErrorResponse) => this.error.set(this.messageFrom(err)),
      });
    });

    // Drop a previously chosen date if it's no longer offered (e.g. a longer-lead item was added).
    effect(() => {
      const dates = this.dates();
      const current = this.form.controls.date.value;
      if (current && dates.length && !dates.some((d) => d.value === current)) {
        this.form.controls.date.setValue('');
      }
    });
  }

  setMethod(method: FulfilmentMethod): void {
    this.method.set(method);
    const required = method === 'delivery' ? [Validators.required] : [];
    for (const name of ['address_line1', 'city', 'postal_code'] as const) {
      this.form.controls[name].setValidators(required);
      this.form.controls[name].updateValueAndValidity();
    }
  }

  applyCoupon(): void {
    this.couponCode.set(this.form.controls.coupon.value.trim());
  }

  errorFor(field: string): string | null {
    return this.fieldErrors()[field]?.[0] ?? null;
  }

  submit(): void {
    this.error.set(null);
    this.fieldErrors.set({});
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      this.error.set('Please fill in the highlighted fields.');
      return;
    }

    const v = this.form.getRawValue();
    const payload: CheckoutPayload = {
      items: this.cart.lines().map((l) => ({ product_id: l.product_id, option_id: l.option_id, quantity: l.quantity })),
      customer: { first_name: v.first_name, last_name: v.last_name, email: v.email, phone: v.phone },
      fulfilment: {
        method: this.method(),
        date: v.date,
        time_slot: v.time_slot,
        ...(this.method() === 'delivery'
          ? { address_line1: v.address_line1, address_line2: v.address_line2, city: v.city, postal_code: v.postal_code }
          : {}),
      },
      notes: v.notes,
      coupon_code: this.couponCode(),
    };

    this.submitting.set(true);
    this.api.post<{ order_id: string; checkout_url: string }>('/checkout', payload).subscribe({
      next: (res) => {
        // The cart is cleared on the confirmation page, once Stripe sends the buyer back.
        window.location.href = res.checkout_url;
      },
      error: (err: HttpErrorResponse) => {
        this.submitting.set(false);
        this.fieldErrors.set((err.error as ApiResponse<unknown>)?.errors ?? {});
        this.error.set(this.messageFrom(err));
        window.scrollTo({ top: 0, behavior: 'smooth' });
      },
    });
  }

  private messageFrom(err: HttpErrorResponse): string {
    return (err.error as ApiResponse<unknown>)?.message ?? 'Something went wrong. Please try again, or call us to order.';
  }
}
