import { ChangeDetectionStrategy, Component, inject } from '@angular/core';
import { RouterLink } from '@angular/router';
import { SeoService } from '../../core/seo.service';
import { SITE } from '../../core/site';
import { ContactChannels } from '../../shared/contact-channels';

@Component({
  selector: 'app-contact',
  imports: [ContactChannels, RouterLink],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <section class="page-hero">
      <div class="container">
        <p class="eyebrow">Contact</p>
        <h1>Talk to us directly</h1>
        <p class="lead">Questions, custom orders or catering? Reach us on whichever channel suits you. We reply seven days a week.</p>
      </div>
    </section>

    <section class="section container">
      <app-contact-channels message="Hi Staga's Bites! I have a question about an order." />

      <div class="info">
        <div class="card">
          <h2>Kitchen</h2>
          <p>{{ site.city }}, Ontario, Canada</p>
          <p class="text-soft">Pickup is by appointment. The exact address is sent with your order confirmation.</p>
        </div>
        <div class="card">
          <h2>We deliver to</h2>
          <p>{{ site.serviceAreas.slice(0, 4).join(', ') }}</p>
          <p class="text-soft">Further afield? Message us for a delivery quote.</p>
        </div>
        <div class="card">
          <h2>Ready to order?</h2>
          <p class="text-soft">Most questions are answered in our FAQ, and you can order online any time.</p>
          <div class="info__cta">
            <a routerLink="/menu" class="btn btn--sm">Order online</a>
            <a routerLink="/faq" class="btn btn--ghost btn--sm">Read the FAQ</a>
          </div>
        </div>
      </div>
    </section>
  `,
  styles: `
    .info {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
      gap: 1rem;
      margin-top: 1rem;

      h2 {
        font-size: 1.35rem;
      }

      p {
        margin-bottom: 0.4rem;
      }
    }

    .info__cta {
      display: flex;
      flex-wrap: wrap;
      gap: 0.6rem;
      margin-top: 1rem;
    }
  `,
})
export class Contact {
  protected readonly site = SITE;

  constructor() {
    inject(SeoService).set({
      title: 'Contact Us',
      description: "Questions, custom orders or catering? Call, WhatsApp or email Staga's Bites in Oakville, Ontario. We reply seven days a week.",
      path: '/contact',
    });
  }
}
