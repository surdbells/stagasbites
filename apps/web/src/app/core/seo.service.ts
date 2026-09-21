import { DOCUMENT } from '@angular/common';
import { Injectable, inject } from '@angular/core';
import { Meta, Title } from '@angular/platform-browser';
import { environment } from '../../environments/environment';

export interface SeoConfig {
  title: string;
  description: string;
  /** Path beginning with "/". Defaults to the current location path. */
  path?: string;
  image?: string | null;
  type?: 'website' | 'product' | 'article';
  noindex?: boolean;
  /** One or more schema.org JSON-LD objects for this page. */
  jsonLd?: object | object[];
}

const SITE_NAME = "Staga's Bites";
const DEFAULT_IMAGE = '/og-default.jpg';

/**
 * Keeps <title>, meta description, canonical, Open Graph/Twitter tags and
 * JSON-LD in sync with the active route. The API mirrors the same tags into
 * index.html for crawlers that don't execute JavaScript (see SpaController).
 */
@Injectable({ providedIn: 'root' })
export class SeoService {
  private readonly title = inject(Title);
  private readonly meta = inject(Meta);
  private readonly doc = inject(DOCUMENT);

  set(config: SeoConfig): void {
    const fullTitle = config.title.includes(SITE_NAME)
      ? config.title
      : `${config.title} | ${SITE_NAME}`;
    const url = this.absolute(config.path ?? this.doc.location.pathname);
    const image = this.absolute(config.image || DEFAULT_IMAGE);

    this.title.setTitle(fullTitle);
    this.meta.updateTag({ name: 'description', content: config.description });
    this.meta.updateTag({
      name: 'robots',
      content: config.noindex ? 'noindex, nofollow' : 'index, follow, max-image-preview:large',
    });

    this.meta.updateTag({ property: 'og:site_name', content: SITE_NAME });
    this.meta.updateTag({ property: 'og:title', content: fullTitle });
    this.meta.updateTag({ property: 'og:description', content: config.description });
    this.meta.updateTag({ property: 'og:type', content: config.type ?? 'website' });
    this.meta.updateTag({ property: 'og:url', content: url });
    this.meta.updateTag({ property: 'og:image', content: image });
    this.meta.updateTag({ property: 'og:locale', content: 'en_CA' });

    this.meta.updateTag({ name: 'twitter:card', content: 'summary_large_image' });
    this.meta.updateTag({ name: 'twitter:title', content: fullTitle });
    this.meta.updateTag({ name: 'twitter:description', content: config.description });
    this.meta.updateTag({ name: 'twitter:image', content: image });

    this.setCanonical(url);
    this.setJsonLd(config.jsonLd);
  }

  absolute(pathOrUrl: string): string {
    if (/^https?:\/\//i.test(pathOrUrl)) {
      return pathOrUrl;
    }
    return environment.siteUrl.replace(/\/$/, '') + (pathOrUrl.startsWith('/') ? '' : '/') + pathOrUrl;
  }

  private setCanonical(url: string): void {
    let link = this.doc.head.querySelector<HTMLLinkElement>('link[rel="canonical"]');
    if (!link) {
      link = this.doc.createElement('link');
      link.rel = 'canonical';
      this.doc.head.appendChild(link);
    }
    link.href = url;
  }

  private setJsonLd(data?: object | object[]): void {
    this.doc.head.querySelectorAll('script[data-seo-jsonld]').forEach((el) => el.remove());
    if (!data) {
      return;
    }
    for (const entry of Array.isArray(data) ? data : [data]) {
      const script = this.doc.createElement('script');
      script.type = 'application/ld+json';
      script.setAttribute('data-seo-jsonld', '');
      script.textContent = JSON.stringify(entry).replace(/</g, '\\u003c');
      this.doc.head.appendChild(script);
    }
  }
}
