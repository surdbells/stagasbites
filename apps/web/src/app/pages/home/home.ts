import { ChangeDetectionStrategy, Component, inject } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { RouterLink } from '@angular/router';
import { catchError, map, of } from 'rxjs';
import { CatalogService } from '../../core/catalog.service';
import { SeoService } from '../../core/seo.service';
import { SITE } from '../../core/site';
import { GoogleReviews } from '../../shared/google-reviews';
import { LiveSearch } from '../../shared/live-search';
import { ProductCard } from '../../shared/product-card';
import { RevealDirective } from '../../shared/reveal.directive';

@Component({
  selector: 'app-home',
  imports: [RouterLink, ProductCard, RevealDirective, LiveSearch, GoogleReviews],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './home.html',
  styleUrl: './home.scss',
})
export class Home {
  private readonly catalog = inject(CatalogService);
  private readonly seo = inject(SeoService);

  protected readonly site = SITE;

  protected readonly categories = toSignal(this.catalog.categories().pipe(catchError(() => of([]))), { initialValue: [] });
  protected readonly featured = toSignal(
    this.catalog.products({ featured: true, per_page: 8 }).pipe(
      map((r) => r.data),
      catchError(() => of([])),
    ),
    { initialValue: null },
  );

  protected readonly marquee = ['Samosas', 'Puff Puff', 'Asun', 'Meat Pies', 'Suya', 'Spring Rolls', 'Grilled Tilapia', 'Scotch Eggs', 'Peppered Wings', 'Party Platters'];

  protected readonly steps = [
    { n: '01', title: 'Build your tray', text: 'Pick your small chops, grills and pastries by the dozen, the tray or the platter.' },
    { n: '02', title: 'Choose your slot', text: 'Select a weekend pickup or delivery window. We need about 48 hours to prep.' },
    { n: '03', title: 'We cook it fresh', text: 'Nothing is made ahead and frozen. Your order is cooked the day you collect it.' },
  ];

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
