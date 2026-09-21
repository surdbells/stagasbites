import { DatePipe } from '@angular/common';
import { ChangeDetectionStrategy, Component, inject } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { RouterLink } from '@angular/router';
import { catchError, of } from 'rxjs';
import { ApiService } from '../../core/api.service';
import { AuthStore } from '../../core/auth.store';
import { Order } from '../../core/models';
import { SeoService } from '../../core/seo.service';
import { MoneyPipe } from '../../shared/money.pipe';

@Component({
  selector: 'app-account-dashboard',
  imports: [RouterLink, MoneyPipe, DatePipe],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <section class="page-hero">
      <div class="container head">
        <div>
          <p class="eyebrow">My account</p>
          <h1>Hi, {{ auth.user()?.first_name }}</h1>
        </div>
        <div class="head__actions">
          @if (auth.isAdmin()) {
            <a routerLink="/admin" class="btn btn--gold btn--sm">Open admin</a>
          }
          <button type="button" class="btn btn--ghost btn--sm" (click)="auth.logout()">Sign out</button>
        </div>
      </div>
    </section>

    <section class="section container">
      <h2>Your orders</h2>
      @if (orders(); as list) {
        @if (list.length === 0) {
          <div class="card empty">
            <p class="text-soft">No orders yet. Your first tray is waiting.</p>
            <a routerLink="/menu" class="btn">Browse the menu</a>
          </div>
        } @else {
          <ul class="orders">
            @for (o of list; track o.id) {
              <li>
                <a class="card order" [routerLink]="['/order', o.id]">
                  <div>
                    <strong>{{ o.order_number }}</strong>
                    <span class="text-soft">{{ o.fulfilment_method === 'delivery' ? 'Delivery' : 'Pickup' }} · {{ o.fulfilment_date | date: 'EEE, MMM d' }}</span>
                  </div>
                  <span class="text-soft order__items">{{ summary(o) }}</span>
                  <span class="chip" [attr.data-status]="o.status">{{ o.status.replace('_', ' ') }}</span>
                  <strong>{{ o.total | money: o.currency }}</strong>
                </a>
              </li>
            }
          </ul>
        }
      } @else {
        <div class="skeleton" style="height: 6rem"></div>
      }
    </section>
  `,
  styles: `
    .head {
      display: flex;
      flex-wrap: wrap;
      align-items: end;
      justify-content: space-between;
      gap: 1rem;

      h1 {
        margin: 0;
      }

      &__actions {
        display: flex;
        gap: 0.6rem;
      }
    }

    .empty {
      text-align: center;
    }

    .orders {
      list-style: none;
      margin: 0;
      padding: 0;
      display: grid;
      gap: 0.8rem;
    }

    .order {
      display: grid;
      grid-template-columns: 1.2fr 2fr auto auto;
      align-items: center;
      gap: 1.5rem;
      padding-block: 1.2rem;
      transition: border-color 0.25s;

      &:hover {
        border-color: var(--gold-400);
      }

      div {
        display: grid;
      }

      &__items {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
      }
    }

    .chip[data-status='paid'],
    .chip[data-status='ready'],
    .chip[data-status='completed'] {
      color: var(--leaf-500);
      border-color: rgba(79, 157, 105, 0.5);
    }

    .chip[data-status='cancelled'],
    .chip[data-status='refunded'] {
      color: var(--ember-400);
      border-color: rgba(232, 82, 63, 0.5);
    }

    @media (max-width: 800px) {
      .order {
        grid-template-columns: 1fr auto;

        &__items {
          display: none;
        }
      }
    }
  `,
})
export class AccountDashboard {
  protected readonly auth = inject(AuthStore);
  protected readonly orders = toSignal(inject(ApiService).get<Order[]>('/account/orders').pipe(catchError(() => of([] as Order[]))));

  constructor() {
    inject(SeoService).set({ title: 'My account', description: 'Your orders and details.', noindex: true });
  }

  summary(order: Order): string {
    return order.items.map((i) => `${i.quantity}× ${i.name}`).join(', ');
  }
}
