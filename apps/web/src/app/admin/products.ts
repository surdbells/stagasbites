import { ChangeDetectionStrategy, Component, effect, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { ApiService } from '../core/api.service';
import { Product } from '../core/models';
import { MoneyPipe } from '../shared/money.pipe';

@Component({
  selector: 'app-admin-products',
  imports: [RouterLink, FormsModule, MoneyPipe],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <div class="adm-head">
      <h1>Products</h1>
      <a routerLink="/admin/products/new" class="btn btn--sm">Add product</a>
    </div>

    <div class="adm-filters">
      <input type="search" placeholder="Search products" [ngModel]="search()" (ngModelChange)="search.set($event)" />
    </div>

    <div class="adm-table-wrap">
      <table>
        <thead><tr><th></th><th>Name</th><th>Category</th><th>From</th><th>Sizes</th><th>Visible</th><th>Featured</th></tr></thead>
        <tbody>
          @for (p of products(); track p.id) {
            <tr>
              <td><img [src]="p.image_url || '/placeholder.svg'" alt="" /></td>
              <td><a [routerLink]="['/admin/products', p.id]">{{ p.name }}</a></td>
              <td>{{ p.category?.name ?? '—' }}</td>
              <td>{{ p.price_from | money }}</td>
              <td>{{ p.options.length }}</td>
              <td>{{ p.is_available ? 'Yes' : 'Hidden' }}</td>
              <td>{{ p.is_featured ? '★' : '' }}</td>
            </tr>
          } @empty {
            <tr><td colspan="7" class="adm-empty">No products found.</td></tr>
          }
        </tbody>
      </table>
    </div>
  `,
})
export class AdminProducts {
  private readonly api = inject(ApiService);
  protected readonly search = signal('');
  protected readonly products = signal<Product[]>([]);

  constructor() {
    effect(() => {
      this.api.getPage<Product[]>('/admin/products', { search: this.search(), per_page: 100 }).subscribe((res) => this.products.set(res.data));
    });
  }
}
