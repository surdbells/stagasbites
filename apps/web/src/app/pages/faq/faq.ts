import { ChangeDetectionStrategy, Component, inject } from '@angular/core';
import { RouterLink } from '@angular/router';
import { SeoService } from '../../core/seo.service';

const FAQS = [
  { q: 'How far ahead do I need to order?', a: 'Most items need about 48 hours’ notice. Checkout only shows the days we can actually fulfil, based on what’s in your cart. For large events, a week or more is ideal.' },
  { q: 'Which days can I pick up or get delivery?', a: 'We cook for weekend slots: Friday, Saturday and Sunday. Need a weekday? Send us a message and we’ll do our best.' },
  { q: 'Where do you deliver?', a: 'Oakville, Burlington, Mississauga and Milton. Delivery is free over $250; otherwise a flat fee applies at checkout. For other parts of the GTA, contact us for a quote.' },
  { q: 'Where is pickup?', a: 'Pickup is in Oakville, Ontario. The exact address is sent with your order confirmation.' },
  { q: 'How spicy is the food?', a: 'Each item shows a heat level. Asun and peppered snails are properly hot; pastries and puff puff have no heat at all. Tell us in the order notes if you’d like something milder.' },
  { q: 'Do you cater for allergies?', a: 'Our kitchen handles wheat, eggs, dairy, peanuts, fish and shellfish, so we can’t guarantee any item is allergen-free. Please tell us about allergies in your order notes and we’ll advise.' },
  { q: 'Can I change or cancel my order?', a: 'Yes, up to 48 hours before your slot for a full refund. After that we’ve already bought ingredients, so we can’t refund, but we’ll always try to reschedule.' },
  { q: 'How do I pay?', a: 'Securely by card through Stripe at checkout. For large catering orders we can arrange a deposit by invoice.' },
  { q: 'Do you cater events?', a: 'Absolutely, from 10 guests upwards. Request a quote on our catering page and we’ll reply within one business day.' },
];

@Component({
  selector: 'app-faq',
  imports: [RouterLink],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <section class="page-hero">
      <div class="container">
        <p class="eyebrow">FAQ</p>
        <h1>Good questions</h1>
        <p class="lead">How pre-orders, pickup, delivery and catering work.</p>
      </div>
    </section>

    <section class="section container faq">
      @for (item of faqs; track item.q) {
        <details>
          <summary><h2>{{ item.q }}</h2><span aria-hidden="true">+</span></summary>
          <p>{{ item.a }}</p>
        </details>
      }
      <p class="faq__more">Still stuck? <a routerLink="/contact">Ask us anything →</a></p>
    </section>
  `,
  styles: `
    .faq {
      max-width: 860px;
    }

    details {
      border-bottom: 1px solid var(--border);

      &[open] summary span {
        transform: rotate(45deg);
        color: var(--ember-400);
      }

      p {
        color: var(--text-2);
        padding: 0 3rem 1.6rem 0;
        margin: 0;
        animation: open 0.5s var(--ease);
      }
    }

    summary {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
      padding: 1.5rem 0;
      cursor: pointer;
      list-style: none;

      &::-webkit-details-marker {
        display: none;
      }

      h2 {
        font-size: clamp(1.2rem, 2.2vw, 1.55rem);
        margin: 0;
      }

      span {
        font-size: 1.8rem;
        line-height: 1;
        color: var(--gold-400);
        transition: transform 0.4s var(--ease);
      }
    }

    .faq__more {
      margin-top: 2.5rem;

      a {
        color: var(--gold-400);
      }
    }

    @keyframes open {
      from {
        opacity: 0;
        transform: translateY(-8px);
      }
    }
  `,
})
export class Faq {
  protected readonly faqs = FAQS;

  constructor() {
    inject(SeoService).set({
      title: 'Frequently Asked Questions',
      description: "How pre-orders, pickup, delivery, lead times, allergens and catering work at Staga's Bites.",
      path: '/faq',
      jsonLd: {
        '@context': 'https://schema.org',
        '@type': 'FAQPage',
        mainEntity: FAQS.map((f) => ({ '@type': 'Question', name: f.q, acceptedAnswer: { '@type': 'Answer', text: f.a } })),
      },
    });
  }
}
