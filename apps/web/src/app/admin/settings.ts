import { HttpErrorResponse } from '@angular/common/http';
import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule } from '@angular/forms';
import { ApiService } from '../core/api.service';
import { ApiResponse, StoreSettings } from '../core/models';

const WEEKDAYS = [
  { iso: 1, label: 'Mon' },
  { iso: 2, label: 'Tue' },
  { iso: 3, label: 'Wed' },
  { iso: 4, label: 'Thu' },
  { iso: 5, label: 'Fri' },
  { iso: 6, label: 'Sat' },
  { iso: 7, label: 'Sun' },
];

@Component({
  selector: 'app-admin-settings',
  imports: [ReactiveFormsModule],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <div class="adm-head"><h1>Store settings</h1></div>
    @if (message(); as m) { <div class="alert" [class.alert--error]="failed()" [class.alert--success]="!failed()">{{ m }}</div> }

    <form [formGroup]="form" (ngSubmit)="save()" class="adm-grid" novalidate>
      <div class="card">
        <h2>Ordering</h2>
        <p class="text-soft">Days you cook for</p>
        <div class="adm-actions" style="margin-bottom: 1.2rem">
          @for (d of weekdays; track d.iso) {
            <label class="check"><input type="checkbox" [checked]="days().includes(d.iso)" (change)="toggleDay(d.iso)" /> {{ d.label }}</label>
          }
        </div>
        <div class="field"><label for="slots">Time slots (one per line)</label><textarea id="slots" formControlName="time_slots" rows="5"></textarea></div>
        <div class="field"><label for="lead">Minimum notice (hours)</label><input id="lead" type="number" min="0" formControlName="min_lead_hours" /></div>
        <div class="field"><label for="pickup">Pickup address shown at checkout</label><input id="pickup" formControlName="pickup_address" /></div>

        <h2 style="margin-top: 2rem">Delivery &amp; tax</h2>
        <div class="form-row">
          <div class="field"><label for="fee">Delivery fee ($)</label><input id="fee" type="number" min="0" step="0.01" formControlName="delivery_fee" /></div>
          <div class="field"><label for="free">Free delivery over ($, blank = never)</label><input id="free" type="number" min="0" step="0.01" formControlName="free_delivery_threshold" /></div>
        </div>
        <div class="field"><label for="areas">Delivery areas (one city per line)</label><textarea id="areas" formControlName="delivery_areas" rows="4"></textarea></div>
        <div class="form-row">
          <div class="field"><label for="tax">Tax rate (%)</label><input id="tax" type="number" min="0" max="100" step="0.001" formControlName="tax_rate" /></div>
          <div class="field"><label for="taxl">Tax label</label><input id="taxl" formControlName="tax_label" /></div>
        </div>
      </div>

      <div style="display: grid; gap: 1.5rem">
        <div class="card">
          <h2>Contact &amp; social</h2>
          <div class="field"><label for="phone">Phone</label><input id="phone" formControlName="phone" /></div>
          <div class="field"><label for="email">Email</label><input id="email" type="email" formControlName="email" /></div>
          <div class="field"><label for="wa">WhatsApp number (digits with country code)</label><input id="wa" formControlName="whatsapp" placeholder="16476738796" /></div>
          <div class="field"><label for="ig">Instagram URL</label><input id="ig" formControlName="instagram" /></div>
          <div class="field"><label for="fb">Facebook URL</label><input id="fb" formControlName="facebook" /></div>
          <div class="field"><label for="gr">Google reviews URL</label><input id="gr" formControlName="google_reviews_url" /></div>
        </div>
        <button type="submit" class="btn" [disabled]="busy()">{{ busy() ? 'Saving…' : 'Save settings' }}</button>
      </div>
    </form>
  `,
})
export class AdminSettings {
  private readonly api = inject(ApiService);
  protected readonly weekdays = WEEKDAYS;
  protected readonly days = signal<number[]>([]);
  protected readonly busy = signal(false);
  protected readonly failed = signal(false);
  protected readonly message = signal<string | null>(null);

  protected readonly form = inject(FormBuilder).nonNullable.group({
    time_slots: [''],
    min_lead_hours: [48],
    pickup_address: [''],
    delivery_fee: [0],
    free_delivery_threshold: [null as number | null],
    delivery_areas: [''],
    tax_rate: [13],
    tax_label: [''],
    phone: [''],
    email: [''],
    whatsapp: [''],
    instagram: [''],
    facebook: [''],
    google_reviews_url: [''],
  });

  constructor() {
    this.api.get<StoreSettings>('/settings').subscribe((s) => this.fill(s));
  }

  toggleDay(iso: number): void {
    this.days.update((d) => (d.includes(iso) ? d.filter((x) => x !== iso) : [...d, iso].sort()));
  }

  save(): void {
    const v = this.form.getRawValue();
    const lines = (text: string) => text.split('\n').map((l) => l.trim()).filter(Boolean);
    const body = {
      ...v,
      pickup_days: this.days(),
      time_slots: lines(v.time_slots),
      delivery_areas: lines(v.delivery_areas),
      min_lead_hours: Number(v.min_lead_hours),
      // The form edits dollars and percent; the API stores cents and a fraction.
      delivery_fee: Math.round(Number(v.delivery_fee) * 100),
      free_delivery_threshold: v.free_delivery_threshold === null || `${v.free_delivery_threshold}` === '' ? null : Math.round(Number(v.free_delivery_threshold) * 100),
      tax_rate: Number(v.tax_rate) / 100,
    };
    this.busy.set(true);
    this.api.put<StoreSettings>('/admin/settings', body).subscribe({
      next: (s) => {
        this.fill(s);
        this.busy.set(false);
        this.failed.set(false);
        this.message.set('Settings saved.');
      },
      error: (err: HttpErrorResponse) => {
        this.busy.set(false);
        this.failed.set(true);
        this.message.set((err.error as ApiResponse<unknown>)?.message ?? 'Could not save settings.');
      },
    });
  }

  private fill(s: StoreSettings): void {
    this.days.set(s.pickup_days);
    this.form.patchValue({
      time_slots: s.time_slots.join('\n'),
      min_lead_hours: s.min_lead_hours,
      pickup_address: s.pickup_address,
      delivery_fee: s.delivery_fee / 100,
      free_delivery_threshold: s.free_delivery_threshold === null ? null : s.free_delivery_threshold / 100,
      delivery_areas: s.delivery_areas.join('\n'),
      tax_rate: +(s.tax_rate * 100).toFixed(3),
      tax_label: s.tax_label,
      phone: s.phone,
      email: s.email,
      whatsapp: s.whatsapp ?? '',
      instagram: s.instagram ?? '',
      facebook: s.facebook ?? '',
      google_reviews_url: s.google_reviews_url ?? '',
    });
  }
}
