/**
 * Cloudflare Pages middleware: SEO for a client-rendered Angular app, without SSR.
 *
 * Pages serves the same index.html for every storefront URL. Before it leaves the edge, this
 * function asks the API for that URL's metadata and writes the real <title>, description,
 * canonical, Open Graph/Twitter tags and JSON-LD into the HTML, and returns a true 404 status
 * for unknown pages. Crawlers and link previews therefore see finished tags; Angular's
 * SeoService then keeps them in sync during client-side navigation.
 *
 * It fails open: if the API is slow or down, visitors simply get the untouched page.
 *
 * Pages setting required: environment variable API_ORIGIN, e.g. https://api.stagasbites.ca
 */

interface Env {
  API_ORIGIN?: string;
}

interface PageMeta {
  title: string;
  description: string;
  url: string;
  image: string;
  type: string;
  noindex: boolean;
  status: number;
  json_ld: unknown[];
}

interface Context {
  request: Request;
  env: Env;
  next: () => Promise<Response>;
}

const HAS_EXTENSION = /\.[a-z0-9]{2,5}$/i;
const REPLACED_TAGS = [
  'meta[name="description"]',
  'meta[name="robots"]',
  'link[rel="canonical"]',
  'meta[property^="og:"]',
  'meta[name^="twitter:"]',
  'script[data-seo-jsonld]',
];

const escapeAttr = (value: string): string =>
  value.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

export const onRequest = async ({ request, env, next }: Context): Promise<Response> => {
  const url = new URL(request.url);
  const wantsHtml = (request.headers.get('accept') ?? '').includes('text/html');

  if (request.method !== 'GET' || !wantsHtml || HAS_EXTENSION.test(url.pathname) || !env.API_ORIGIN) {
    return next();
  }

  const page = await next();
  if (!(page.headers.get('content-type') ?? '').includes('text/html')) {
    return page;
  }

  let meta: PageMeta;
  try {
    const endpoint = `${env.API_ORIGIN.replace(/\/$/, '')}/api/v1/seo/meta?path=${encodeURIComponent(url.pathname)}`;
    const res = await fetch(endpoint, {
      signal: AbortSignal.timeout(2500),
      // Cached at the edge for five minutes, so most page views never reach the origin.
      cf: { cacheTtl: 300, cacheEverything: true },
    } as RequestInit);
    if (!res.ok) {
      return page;
    }
    meta = ((await res.json()) as { data: PageMeta }).data;
  } catch {
    return page;
  }

  const tags = [
    `<meta name="description" content="${escapeAttr(meta.description)}">`,
    `<meta name="robots" content="${meta.noindex ? 'noindex, nofollow' : 'index, follow, max-image-preview:large'}">`,
    `<link rel="canonical" href="${escapeAttr(meta.url)}">`,
    `<meta property="og:site_name" content="Staga's Bites">`,
    `<meta property="og:title" content="${escapeAttr(meta.title)}">`,
    `<meta property="og:description" content="${escapeAttr(meta.description)}">`,
    `<meta property="og:type" content="${escapeAttr(meta.type)}">`,
    `<meta property="og:url" content="${escapeAttr(meta.url)}">`,
    `<meta property="og:image" content="${escapeAttr(meta.image)}">`,
    `<meta property="og:locale" content="en_CA">`,
    `<meta name="twitter:card" content="summary_large_image">`,
    `<meta name="twitter:title" content="${escapeAttr(meta.title)}">`,
    `<meta name="twitter:description" content="${escapeAttr(meta.description)}">`,
    `<meta name="twitter:image" content="${escapeAttr(meta.image)}">`,
    ...meta.json_ld.map(
      (block) => `<script type="application/ld+json" data-seo-jsonld>${JSON.stringify(block).replace(/</g, '\\u003c')}</script>`,
    ),
  ].join('\n');

  let rewriter = new HTMLRewriter()
    .on('title', { element: (el) => void el.setInnerContent(meta.title) })
    .on('head', { element: (el) => void el.append(tags, { html: true }) });
  for (const selector of REPLACED_TAGS) {
    rewriter = rewriter.on(selector, { element: (el) => void el.remove() });
  }

  const headers = new Headers(page.headers);
  headers.set('Cache-Control', 'public, max-age=0, must-revalidate');

  return new Response(rewriter.transform(page).body, { status: meta.status === 404 ? 404 : page.status, headers });
};

// Minimal ambient types so this file type-checks without pulling in @cloudflare/workers-types.
interface RewriterElement {
  setInnerContent(content: string): void;
  append(content: string, options?: { html: boolean }): void;
  remove(): void;
}

declare class HTMLRewriter {
  on(selector: string, handlers: { element: (el: RewriterElement) => void }): HTMLRewriter;
  transform(response: Response): Response;
}
