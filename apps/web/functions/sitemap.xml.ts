/** Serves the database-driven sitemap from the storefront domain by proxying the API. */
export const onRequestGet = async ({ env }: { env: { API_ORIGIN?: string } }): Promise<Response> => {
  if (!env.API_ORIGIN) {
    return new Response('API_ORIGIN is not configured.', { status: 503 });
  }
  const upstream = await fetch(`${env.API_ORIGIN.replace(/\/$/, '')}/sitemap.xml`, {
    cf: { cacheTtl: 3600, cacheEverything: true },
  } as RequestInit);

  return new Response(upstream.body, {
    status: upstream.status,
    headers: { 'Content-Type': 'application/xml; charset=utf-8', 'Cache-Control': 'public, max-age=3600' },
  });
};
