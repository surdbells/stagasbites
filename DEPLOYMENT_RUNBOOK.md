# Deployment Runbook

How Staga's Bites goes live, and how to ship changes afterwards.

| Part | Where it runs | How it deploys |
|---|---|---|
| Storefront and admin (Angular, `apps/web`) | **Cloudflare Pages**, `stagasbites.ca` | Automatically, on every push to `main` on GitHub |
| API (PHP Slim, `apps/api`) | **Linux server with aaPanel**, `api.stagasbites.ca` | `bin/deploy.sh` on the server |
| Database | PostgreSQL on the same aaPanel server | Doctrine migrations, run by `deploy.sh` |

```
Browser ──> stagasbites.ca (Cloudflare Pages: static Angular build + edge functions)
   │                 │
   │                 └── edge function asks the API for each page's SEO tags
   └──> api.stagasbites.ca (aaPanel: nginx > PHP-FPM 8.3 > Slim > PostgreSQL)
                     ├── Stripe (checkout + signed webhook)
                     ├── ZeptoMail (transactional email)
                     └── Google Places (reviews)
```

If your domain is not `stagasbites.ca`, change it in two places before the first deploy:
`apps/web/src/environments/environment.ts` and the values you enter in sections 2.6 and 3.3.

---

## 1. Before you start

You need:

- The GitHub repository `surdbells/stagasbites`, with `main` as the production branch.
- A Cloudflare account with the domain added as a zone (nameservers pointing at Cloudflare).
- A Linux server (Ubuntu 22.04 or 24.04 recommended, 2 GB RAM or more) with aaPanel installed and root SSH access.
- Accounts and keys for: **Stripe** (live secret key), **ZeptoMail** (Send Mail token and a verified sender domain), **Google Cloud** (API key with "Places API (New)" enabled).

Order of work: **API first** (section 2), **then the storefront** (section 3), because the storefront needs a working API URL.

---

## 2. API on aaPanel

### 2.1 Install the stack

In aaPanel, **App Store**:

1. **Nginx** (any current version).
2. **PHP 8.3**.
3. **PostgreSQL Manager**, then inside it install PostgreSQL 16.

Then **App Store > PHP 8.3 > Settings**:

- **Install extensions**: `pgsql`, `pdo_pgsql`, `intl`, `fileinfo`, `sodium`, `opcache`. (`mbstring`, `curl`, `openssl`, `gd` and `zip` are normally already present.)
- **Disabled functions**: remove `putenv`, `proc_open` and `proc_get_status` from the list. Composer needs them.
- **Configuration**: `upload_max_filesize = 6M`, `post_max_size = 8M`.

Install Composer and Git over SSH:

```bash
apt update && apt install -y git unzip
curl -sS https://getcomposer.org/installer | /www/server/php/83/bin/php -- --install-dir=/usr/local/bin --filename=composer
```

Confirm the PHP-FPM service name and user. On aaPanel they are normally `php-fpm-83` and `www`:

```bash
ls /etc/init.d/ | grep php-fpm
ps -o user= -C php-fpm | grep -v '^root$' | sort -u
```

### 2.2 Create the database

**PostgreSQL Manager > Add database**:

- Database: `stagasbites`
- User: `stagasbites`
- Password: generate a strong one and keep it for `.env`
- Access: local server only

### 2.3 Get the code

```bash
cd /www/wwwroot
git clone https://github.com/surdbells/stagasbites.git
```

For a private repository, add a read-only **deploy key** (GitHub > repo > Settings > Deploy keys) and clone over SSH instead.

### 2.4 Create the website in aaPanel

**Website > Add site**:

- Domain: `api.stagasbites.ca`
- Root directory: `/www/wwwroot/stagasbites`
- PHP version: **PHP-83**
- Database: none (created above)

Open the new site's settings:

