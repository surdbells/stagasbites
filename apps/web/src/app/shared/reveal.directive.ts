import { Directive, ElementRef, OnDestroy, OnInit, inject, input } from '@angular/core';

/** Adds `.is-visible` when the host scrolls into view; styles live in styles.scss. */
@Directive({ selector: '[appReveal]', host: { class: 'reveal' } })
export class RevealDirective implements OnInit, OnDestroy {
  readonly delay = input(0, { alias: 'appReveal', transform: (v: number | string) => Number(v) || 0 });
  private readonly el = inject<ElementRef<HTMLElement>>(ElementRef);
  private observer?: IntersectionObserver;

  ngOnInit(): void {
    const node = this.el.nativeElement;
    if (typeof IntersectionObserver === 'undefined') {
      node.classList.add('is-visible');
      return;
    }
    node.style.transitionDelay = `${this.delay()}ms`;
    this.observer = new IntersectionObserver(
      ([entry]) => {
        if (entry.isIntersecting) {
          node.classList.add('is-visible');
          this.observer?.disconnect();
        }
      },
      { threshold: 0.12, rootMargin: '0px 0px -40px 0px' },
    );
    this.observer.observe(node);
  }

  ngOnDestroy(): void {
    this.observer?.disconnect();
  }
}
