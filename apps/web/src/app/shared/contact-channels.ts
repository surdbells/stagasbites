import { ChangeDetectionStrategy, Component, computed, inject, input } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { catchError, of } from 'rxjs';
import { CatalogService } from '../core/catalog.service';
import { SITE } from '../core/site';

interface Channel {
  key: string;
  label: string;
  value: string;
  href: string;
  external: boolean;
}

/** Direct ways to reach the kitchen. Social channels appear once they are set in Admin, Settings. */
@Component({
  selector: 'app-contact-channels',
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <ul class="channels">
      @for (c of channels(); track c.key) {
        <li>
          <a class="channel" [attr.data-key]="c.key" [href]="c.href" [attr.target]="c.external ? '_blank' : null" [attr.rel]="c.external ? 'noopener' : null">
            <span class="channel__icon" aria-hidden="true">
              @switch (c.key) {
                @case ('phone') { <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M5 4h4l2 5-2.500 1.500a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2Z"/></svg> }
                @case ('whatsapp') { <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M3 21l1.650-4.800A8.500 8.500 0 1 1 8 19.500L3 21Z"/><path d="M9 10c.500 2 2.500 4 4.500 4.500l1.500-1.500"/></svg> }
                @case ('email') { <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg> }
                @case ('instagram') { <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.500" cy="6.500" r="1" fill="currentColor"/></svg> }
                @case ('facebook') { <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M14 8h3V4h-3a4 4 0 0 0-4 4v3H7v4h3v6h4v-6h3l1-4h-4V8Z"/></svg> }
              }
            </span>
            <span class="channel__body">
              <small>{{ c.label }}</small>
              <strong>{{ c.value }}</strong>
            </span>
          </a>
        </li>
      }
    </ul>
  `,
  styleUrl: './contact-channels.scss',
})
export class ContactChannels {
  /** Pre-filled WhatsApp / email text, e.g. a catering enquiry opener. */
  readonly message = input('');

  private readonly settings = toSignal(inject(CatalogService).settings().pipe(catchError(() => of(null))), { initialValue: null });

  protected readonly channels = computed<Channel[]>(() => {
    const s = this.settings();
    const text = encodeURIComponent(this.message());
    const list: Channel[] = [{ key: 'phone', label: 'Call or text', value: s?.phone ?? SITE.phone, href: SITE.phoneHref, external: false }];

    if (s?.whatsapp) {
      list.push({ key: 'whatsapp', label: 'WhatsApp', value: 'Chat with us', href: `https://wa.me/${s.whatsapp}${text ? '?text=' + text : ''}`, external: true });
    }
    const email = s?.email ?? SITE.email;
    list.push({ key: 'email', label: 'Email', value: email, href: `mailto:${email}${text ? '?body=' + text : ''}`, external: false });
    if (s?.instagram) {
      list.push({ key: 'instagram', label: 'Instagram', value: handle(s.instagram), href: s.instagram, external: true });
    }
    if (s?.facebook) {
      list.push({ key: 'facebook', label: 'Facebook', value: handle(s.facebook), href: s.facebook, external: true });
    }
    return list;
  });
}

/** "https://instagram.com/stagasbites/" becomes "@stagasbites". */
function handle(url: string): string {
  const last = url.replace(/[?#].*$/, '').replace(/\/+$/, '').split('/').pop() ?? '';
  return last ? '@' + last.replace(/^@/, '') : url;
}
