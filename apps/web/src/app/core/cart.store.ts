import { Injectable, computed, effect, signal } from '@angular/core';
import { CartLine, Product, ProductOption } from './models';

const STORAGE_KEY = 'stagas.cart.v1';

@Injectable({ providedIn: 'root' })
export class CartStore {
  private readonly _lines = signal<CartLine[]>(this.restore());
  readonly drawerOpen = signal(false);

  readonly lines = this._lines.asReadonly();
  readonly count = computed(() => this._lines().reduce((n, l) => n + l.quantity, 0));
  readonly subtotal = computed(() =>
    this._lines().reduce((sum, l) => sum + l.unit_price * l.quantity, 0),
  );
  readonly isEmpty = computed(() => this._lines().length === 0);

  constructor() {
    effect(() => {
      try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(this._lines()));
      } catch {
        // storage unavailable (private mode) — cart stays in memory
      }
    });
  }

  add(product: Product, option: ProductOption, quantity: number): void {
    const qty = Math.max(quantity, product.min_quantity);
    this._lines.update((lines) => {
      const existing = lines.find((l) => l.option_id === option.id);
      if (existing) {
        return lines.map((l) =>
          l.option_id === option.id ? { ...l, quantity: l.quantity + quantity } : l,
        );
      }
      return [
        ...lines,
        {
          product_id: product.id,
          option_id: option.id,
          slug: product.slug,
          name: product.name,
          option_label: option.label,
          image_url: product.image_url,
          unit_price: option.price,
          quantity: qty,
          min_quantity: product.min_quantity,
        },
      ];
    });
    this.drawerOpen.set(true);
  }

  setQuantity(optionId: string, quantity: number): void {
    this._lines.update((lines) =>
      lines.map((l) =>
        l.option_id === optionId ? { ...l, quantity: Math.max(quantity, l.min_quantity) } : l,
      ),
    );
  }

  remove(optionId: string): void {
    this._lines.update((lines) => lines.filter((l) => l.option_id !== optionId));
  }

  clear(): void {
    this._lines.set([]);
  }

  private restore(): CartLine[] {
    try {
      const raw = localStorage.getItem(STORAGE_KEY);
      const parsed = raw ? JSON.parse(raw) : [];
      return Array.isArray(parsed) ? parsed : [];
    } catch {
      return [];
    }
  }
}
