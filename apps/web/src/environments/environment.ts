/**
 * Production. The storefront is hosted on Cloudflare Pages and the API on its own subdomain,
 * so the API URL is absolute. Change both values here if the domain changes.
 */
export const environment = {
  production: true,
  apiUrl: 'https://api.stagasbites.ca/api/v1',
  siteUrl: 'https://stagasbites.ca',
};
