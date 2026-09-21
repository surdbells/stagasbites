import { DatePipe } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ApiService } from '../core/api.service';
import { ApiResponse } from '../core/models';
import { MoneyPipe } from '../shared/money.pipe';

interface Coupon {
  id: string;
  code: string;
  type: 'percent' | 'fixed';
  value: number;
  min_subtotal: number;
  max_redemptions: number | null;
  times_redeemed: number;
  expires_at: string | null;
  is_active: boolean;
}

@Component({
  selector: 'app-admin-coupons',
  imports: [ReactiveFormsModule, MoneyPipe, DatePipe],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <div class="adm-head"><h1>Coupons</h1></div>
    @if (message(); as m) { <div class="alert alert--error">{{ m }}</div> }

    <div class="adm-grid">
      <div class="adm-table-wrap">
        <table>
          <thead><tr><th>Code</th><th>Discount</th><th>Min spend</th><th>Used</th><th>Expires</th><th>Active</th><th></th></tr></thead>
          <tbody>
            @for (c of coupons(); track c.id) {
              <tr>
                <td><strong>{{ c.code }}</strong></td>
                <td>{{ c.type === 'percent' ? c.value + '%' : (c.value | money) }}</td>
                <td>{{ c.min_subtotal ? (c.min_subtotal | money) : '—' }}</td>
                <td>{{ c.times_redeemed }}{{ c.max_redemptions ? ' / ' + c.max_redemptions : '' }}</td>
                <td>{{ c.expires_at ? (c.expires_at | date: 'mediumDate') : 'Never' }}</td>
                <td>{{ c.is_active ? 'Yes' : 'No' }}</td>
                <td><button type="button" class="link-btn link-btn--danger" (click)="remove(c)">Delete</button></td>
              </tr>
            } @empty {
              <tr><td colspan="7" class="adm-empty">No coupons yet.</td></tr>
            }
          </tbody>
        </table>
      </div>

      <form class="card" [formGroup]="form" (ngSubmit)="save()" novalidate>
        <h2>New coupon</h2>
        <div class="field"><label for="code">Code</label><input id="code" formControlName="code" style="text-transform: uppercase" /></div>
        <div class="form-row">
          <div class="field">
            <label for="type">Type</label>
            <select id="type" formControlName="type"><option value="percent">Percent off</option><option value="fixed">Dollars off</option></select>
          </div>
          <div class="field">
            <label for="value">{{ form.controls.type.value === 'percent' ? 'Percent' : 'Dollars' }}</label>
            <input id="value" type="number" min="1" step="0.01" formControlName="value" />
          </div>
        </div>
        <div class="field"><label for="min">Minimum spend in dollars (optional)</label><input id="min" type="number" min="0" formControlName="min_subtotal" /></div>
        <div class="field"><label for="max">Max redemptions (optional)</label><input id="max" type="number" min="1" formControlName="max_redemptions" /></div>
        <div class="field"><label for="exp">Expires (optional)</label><input id="exp" type="date" formControlName="expires_at" /></div>
        <button type="submit" class="btn btn--sm">Create coupon</button>
      </form>
    </div>
  `,
})
export class AdminCoupons {
  private readonly api = inject(ApiService);
  protected readonly coupons = signal<Coupon[]>([]);
  protected readonly message = signal<string | null>(null);

  protected readonly form = inject(FormBuilder).nonNullable.group({
    code: ['', [Validators.required, Validators.pattern(/^[A-Za-z0-9_-]+$/)]],
    type: ['percent' as 'percent' | 'fixed'],
    value: [10, [Validators.required, Validators.min(0.01)]],
    min_subtotal: [null as number | null],
    max_redemptions: [null as number | null],
    expires_at: [''],
  });

  constructor() {
    this.load();
  }

  save(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }
    const v = this.form.getRawValue();
    const body = {
      code: v.code,
      type: v.type,
      // Percent stays as-is; dollar amounts are stored in cents.
      value: v.type === 'percent' ? Math.round(Number(v.value)) : Math.round(Number(v.value) * 100),
      min_subtotal: Math.round(Number(v.min_subtotal ?? 0) * 100),
      max_redemptions: v.max_redemptions ? Number(v.max_redemptions) : null,
      expires_at: v.expires_at ? `${v.expires_at} 23:59:59` : null,
      is_active: true,
    };
    this.api.post('/admin/coupons', body).subscribe({
      next: () => {
        this.message.set(null);
        this.form.reset();
        this.load();
      },
      error: (err: HttpErrorResponse) => {
        const res = err.error as ApiResponse<unknown>;
        this.message.set(Object.values(res?.errors ?? {})[0]?.[0] ?? res?.message ?? 'Could not save the coupon.');
      },
    });
  }

  remove(c: Coupon): void {
    if (confirm(`Delete coupon ${c.code}?`)) {
      this.api.delete(`/admin/coupons/${c.id}`).subscribe(() => this.load());
    }
  }

  private load(): void {
    this.api.get<Coupon[]>('/admin/coupons').subscribe((list) => this.coupons.set(list));
  }
}
