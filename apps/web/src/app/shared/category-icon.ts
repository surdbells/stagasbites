import { ChangeDetectionStrategy, Component, input } from '@angular/core';

/** Line icon for a menu category, chosen by slug. Unknown (admin-created) categories get cutlery. */
@Component({
  selector: 'app-category-icon',
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      @switch (slug()) {
        @case ('all') {
          <rect x="3.500" y="3.500" width="7" height="7" rx="1.500" /><rect x="13.500" y="3.500" width="7" height="7" rx="1.500" />
          <rect x="3.500" y="13.500" width="7" height="7" rx="1.500" /><rect x="13.500" y="13.500" width="7" height="7" rx="1.500" />
        }
        @case ('small-chops') {
          <path d="M12 4 3.500 19h17L12 4Z" /><path d="M9.500 14.500h.010M13 12h.010M14.500 16h.010" />
        }
        @case ('grills') {
          <path d="M12 3c.800 3.600-3.600 5-3.600 9.400a3.600 3.600 0 0 0 7.200 0c0-1.400-.700-2.100-.700-3.600 2.100 1.400 3.900 4 3.900 6.700a6.800 6.800 0 0 1-13.600 0C5.200 9.800 10.600 8.100 12 3Z" />
        }
        @case ('snacks') {
          <path d="M3 14a9 9 0 0 1 18 0H3Z" /><path d="M2 17.500h20M8 14c0-2 .800-3.500 2-4.500M13 9.500c1.200 1 2 2.500 2 4.500" />
        }
        @case ('platters') {
          <ellipse cx="12" cy="15" rx="9" ry="3.500" /><path d="M3 15v1.500c0 1.900 4 3.500 9 3.500s9-1.600 9-3.500V15M8 9.500a4 4 0 0 1 8 0M12 5.500v-2" />
        }
        @case ('rice-mains') {
          <path d="M3 11h18a9 9 0 0 1-18 0Z" /><path d="M7 20h10M8 7.500c0-1.200 1-1.300 1-2.500M12 7.500c0-1.200 1-1.300 1-2.500M16 7.500c0-1.200 1-1.300 1-2.500" />
        }
        @case ('soups-swallows') {
          <path d="M5 10h14v6a4 4 0 0 1-4 4H9a4 4 0 0 1-4-4v-6Z" /><path d="M3 10h18M2.500 13H5M19 13h2.500M9 6.500c0-1.200 1-1.300 1-2.500M14 6.500c0-1.200 1-1.300 1-2.500" />
        }
        @case ('sides-sauces') {
          <path d="M10 3h4v3.500l1.500 2.500V19a2 2 0 0 1-2 2h-3a2 2 0 0 1-2-2V9L10 6.500V3Z" /><path d="M8.500 13h7M9.500 3h5" />
        }
        @case ('drinks') {
          <path d="M6 4h12l-1.500 15a2 2 0 0 1-2 2h-5a2 2 0 0 1-2-2L6 4Z" /><path d="M6.700 10h10.600M14 4l2-2.500" />
        }
        @default {
          <path d="M7 3v8a2 2 0 0 0 2 2v8M5 3v5M9 3v5M17 3c-2 1.500-3 4-3 7h3v11" />
        }
      }
    </svg>
  `,
  styles: `
    :host {
      display: inline-grid;
      place-items: center;
      flex: none;
    }
  `,
})
export class CategoryIcon {
  readonly slug = input.required<string>();
}
