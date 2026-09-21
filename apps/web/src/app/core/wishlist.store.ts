import { Injectable, computed, effect, signal } from '@angular/core';

const STORAGE_KEY = 'stagas.wishlist.v1';

/** Saved-for-later product ids, kept on the device so it works without an account. */
@Injectable({ providedIn: 'root' })
export class WishlistStore {
  private readonly _ids = signal<string[]>(this.restore());

  readonly ids = this._ids.asReadonly();
  readonly count = computed(() => this._ids().length);

  constructor() {
    effect(() => {
      try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(this._ids()));
      } catch {
        // storage unavailable — the list simply lasts for this visit
      }
    });
  }

  has(id: string): boolean {
    return this._ids().includes(id);
  }

  toggle(id: string): void {
    this._ids.update((ids) => (ids.includes(id) ? ids.filter((x) => x !== id) : [...ids, id]));
  }

  private restore(): string[] {
    try {
      const parsed = JSON.parse(localStorage.getItem(STORAGE_KEY) ?? '[]');
      return Array.isArray(parsed) ? parsed.filter((x): x is string => typeof x === 'string') : [];
    } catch {
      return [];
    }
  }
}
