import { ChangeDetectionStrategy, Component, computed, effect, inject, input, signal } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { catchError, of } from 'rxjs';
import { CatalogService, ProductQuery } from '../../core/catalog.service';
import { PageMeta, Product } from '../../core/models';
import { SeoService } from '../../core/seo.service';
import { ProductCard } from '../../shared/product-card';

const PER_PAGE = 12;

@Component({
  selector: 'app-menu',
  imports: [RouterLink, FormsModule, ProductCard],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './menu.html',
  styleUrl: './menu.scss',
})
export class Menu {
  /** Bound from the `/menu/:category` route param. */
  readonly category = input<string>();
  /** Bound from the `?q=` query param (hero search, shared links). */
  readonly q = input<string>();

  private readonly catalog = inject(CatalogService);
  private readonly seo = inject(SeoService);

  protected readonly categories = toSignal(this.catalog.categories().pipe(catchError(() => of([]))), { initialValue: [] });
  protected readonly activeCategory = computed(() => this.categories().find((c) => c.slug === this.category()) ?? null);

  protected readonly search = signal('');
  protected readonly sort = signal<NonNullable<ProductQuery['sort']>>('featured');
  protected readonly products = signal<Product[]>([]);
  protected readonly meta = signal<PageMeta | null>(null);
  protected readonly loading = signal(true);
  protected readonly failed = signal(false);
  protected readonly hasMore = computed(() => (this.meta()?.page ?? 1) < (this.meta()?.last_page ?? 1));

  private searchTimer?: ReturnType<typeof setTimeout>;
  private requestId = 0;

  constructor() {
    effect(() => this.search.set(this.q()?.trim() ?? ''));

    // Reload from page 1 whenever the category, sort or (debounced) search changes.
    effect(() => {
      this.category();
      this.sort();
      this.search();
      this.load(1);
    });

    effect(() => {
      const cat = this.activeCategory();
      const slug = this.category();
      if (slug && !cat && this.categories().length === 0) {
        return; // categories still loading
      }
      this.seo.set({
        title: cat ? `${cat.name} — Order Online in Oakville & the GTA` : 'Menu — Small Chops, Grills & Pastries',
        description:
          cat?.meta_description ??
          cat?.description ??
          "Browse the full Staga's Bites menu: samosas, spring rolls, puff puff, meat pies, asun, suya, grilled tilapia and party platters. Order online for pickup or delivery in the GTA.",
        path: cat ? `/menu/${cat.slug}` : '/menu',
        image: cat?.image_url,
        jsonLd: {
          '@context': 'https://schema.org',
          '@type': 'BreadcrumbList',
          itemListElement: [
            { '@type': 'ListItem', position: 1, name: 'Home', item: this.seo.absolute('/') },
            { '@type': 'ListItem', position: 2, name: 'Menu', item: this.seo.absolute('/menu') },
            ...(cat ? [{ '@type': 'ListItem', position: 3, name: cat.name, item: this.seo.absolute(`/menu/${cat.slug}`) }] : []),
          ],
        },
      });
    });
  }

  onSearch(value: string): void {
    clearTimeout(this.searchTimer);
    this.searchTimer = setTimeout(() => this.search.set(value.trim()), 300);
  }

  loadMore(): void {
    this.load((this.meta()?.page ?? 1) + 1);
  }

  private load(page: number): void {
    const id = ++this.requestId;
    this.loading.set(true);
    this.failed.set(false);
    this.catalog
      .products({ category: this.category(), search: this.search(), sort: this.sort(), page, per_page: PER_PAGE })
      .subscribe({
        next: (res) => {
          if (id !== this.requestId) {
            return; // a newer request superseded this one
          }
          this.products.update((current) => (page === 1 ? res.data : [...current, ...res.data]));
          this.meta.set(res.meta ?? null);
          this.loading.set(false);
        },
        error: () => {
          if (id === this.requestId) {
            this.failed.set(true);
            this.loading.set(false);
          }
        },
      });
  }
}
