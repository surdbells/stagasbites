import { ChangeDetectionStrategy, Component, inject } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { NavigationEnd, Router, RouterOutlet } from '@angular/router';
import { filter, map } from 'rxjs';
import { CartDrawer } from './layout/cart-drawer';
import { Footer } from './layout/footer';
import { Header } from './layout/header';

@Component({
  selector: 'app-root',
  imports: [RouterOutlet, Header, Footer, CartDrawer],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <a class="skip-link" href="#main">Skip to content</a>
    @if (!isAdmin()) {
      <app-header />
    }
    <main id="main" tabindex="-1">
      <router-outlet />
    </main>
    @if (!isAdmin()) {
      <app-footer />
      <app-cart-drawer />
    }
  `,
  styles: `
    main {
      min-height: 70vh;
      outline: none;
    }

    .skip-link {
      position: fixed;
      top: -100px;
      left: 1rem;
      z-index: 200;
      padding: 0.7rem 1.2rem;
      background: var(--gold-400);
      color: var(--bg);
      border-radius: var(--radius-sm);

      &:focus {
        top: 1rem;
      }
    }
  `,
})
export class App {
  private readonly router = inject(Router);

  /** The admin area brings its own chrome. */
  protected readonly isAdmin = toSignal(
    this.router.events.pipe(
      filter((e) => e instanceof NavigationEnd),
      map((e) => (e as NavigationEnd).urlAfterRedirects.startsWith('/admin')),
    ),
    { initialValue: false },
  );
}
