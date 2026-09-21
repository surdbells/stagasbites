import { ChangeDetectionStrategy, Component, computed, effect, inject, input, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { CartStore } from '../../core/cart.store';
import { CatalogService } from '../../core/catalog.service';
import { Product, ProductOption } from '../../core/models';
import { SeoService } from '../../core/seo.service';
import { SITE } from '../../core/site';
import { MoneyPipe } from '../../shared/money.pipe';
import { ProductCard } from '../../shared/product-card';

@Component({
  selector: 'app-product-page',
  imports: [RouterLink, MoneyPipe, ProductCard],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './product.html',
  styleUrl: './product.scss',
})
export class ProductPage {
  /** Bound from the `/product/:slug` route param. */
  readonly slug = input.required<string>();

  private readonly catalog = inject(CatalogService);
  private readonly cart = inject(CartStore);
  private readonly seo = inject(SeoService);

  protected readonly product = signal<Product | null>(null);
  protected readonly related = signal<Product[]>([]);
  protected readonly state = signal<'loading' | 'ready' | 'missing'>('loading');
  protected readonly option = signal<ProductOption | null>(null);
  protected readonly quantity = signal(1);
  protected readonly activeImage = signal<string | null>(null);
  protected readonly added = signal(false);

  protected readonly images = computed(() => {
    const p = this.product();
    return p ? [p.image_url, ...p.gallery].filter((src): src is string => !!src) : [];
  });
  protected readonly lineTotal = computed(() => (this.option()?.price ?? 0) * this.quantity());
  protected readonly spice = computed(() => Array.from({ length: this.product()?.spice_level ?? 0 }, (_, i) => i));

  constructor() {
    effect(() => this.load(this.slug()));
  }

  selectOption(option: ProductOption): void {
    this.option.set(option);
  }

  step(delta: number): void {
    const min = this.product()?.min_quantity ?? 1;
    this.quantity.update((q) => Math.max(min, Math.min(500, q + delta)));
  }

  addToCart(): void {
    const product = this.product();
    const option = this.option();
    if (!product || !option) {
      return;
    }
    this.cart.add(product, option, this.quantity());
    this.added.set(true);
    setTimeout(() => this.added.set(false), 2200);
  }

  private load(slug: string): void {
    this.state.set('loading');
    this.catalog.product(slug).subscribe({
      next: (product) => {
        this.product.set(product);
        this.option.set(product.options.find((o) => o.is_default) ?? product.options[0] ?? null);
        this.quantity.set(product.min_quantity);
        this.activeImage.set(product.image_url);
        this.state.set('ready');
        this.applySeo(product);
        this.loadRelated(product);
      },
      error: () => {
        this.state.set('missing');
        this.seo.set({ title: 'Item not found', description: 'This menu item is no longer available.', noindex: true });
      },
    });
  }

  private loadRelated(product: Product): void {
    this.catalog.products({ category: product.category?.slug, per_page: 5 }).subscribe({
      next: (res) => this.related.set(res.data.filter((p) => p.id !== product.id).slice(0, 4)),
      error: () => this.related.set([]),
    });
  }

  private applySeo(product: Product): void {
    const path = `/product/${product.slug}`;
    const prices = product.options.length ? product.options.map((o) => o.price) : [0];
    const description = product.meta_description ?? product.short_description ?? product.description ?? product.name;

    this.seo.set({
      title: product.meta_title ?? `${product.name}: Order Online in Oakville`,
      description,
      path,
      image: product.image_url,
      type: 'product',
      jsonLd: [
        {
          '@context': 'https://schema.org',
          '@type': 'Product',
          name: product.name,
          description: product.description ?? description,
          image: this.images(),
          category: product.category?.name,
          brand: { '@type': 'Brand', name: SITE.name },
          url: this.seo.absolute(path),
          offers: {
            '@type': 'AggregateOffer',
            priceCurrency: 'CAD',
            lowPrice: (Math.min(...prices) / 100).toFixed(2),
            highPrice: (Math.max(...prices) / 100).toFixed(2),
            offerCount: prices.length,
            availability: 'https://schema.org/PreOrder',
          },
        },
        {
          '@context': 'https://schema.org',
          '@type': 'BreadcrumbList',
          itemListElement: [
            { '@type': 'ListItem', position: 1, name: 'Menu', item: this.seo.absolute('/menu') },
            ...(product.category
              ? [{ '@type': 'ListItem', position: 2, name: product.category.name, item: this.seo.absolute(`/menu/${product.category.slug}`) }]
              : []),
            { '@type': 'ListItem', position: product.category ? 3 : 2, name: product.name, item: this.seo.absolute(path) },
          ],
        },
      ],
    });
  }
}
