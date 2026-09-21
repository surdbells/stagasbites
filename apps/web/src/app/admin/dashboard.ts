import { DatePipe } from '@angular/common';
import { ChangeDetectionStrategy, Component, inject } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { RouterLink } from '@angular/router';
import { ApiService } from '../core/api.service';
import { Order } from '../core/models';
import { MoneyPipe } from '../shared/money.pipe';

interface Stats {
  revenue_30d: number;
  orders_30d: number;
  open_orders: number;
  upcoming: Order[];
}

@Component({
  selector: 'app-admin-dashboard',
  imports: [RouterLink, MoneyPipe, DatePipe],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <div class="adm-head"><h1>Dashboard</h1></div>
    @if (stats(); as s) {
      <div class="adm-stats">
        <div class="card"><small>Revenue · 30 days</small><strong>{{ s.revenue_30d | money }}</strong></div>
        <div class="card"><small>Orders · 30 days</small><strong>{{ s.orders_30d }}</strong></div>
        <div class="card"><small>Open orders</small><strong>{{ s.open_orders }}</strong></div>
        <a class="card" routerLink="/admin/subscribers"><small>Newsletter</small><strong>View list</strong></a>
      </div>

      <h2>Coming up</h2>
      <div class="adm-table-wrap">
        @if (s.upcoming.length) {
          <table>
            <thead><tr><th>Order</th><th>Customer</th><th>When</th><th>Method</th><th>Status</th><th>Total</th></tr></thead>
            <tbody>
              @for (o of s.upcoming; track o.id) {
                <tr>
                  <td><a [routerLink]="['/admin/orders', o.id]">{{ o.order_number }}</a></td>
                  <td>{{ o.customer_first_name }} {{ o.customer_last_name }}</td>
                  <td>{{ o.fulfilment_date | date: 'EEE, MMM d' }} · {{ o.fulfilment_time_slot }}</td>
                  <td>{{ o.fulfilment_method }}</td>
                  <td><span class="status" [attr.data-s]="o.status">{{ o.status }}</span></td>
                  <td>{{ o.total | money: o.currency }}</td>
                </tr>
              }
            </tbody>
          </table>
        } @else {
          <p class="adm-empty">No open orders. Time for a cup of tea.</p>
        }
      </div>
    } @else {
      <div class="skeleton" style="height: 12rem"></div>
    }
  `,
})
export class AdminDashboard {
  protected readonly stats = toSignal(inject(ApiService).get<Stats>('/admin/dashboard'));
}