1. **Site directory > Running directory**: `/apps/api/public`. Save.
2. **Site directory**: untick **Anti-XSS attack (open_basedir)**, or the API cannot read `apps/api/src` and `vendor`, which sit above the running directory. The API returns a blank 500 if this is left on.
3. **URL rewrite**: paste the location blocks from `docker/nginx/stagasbites.conf`. Save.
4. **SSL > Let's Encrypt**: issue a certificate for `api.stagasbites.ca`, then enable **Force HTTPS**.

In Cloudflare DNS, add an `A` record `api` pointing at the server IP. Set it to **DNS only** (grey cloud) while issuing the certificate. You can switch it to proxied afterwards, with SSL/TLS mode **Full (strict)**.

### 2.5 Configure the environment

```bash
cd /www/wwwroot/stagasbites/apps/api
cp .env.example .env
/www/server/php/83/bin/php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"   # use this as JWT_SECRET
nano .env
chmod 640 .env && chown www:www .env
```

Production values:

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.stagasbites.ca
SITE_URL=https://stagasbites.ca

DB_HOST=127.0.0.1
DB_PORT=5432
DB_NAME=stagasbites
DB_USER=stagasbites
DB_PASSWORD=<from 2.2>

JWT_SECRET=<64 hex characters generated above>

ZEPTOMAIL_API_KEY=<Send Mail token>
ZEPTOMAIL_FROM_EMAIL=orders@stagasbites.ca
ADMIN_NOTIFY_EMAIL=contact@stagasbites.ca

STRIPE_SECRET_KEY=sk_live_...
STRIPE_WEBHOOK_SECRET=whsec_...        # from 2.8

GOOGLE_PLACES_API_KEY=<Places API (New) key>
GOOGLE_PLACE_ID=ChIJ218RGCxlK4gRtgqbVBx8Nks

# The storefront origins allowed to call the API. The wildcard covers Cloudflare preview deployments.
CORS_ALLOWED_ORIGINS=https://stagasbites.ca,https://www.stagasbites.ca,https://*.stagasbites.pages.dev

LOG_LEVEL=info
```

Note that `DB_PORT` is `5432` in production. The `5439` in `.env.example` only exists to avoid clashes on the development machine.

### 2.6 First install

```bash
cd /www/wwwroot/stagasbites/apps/api
composer install --no-dev --optimize-autoloader
/www/server/php/83/bin/php bin/console.php migrations:migrate --no-interaction
/www/server/php/83/bin/php bin/console.php app:seed
/www/server/php/83/bin/php bin/console.php app:create-admin you@stagasbites.ca    # prompts for a password

mkdir -p var/cache var/log var/doctrine public/uploads
chown -R www:www var public/uploads
/etc/init.d/php-fpm-83 reload
```

`var/` and `public/uploads/` **must** be owned by the PHP-FPM user (`www`). If you run a console command as root later and it creates files in `var/`, PHP-FPM can no longer write there and the API starts returning 500s. `deploy.sh` fixes ownership on every run for this reason.

### 2.7 Verify the API

```bash
curl -s https://api.stagasbites.ca/api/health
# {"success":true,"data":{"status":"ok","database":"ok"}}

curl -s "https://api.stagasbites.ca/api/v1/products?per_page=1" | head -c 200
curl -s "https://api.stagasbites.ca/api/v1/seo/meta?path=/menu" | head -c 200
```

### 2.8 Stripe webhook

Stripe Dashboard > Developers > Webhooks > **Add endpoint**:

- URL: `https://api.stagasbites.ca/api/v1/webhooks/stripe`
- Events: `checkout.session.completed`, `checkout.session.async_payment_succeeded`, `checkout.session.async_payment_failed`, `checkout.session.expired`

Copy the **signing secret** into `STRIPE_WEBHOOK_SECRET`, then reload PHP-FPM. Orders are only marked paid by this webhook, so checkout is not live until this step is done. If the API sits behind Cloudflare's proxy, make sure no WAF rule or bot challenge blocks `/api/v1/webhooks/*`.

### 2.9 ZeptoMail

