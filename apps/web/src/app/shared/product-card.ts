import { ChangeDetectionStrategy, Component, computed, inject, input } from '@angular/core';
import { RouterLink } from '@angular/router';
import { CartStore } from '../core/cart.store';
import { Product } from '../core/models';
import { MoneyPipe } from './money.pipe';

@Component({
  selector: 'app-product-card',
  imports: [RouterLink, MoneyPipe],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <article class="pcard">
      <a class="pcard__media" [routerLink]="['/product', product().slug]" [attr.aria-label]="product().name">
        <img [src]="product().image_url || '/placeholder.svg'" [alt]="product().name" width="480" height="480"
             [attr.loading]="eager() ? 'eager' : 'lazy'" decoding="async" />
        @if (product().tags[0]; as tag) {
          <span class="pcard__tag">{{ tag }}</span>
        }
        @if (product().spice_level > 0) {
          <span class="pcard__spice" [attr.aria-label]="'Spice level ' + product().spice_level + ' of 3'">
            @for (i of spice(); track i) {
              <svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor" aria-hidden="true"><path d="M12 2c1 5-5 7-5 13a5 5 0 0 0 10 0c0-2-1-3-1-5 3 2 5 5 5 8a9 9 0 0 1-18 0C3 10 10 9 12 2Z"/></svg>
            }
          </span>
        }
      </a>
      <div class="pcard__body">
        @if (product().category; as cat) {
          <span class="pcard__cat">{{ cat.name }}</span>
        }
        <h3><a [routerLink]="['/product', product().slug]">{{ product().name }}</a></h3>
        <p>{{ product().short_description }}</p>
        <div class="pcard__foot">
          <span class="pcard__price"><small>from</small> {{ product().price_from | money }}</span>
          <button type="button" class="pcard__add" (click)="quickAdd()" [attr.aria-label]="'Add ' + product().name + ' to cart'">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
          </button>
        </div>
      </div>
    </article>
  `,
  styleUrl: './product-card.scss',
})
export class ProductCard {
  readonly product = input.required<Product>();
  readonly eager = input(false);
  private readonly cart = inject(CartStore);

  protected readonly spice = computed(() => Array.from({ length: this.product().spice_level }, (_, i) => i));

  quickAdd(): void {
    const product = this.product();
    const option = product.options.find((o) => o.is_default) ?? product.options[0];
    if (option) {
      this.cart.add(product, option, product.min_quantity);
    }
  }
}
