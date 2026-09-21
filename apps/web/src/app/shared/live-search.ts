import { ChangeDetectionStrategy, Component, ElementRef, HostListener, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { Router, RouterLink } from '@angular/router';
import { Subject, catchError, debounceTime, distinctUntilChanged, map, of, switchMap, tap } from 'rxjs';
import { CatalogService } from '../core/catalog.service';
import { Product } from '../core/models';
import { MoneyPipe } from './money.pipe';

/** Search-as-you-type over the menu, with full keyboard support (arrows, Enter, Escape). */
@Component({
  selector: 'app-live-search',
  imports: [RouterLink, MoneyPipe],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <form class="ls" role="search" (submit)="$event.preventDefault(); submit()">
      <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.500-3.500"/></svg>
      <input
        type="search"
        autocomplete="off"
        placeholder="What are you craving? Try “meat pie”"
        aria-label="Search the menu"
        role="combobox"
        aria-autocomplete="list"
        aria-controls="ls-results"
        [attr.aria-expanded]="open()"
        [attr.aria-activedescendant]="active() >= 0 ? 'ls-opt-' + active() : null"
        [value]="term()"
        (input)="onInput($any($event.target).value)"
        (focus)="open.set(term().length >= 2)"
        (keydown)="onKey($event)" />
      @if (loading()) { <span class="ls__spin" aria-hidden="true"></span> }
      <button type="submit" class="btn btn--sm">Search</button>

      @if (open()) {
        <div class="ls__panel" id="ls-results" role="listbox" aria-label="Matching dishes">
          @for (p of results(); track p.id; let i = $index) {
            <a class="ls__item" role="option" [id]="'ls-opt-' + i" [attr.aria-selected]="i === active()" [class.is-active]="i === active()"
               [routerLink]="['/product', p.slug]" (mouseenter)="active.set(i)" (click)="close()">
              <img [src]="p.image_url || '/placeholder.svg'" alt="" width="48" height="48" loading="lazy" />
              <span class="ls__name">{{ p.name }}<small>{{ p.category?.name }}</small></span>
              <span class="ls__price">from {{ p.price_from | money }}</span>
            </a>
          } @empty {
            @if (!loading()) {
              <p class="ls__empty">No dishes match “{{ term() }}”. Try “pie”, “suya” or “platter”.</p>
            }
          }
          @if (total() > results().length) {
            <button type="button" class="ls__all" (click)="submit()">See all {{ total() }} results</button>
          }
        </div>
      }
    </form>
  `,
  styleUrl: './live-search.scss',
})
export class LiveSearch {
  private readonly catalog = inject(CatalogService);
  private readonly router = inject(Router);
  private readonly host = inject<ElementRef<HTMLElement>>(ElementRef);
  private readonly input$ = new Subject<string>();

  protected readonly term = signal('');
  protected readonly results = signal<Product[]>([]);
  protected readonly total = signal(0);
  protected readonly loading = signal(false);
  protected readonly open = signal(false);
  protected readonly active = signal(-1);

  constructor() {
    this.input$
      .pipe(
        map((value) => value.trim()),
        debounceTime(180),
        distinctUntilChanged(),
        tap((value) => {
          this.active.set(-1);
          this.loading.set(value.length >= 2);
          if (value.length < 2) {
            this.results.set([]);
            this.total.set(0);
            this.open.set(false);
          }
        }),
        // switchMap drops stale responses, so results always belong to the latest keystroke.
        switchMap((value) =>
          value.length < 2
            ? of(null)
            : this.catalog.products({ search: value, per_page: 6 }).pipe(catchError(() => of({ data: [] as Product[], meta: undefined }))),
        ),
        takeUntilDestroyed(),
      )
      .subscribe((res) => {
        if (!res) {
          return;
        }
        this.results.set(res.data);
        this.total.set(res.meta?.total ?? res.data.length);
        this.loading.set(false);
        this.open.set(true);
      });
  }

  onInput(value: string): void {
    this.term.set(value);
    this.input$.next(value);
  }

  onKey(event: KeyboardEvent): void {
    const count = this.results().length;
    if (event.key === 'ArrowDown' && count) {
      event.preventDefault();
      this.open.set(true);
      this.active.update((i) => (i + 1) % count);
    } else if (event.key === 'ArrowUp' && count) {
      event.preventDefault();
      this.active.update((i) => (i <= 0 ? count - 1 : i - 1));
    } else if (event.key === 'Enter') {
      event.preventDefault();
      this.submit();
    } else if (event.key === 'Escape') {
      this.close();
    }
  }

  submit(): void {
    const picked = this.results()[this.active()];
    this.close();
    if (picked) {
      this.router.navigate(['/product', picked.slug]);
      return;
    }
    const q = this.term().trim();
    this.router.navigate(['/menu'], { queryParams: q ? { q } : {} });
  }

  close(): void {
    this.open.set(false);
    this.active.set(-1);
  }

  @HostListener('document:click', ['$event.target'])
  onDocumentClick(target: EventTarget | null): void {
    if (target instanceof Node && !this.host.nativeElement.contains(target)) {
      this.close();
    }
  }
}
