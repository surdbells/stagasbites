import { ChangeDetectionStrategy, Component, inject } from '@angular/core';
import { RouterLink } from '@angular/router';
import { CartStore } from '../../core/cart.store';
import { SeoService } from '../../core/seo.service';
import { MoneyPipe } from '../../shared/money.pipe';

@Component({
  selector: 'app-cart-page',
  imports: [RouterLink, MoneyPipe],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <section class="page-hero">
      <div class="container">
        <h1>Your order</h1>
        <p class="lead">Review your tray before choosing a pickup or delivery slot.</p>
      </div>
    </section>

    <section class="container cart">
      @if (cart.isEmpty()) {
        <div class="cart__empty">
          <h2>Your tray is empty</h2>
          <p class="text-soft">Let’s fix that.</p>
          <a routerLink="/menu" class="btn">Browse the menu</a>
        </div>
      } @else {
        <ul class="cart__lines">
          @for (line of cart.lines(); track line.option_id) {
            <li class="row">
              <a [routerLink]="['/product', line.slug]" class="row__img">
                <img [src]="line.image_url || '/placeholder.svg'" [alt]="line.name" width="110" height="110" loading="lazy" />
              </a>
              <div class="row__info">
                <a [routerLink]="['/product', line.slug]"><h2>{{ line.name }}</h2></a>
                <span class="text-soft">{{ line.option_label }} · {{ line.unit_price | money }} each</span>
                <button type="button" class="row__remove" (click)="cart.remove(line.option_id)">Remove</button>
              </div>
              <div class="qty" role="group" [attr.aria-label]="'Quantity of ' + line.name">
                <button type="button" (click)="cart.setQuantity(line.option_id, line.quantity - 1)" [disabled]="line.quantity <= line.min_quantity" aria-label="Decrease">−</button>
                <span>{{ line.quantity }}</span>
                <button type="button" (click)="cart.setQuantity(line.option_id, line.quantity + 1)" aria-label="Increase">+</button>
              </div>
              <strong class="row__total">{{ line.unit_price * line.quantity | money }}</strong>
            </li>
          }
        </ul>

        <aside class="card summary">
          <div class="summary__row"><span>Subtotal</span><strong>{{ cart.subtotal() | money }}</strong></div>
          <p class="text-soft">HST and any delivery fee are calculated at checkout.</p>
          <a routerLink="/checkout" class="btn btn--block">Continue to checkout</a>
          <a routerLink="/menu" class="summary__back">← Keep browsing</a>
        </aside>
      }
    </section>
  `,
  styleUrl: './cart.scss',
})
export class CartPage {
  protected readonly cart = inject(CartStore);

  constructor() {
    inject(SeoService).set({ title: 'Your order', description: 'Review the items in your order.', noindex: true });
  }
}
