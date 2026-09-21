import { ChangeDetectionStrategy, Component, HostListener, computed, inject, signal } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { RouterLink } from '@angular/router';
import { catchError, map, of } from 'rxjs';
import { CatalogService } from '../../core/catalog.service';
import { SeoService } from '../../core/seo.service';

interface Shot {
  src: string;
  caption: string;
  slug: string;
}

@Component({
  selector: 'app-gallery',
  imports: [RouterLink],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <section class="page-hero">
      <div class="container">
        <p class="eyebrow">Gallery</p>
        <h1>Straight from our kitchen</h1>
        <p class="lead">Real trays, real platters, real parties. Tap any photo for a closer look.</p>
      </div>
    </section>

    <section class="section container">
      <div class="masonry">
        @for (shot of shots(); track shot.src; let i = $index) {
          <button type="button" class="shot" (click)="open(i)" [attr.aria-label]="'View photo: ' + shot.caption">
            <img [src]="shot.src" [alt]="shot.caption" loading="lazy" decoding="async" />
            <span>{{ shot.caption }}</span>
          </button>
        } @empty {
          @for (s of [0, 1, 2, 3, 4, 5]; track s) { <div class="skeleton" style="height: 260px; margin-bottom: 1rem"></div> }
        }
      </div>
    </section>

    @if (active(); as shot) {
      <div class="lightbox" role="dialog" aria-modal="true" [attr.aria-label]="shot.caption" (click)="close()">
        <button type="button" class="lightbox__close" aria-label="Close">✕</button>
        <button type="button" class="lightbox__nav lightbox__nav--prev" (click)="step(-1, $event)" aria-label="Previous photo">‹</button>
        <figure (click)="$event.stopPropagation()">
          <img [src]="shot.src" [alt]="shot.caption" />
          <figcaption>
            {{ shot.caption }}
            <a [routerLink]="['/product', shot.slug]" class="btn btn--sm">Order this</a>
          </figcaption>
        </figure>
        <button type="button" class="lightbox__nav lightbox__nav--next" (click)="step(1, $event)" aria-label="Next photo">›</button>
      </div>
    }
  `,
  styleUrl: './gallery.scss',
})
export class Gallery {
  protected readonly shots = toSignal(
    inject(CatalogService)
      .products({ per_page: 60 })
      .pipe(
        map((res) =>
          res.data.flatMap((p) =>
            [p.image_url, ...p.gallery].filter((src): src is string => !!src).map((src): Shot => ({ src, caption: p.name, slug: p.slug })),
          ),
        ),
        catchError(() => of([] as Shot[])),
      ),
    { initialValue: [] as Shot[] },
  );

  private readonly index = signal<number | null>(null);
  protected readonly active = computed(() => {
    const i = this.index();
    return i === null ? null : (this.shots()[i] ?? null);
  });

  constructor() {
    inject(SeoService).set({
      title: 'Gallery: Small Chops, Grills & Party Platters',
      description: "Photos of Staga's Bites small chops, pastries, grills and event platters, made fresh in Oakville, Ontario.",
      path: '/gallery',
    });
  }

  open(i: number): void {
    this.index.set(i);
  }

  @HostListener('document:keydown.escape')
  close(): void {
    this.index.set(null);
  }

  @HostListener('document:keydown.arrowright')
  next(): void {
    this.step(1);
  }

  @HostListener('document:keydown.arrowleft')
  prev(): void {
    this.step(-1);
  }

  step(delta: number, event?: Event): void {
    event?.stopPropagation();
    const i = this.index();
    const total = this.shots().length;
    if (i !== null && total) {
      this.index.set((i + delta + total) % total);
    }
  }
}
