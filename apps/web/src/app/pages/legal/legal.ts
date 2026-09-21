import { ChangeDetectionStrategy, Component, computed, effect, inject, input } from '@angular/core';
import { RouterLink } from '@angular/router';
import { SeoService } from '../../core/seo.service';
import { SITE } from '../../core/site';

interface LegalDoc {
  title: string;
  description: string;
  sections: { heading: string; body: string[] }[];
}

/**
 * Starter policy copy. These are sensible defaults for a made-to-order food business in Ontario,
 * NOT legal advice — have them reviewed before launch.
 */
const DOCS: Record<string, LegalDoc> = {
  delivery: {
    title: 'Pickup & Delivery',
    description: "Pickup and local delivery information for Staga's Bites orders in Oakville and the GTA.",
    sections: [
      { heading: 'Pickup', body: ['Pickup is from our kitchen in Oakville, Ontario during the time slot you choose at checkout. The exact address is included in your order confirmation.', 'Please arrive within your slot so your food is at its best.'] },
      { heading: 'Delivery', body: ['We deliver to Oakville, Burlington, Mississauga and Milton. A flat delivery fee is shown at checkout, and delivery is free on orders over the threshold shown there.', 'For addresses outside these areas, contact us before ordering and we will quote a delivery fee.'] },
      { heading: 'Lead times', body: ['Everything is made to order. Most items need at least 48 hours’ notice; checkout only offers dates we can fulfil.'] },
    ],
  },
  refunds: {
    title: 'Refund & Cancellation Policy',
    description: "Refund and cancellation policy for Staga's Bites orders.",
    sections: [
      { heading: 'Cancelling an order', body: ['You can cancel for a full refund up to 48 hours before your pickup or delivery slot. Inside 48 hours, ingredients have been purchased and preparation may have begun, so orders are non-refundable; we will always try to reschedule instead.'] },
      { heading: 'If something isn’t right', body: ['Because our products are perishable we cannot accept returns. If there is a problem with your order, contact us within 24 hours with a photo and we will make it right with a replacement, credit or refund.'] },
      { heading: 'How refunds are paid', body: ['Refunds go back to the original payment method through Stripe and typically appear within 5–10 business days.'] },
    ],
  },
  terms: {
    title: 'Terms & Conditions',
    description: "Terms and conditions for ordering from Staga's Bites.",
    sections: [
      { heading: 'Orders', body: ['An order is confirmed once payment has been received and you have been sent a confirmation email. Prices are in Canadian dollars and exclude HST, which is added at checkout.'] },
      { heading: 'Allergens', body: ['Our kitchen handles wheat, eggs, dairy, peanuts, tree nuts, fish and shellfish. We cannot guarantee that any item is free from allergens. Please tell us about allergies before ordering.'] },
      { heading: 'Catering quotes', body: ['Catering quotes are valid for 14 days. A deposit may be required to secure your date.'] },
      { heading: 'Liability', body: ['Once food has been collected or delivered, safe storage and serving are the customer’s responsibility. We recommend serving hot items within two hours.'] },
    ],
  },
  privacy: {
    title: 'Privacy Policy',
    description: "How Staga's Bites collects, uses and protects your personal information.",
    sections: [
      { heading: 'What we collect', body: ['Your name, email, phone number, delivery address and order details — only what we need to prepare and deliver your order and to contact you about it.'] },
      { heading: 'Payments', body: ['Card payments are processed by Stripe. Your card details go directly to Stripe and never touch our servers.'] },
      { heading: 'Email', body: ['We send transactional emails (order confirmations and updates) through ZeptoMail. We do not sell or rent your information to anyone.'] },
      { heading: 'Your rights', body: ['You can ask us for a copy of your data, or to correct or delete it, at any time by emailing us. We handle personal information in line with Canada’s PIPEDA.'] },
    ],
  },
};

@Component({
  selector: 'app-legal',
  imports: [RouterLink],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    @if (document(); as d) {
      <section class="page-hero">
        <div class="container">
          <p class="eyebrow">Policies</p>
          <h1>{{ d.title }}</h1>
        </div>
      </section>
      <section class="section container prose">
        @for (s of d.sections; track s.heading) {
          <h2>{{ s.heading }}</h2>
          @for (p of s.body; track p) {
            <p>{{ p }}</p>
          }
        }
        <p class="text-soft">Questions about this policy? Email <a [href]="'mailto:' + site.email">{{ site.email }}</a>.</p>
      </section>
    } @else {
      <section class="page-hero">
        <div class="container">
          <h1>Page not found</h1>
          <a routerLink="/" class="btn">Back home</a>
        </div>
      </section>
    }
  `,
})
export class Legal {
  /** Bound from the `/legal/:doc` route param. */
  readonly doc = input.required<string>();
  protected readonly site = SITE;
  protected readonly document = computed<LegalDoc | null>(() => DOCS[this.doc()] ?? null);

  constructor() {
    const seo = inject(SeoService);
    effect(() => {
      const d = this.document();
      seo.set(d ? { title: d.title, description: d.description, path: `/legal/${this.doc()}` } : { title: 'Page not found', description: '', noindex: true });
    });
  }
}
