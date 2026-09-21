import { ChangeDetectionStrategy, Component, inject } from '@angular/core';
import { SeoService } from '../../core/seo.service';
import { SITE } from '../../core/site';
import { InquiryForm } from '../../shared/inquiry-form';

@Component({
  selector: 'app-contact',
  imports: [InquiryForm],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <section class="page-hero">
      <div class="container">
        <p class="eyebrow">Contact</p>
        <h1>Questions? Custom order?</h1>
        <p class="lead">We’re at your disposal seven days a week, and reply to every message within one business day.</p>
      </div>
    </section>

    <section class="section container split">
      <div class="details">
        <a class="detail" [href]="site.phoneHref">
          <small>Call or text</small><strong>{{ site.phone }}</strong>
        </a>
        <a class="detail" [href]="'mailto:' + site.email">
          <small>Email</small><strong>{{ site.email }}</strong>
        </a>
        <div class="detail">
          <small>Kitchen</small><strong>{{ site.city }}, Ontario, Canada</strong>
          <span class="text-soft">Pickup by appointment — the address is sent with your order confirmation.</span>
        </div>
        <div class="detail">
          <small>We deliver to</small><strong>{{ site.serviceAreas.slice(0, 4).join(' · ') }}</strong>
          <span class="text-soft">Further afield? Ask us for a quote.</span>
        </div>
      </div>
      <div class="card"><app-inquiry-form type="contact" /></div>
    </section>
  `,
  styles: `
    .split {
      display: grid;
      grid-template-columns: 1fr 1.2fr;
      gap: clamp(2rem, 5vw, 5rem);
      align-items: start;
    }

    .details {
      display: grid;
      gap: 1rem;
    }

    .detail {
      display: grid;
      gap: 0.2rem;
      padding: 1.4rem 1.6rem;
      border: 1px solid var(--border);
      border-radius: var(--radius);
      background: var(--surface);
      transition: border-color 0.25s;

      small {
        font-size: 0.75rem;
        letter-spacing: 0.18em;
        text-transform: uppercase;
        color: var(--gold-400);
      }

      strong {
        font-family: var(--font-display);
        font-weight: 500;
        font-size: 1.45rem;
        color: var(--heading);
        overflow-wrap: anywhere;
      }

      span {
        font-size: 0.92rem;
      }
    }

    a.detail:hover {
      border-color: var(--gold-400);
    }

    @media (max-width: 900px) {
      .split {
        grid-template-columns: 1fr;
      }
    }
  `,
})
export class Contact {
  protected readonly site = SITE;

  constructor() {
    inject(SeoService).set({
      title: 'Contact Us',
      description: "Questions, custom orders or catering? Call +1 (647) 673-8796 or message Staga's Bites in Oakville, Ontario. We reply within one business day.",
      path: '/contact',
    });
  }
}
