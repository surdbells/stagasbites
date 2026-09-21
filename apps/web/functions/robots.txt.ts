/** Serves robots.txt (which points at the sitemap) from the storefront domain by proxying the API. */
export const onRequestGet = async ({ env }: { env: { API_ORIGIN?: string } }): Promise<Response> => {
  if (!env.API_ORIGIN) {
    return new Response('User-agent: *\nDisallow:\n', { headers: { 'Content-Type': 'text/plain; charset=utf-8' } });
  }
  const upstream = await fetch(`${env.API_ORIGIN.replace(/\/$/, '')}/robots.txt`, {
    cf: { cacheTtl: 3600, cacheEverything: true },
  } as RequestInit);

  return new Response(upstream.body, {
    status: upstream.status,
    headers: { 'Content-Type': 'text/plain; charset=utf-8', 'Cache-Control': 'public, max-age=3600' },
  });
};
