import { DatePipe } from '@angular/common';
import { ChangeDetectionStrategy, Component, effect, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { ApiService } from '../core/api.service';
import { Order, PageMeta } from '../core/models';
import { MoneyPipe } from '../shared/money.pipe';

@Component({
  selector: 'app-admin-orders',
  imports: [RouterLink, FormsModule, MoneyPipe, DatePipe],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <div class="adm-head"><h1>Orders</h1></div>

    <div class="adm-filters">
      <input type="search" placeholder="Order #, email or surname" [ngModel]="search()" (ngModelChange)="search.set($event); page.set(1)" />
      <select [ngModel]="status()" (ngModelChange)="status.set($event); page.set(1)" aria-label="Status">
        <option value="">All paid orders</option>
        @for (s of statuses; track s) { <option [value]="s">{{ s.replace('_', ' ') }}</option> }
      </select>
      <input type="date" [ngModel]="date()" (ngModelChange)="date.set($event); page.set(1)" aria-label="Fulfilment date" />
    </div>

    <div class="adm-table-wrap">
      <table>
        <thead><tr><th>Order</th><th>Placed</th><th>Customer</th><th>Fulfilment</th><th>Status</th><th>Total</th></tr></thead>
        <tbody>
          @for (o of orders(); track o.id) {
            <tr>
              <td><a [routerLink]="['/admin/orders', o.id]">{{ o.order_number }}</a></td>
              <td>{{ o.created_at | date: 'MMM d, HH:mm' }}</td>
              <td>{{ o.customer_first_name }} {{ o.customer_last_name }}<br /><small class="text-soft">{{ o.customer_email }}</small></td>
              <td>{{ o.fulfilment_method }} · {{ o.fulfilment_date | date: 'EEE, MMM d' }}<br /><small class="text-soft">{{ o.fulfilment_time_slot }}</small></td>
              <td><span class="status" [attr.data-s]="o.status">{{ o.status.replace('_', ' ') }}</span></td>
              <td>{{ o.total | money: o.currency }}</td>
            </tr>
          } @empty {
            <tr><td colspan="6" class="adm-empty">No orders match.</td></tr>
          }
        </tbody>
      </table>
    </div>

    @if (meta(); as m) {
      @if (m.last_page > 1) {
        <div class="adm-actions" style="margin-top: 1rem; align-items: center">
          <button type="button" class="btn btn--ghost btn--sm" [disabled]="m.page <= 1" (click)="page.set(m.page - 1)">Previous</button>
          <span class="text-soft">Page {{ m.page }} of {{ m.last_page }}</span>
          <button type="button" class="btn btn--ghost btn--sm" [disabled]="m.page >= m.last_page" (click)="page.set(m.page + 1)">Next</button>
        </div>
      }
    }
  `,
})
export class AdminOrders {
  private readonly api = inject(ApiService);
  protected readonly statuses = ['pending_payment', 'paid', 'preparing', 'ready', 'completed', 'cancelled', 'refunded'];
  protected readonly search = signal('');
  protected readonly status = signal('');
  protected readonly date = signal('');
  protected readonly page = signal(1);
  protected readonly orders = signal<Order[]>([]);
  protected readonly meta = signal<PageMeta | null>(null);

  constructor() {
    effect(() => {
      this.api
        .getPage<Order[]>('/admin/orders', { search: this.search(), status: this.status(), date: this.date(), page: this.page() })
        .subscribe((res) => {
          this.orders.set(res.data);
          this.meta.set(res.meta ?? null);
        });
    });
  }
}
