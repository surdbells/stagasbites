import { ChangeDetectionStrategy, Component, ElementRef, computed, inject, viewChild } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { catchError, of } from 'rxjs';
import { ApiService } from '../core/api.service';
import { SITE } from '../core/site';

interface GoogleReview {
  author: string;
  author_url: string | null;
  photo_url: string | null;
  rating: number;
  text: string;
  when: string;
}

interface ReviewSummary {
  configured: boolean;
  rating: number | null;
  count: number | null;
  url: string;
  write_url: string;
  reviews: GoogleReview[];
}

/**
 * Live reviews from the business's Google listing (fetched server-side through the Places API).
 * Until an API key is configured it still shows the rating with links to read and write reviews.
 */
@Component({
  selector: 'app-google-reviews',
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <section class="gr container" aria-labelledby="gr-title">
      <header class="gr__head">
        <div>
          <p class="eyebrow">Google reviews</p>
          <h2 id="gr-title">Loved by our customers</h2>
        </div>

        <div class="gr__score">
          <svg class="gr__g" viewBox="0 0 48 48" width="40" height="40" aria-hidden="true">
            <path fill="#EA4335" d="M24 9.500c3.540 0 6.710 1.220 9.210 3.600l6.850-6.850C35.900 2.380 30.470 0 24 0 14.620 0 6.510 5.380 2.560 13.220l7.980 6.190C12.430 13.720 17.740 9.500 24 9.500z"/>
            <path fill="#4285F4" d="M46.980 24.550c0-1.570-.150-3.090-.380-4.550H24v9.020h12.940c-.580 2.960-2.260 5.480-4.780 7.180l7.730 6c4.510-4.180 7.090-10.360 7.090-17.650z"/>
            <path fill="#FBBC05" d="M10.530 28.590c-.480-1.450-.760-2.990-.760-4.590s.270-3.140.760-4.590l-7.980-6.190C.920 16.460 0 20.120 0 24c0 3.880.920 7.540 2.560 10.780l7.970-6.190z"/>
            <path fill="#34A853" d="M24 48c6.480 0 11.930-2.130 15.890-5.810l-7.730-6c-2.150 1.450-4.920 2.300-8.160 2.300-6.260 0-11.570-4.220-13.470-9.910l-7.980 6.190C6.510 42.620 14.620 48 24 48z"/>
          </svg>
          <div>
            <strong>{{ rating().toFixed(1) }} <span class="gr__stars" aria-hidden="true">★★★★★</span></strong>
            <small>Based on {{ count() }} reviews</small>
          </div>
          <a class="btn btn--sm" [href]="data()?.write_url" target="_blank" rel="noopener">Write a review</a>
        </div>
      </header>

      @if (data()?.reviews?.length) {
        <div class="gr__rail" #rail tabindex="0" aria-label="Customer reviews, scroll horizontally">
          @for (r of data()!.reviews; track r.author + r.when) {
            <article class="gr__card">
              <header>
                @if (r.photo_url) {
                  <img [src]="r.photo_url" alt="" width="44" height="44" loading="lazy" referrerpolicy="no-referrer" />
                } @else {
                  <span class="gr__avatar" aria-hidden="true">{{ r.author.charAt(0) }}</span>
                }
                <div>
                  @if (r.author_url) {
                    <a [href]="r.author_url" target="_blank" rel="noopener nofollow">{{ r.author }}</a>
                  } @else {
                    <span>{{ r.author }}</span>
                  }
                  <small>{{ r.when }}</small>
                </div>
              </header>
              <p class="gr__stars" [attr.aria-label]="r.rating + ' out of 5 stars'">{{ '★★★★★'.slice(0, r.rating) }}</p>
              <p class="gr__text">{{ r.text }}</p>
            </article>
          }
        </div>
        <div class="gr__foot">
          <div class="gr__nav">
            <button type="button" (click)="scroll(-1)" aria-label="Previous reviews">‹</button>
            <button type="button" (click)="scroll(1)" aria-label="More reviews">›</button>
          </div>
          <a [href]="data()?.url" target="_blank" rel="noopener">Read all reviews on Google</a>
        </div>
      } @else {
        <p class="gr__fallback">
          See what customers say about our small chops, grills and catering.
          <a [href]="data()?.url ?? fallbackUrl" target="_blank" rel="noopener">Read our reviews on Google</a>
        </p>
      }
    </section>
  `,
  styleUrl: './google-reviews.scss',
})
export class GoogleReviews {
  private readonly rail = viewChild<ElementRef<HTMLElement>>('rail');
  protected readonly fallbackUrl = 'https://www.google.com/maps/place/?q=place_id:ChIJ218RGCxlK4gRtgqbVBx8Nks';

  protected readonly data = toSignal(inject(ApiService).get<ReviewSummary>('/reviews').pipe(catchError(() => of(null))), { initialValue: null });
  protected readonly rating = computed(() => this.data()?.rating ?? SITE.rating.value);
  protected readonly count = computed(() => this.data()?.count ?? SITE.rating.count);

  scroll(direction: 1 | -1): void {
    const el = this.rail()?.nativeElement;
    el?.scrollBy({ left: direction * el.clientWidth * 0.8, behavior: 'smooth' });
  }
}
