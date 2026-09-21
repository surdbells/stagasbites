import { HttpClient, HttpErrorResponse } from '@angular/common/http';
import { ChangeDetectionStrategy, Component, effect, inject, input, signal } from '@angular/core';
import { FormArray, FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { environment } from '../../environments/environment';
import { ApiService } from '../core/api.service';
import { ApiResponse, Category, Product } from '../core/models';

@Component({
  selector: 'app-admin-product-edit',
  imports: [ReactiveFormsModule, RouterLink],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <div class="adm-head">
      <h1>{{ isNew() ? 'New product' : 'Edit product' }}</h1>
      <a routerLink="/admin/products" class="link-btn">← All products</a>
    </div>

    @if (message(); as m) { <div class="alert" [class.alert--error]="failed()" [class.alert--success]="!failed()">{{ m }}</div> }

    <form [formGroup]="form" (ngSubmit)="save()" class="adm-grid" novalidate>
      <div class="card">
        <div class="field"><label for="name">Name</label><input id="name" formControlName="name" /></div>
        <div class="field">
          <label for="slug">URL slug</label>
          <input id="slug" formControlName="slug" placeholder="Generated from the name if left blank" />
          @if (errors()['slug']; as e) { <span class="error">{{ e[0] }}</span> }
        </div>
        <div class="field"><label for="short">Short description (cards &amp; search results)</label><input id="short" formControlName="short_description" maxlength="320" /></div>
        <div class="field"><label for="desc">Full description</label><textarea id="desc" formControlName="description" rows="5"></textarea></div>

        <h2>Sizes &amp; prices</h2>
        <p class="text-soft">Prices are in dollars. The first row is pre-selected for customers.</p>
        <div formArrayName="options">
          @for (row of options.controls; track row; let i = $index) {
            <div class="opt-row" [formGroupName]="i">
              <input formControlName="label" placeholder="e.g. Tray of 25" aria-label="Size label" />
              <input formControlName="price" type="number" min="0" step="0.01" placeholder="Price" aria-label="Price in dollars" />
              <input formControlName="serves" placeholder="Serves 4–6 (optional)" aria-label="Serves" />
              <button type="button" class="link-btn link-btn--danger" (click)="options.removeAt(i)" [disabled]="options.length === 1">Remove</button>
            </div>
          }
        </div>
        @if (errors()['options']; as e) { <span class="error">{{ e[0] }}</span> }
        <button type="button" class="link-btn" (click)="addOption()">+ Add a size</button>

        <h2 style="margin-top: 2rem">Search engine listing</h2>
        <div class="field"><label for="mt">SEO title (optional)</label><input id="mt" formControlName="meta_title" maxlength="160" /></div>
        <div class="field"><label for="md">SEO description (optional)</label><textarea id="md" formControlName="meta_description" rows="2" maxlength="320"></textarea></div>
      </div>

      <div style="display: grid; gap: 1.5rem">
        <div class="card">
          <h2>Photo</h2>
          <img class="img-preview" [src]="form.controls.image_url.value || '/placeholder.svg'" alt="" />
          <div class="field">
            <label for="file">Upload (JPG, PNG, WebP · max 5 MB)</label>
            <input id="file" type="file" accept="image/jpeg,image/png,image/webp,image/avif" (change)="upload($event)" [disabled]="uploading()" />
          </div>
          <div class="field"><label for="img">…or image URL</label><input id="img" formControlName="image_url" /></div>
        </div>

        <div class="card">
          <h2>Organise</h2>
          <div class="field">
            <label for="cat">Category</label>
            <select id="cat" formControlName="category_id">
              <option value="">No category</option>
              @for (c of categories(); track c.id) { <option [value]="c.id">{{ c.name }}</option> }
            </select>
          </div>
          <div class="field"><label for="tags">Tags (comma separated)</label><input id="tags" formControlName="tags" /></div>
          <div class="form-row">
            <div class="field">
              <label for="spice">Heat</label>
              <select id="spice" formControlName="spice_level">
                <option [ngValue]="0">None</option><option [ngValue]="1">Mild</option><option [ngValue]="2">Medium</option><option [ngValue]="3">Hot</option>
              </select>
            </div>
            <div class="field"><label for="minq">Min quantity</label><input id="minq" type="number" min="1" formControlName="min_quantity" /></div>
          </div>
          <div class="field"><label for="lead">Notice needed (hours)</label><input id="lead" type="number" min="0" formControlName="lead_time_hours" /></div>
          <label class="check"><input type="checkbox" formControlName="is_available" /> Visible on the menu</label>
          <label class="check"><input type="checkbox" formControlName="is_featured" /> Feature on the home page</label>
        </div>

        <div class="adm-actions">
          <button type="submit" class="btn" [disabled]="busy()">{{ busy() ? 'Saving…' : 'Save product' }}</button>
          @if (!isNew()) { <button type="button" class="btn btn--ghost" (click)="remove()">Delete</button> }
        </div>
      </div>
    </form>
  `,
})
export class AdminProductEdit {
  readonly id = input.required<string>();

  private readonly api = inject(ApiService);
  private readonly http = inject(HttpClient);
  private readonly router = inject(Router);
  private readonly fb = inject(FormBuilder);

  protected readonly categories = signal<Category[]>([]);
  protected readonly busy = signal(false);
  protected readonly uploading = signal(false);
  protected readonly failed = signal(false);
  protected readonly message = signal<string | null>(null);
  protected readonly errors = signal<Record<string, string[]>>({});
  protected readonly isNew = () => this.id() === 'new';

  protected readonly form = this.fb.nonNullable.group({
    name: ['', Validators.required],
    slug: [''],
    short_description: [''],
    description: [''],
    image_url: [''],
    category_id: [''],
    tags: [''],
    spice_level: [0],
    min_quantity: [1],
    lead_time_hours: [48],
    is_available: [true],
    is_featured: [false],
    meta_title: [''],
    meta_description: [''],
    options: this.fb.array([this.optionRow()]),
  });

  get options(): FormArray {
    return this.form.controls.options;
  }

  constructor() {
    this.api.get<Category[]>('/admin/categories').subscribe((c) => this.categories.set(c));
    effect(() => {
      if (!this.isNew()) {
        this.api.get<Product>(`/admin/products/${this.id()}`).subscribe((p) => this.fill(p));
      }
    });
  }

  addOption(): void {
    this.options.push(this.optionRow());
  }

  upload(event: Event): void {
    const file = (event.target as HTMLInputElement).files?.[0];
    if (!file) {
      return;
    }
    const body = new FormData();
    body.append('file', file);
    this.uploading.set(true);
    this.http.post<ApiResponse<{ url: string }>>(`${environment.apiUrl}/admin/uploads`, body).subscribe({
      next: (res) => {
        this.form.controls.image_url.setValue(res.data.url);
        this.uploading.set(false);
      },
      error: (err: HttpErrorResponse) => {
        this.uploading.set(false);
        this.fail(err, 'Upload failed.');
      },
    });
  }

  save(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }
    const v = this.form.getRawValue();
    const payload = {
      ...v,
      category_id: v.category_id || null,
      tags: v.tags.split(',').map((t) => t.trim()).filter(Boolean),
      spice_level: Number(v.spice_level),
      min_quantity: Number(v.min_quantity),
      lead_time_hours: Number(v.lead_time_hours),
      // The form edits dollars; the API stores cents.
      options: (v.options as { id: string; label: string; price: number; serves: string }[]).map((o, i) => ({
        ...(o.id ? { id: o.id } : {}),
        label: o.label,
        price: Math.round(Number(o.price) * 100),
        serves: o.serves,
        is_default: i === 0,
      })),
    };

    this.busy.set(true);
    this.errors.set({});
    const request = this.isNew() ? this.api.post<Product>('/admin/products', payload) : this.api.put<Product>(`/admin/products/${this.id()}`, payload);
    request.subscribe({
      next: (p) => {
        this.busy.set(false);
        this.failed.set(false);
        this.message.set('Saved.');
        if (this.isNew()) {
          this.router.navigate(['/admin/products', p.id]);
        } else {
          this.fill(p);
        }
      },
      error: (err: HttpErrorResponse) => {
        this.busy.set(false);
        this.fail(err, 'Could not save the product.');
      },
    });
  }

  remove(): void {
    if (confirm('Delete this product? Past orders keep their own copy of the item.')) {
      this.api.delete(`/admin/products/${this.id()}`).subscribe(() => this.router.navigateByUrl('/admin/products'));
    }
  }

  private fill(p: Product): void {
    this.options.clear();
    for (const o of p.options) {
      this.options.push(this.optionRow(o.id, o.label, o.price / 100, o.serves ?? ''));
    }
    this.form.patchValue({
      name: p.name,
      slug: p.slug,
      short_description: p.short_description ?? '',
      description: p.description ?? '',
      image_url: p.image_url ?? '',
      category_id: p.category?.id ?? '',
      tags: p.tags.join(', '),
      spice_level: p.spice_level,
      min_quantity: p.min_quantity,
      lead_time_hours: p.lead_time_hours,
      is_available: p.is_available,
      is_featured: p.is_featured,
      meta_title: p.meta_title ?? '',
      meta_description: p.meta_description ?? '',
    });
  }

  private optionRow(id = '', label = '', price: number | null = null, serves = '') {
    return this.fb.nonNullable.group({
      id: [id],
      label: [label, Validators.required],
      price: [price as number | null, [Validators.required, Validators.min(0)]],
      serves: [serves],
    });
  }

  private fail(err: HttpErrorResponse, fallback: string): void {
    const body = err.error as ApiResponse<unknown>;
    this.failed.set(true);
    this.errors.set(body?.errors ?? {});
    this.message.set(body?.message ?? fallback);
  }
}
