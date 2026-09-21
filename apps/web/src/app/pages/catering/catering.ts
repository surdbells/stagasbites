import { ChangeDetectionStrategy, Component, inject } from '@angular/core';
import { SeoService } from '../../core/seo.service';
import { SITE } from '../../core/site';
import { InquiryForm } from '../../shared/inquiry-form';
import { RevealDirective } from '../../shared/reveal.directive';

@Component({
  selector: 'app-catering',
  imports: [InquiryForm, RevealDirective],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <section class="page-hero hero">
      <div class="container">
        <p class="eyebrow">Event catering &amp; bulk orders</p>
        <h1>Your guests will <em>talk about the food.</em></h1>
        <p class="lead">
          Weddings, birthdays, naming ceremonies, graduations, office lunches. Nigerian small chops, grills and pastries
          for ten guests or a few hundred — across Oakville, Mississauga, Burlington, Milton and the wider GTA.
        </p>
      </div>
    </section>

    <section class="section container">
      <div class="services">
        @for (s of services; track s.title; let i = $index) {
          <article class="card" [appReveal]="i * 90">
            <h2>{{ s.title }}</h2>
            <p class="text-soft">{{ s.text }}</p>
          </article>
        }
      </div>
    </section>

    <section class="section section--form">
      <div class="container split">
        <div appReveal>
          <p class="eyebrow">Get a quote</p>
          <h2>Tell us about <em>your event</em></h2>
          <p class="lead">Share the date, guest count and the vibe. We’ll come back with a menu and a price within one business day.</p>
          <ul class="points">
            <li>Custom menus for any budget</li>
            <li>Individually packed or display-style platters</li>
            <li>Mild options for mixed crowds and kids</li>
            <li>Vegetarian-friendly selections on request</li>
          </ul>
          <p>Prefer to talk? <a class="link" [href]="site.phoneHref">{{ site.phone }}</a></p>
        </div>
        <div class="card" appReveal="120">
          <app-inquiry-form type="catering" />
        </div>
      </div>
    </section>
  `,
  styleUrl: './catering.scss',
})
export class Catering {
  protected readonly site = SITE;

  protected readonly services = [
    { title: 'Small chops by the tray', text: 'Samosas, spring rolls, puff puff, shrimp torpedos and more — priced by the tray so feeding a crowd stays simple.' },
    { title: 'Individual packs', text: 'Pre-packed finger-food boxes for each guest. Tidy, hygienic and perfect for kids’ parties and corporate events.' },
    { title: 'Off-the-grill mains', text: 'Asun, suya, peppered turkey, wings and whole grilled tilapia, delivered hot for your serving time.' },
    { title: 'Display service', text: 'Want the wow factor? We arrange and style the food station so it looks as good as it tastes.' },
  ];

  constructor() {
    const seo = inject(SeoService);
    seo.set({
      title: 'Nigerian Event Catering & Bulk Orders in Oakville & the GTA',
      description:
        'Small chops, grills and pastries for weddings, birthdays, corporate events and house parties. Custom platters for any guest count. Request a catering quote today.',
      path: '/catering',
      jsonLd: {
        '@context': 'https://schema.org',
        '@type': 'Service',
        serviceType: 'Event catering',
        provider: { '@id': seo.absolute('/#business') },
        areaServed: [...SITE.serviceAreas],
        description: 'Nigerian small chops, grills and pastries for events of any size.',
      },
    });
  }
}
