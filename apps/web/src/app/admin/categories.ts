import { HttpErrorResponse } from '@angular/common/http';
import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ApiService } from '../core/api.service';
import { ApiResponse, Category } from '../core/models';

@Component({
  selector: 'app-admin-categories',
  imports: [ReactiveFormsModule],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <div class="adm-head"><h1>Categories</h1></div>
    @if (message(); as m) { <div class="alert alert--error">{{ m }}</div> }

    <div class="adm-grid">
      <div class="adm-table-wrap">
        <table>
          <thead><tr><th></th><th>Name</th><th>Slug</th><th>Order</th><th>Active</th><th></th></tr></thead>
          <tbody>
            @for (c of categories(); track c.id) {
              <tr>
                <td><img [src]="c.image_url || '/placeholder.svg'" alt="" /></td>
                <td>{{ c.name }}</td>
                <td>{{ c.slug }}</td>
                <td>{{ c.sort_order }}</td>
                <td>{{ c.is_active ? 'Yes' : 'No' }}</td>
                <td class="adm-actions">
                  <button type="button" class="link-btn" (click)="edit(c)">Edit</button>
                  <button type="button" class="link-btn link-btn--danger" (click)="remove(c)">Delete</button>
                </td>
              </tr>
            } @empty {
              <tr><td colspan="6" class="adm-empty">No categories yet.</td></tr>
            }
          </tbody>
        </table>
      </div>

      <form class="card" [formGroup]="form" (ngSubmit)="save()" novalidate>
        <h2>{{ editing() ? 'Edit category' : 'New category' }}</h2>
        <div class="field"><label for="c-name">Name</label><input id="c-name" formControlName="name" /></div>
        <div class="field"><label for="c-slug">URL slug</label><input id="c-slug" formControlName="slug" placeholder="Generated if blank" /></div>
        <div class="field"><label for="c-desc">Description</label><textarea id="c-desc" formControlName="description" rows="3"></textarea></div>
        <div class="field"><label for="c-img">Image URL</label><input id="c-img" formControlName="image_url" /></div>
        <div class="field"><label for="c-order">Sort order</label><input id="c-order" type="number" formControlName="sort_order" /></div>
        <div class="field"><label for="c-md">SEO description (optional)</label><textarea id="c-md" formControlName="meta_description" rows="2" maxlength="320"></textarea></div>
        <label class="check"><input type="checkbox" formControlName="is_active" /> Show on the site</label>
        <div class="adm-actions">
          <button type="submit" class="btn btn--sm">Save</button>
          @if (editing()) { <button type="button" class="btn btn--ghost btn--sm" (click)="reset()">Cancel</button> }
        </div>
      </form>
    </div>
  `,
})
export class AdminCategories {
  private readonly api = inject(ApiService);
  protected readonly categories = signal<Category[]>([]);
  protected readonly editing = signal<string | null>(null);
  protected readonly message = signal<string | null>(null);

  protected readonly form = inject(FormBuilder).nonNullable.group({
    name: ['', Validators.required],
    slug: [''],
    description: [''],
    image_url: [''],
    sort_order: [0],
    meta_description: [''],
    is_active: [true],
  });

  constructor() {
    this.load();
  }

  edit(c: Category): void {
    this.editing.set(c.id);
    this.form.patchValue({
      name: c.name,
      slug: c.slug,
      description: c.description ?? '',
      image_url: c.image_url ?? '',
      sort_order: c.sort_order,
      meta_description: c.meta_description ?? '',
      is_active: c.is_active,
    });
  }

  reset(): void {
    this.editing.set(null);
    this.form.reset();
  }

  save(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }
    const id = this.editing();
    const body = { ...this.form.getRawValue(), sort_order: Number(this.form.controls.sort_order.value) };
    (id ? this.api.put(`/admin/categories/${id}`, body) : this.api.post('/admin/categories', body)).subscribe({
      next: () => {
        this.message.set(null);
        this.reset();
        this.load();
      },
      error: (err: HttpErrorResponse) => this.message.set(this.describe(err)),
    });
  }

  remove(c: Category): void {
    if (confirm(`Delete “${c.name}”? Its products stay, but become uncategorised.`)) {
      this.api.delete(`/admin/categories/${c.id}`).subscribe(() => this.load());
    }
  }

  private load(): void {
    this.api.get<Category[]>('/admin/categories').subscribe((c) => this.categories.set(c));
  }

  private describe(err: HttpErrorResponse): string {
    const body = err.error as ApiResponse<unknown>;
    return Object.values(body?.errors ?? {})[0]?.[0] ?? body?.message ?? 'Could not save.';
  }
}
