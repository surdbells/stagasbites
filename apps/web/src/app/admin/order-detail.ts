import { DatePipe } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { ChangeDetectionStrategy, Component, effect, inject, input, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { ApiService } from '../core/api.service';
import { ApiResponse, Order, OrderStatus } from '../core/models';
import { MoneyPipe } from '../shared/money.pipe';

@Component({
  selector: 'app-admin-order-detail',
  imports: [RouterLink, FormsModule, MoneyPipe, DatePipe],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <div class="adm-head">
      <h1>{{ order()?.order_number ?? 'Order' }}</h1>
      <a routerLink="/admin/orders" class="link-btn">← All orders</a>
    </div>

    @if (order(); as o) {
      @if (message(); as m) { <div class="alert" [class.alert--error]="failed()" [class.alert--success]="!failed()">{{ m }}</div> }
      <div class="adm-grid">
        <div class="card">
          <h2>Items</h2>
          <table>
            <tbody>
              @for (item of o.items; track $index) {
                <tr>
                  <td>{{ item.quantity }} ×</td>
                  <td>{{ item.name }}<br /><small class="text-soft">{{ item.option_label }}</small></td>
                  <td style="text-align: right">{{ item.line_total | money: o.currency }}</td>
                </tr>
              }
              <tr><td colspan="2">Subtotal</td><td style="text-align: right">{{ o.subtotal | money: o.currency }}</td></tr>
              @if (o.discount) { <tr><td colspan="2">Discount</td><td style="text-align: right">−{{ o.discount | money: o.currency }}</td></tr> }
              @if (o.delivery_fee) { <tr><td colspan="2">Delivery</td><td style="text-align: right">{{ o.delivery_fee | money: o.currency }}</td></tr> }
              <tr><td colspan="2">Tax</td><td style="text-align: right">{{ o.tax | money: o.currency }}</td></tr>
              <tr><td colspan="2"><strong>Total</strong></td><td style="text-align: right"><strong>{{ o.total | money: o.currency }}</strong></td></tr>
            </tbody>
          </table>
          @if (o.notes) { <p style="margin-top: 1rem"><strong>Customer notes:</strong> {{ o.notes }}</p> }
        </div>

        <div style="display: grid; gap: 1.5rem">
          <div class="card">
            <h2>Status</h2>
            <p><span class="status" [attr.data-s]="o.status">{{ o.status.replace('_', ' ') }}</span></p>
            <div class="adm-actions">
              @for (s of nextStatuses; track s) {
                <button type="button" class="btn btn--ghost btn--sm" [disabled]="busy() || o.status === s" (click)="setStatus(s)">Mark {{ s }}</button>
              }
            </div>
            <label class="check" style="margin-top: 1rem"><input type="checkbox" [(ngModel)]="notify" /> Email the customer about this change</label>
          </div>

          <div class="card">
            <h2>{{ o.fulfilment_method === 'delivery' ? 'Delivery' : 'Pickup' }}</h2>
            <p>{{ o.fulfilment_date | date: 'EEEE, MMMM d, y' }}<br />{{ o.fulfilment_time_slot }}</p>
            @if (o.delivery_address) { <p>{{ o.delivery_address }}</p> }
          </div>

          <div class="card">
            <h2>Customer</h2>
            <p>
              {{ o.customer_first_name }} {{ o.customer_last_name }}<br />
              <a [href]="'mailto:' + o.customer_email">{{ o.customer_email }}</a><br />
              <a [href]="'tel:' + o.customer_phone">{{ o.customer_phone }}</a>
            </p>
            <small class="text-soft">Placed {{ o.created_at | date: 'medium' }} @if (o.paid_at) { · paid {{ o.paid_at | date: 'medium' }} }</small>
          </div>
        </div>
      </div>
    } @else {
      <div class="skeleton" style="height: 14rem"></div>
    }
  `,
})
export class AdminOrderDetail {
  readonly id = input.required<string>();
  private readonly api = inject(ApiService);

  protected readonly nextStatuses: OrderStatus[] = ['preparing', 'ready', 'completed', 'cancelled'];
  protected readonly order = signal<Order | null>(null);
  protected readonly busy = signal(false);
  protected readonly message = signal<string | null>(null);
  protected readonly failed = signal(false);
  protected notify = true;

  constructor() {
    effect(() => this.api.get<Order>(`/admin/orders/${this.id()}`).subscribe((o) => this.order.set(o)));
  }

  setStatus(status: OrderStatus): void {
    if (status === 'cancelled' && !confirm('Cancel this order? Refunds must be issued separately in the Stripe dashboard.')) {
      return;
    }
    this.busy.set(true);
    this.api.patch<Order>(`/admin/orders/${this.id()}/status`, { status, notify: this.notify }).subscribe({
      next: (o) => {
        this.order.set(o);
        this.busy.set(false);
        this.failed.set(false);
        this.message.set(`Order marked ${status}.`);
      },
      error: (err: HttpErrorResponse) => {
        this.busy.set(false);
        this.failed.set(true);
        this.message.set((err.error as ApiResponse<unknown>)?.message ?? 'Could not update the order.');
      },
    });
  }
}
