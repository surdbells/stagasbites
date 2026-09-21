import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { catchError, of } from 'rxjs';
import { ApiService } from '../core/api.service';
import { CatalogService } from '../core/catalog.service';
import { SITE } from '../core/site';

@Component({
  selector: 'app-footer',
  imports: [RouterLink, FormsModule],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './footer.html',
  styleUrl: './footer.scss',
})
export class Footer {
  private readonly api = inject(ApiService);
  protected readonly site = SITE;
  protected readonly year = new Date().getFullYear();
  protected readonly settings = toSignal(inject(CatalogService).settings().pipe(catchError(() => of(null))), { initialValue: null });

  protected email = '';
  protected readonly newsletter = signal<{ ok: boolean; text: string } | null>(null);
  protected readonly sending = signal(false);

  subscribe(): void {
    if (!/^\S+@\S+\.\S+$/.test(this.email)) {
      this.newsletter.set({ ok: false, text: 'Please enter a valid email address.' });
      return;
    }
    this.sending.set(true);
    this.api.post('/newsletter', { email: this.email }).subscribe({
      next: () => {
        this.sending.set(false);
        this.email = '';
        this.newsletter.set({ ok: true, text: "You're on the list — thank you!" });
      },
      error: () => {
        this.sending.set(false);
        this.newsletter.set({ ok: false, text: 'Something went wrong. Please try again.' });
      },
    });
  }
}
