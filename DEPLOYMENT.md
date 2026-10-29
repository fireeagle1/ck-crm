# Deploying to cPanel (No SSH)

## Pre-deployment (on your local machine)

1. **Build frontend assets:**
   ```bash
   npm run build
   ```

2. **Install production Composer dependencies:**
   ```bash
   composer install --optimize-autoloader --no-dev
   ```

3. **Set production .env values** (copy .env.example, fill in production values)

4. **Generate app key** (if not already set):
   ```bash
   php artisan key:generate
   ```

## What to upload

Upload the **entire project** including:
- ✅ `vendor/` folder (required — no SSH means no `composer install` on server)
- ✅ `public/build/` folder (compiled CSS/JS from `npm run build`)
- ✅ `node_modules/` — **NOT needed**, skip this
- ✅ `.env` — with production values
- ✅ `storage/` folder structure

## Directory structure on cPanel

### Option A: Subdomain or addon domain pointing to `public/`
```
/home/user/ck-crm/              ← upload everything here (OUTSIDE public_html)
/home/user/ck-crm/public/      ← point your domain's document root here
```

In cPanel → Domains → select your domain → set Document Root to `/home/user/ck-crm/public`

### Option B: Inside public_html (less ideal but works)
```
/home/user/public_html/         ← contents of public/ go here
/home/user/ck-crm/             ← everything else goes here
```

Then edit `public_html/index.php` to fix paths:
```php
require __DIR__.'/../ck-crm/vendor/autoload.php';
$app = require_once __DIR__.'/../ck-crm/bootstrap/app.php';
```

## Post-upload steps (via cPanel)

### 1. Set file permissions
Via File Manager:
- `storage/` → 775 (recursive)
- `bootstrap/cache/` → 775

### 2. Rebuild the storage symlink (required after every deploy that touches `public/`)
Uploaded images (product photos, ticket attachments) live in
`storage/app/public/` and are referenced as `/storage/...` URLs. That URL only
resolves if `public/storage` is a symlink to `../storage/app/public`. The
symlink is **git-ignored** (`/public/storage`), so it never ships with the code
and a deploy that overwrites `public/` will leave it missing or broken — which
makes every uploaded image return 404.

The simplest fix: go to **Admin → Settings → Maintenance** and click
**Rebuild storage link** (and **Clear all caches**). No SSH or File Manager
needed. Do this after any deploy where images stop loading.

Manual alternatives if you prefer:
- **cron (one-off):** `cd /home/user/ck-crm && /usr/local/bin/php artisan storage:link`
- **File Manager:** in `public/`, create a symbolic link named `storage`
  pointing to `../storage/app/public`.

**If your host blocks symlinks entirely:** the `public` disk in
`config/filesystems.php` sets `'serve' => true`, which makes Laravel
auto-register a `GET /storage/{path}` route (named `storage.public`) that
streams files straight from `storage/app/public`. So images can load without a
symlink. **Caveat:** that route only exists once config and routes are cached
fresh — if the route cache was built before `serve => true` was added, the
route is missing and images 404. Always run **Clear all caches** (below) after
deploying. The symlink is still faster (the web server serves the file
directly, bypassing PHP), so prefer it when the host allows it.

### 3. Run migrations
Use cPanel's **Cron Jobs** to run a one-off migration:
```
cd /home/user/ck-crm && /usr/local/bin/php artisan migrate --force
```
(Set it to run once, then delete the cron)

### 3a. Install Laravel Passport (MCP integration — no SSH)

The ChatGPT MCP integration adds `laravel/passport` as an OAuth 2.1 authorization
server. It is **additive**: a new `api` guard (`driver => passport`) was added to
`config/auth.php`; the existing `web` guard and Sanctum's token handling are
untouched, so the admin API and portal behave exactly as before.

Because cPanel has no SSH, Composer cannot run on the server. Do the install
locally and ship the result:

1. **Locally**, require the package and commit the lock file + vendor dir:
   ```bash
   composer require laravel/passport
   git add composer.json composer.lock
   git commit -m "Add laravel/passport"
   ```
   Upload the refreshed `vendor/` folder along with `composer.json` and
   `composer.lock` (same no-SSH rule as the rest of the app — the server never
   runs `composer install`). If your host provides a Composer UI/terminal, you
   may instead run `composer install --optimize-autoloader --no-dev` there after
   uploading the updated `composer.lock`.

2. **Run the Passport migrations** on the server. Passport ships its own
   migrations (the `oauth_*` tables); they run with the normal migrate command.
   Use a one-off cPanel cron job (then delete it):
   ```
   cd /home/user/ck-crm && /usr/local/bin/php artisan migrate --force
   ```

3. **Generate the Passport encryption keys** with another one-off cron job:
   ```
   cd /home/user/ck-crm && /usr/local/bin/php artisan passport:keys --force
   ```
   This writes `storage/oauth-private.key` and `storage/oauth-public.key`.
   > The `api` guard throws `Invalid key supplied` until these keys exist, so this
   > step is mandatory before any MCP/OAuth request will authenticate.

