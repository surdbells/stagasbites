import { DatePipe } from '@angular/common';
import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { ApiService } from '../core/api.service';

interface Inquiry {
  id: string;
  type: 'contact' | 'catering';
  name: string;
  email: string;
  phone: string | null;
  event_date: string | null;
  guest_count: number | null;
  event_type: string | null;
  message: string;
  status: 'new' | 'in_progress' | 'closed';
  created_at: string;
}

@Component({
  selector: 'app-admin-inquiries',
  imports: [DatePipe],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <div class="adm-head"><h1>Inquiries</h1></div>
    <div style="display: grid; gap: 1rem">
      @for (i of inquiries(); track i.id) {
        <article class="card">
          <div class="adm-head" style="margin-bottom: 0.6rem">
            <div>
              <strong>{{ i.name }}</strong> · <span class="text-soft">{{ i.type }}</span>
              <span class="status" [attr.data-s]="i.status" style="margin-left: 0.5rem">{{ i.status.replace('_', ' ') }}</span><br />
              <small class="text-soft">
                <a [href]="'mailto:' + i.email">{{ i.email }}</a>
                @if (i.phone) { · <a [href]="'tel:' + i.phone">{{ i.phone }}</a> }
                · {{ i.created_at | date: 'MMM d, HH:mm' }}
              </small>
            </div>
            <div class="adm-actions">
              @if (i.status !== 'in_progress') { <button type="button" class="link-btn" (click)="set(i, 'in_progress')">In progress</button> }
              @if (i.status !== 'closed') { <button type="button" class="link-btn" (click)="set(i, 'closed')">Close</button> }
            </div>
          </div>
          @if (i.type === 'catering') {
            <p class="text-soft">
              {{ i.event_type || 'Event' }} @if (i.event_date) { · {{ i.event_date | date: 'mediumDate' }} } @if (i.guest_count) { · {{ i.guest_count }} guests }
            </p>
          }
          <p style="white-space: pre-line; margin: 0">{{ i.message }}</p>
        </article>
      } @empty {
        <p class="adm-empty">No inquiries yet.</p>
      }
    </div>
  `,
})
export class AdminInquiries {
  private readonly api = inject(ApiService);
  protected readonly inquiries = signal<Inquiry[]>([]);

  constructor() {
    this.api.get<Inquiry[]>('/admin/inquiries').subscribe((list) => this.inquiries.set(list));
  }

  set(inquiry: Inquiry, status: Inquiry['status']): void {
    this.api.patch<Inquiry>(`/admin/inquiries/${inquiry.id}`, { status }).subscribe((updated) => {
      this.inquiries.update((list) => list.map((i) => (i.id === updated.id ? updated : i)));
    });
  }
}
