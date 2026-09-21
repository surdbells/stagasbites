# Staga's Bites

Ecommerce platform for Staga's Bites: Nigerian small chops, pastries and grills in Oakville, Ontario.

| Path | What |
|---|---|
| `apps/api` | PHP 8.3 · Slim 4 · PHP-DI · Doctrine ORM + Migrations · PostgreSQL |
| `apps/web` | Angular 22 SPA (no SSR) · signals · SCSS design system · self-hosted fonts |
| `docker-compose.yml` | Local PostgreSQL (host port **5439**) |
| `apps/web/functions` | Cloudflare Pages edge functions (SEO tags, sitemap, robots) |
| `docker/nginx` | nginx rules for the API vhost |
| `DEPLOYMENT_RUNBOOK.md` | Going live: Cloudflare Pages + aaPanel |

Payments are **Stripe Checkout** (hosted; card data never touches this server). Transactional email is **ZeptoMail** over its HTTP API.

## Local setup

```bash
docker compose up -d                      # PostgreSQL on localhost:5439

cd apps/api
cp .env.example .env                      # then set JWT_SECRET (see comment in the file)
composer install
php bin/console.php migrations:migrate --no-interaction
php bin/console.php app:seed              # 4 categories, 20 products
php bin/console.php app:create-admin you@example.com   # prompts for a password
composer serve                            # API on http://localhost:8090

cd ../web
npm install
npm start                                 # storefront on http://localhost:4200
```

Admin lives at `/admin` (sign in with the admin account).

### Stripe

1. Put your keys in `apps/api/.env` (`STRIPE_SECRET_KEY`, `STRIPE_WEBHOOK_SECRET`).
2. Forward webhooks locally: `stripe listen --forward-to localhost:8090/api/v1/webhooks/stripe`
3. In production, add the endpoint `https://<domain>/api/v1/webhooks/stripe` with events
   `checkout.session.completed`, `checkout.session.async_payment_succeeded`,
   `checkout.session.async_payment_failed`, `checkout.session.expired`.

Orders are only marked paid by the signed webhook, never by the browser redirect. The webhook
fails closed if the secret is missing, is idempotent per Stripe event id, and checks the charged
amount against the order total.

### ZeptoMail

Set `ZEPTOMAIL_API_KEY` (the "Send Mail token"), a verified `ZEPTOMAIL_FROM_EMAIL`, and
`ADMIN_NOTIFY_EMAIL`. Without a key, emails are skipped and logged, and nothing breaks.

### Google reviews

The home page shows live reviews from the Google Business listing (Place ID `ChIJ218RGCxlK4gRtgqbVBx8Nks`).

1. In Google Cloud Console, enable **Places API (New)** and create an API key.
2. Restrict the key to that API (and to the server's IP).
3. Set `GOOGLE_PLACES_API_KEY` in `apps/api/.env`.

The key never reaches the browser: the API fetches the reviews and caches them for an hour. Until a key is
set, the section still shows the rating with "read" and "write a review" links. Google returns at most five
reviews per listing through this API.

## Deployment

The storefront deploys to **Cloudflare Pages** from GitHub on every push to `main`; the API runs on a Linux
server with **aaPanel**. Full steps, rollback, backups and troubleshooting are in
[DEPLOYMENT_RUNBOOK.md](DEPLOYMENT_RUNBOOK.md).

## SEO without SSR

The Angular app is a plain client-rendered SPA. For crawlers and link previews, the API serves the
built `index.html` for every storefront URL with the route's `<title>`, description, canonical,
Open Graph/Twitter tags and JSON-LD already injected (`src/Module/Seo/SeoController.php`), returns a
real `404` for unknown URLs, and generates `/sitemap.xml` and `/robots.txt` from the database.
Once Angular boots, `SeoService` keeps the same tags in sync on navigation.

On Cloudflare Pages the same job is done at the edge: `apps/web/functions/_middleware.ts` fetches the tags from
`GET /api/v1/seo/meta?path=...` and rewrites `index.html` before it is served.

## Useful commands

```bash
# apps/api
composer test            # PHPUnit
composer analyse         # PHPStan (level 6 + doctrine extension)
composer lint            # php-cs-fixer dry run
php bin/console.php migrations:generate   # new raw-SQL migration
php bin/console.php migrations:status

# apps/web
npm run build            # production build -> dist/web/browser
```

## Before launch

- **Prices are placeholders.** The old site listed every item at a dummy $105; review each product in the admin.
- **Product photos** still load from the old WordPress media library. Re-upload them in the admin (Products → edit → Upload) before that site is retired.
- Policy pages (`/legal/*`) are starter copy, not legal advice.
- Set store details in Admin → Settings: WhatsApp number, Instagram/Facebook, Google reviews link, delivery areas, fees, tax and time slots.
