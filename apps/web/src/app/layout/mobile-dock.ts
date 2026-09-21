import { ChangeDetectionStrategy, Component, inject } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { RouterLink, RouterLinkActive } from '@angular/router';
import { catchError, of } from 'rxjs';
import { AuthStore } from '../core/auth.store';
import { CartStore } from '../core/cart.store';
import { CatalogService } from '../core/catalog.service';
import { WishlistStore } from '../core/wishlist.store';

/** Thumb-reach tab bar on phones, plus the floating WhatsApp chat button on every screen size. */
@Component({
  selector: 'app-mobile-dock',
  imports: [RouterLink, RouterLinkActive],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    @if (settings()?.whatsapp; as number) {
      <a class="wa" [href]="'https://wa.me/' + number" target="_blank" rel="noopener" aria-label="Chat with us on WhatsApp">
        <svg viewBox="0 0 24 24" width="26" height="26" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.600 15.100L2 22l5-1.300A10 10 0 1 0 12 2Zm0 18.200a8.200 8.200 0 0 1-4.200-1.150l-.300-.180-3 .790.800-2.900-.200-.300A8.200 8.200 0 1 1 12 20.200Zm4.500-6.100c-.250-.120-1.460-.720-1.690-.800s-.390-.120-.560.120-.640.800-.790.970-.290.180-.540.060a6.700 6.700 0 0 1-2-1.220 7.400 7.400 0 0 1-1.360-1.700c-.140-.250 0-.380.110-.500s.250-.290.370-.430a1.700 1.700 0 0 0 .250-.410.460.460 0 0 0 0-.430c-.060-.120-.560-1.340-.760-1.840s-.400-.420-.560-.430h-.470a.900.900 0 0 0-.660.310 2.800 2.800 0 0 0-.860 2.050 4.800 4.800 0 0 0 1 2.540 11 11 0 0 0 4.200 3.700c.590.250 1.050.400 1.400.520a3.400 3.400 0 0 0 1.550.100 2.500 2.500 0 0 0 1.660-1.170 2 2 0 0 0 .140-1.170c-.060-.100-.220-.160-.470-.290Z"/></svg>
      </a>
    }

    <nav class="dock" aria-label="Quick navigation">
      <a routerLink="/" routerLinkActive="is-active" [routerLinkActiveOptions]="{ exact: true }">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="m3 11 9-7 9 7v9a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1v-9Z"/></svg>Home
      </a>
      <a routerLink="/menu" routerLinkActive="is-active">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M4 6h16M4 12h16M4 18h10"/></svg>Menu
      </a>
      <button type="button" (click)="cart.drawerOpen.set(true)">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M5 8h14l-1.200 11.200a2 2 0 0 1-2 1.800H8.200a2 2 0 0 1-2-1.800L5 8Z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/></svg>Cart
        @if (cart.count() > 0) { <span class="dock__badge">{{ cart.count() }}</span> }
      </button>
      <a routerLink="/wishlist" routerLinkActive="is-active">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M12 20s-7-4.400-7-10a4 4 0 0 1 7-2.600A4 4 0 0 1 19 10c0 5.600-7 10-7 10Z"/></svg>Saved
        @if (wishlist.count() > 0) { <span class="dock__badge">{{ wishlist.count() }}</span> }
      </a>
      <a [routerLink]="auth.isLoggedIn() ? '/account' : '/account/login'" routerLinkActive="is-active">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.400 3.600-7 8-7s8 2.600 8 7"/></svg>Account
      </a>
    </nav>
  `,
  styles: `
    .wa {
      position: fixed;
      right: clamp(1rem, 2.5vw, 2rem);
      bottom: clamp(1rem, 2.5vw, 2rem);
      z-index: 60;
      display: grid;
      place-items: center;
      width: 56px;
      height: 56px;
      border-radius: 50%;
      background: #25d366;
      color: #fff;
      box-shadow: 0 12px 30px -10px rgba(37, 211, 102, 0.8);
      transition: transform 0.35s var(--ease);

      &:hover {
        transform: translateY(-3px) scale(1.05);
      }
    }

    .dock {
      display: none;
    }

    @media (max-width: 900px) {
      .wa {
        bottom: calc(76px + env(safe-area-inset-bottom));
      }

      .dock {
        position: fixed;
        inset: auto 0 0;
        z-index: 55;
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        padding: 0.4rem 0.25rem calc(0.4rem + env(safe-area-inset-bottom));
        background: rgb(255 255 255 / 0.96);
        backdrop-filter: blur(14px);
        border-top: 1px solid var(--border);

        a,
        button {
          position: relative;
          display: grid;
          justify-items: center;
          gap: 0.15rem;
          padding: 0.35rem 0;
          font-size: 0.68rem;
          font-weight: 600;
          color: var(--text-soft);

          &.is-active {
            color: var(--ember-500);
          }
        }

        svg {
          width: 22px;
          height: 22px;
        }
      }

      .dock__badge {
        position: absolute;
        top: 0;
        left: calc(50% + 4px);
        min-width: 16px;
        height: 16px;
        padding: 0 4px;
        border-radius: 8px;
        background: var(--ember-500);
        color: #fff;
        font-size: 0.62rem;
        line-height: 16px;
        text-align: center;
      }
    }
  `,
})
export class MobileDock {
  protected readonly cart = inject(CartStore);
  protected readonly wishlist = inject(WishlistStore);
  protected readonly auth = inject(AuthStore);
  protected readonly settings = toSignal(inject(CatalogService).settings().pipe(catchError(() => of(null))), { initialValue: null });
}
