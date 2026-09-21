import { DatePipe } from '@angular/common';
import { ChangeDetectionStrategy, Component, OnDestroy, computed, effect, inject, input, signal } from '@angular/core';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { ApiService } from '../../core/api.service';
import { CartStore } from '../../core/cart.store';
import { Order, OrderStatus } from '../../core/models';
import { SeoService } from '../../core/seo.service';
import { SITE } from '../../core/site';
import { MoneyPipe } from '../../shared/money.pipe';

const TIMELINE: { status: OrderStatus; label: string }[] = [
  { status: 'paid', label: 'Confirmed' },
  { status: 'preparing', label: 'Preparing' },
  { status: 'ready', label: 'Ready' },
  { status: 'completed', label: 'Completed' },
];

@Component({
  selector: 'app-order-page',
  imports: [RouterLink, MoneyPipe, DatePipe],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './order.html',
  styleUrl: './order.scss',
})
export class OrderPage implements OnDestroy {
  /** Bound from the `/order/:id` route param. */
  readonly id = input.required<string>();

  private readonly api = inject(ApiService);
  private readonly cart = inject(CartStore);
  private readonly justPlaced = inject(ActivatedRoute).snapshot.queryParamMap.has('placed');

  protected readonly site = SITE;
  protected readonly timeline = TIMELINE;
  protected readonly order = signal<Order | null>(null);
  protected readonly state = signal<'loading' | 'ready' | 'missing'>('loading');
  protected readonly stepIndex = computed(() => TIMELINE.findIndex((s) => s.status === this.order()?.status));
  protected readonly awaitingPayment = computed(() => this.order()?.status === 'pending_payment');

  private pollTimer?: ReturnType<typeof setTimeout>;
  private polls = 0;

  constructor() {
    inject(SeoService).set({ title: 'Your order', description: 'Order status and details.', noindex: true });
    if (this.justPlaced) {
      this.cart.clear();
    }
    effect(() => this.load(this.id()));
  }

  ngOnDestroy(): void {
    clearTimeout(this.pollTimer);
  }

  private load(id: string): void {
    this.api.get<Order>(`/orders/${encodeURIComponent(id)}`).subscribe({
      next: (order) => {
        this.order.set(order);
        this.state.set('ready');
        // Stripe's webhook usually lands within a second or two of the redirect; poll briefly until it does.
        if (order.status === 'pending_payment' && this.justPlaced && this.polls++ < 15) {
          this.pollTimer = setTimeout(() => this.load(id), 2000);
        }
      },
      error: () => this.state.set('missing'),
    });
  }
}
