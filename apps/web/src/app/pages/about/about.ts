import { ChangeDetectionStrategy, Component, inject } from '@angular/core';
import { RouterLink } from '@angular/router';
import { SeoService } from '../../core/seo.service';
import { SITE } from '../../core/site';
import { RevealDirective } from '../../shared/reveal.directive';

@Component({
  selector: 'app-about',
  imports: [RouterLink, RevealDirective],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <section class="page-hero">
      <div class="container">
        <p class="eyebrow">Our story</p>
        <h1>Party food, the way <em>home</em> makes it.</h1>
        <p class="lead">
          Staga’s Bites began with a simple idea: nobody in Oakville should have to settle for a party without proper
          small chops.
        </p>
      </div>
    </section>

    <section class="section container story">
      <div class="prose" appReveal>
        <h2>Who we are</h2>
        <p>
          We’re a Nigerian kitchen based in Oakville, Ontario. We make the food we grew up with — samosas and spring
          rolls folded by hand, puff puff fried in small batches, meat pies with proper buttery shortcrust, and asun and
          suya with real smoke and real heat.
        </p>
        <h2>Our kitchen</h2>
        <p>
          Every order is prepared by a certified food handler and cooked for your slot. We don’t batch-cook and freeze;
          if you’re collecting on Saturday, it’s made on Saturday.
        </p>
        <h2>Commitment to quality</h2>
        <p>
          Fresh ingredients, house-ground spice blends, and the patience to do things the long way. It’s why we hold a
          {{ site.rating.value.toFixed(1) }}-star rating across {{ site.rating.count }} Google reviews and a Canadian Choice Award.
        </p>
        <a routerLink="/menu" class="btn">See the menu</a>
      </div>

      <ul class="values" appReveal="150">
        @for (v of values; track v.title) {
          <li><strong>{{ v.title }}</strong><span>{{ v.text }}</span></li>
        }
      </ul>
    </section>
  `,
  styles: `
    h1 em {
      font-style: italic;
      color: var(--gold-400);
    }

    .story {
      display: grid;
      grid-template-columns: 1.3fr 1fr;
      gap: clamp(2rem, 6vw, 6rem);
      align-items: start;
    }

    .prose .btn {
      margin-top: 1rem;
      color: #fff;
      text-decoration: none;
    }

    .values {
      list-style: none;
      margin: 0;
      padding: 0;
      display: grid;
      gap: 1rem;
      position: sticky;
      top: calc(var(--header-h) + 1.5rem);

      li {
        display: grid;
        gap: 0.3rem;
        padding: 1.5rem;
        border: 1px solid var(--border);
        border-radius: var(--radius);
        background: linear-gradient(160deg, var(--surface-2), var(--surface));
      }

      strong {
        font-family: var(--font-display);
        font-weight: 500;
        font-size: 1.35rem;
        color: var(--gold-400);
      }

      span {
        color: var(--text-soft);
      }
    }

    @media (max-width: 900px) {
      .story {
        grid-template-columns: 1fr;
      }

      .values {
        position: static;
      }
    }
  `,
})
export class About {
  protected readonly site = SITE;
  protected readonly values = [
    { title: 'Made to order', text: 'Cooked the day you collect. Never frozen, never reheated.' },
    { title: 'Certified & careful', text: 'Every order is prepared by a certified food handler.' },
    { title: 'Authentic, not watered down', text: 'Real scotch bonnet, real yaji, real flavour — with mild options when you need them.' },
    { title: 'On time', text: 'Your event runs on a schedule. So do we.' },
  ];

  constructor() {
    inject(SeoService).set({
      title: 'Our Story',
      description: "Meet Staga's Bites — an award-winning, certified Nigerian kitchen in Oakville, Ontario, serving small chops, pastries and grills made fresh to order.",
      path: '/about',
    });
  }
}
