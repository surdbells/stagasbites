import { ChangeDetectionStrategy, Component, HostListener, inject } from '@angular/core';
import { RouterLink } from '@angular/router';
import { CartStore } from '../core/cart.store';
import { MoneyPipe } from '../shared/money.pipe';

@Component({
  selector: 'app-cart-drawer',
  imports: [RouterLink, MoneyPipe],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './cart-drawer.html',
  styleUrl: './cart-drawer.scss',
})
export class CartDrawer {
  protected readonly cart = inject(CartStore);

  @HostListener('document:keydown.escape')
  close(): void {
    this.cart.drawerOpen.set(false);
  }
}
