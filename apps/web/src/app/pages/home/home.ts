import { ChangeDetectionStrategy, Component, Signal, computed, inject } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { RouterLink } from '@angular/router';
import { catchError, map, of } from 'rxjs';
import { CartStore } from '../../core/cart.store';
import { CatalogService, ProductQuery } from '../../core/catalog.service';
import { Product } from '../../core/models';
import { SeoService } from '../../core/seo.service';
import { SITE } from '../../core/site';
import { GoogleReviews } from '../../shared/google-reviews';
import { LiveSearch } from '../../shared/live-search';
import { MoneyPipe } from '../../shared/money.pipe';
import { ProductCard } from '../../shared/product-card';
import { RevealDirective } from '../../shared/reveal.directive';

@Component({
  selector: 'app-home',
  imports: [RouterLink, ProductCard, RevealDirective, LiveSearch, GoogleReviews, MoneyPipe],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './home.html',
  styleUrls: ['./home.scss', './home-shelves.scss'],
})
export class Home {
  private readonly catalog = inject(CatalogService);
  private readonly seo = inject(SeoService);
  private readonly cart = inject(CartStore);

  protected readonly site = SITE;

  protected readonly categories = toSignal(this.catalog.categories().pipe(catchError(() => of([]))), { initialValue: [] });
  protected readonly featured = this.shelf({ featured: true, per_page: 10 });
  /** Each home page shelf is one small, cacheable catalogue query. */
  protected readonly grills = this.shelf({ category: 'grills', per_page: 8 });
  protected readonly platters = this.shelf({ category: 'platters', per_page: 5 });
  protected readonly mains = this.shelf({ category: 'rice-mains', per_page: 5 });
  protected readonly bargains = this.shelf({ min_price: 1000, max_price: 2000, sort: 'price_asc', per_page: 6 });
  protected readonly drinks = this.shelf({ category: 'drinks', per_page: 5 });
  protected readonly sides = this.shelf({ category: 'sides-sauces', per_page: 5 });
  protected readonly extras = computed(() => [...(this.drinks() ?? []), ...(this.sides() ?? [])]);

  protected readonly marquee = ['Samosas', 'Puff Puff', 'Asun', 'Meat Pies', 'Suya', 'Spring Rolls', 'Grilled Tilapia', 'Scotch Eggs', 'Peppered Wings', 'Party Platters'];

  protected readonly steps = [
    { n: '01', title: 'Build your tray', text: 'Pick your small chops, grills and pastries by the dozen, the tray or the platter.' },
    { n: '02', title: 'Choose your slot', text: 'Select a weekend pickup or delivery window. We need about 48 hours to prep.' },
    { n: '03', title: 'We cook it fresh', text: 'Nothing is made ahead and frozen. Your order is cooked the day you collect it.' },
  ];

  /** Adds the default size straight to the cart from any shelf. */
  quickAdd(product: Product): void {
    const option = product.options.find((o) => o.is_default) ?? product.options[0];
    if (option) {
      this.cart.add(product, option, product.min_quantity);
    }
  }

  scrollRail(rail: HTMLElement, direction: 1 | -1): void {
    rail.scrollBy({ left: direction * rail.clientWidth * 0.8, behavior: 'smooth' });
  }

  private shelf(query: ProductQuery): Signal<Product[] | null> {
    return toSignal(
      this.catalog.products(query).pipe(
        map((r) => r.data),
        catchError(() => of([] as Product[])),
      ),
      { initialValue: null },
    );
  }

  constructor() {
    this.seo.set({
      title: "Staga's Bites | Nigerian Small Chops, Pastries & Grills in Oakville",
      description:
        'Authentic Nigerian small chops, meat pies, puff puff, suya and flame-grilled favourites in Oakville, ON. Pre-order for weekend pickup or delivery, or book us to cater your event.',
      path: '/',
      jsonLd: {
        '@context': 'https://schema.org',
        '@type': ['FoodEstablishment', 'Caterer'],
        '@id': this.seo.absolute('/#business'),
        name: SITE.name,
        url: this.seo.absolute('/'),
        image: this.seo.absolute('/og-default.jpg'),
        logo: this.seo.absolute('/img/brand/icon-512.png'),
        telephone: '+1-647-673-8796',
        email: SITE.email,
        servesCuisine: ['Nigerian', 'West African', 'African'],
        priceRange: '$$',
        address: { '@type': 'PostalAddress', addressLocality: SITE.city, addressRegion: SITE.region, addressCountry: SITE.country },
        areaServed: [...SITE.serviceAreas],
        hasMenu: this.seo.absolute('/menu'),
      },
    });
  }
}