In ZeptoMail, verify the sending domain and add the SPF and DKIM records it gives you in Cloudflare DNS (set those records to DNS only). `ZEPTOMAIL_FROM_EMAIL` must belong to the verified domain. Place a test order to confirm the confirmation and admin emails arrive.

---

## 3. Storefront on Cloudflare Pages

### 3.1 Connect GitHub

Cloudflare dashboard > **Workers & Pages > Create > Pages > Connect to Git**. Authorise the GitHub app for `surdbells/stagasbites` and select it.

### 3.2 Build settings

| Setting | Value |
|---|---|
| Production branch | `main` |
| Framework preset | None |
| Root directory | `apps/web` |
| Build command | `npm run build` |
| Build output directory | `dist/web/browser` |

The root directory matters: Pages looks for the `functions/` folder (the SEO edge functions) inside it.

### 3.3 Environment variables

Pages project > **Settings > Variables and secrets**, for **Production** and **Preview**:

| Variable | Value | Purpose |
|---|---|---|
| `NODE_VERSION` | `22` | Angular 22 needs a current Node |
| `API_ORIGIN` | `https://api.stagasbites.ca` | Lets the edge functions fetch SEO tags, the sitemap and robots.txt |

Save, then **Deployments > Retry deployment** so the variables apply.

### 3.4 Custom domain

Pages project > **Custom domains > Set up a domain**: add `stagasbites.ca`, then `www.stagasbites.ca`. Cloudflare creates the DNS records. Add a redirect rule from `www` to the apex (Rules > Redirect rules) so there is one canonical host.

### 3.5 Verify the storefront

```bash
# The page title comes from the edge function, without JavaScript:
curl -s https://stagasbites.ca/product/samosas | grep -o "<title>[^<]*</title>"

# Unknown pages return a real 404:
curl -s -o /dev/null -w "%{http_code}\n" https://stagasbites.ca/not-a-page

curl -s https://stagasbites.ca/sitemap.xml | head -5
curl -s https://stagasbites.ca/robots.txt
```

Then in a browser: load the home page, run a search, add a dish to the cart, and go through checkout with a real card for a small amount (refund it from Stripe afterwards). Sign in at `/admin` and confirm the order appears.

Finally, submit `https://stagasbites.ca/sitemap.xml` in Google Search Console.

### How the SEO works on Pages

The Angular app has no server-side rendering. `apps/web/functions/_middleware.ts` runs at Cloudflare's edge on every page request: it fetches that URL's metadata from `GET /api/v1/seo/meta?path=...` (cached at the edge for five minutes), writes the title, description, canonical, Open Graph tags and JSON-LD into `index.html`, and returns a true 404 status for unknown pages. If the API is unreachable it serves the page untouched, so an API outage never takes the storefront down.

---

## 4. Shipping changes

**Storefront only** (anything under `apps/web`): push to `main`. Pages builds and publishes in about two minutes. Pull requests get their own preview URL.

**API** (anything under `apps/api`): push to `main`, then on the server:

```bash
sudo bash /www/wwwroot/stagasbites/apps/api/bin/deploy.sh
```

It pulls `main`, installs dependencies, runs migrations, clears the compiled caches, fixes ownership, reloads PHP-FPM and checks `/api/health`. For a different PHP version or FPM user: `sudo PHP_VERSION=84 FPM_USER=www bash .../deploy.sh`.

**Both changed**: deploy the API first when the storefront depends on a new endpoint. Pages will usually finish first, so run `deploy.sh` promptly after pushing.

**Database changes**: create a migration locally (`php bin/console.php migrations:generate`), write the SQL, commit it. `deploy.sh` applies it. Never edit a migration that has already run in production; add a new one.

---

## 5. Rollback

**Storefront**: Pages project > Deployments > pick the last good deployment > **Rollback to this deployment**. Instant.

**API**:

```bash
cd /www/wwwroot/stagasbites
git log --oneline -5
git checkout <last-good-commit>
cd apps/api && composer install --no-dev --optimize-autoloader
rm -rf var/cache/* var/doctrine/* && chown -R www:www var
/etc/init.d/php-fpm-83 reload
```

Return to normal afterwards with `git checkout main`. If the bad release included a migration, check whether the old code still works with the new schema before rolling the migration back:

```bash
/www/server/php/83/bin/php bin/console.php migrations:migrate prev --no-interaction
```

---

## 6. Backups

- **Database**: aaPanel > **Cron > Add task > Backup database**, daily, keep 14 copies, ideally to off-server storage (aaPanel supports S3-compatible targets). Manual dump:
  ```bash
  sudo -u postgres pg_dump -Fc stagasbites > /www/backup/stagasbites-$(date +%F).dump
  ```
- **Uploaded photos**: back up `/www/wwwroot/stagasbites/apps/api/public/uploads` on the same schedule (Cron > Backup directory).
- **Secrets**: keep a copy of `.env` in a password manager. It is deliberately not in Git.

Restore a dump with `pg_restore -d stagasbites --clean <file>`.

---

## 7. Troubleshooting

| Symptom | Likely cause and fix |
|---|---|
| API returns a blank 500 on every request | `open_basedir` is still on (2.4 step 2), or `var/` is not owned by `www`. Check `apps/api/var/log/app.log` and the site's nginx error log. |
| 500s start right after running a console command | The command ran as root and created root-owned files in `var/`. Run `chown -R www:www var` and reload FPM. |
| Storefront loads but shows no products; console shows a CORS error | The storefront's origin is missing from `CORS_ALLOWED_ORIGINS`, or the variable has a trailing slash. Fix `.env` and reload FPM. |
| Customer paid but the order still says "awaiting payment" | The webhook is not arriving. Stripe Dashboard > Webhooks shows each attempt and the response. Check the signing secret and any Cloudflare WAF rule on `/api/v1/webhooks/`. Resend the event from Stripe once fixed; handling is idempotent. |
| Link previews (WhatsApp, Facebook) show the generic title | `API_ORIGIN` is missing in Pages, or the API's `/api/v1/seo/meta` is failing. Test both `curl` commands in 2.7 and 3.5. |
| Pages build fails on the Node version | Set `NODE_VERSION=22` (3.3) and retry. |
| `composer install` fails with "proc_open is disabled" | Remove `proc_open` and `putenv` from PHP's disabled functions (2.1). |
| Emails do not arrive | The sender domain is not verified in ZeptoMail, or SPF/DKIM records are proxied in Cloudflare. Failures are logged in `var/log/app.log`; they never block an order. |
| Google reviews section shows only the rating | `GOOGLE_PLACES_API_KEY` is empty, the key is not enabled for "Places API (New)", or billing is not enabled on the Google Cloud project. |
| "Class not found" or stale behaviour right after a deploy | The compiled container in `var/cache` is out of date. Run `rm -rf var/cache/* var/doctrine/*`, fix ownership and reload FPM (`deploy.sh` does this for you). |

Logs: API application log at `apps/api/var/log/app.log`; nginx logs at `/www/wwwlogs/api.stagasbites.ca.log` and `.error.log`; Pages build and function logs in the Cloudflare dashboard.

---

## 8. Go-live checklist

- [ ] Real prices and photos entered for every visible dish; dishes you do not sell are hidden
- [ ] Admin > Settings: delivery areas, fee, tax, time slots, WhatsApp, Instagram, Facebook
- [ ] Stripe in **live** mode, webhook added, a real test order paid and refunded
- [ ] Confirmation email and admin alert email received
- [ ] Google reviews showing on the home page
- [ ] `https://stagasbites.ca/sitemap.xml` submitted to Google Search Console
- [ ] `www` redirects to the apex domain, HTTPS forced on both hosts
- [ ] Daily database and uploads backups scheduled, one restore tested
- [ ] Policy pages reviewed
- [ ] Old WordPress site retired only after product photos have been re-uploaded