4. **Deploy the keys securely.** The `oauth-*.key` files are secrets:
   - They must be present in `storage/` on the server (the path above).
   - They must stay **out of version control** — `.gitignore` already excludes
     `/storage/*.key`. Never commit them.
   - If you prefer not to generate keys on the server, generate them locally with
     `php artisan passport:keys`, then upload the two files into the server's
     `storage/` directory via File Manager (set permissions to 600). Do **not**
     put them under `public/`.
   - Alternatively, the keys can be provided via the `PASSPORT_PRIVATE_KEY` /
     `PASSPORT_PUBLIC_KEY` environment variables in `.env` instead of files.

5. **Clear config cache** after deployment so the new `api` guard is picked up:
   ```
   cd /home/user/ck-crm && /usr/local/bin/php artisan config:clear
   ```

6. **Create the public MCP OAuth client** (the identity OpenAI's connector uses).
   It is a *public* client: no client secret is generated, so the authorization
   code flow is protected by PKCE (Requirement 1.2). Run the bundled command via a
   one-off cPanel cron job (then delete it), passing the OpenAI connector's
   redirect/callback URL:
   ```
   cd /home/user/ck-crm && /usr/local/bin/php artisan mcp:create-client --redirect-uri="https://chat.openai.com/connector_platform_oauth_redirect"
   ```
   This prints a **Client ID** and shows `Secret: (none — public client)`. Configure
   OpenAI's custom MCP connector with that Client ID; do **not** supply a secret.
   (The command is a pre-named wrapper around the stock
   `php artisan passport:client --public` — either works; the wrapper names the
   client `mcp_client`, which is what the admin token-management screen filters on.)

7. **Manage / revoke tokens.** Issued MCP tokens can be reviewed and revoked from
   the admin area at `/admin/mcp/tokens` (admin login required). The screen lists
   each token's id, scopes, created/last-used/expiry timestamps — never the token
   value — and a **Revoke** action that invalidates the token and its refresh token
   immediately (Requirement 1.5).

6. **Scopes, token lifetimes and PKCE** are configured in code by
   `App\Providers\PassportServiceProvider` (registered in `bootstrap/providers.php`)
   — no deployment action is required for these, they apply automatically:
   - **Scopes** are locked to exactly: `customers:read`, `invoices:read`,
     `projects:read`, `services:read`, `tickets:read`, `tickets:write`. A token
     can never carry any other scope.
   - **Access tokens** expire after **60 minutes**; **refresh tokens** after
     **14 days**. The connector refreshes rather than holding a long-lived token.
   - **PKCE** is enforced by creating the MCP connector client as a Passport
     **public** client (no secret):
     ```
     cd /home/user/ck-crm && /usr/local/bin/php artisan passport:client --public
     ```
     A public client has no secret to fall back on, so Passport requires a
     `code_challenge`/`code_verifier` on the authorization-code flow. The
     implicit and password grants are deliberately left disabled. (Creating the
     client and admin token management is completed in task 6.3.)

### 4. Set up the scheduler
Add this cron job in cPanel → Cron Jobs (every minute):
```
cd /home/user/ck-crm && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
```

### 5. Queue worker (optional)
If using database queue, add a cron that runs every minute:
```
cd /home/user/ck-crm && /usr/local/bin/php artisan queue:work --stop-when-empty >> /dev/null 2>&1
```

## .env production values

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.co.uk

DB_CONNECTION=mysql
DB_HOST=localhost
DB_DATABASE=your_db_name
DB_USERNAME=your_db_user
DB_PASSWORD=your_db_password

SESSION_DRIVER=database
CACHE_STORE=file
QUEUE_CONNECTION=sync

STRIPE_KEY=pk_live_xxx
STRIPE_SECRET=sk_live_xxx
```

**Key notes:**
- Use `QUEUE_CONNECTION=sync` if you don't want to deal with queue workers on cPanel
- Use `CACHE_STORE=file` instead of database for simpler caching
- Set `SESSION_DRIVER=database` (the table is already migrated)

## PHP version
Ensure cPanel is set to PHP 8.3+ (Select PHP Version in cPanel).

Required PHP extensions: `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `curl`

## Troubleshooting

- **500 error**: Check `storage/logs/laravel.log` — most likely a permissions issue
- **CSS/JS not loading**: Ensure `public/build/` was uploaded and `APP_URL` is correct
- **Uploaded images 404 / stopped showing after a deploy**: the `public/storage` symlink was almost certainly removed or broken by the deploy (it is git-ignored and does not ship). Go to **Admin → Settings → Maintenance** and click **Rebuild storage link**, then **Clear all caches**. If the host blocks symlinks, the built-in PHP fallback route serves the files instead, so they still load. Also confirm `storage/app/public/` exists and is writable (775).
- **Artisan commands**: Use cPanel's Terminal (if available) or cron jobs
