import { ChangeDetectionStrategy, Component, inject } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { Router, RouterLink } from '@angular/router';
import { catchError, map, of } from 'rxjs';
import { CatalogService } from '../../core/catalog.service';
import { SeoService } from '../../core/seo.service';
import { SITE } from '../../core/site';
import { ProductCard } from '../../shared/product-card';
import { RevealDirective } from '../../shared/reveal.directive';

@Component({
  selector: 'app-home',
  imports: [RouterLink, ProductCard, RevealDirective],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './home.html',
  styleUrl: './home.scss',
})
export class Home {
  private readonly catalog = inject(CatalogService);
  private readonly seo = inject(SeoService);
  private readonly router = inject(Router);

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
    { n: '01', title: 'Build your tray', text: 'Pick your small chops, grills and pastries — by the dozen, the tray or the platter.' },
    { n: '02', title: 'Choose your slot', text: 'Select a weekend pickup or delivery window. We need about 48 hours to prep.' },
    { n: '03', title: 'We cook it fresh', text: 'Nothing is made ahead and frozen. Your order is cooked the day you collect it.' },
  ];

  /** Themes that come up again and again in our Google reviews — summarised, not quoted. */
  protected readonly praise = [
    { title: 'The spring rolls', text: 'Regularly called out by name. Thin, blistered wrappers and a filling that actually tastes of something.' },
    { title: 'Party-ready presentation', text: 'Platters arrive arranged and labelled, so they go straight from the box to the table.' },
    { title: 'On time, every time', text: 'Hosts tell us the same thing: the order was ready when we said it would be.' },
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

  goSearch(term: string): void {
    this.router.navigate(['/menu'], { queryParams: term.trim() ? { q: term.trim() } : {} });
  }
}
