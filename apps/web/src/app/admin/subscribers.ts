import { DatePipe } from '@angular/common';
import { ChangeDetectionStrategy, Component, computed, inject } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { ApiService } from '../core/api.service';

interface Subscriber {
  id: string;
  email: string;
  is_active: boolean;
  created_at: string;
}

@Component({
  selector: 'app-admin-subscribers',
  imports: [DatePipe],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <div class="adm-head">
      <h1>Newsletter subscribers</h1>
      <a class="btn btn--ghost btn--sm" [href]="csv()" download="subscribers.csv">Export CSV</a>
    </div>
    <div class="adm-table-wrap">
      <table>
        <thead><tr><th>Email</th><th>Joined</th><th>Status</th></tr></thead>
        <tbody>
          @for (s of subscribers(); track s.id) {
            <tr>
              <td>{{ s.email }}</td>
              <td>{{ s.created_at | date: 'mediumDate' }}</td>
              <td><span class="status" [attr.data-s]="s.is_active ? 'completed' : 'cancelled'">{{ s.is_active ? 'Subscribed' : 'Unsubscribed' }}</span></td>
            </tr>
          } @empty {
            <tr><td colspan="3" class="adm-empty">No subscribers yet.</td></tr>
          }
        </tbody>
      </table>
    </div>
  `,
})
export class AdminSubscribers {
  protected readonly subscribers = toSignal(inject(ApiService).get<Subscriber[]>('/admin/subscribers'), { initialValue: [] as Subscriber[] });

  protected readonly csv = computed(() => {
    const rows = this.subscribers().filter((s) => s.is_active).map((s) => `${s.email},${s.created_at}`);
    return 'data:text/csv;charset=utf-8,' + encodeURIComponent(['email,joined', ...rows].join('\n'));
  });
}
