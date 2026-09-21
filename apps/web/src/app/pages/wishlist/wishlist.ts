import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { CatalogService } from '../../core/catalog.service';
import { Product } from '../../core/models';
import { SeoService } from '../../core/seo.service';
import { WishlistStore } from '../../core/wishlist.store';
import { ProductCard } from '../../shared/product-card';

@Component({
  selector: 'app-wishlist',
  imports: [RouterLink, ProductCard],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <section class="page-hero">
      <div class="container">
        <p class="eyebrow">Saved for later</p>
        <h1>Your favourites</h1>
        <p class="lead">Tap the heart on any dish to keep it here for your next order.</p>
      </div>
    </section>

    <section class="section container">
      @if (loading()) {
        <div class="grid">
          @for (s of [0, 1, 2, 3]; track s) { <div class="skeleton" style="aspect-ratio: 3 / 4"></div> }
        </div>
      } @else if (visible().length) {
        <div class="grid">
          @for (p of visible(); track p.id) { <app-product-card [product]="p" /> }
        </div>
      } @else {
        <div class="empty">
          <h2>Nothing saved yet</h2>
          <a routerLink="/menu" class="btn">Browse the menu</a>
        </div>
      }
    </section>
  `,
  styles: `
    .grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
      gap: 1.4rem;
    }

    .empty {
      text-align: center;
      padding: 2rem 0;
    }
  `,
})
export class Wishlist {
  private readonly wishlist = inject(WishlistStore);
  private readonly products = signal<Product[]>([]);
  protected readonly loading = signal(true);

  /** Un-hearting a card removes it straight away, without a refetch. */
  protected readonly visible = computed(() => this.products().filter((p) => this.wishlist.has(p.id)));

  constructor() {
    inject(SeoService).set({ title: 'Your favourites', description: 'Dishes you have saved for later.', noindex: true });

    const ids = this.wishlist.ids();
    if (ids.length === 0) {
      this.loading.set(false);
      return;
    }
    inject(CatalogService)
      .products({ ids: ids.join(','), per_page: 60 })
      .subscribe({
        next: (res) => {
          this.products.set(res.data);
          this.loading.set(false);
        },
        error: () => this.loading.set(false),
      });
  }
}
