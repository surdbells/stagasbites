import { ChangeDetectionStrategy, Component, inject } from '@angular/core';
import { SeoService } from '../../core/seo.service';
import { SITE } from '../../core/site';
import { ContactChannels } from '../../shared/contact-channels';
import { RevealDirective } from '../../shared/reveal.directive';

@Component({
  selector: 'app-catering',
  imports: [ContactChannels, RevealDirective],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <section class="page-hero">
      <div class="container">
        <p class="eyebrow">Event catering &amp; bulk orders</p>
        <h1>Your guests will <em>talk about the food.</em></h1>
        <p class="lead">
          Weddings, birthdays, naming ceremonies, graduations and office lunches. Nigerian small chops, grills and pastries
          for ten guests or a few hundred, across Oakville, Mississauga, Burlington, Milton and the wider GTA.
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

    <section class="section section--quote">
      <div class="container">
        <header class="quote__head" appReveal>
          <p class="eyebrow">Get a quote</p>
          <h2>Tell us about <em>your event</em></h2>
          <p class="lead">
            Message or call us with your date, guest count and the kind of spread you have in mind. We will come back
            with a menu and a price within one business day.
          </p>
        </header>

        <app-contact-channels [message]="quoteMessage" />

        <ul class="points" appReveal="120">
          <li>Custom menus for any budget</li>
          <li>Individually packed or display-style platters</li>
          <li>Mild options for mixed crowds and kids</li>
          <li>Vegetarian-friendly selections on request</li>
        </ul>
      </div>
    </section>
  `,
  styleUrl: './catering.scss',
})
export class Catering {
  protected readonly site = SITE;
  protected readonly quoteMessage =
    "Hi Staga's Bites! I'd like a catering quote.\nEvent date: \nNumber of guests: \nType of event: \nPickup or delivery: ";

  protected readonly services = [
    { title: 'Small chops by the tray', text: 'Samosas, spring rolls, puff puff, shrimp torpedos and more, priced by the tray so feeding a crowd stays simple.' },
    { title: 'Individual packs', text: 'Pre-packed finger-food boxes for each guest. Tidy, hygienic and perfect for kids’ parties and corporate events.' },
    { title: 'Off-the-grill mains', text: 'Asun, suya, peppered turkey, wings and whole grilled tilapia, delivered hot for your serving time.' },
    { title: 'Display service', text: 'Want the wow factor? We arrange and style the food station so it looks as good as it tastes.' },
  ];

  constructor() {
    const seo = inject(SeoService);
    seo.set({
      title: 'Nigerian Event Catering & Bulk Orders in Oakville & the GTA',
      description:
        'Small chops, grills and pastries for weddings, birthdays, corporate events and house parties. Custom platters for any guest count. Call or WhatsApp us for a catering quote.',
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
