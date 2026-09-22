import { ChangeDetectionStrategy, Component, ViewEncapsulation, inject } from '@angular/core';
import { RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { AuthStore } from '../core/auth.store';
import { SeoService } from '../core/seo.service';

@Component({
  selector: 'app-admin-layout',
  imports: [RouterOutlet, RouterLink, RouterLinkActive],
  changeDetection: ChangeDetectionStrategy.OnPush,
  // The admin stylesheet is shared by every admin screen rendered inside this shell.
  encapsulation: ViewEncapsulation.None,
  template: `
    <div class="adm">
      <aside class="adm__side">
        <a routerLink="/" class="adm__brand"><img src="/img/brand/logo-horizontal-ink.webp" alt="Staga's Bites" width="144" height="40" /><small>Admin</small></a>
        <nav aria-label="Admin">
          @for (link of links; track link.path) {
            <a [routerLink]="link.path" routerLinkActive="is-active" [routerLinkActiveOptions]="{ exact: link.path === '/admin' }">{{ link.label }}</a>
          }
        </nav>
        <div class="adm__user">
          <span>{{ auth.user()?.email }}</span>
          <button type="button" (click)="auth.logout()">Sign out</button>
        </div>
      </aside>
      <div class="adm__main"><router-outlet /></div>
    </div>
  `,
  styleUrl: './admin.scss',
})
export class AdminLayout {
  protected readonly auth = inject(AuthStore);
  protected readonly links = [
    { path: '/admin', label: 'Dashboard' },
    { path: '/admin/orders', label: 'Orders' },
    { path: '/admin/products', label: 'Products' },
    { path: '/admin/categories', label: 'Categories' },
    { path: '/admin/subscribers', label: 'Subscribers' },
    { path: '/admin/coupons', label: 'Coupons' },
    { path: '/admin/settings', label: 'Settings' },
  ];

  constructor() {
    inject(SeoService).set({ title: 'Admin', description: 'Store administration.', noindex: true });
  }
}
