import { Injectable, inject } from '@angular/core';
import { Observable, shareReplay } from 'rxjs';
import { ApiService } from './api.service';
import { ApiResponse, Category, Product, StoreSettings } from './models';

export interface ProductQuery {
  category?: string;
  search?: string;
  featured?: boolean;
  sort?: 'featured' | 'name' | 'price_asc' | 'price_desc' | 'newest';
  page?: number;
  per_page?: number;
}

@Injectable({ providedIn: 'root' })
export class CatalogService {
  private readonly api = inject(ApiService);
  private categories$?: Observable<Category[]>;
  private settings$?: Observable<StoreSettings>;

  categories(): Observable<Category[]> {
    return (this.categories$ ??= this.api.get<Category[]>('/categories').pipe(shareReplay(1)));
  }

  settings(): Observable<StoreSettings> {
    return (this.settings$ ??= this.api.get<StoreSettings>('/settings').pipe(shareReplay(1)));
  }

  products(query: ProductQuery = {}): Observable<ApiResponse<Product[]>> {
    return this.api.getPage<Product[]>('/products', { ...query });
  }

  product(slug: string): Observable<Product> {
    return this.api.get<Product>(`/products/${encodeURIComponent(slug)}`);
  }
}
