import { ChangeDetectionStrategy, Component, inject } from '@angular/core';
import { RouterLink } from '@angular/router';
import { SeoService } from '../../core/seo.service';

@Component({
  selector: 'app-not-found',
  imports: [RouterLink],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <section class="nf">
      <p class="nf__code" aria-hidden="true">404</p>
      <h1>This plate is empty.</h1>
      <p class="lead">The page you’re after has moved or never existed. The food, happily, is real.</p>
      <div class="nf__cta">
        <a routerLink="/menu" class="btn">See the menu</a>
        <a routerLink="/" class="btn btn--ghost">Back home</a>
      </div>
    </section>
  `,
  styles: `
    .nf {
      min-height: 90svh;
      display: grid;
      place-content: center;
      justify-items: center;
      text-align: center;
      padding: calc(var(--header-h) + 2rem) 1.25rem 4rem;
      background: radial-gradient(ellipse 50% 50% at 50% 40%, rgba(201, 48, 44, 0.25), transparent 70%);
    }

    .nf__code {
      font-family: var(--font-display);
      font-size: clamp(7rem, 22vw, 16rem);
      line-height: 0.9;
      margin: 0;
      color: transparent;
      -webkit-text-stroke: 1.5px var(--ember-400);
    }

    .nf__cta {
      display: flex;
      flex-wrap: wrap;
      gap: 0.8rem;
      justify-content: center;
      margin-top: 1rem;
    }
  `,
})
export class NotFound {
  constructor() {
    inject(SeoService).set({ title: 'Page not found', description: 'This page could not be found.', noindex: true });
  }
}
