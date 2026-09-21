import { Pipe, PipeTransform } from '@angular/core';

/** Formats minor units (cents) as currency, e.g. 2500 -> $25.00. */
@Pipe({ name: 'money' })
export class MoneyPipe implements PipeTransform {
  private readonly formatters = new Map<string, Intl.NumberFormat>();

  transform(cents: number | null | undefined, currency = 'CAD'): string {
    let fmt = this.formatters.get(currency);
    if (!fmt) {
      fmt = new Intl.NumberFormat('en-CA', { style: 'currency', currency });
      this.formatters.set(currency, fmt);
    }
    return fmt.format((cents ?? 0) / 100);
  }
}
