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

5. **Clear the config AND route caches** after deployment so the new `api`
   guard and the auto-registered `/oauth/*` + `/.well-known/*` routes are picked
   up:
   ```
   cd /home/user/ck-crm && /usr/local/bin/php artisan config:clear
   cd /home/user/ck-crm && /usr/local/bin/php artisan route:clear
   ```
   > **Critical for the 404-at-`/oauth/authorize` symptom.** Passport registers
   > its `/oauth/*` routes automatically at boot (Laravel 11+/Passport 12+; there
   > is no `Passport::routes()` call). If the server has a **stale route cache**
   > built *before* Passport was deployed, that cached table has no `/oauth/*`
   > entries and every authorize/token request returns the app's generic **404**
   > — even though the code is correct. `route:clear` drops the stale table so the
   > routes resolve again. A *missing key* or *un-migrated `oauth_*` table* shows
   > up as a **500**, not a 404, so a 404 specifically points at the route cache.
   >
   > **No SSH?** The same clears are exposed in the admin UI at
   > **Settings → Maintenance**: click **"Clear all caches"** (runs
   > `config:clear`, `route:clear`, `view:clear`, `cache:clear`) or the
   > individual **"Clear routes"** button. Do this after every deploy.

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

8. **Scopes, token lifetimes and PKCE** are configured in code by
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

### 3b. Manual verification — MCP is request/response only (no persistent process)

The MCP integration is served entirely by the existing Laravel app as an
ordinary HTTPS route group (`/api/mcp/*`) behind cPanel's normal PHP-FPM/web
server. There is **no stdio MCP server, no daemon, and no long-lived
`php artisan` process** — every MCP interaction is a standard request/response
over HTTPS, handled by the same PHP process model as the rest of the app
(Requirement 14.3). The only background work is the optional queue worker cron
in step 5, which itself exits when the queue is empty.

Run these checks from your machine after deploying (replace the host,
client id and token values). They confirm the integration works **without**
starting any persistent process on the server:

1. **OAuth discovery metadata resolves (fixes ChatGPT automatic discovery).**
   ChatGPT's connector discovers the authorization server *automatically* by
   reading these well-known documents before it ever calls `/oauth/authorize`.
   They must return **200 with JSON**, not 404:
   ```bash
   curl -s "https://app.ckenterprises.co.uk/.well-known/oauth-authorization-server"
   curl -s "https://app.ckenterprises.co.uk/.well-known/oauth-protected-resource"
   ```
   The first must list `authorization_endpoint`, `token_endpoint`,
   `grant_types_supported` (`authorization_code`, `refresh_token`),
   `code_challenge_methods_supported` (`S256`) and
   `token_endpoint_auth_methods_supported` (`none`, i.e. public client). If
   either 404s, the route cache is stale — run the `route:clear` from step 5
   (or "Clear all caches" in the admin Maintenance screen). With these live, the
   endpoints no longer have to be entered manually in the connector config.

2. **OAuth endpoints respond over HTTPS.** Hit the authorization server's
   authorize and token endpoints directly:
   ```bash
   # Authorization endpoint (should return a login/redirect response, not 404/500)
   curl -sI "https://app.ckenterprises.co.uk/oauth/authorize?response_type=code&client_id=01a18919-69df-734f-940a-7bc98b7790a4&redirect_uri=https%3A%2F%2Fchatgpt.com%2Fconnector%2Foauth%2Fz59Zsl9Jikvy&scope=tickets:read&code_challenge=CHALLENGE&code_challenge_method=S256"
   ```
   A **404 means a stale route cache** (Passport routes not in the cached table —
   clear it per step 5). A **500 means the routes resolve but Passport is
   misconfigured** — typically the `oauth-*.key` files are missing (step 3) or the
   `oauth_*` migrations have not run (step 2). A login/redirect `200`/`302` is the
   healthy result. Complete the PKCE authorization-code flow (the connector does
   this for you) and exchange the code at `POST /oauth/token` for an access token.
   No server process had to be started for this to work.

3. **HTTP is rejected, HTTPS is required.** A plain-HTTP request to the MCP
   endpoint must not be served:
   ```bash
   curl -sI "http://your-domain.co.uk/api/mcp"   # expect a redirect to https or a 4xx, never a 200
   ```

4. **Run the MCP JSON-RPC lifecycle over HTTPS.** Each call below is a single
   request that returns a single response — nothing stays running afterwards.
   ```bash
   TOKEN=... # access token from step 2

   # initialize handshake — returns server capabilities
   curl -s -X POST "https://your-domain.co.uk/api/mcp" \
     -H "Authorization: Bearer $TOKEN" \
     -H "Content-Type: application/json" \
     -d '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{}}'

   # tools/list — returns only the tools the token's scopes allow
   curl -s -X POST "https://your-domain.co.uk/api/mcp" \
     -H "Authorization: Bearer $TOKEN" \
     -H "Content-Type: application/json" \
     -d '{"jsonrpc":"2.0","id":2,"method":"tools/list","params":{}}'

   # tools/call — e.g. a read tool the token is scoped for
   curl -s -X POST "https://your-domain.co.uk/api/mcp" \
     -H "Authorization: Bearer $TOKEN" \
     -H "Content-Type: application/json" \
     -d '{"jsonrpc":"2.0","id":3,"method":"tools/call","params":{"name":"search_tickets","arguments":{"per_page":5}}}'
   ```
   Each `curl` returns and the connection closes. Confirm that **no
   `php artisan` command needs to be left running** for any of these to
   succeed — they are handled by the web server exactly like any other route.

5. **The SSE/streaming GET endpoint completes (does not hang).** OpenAI's
   connector may open the MCP endpoint with `GET` and `Accept: text/event-stream`.
   The server replies with the streamable-HTTP `endpoint` event and then
   finishes — it must **not** hold the connection open as a long-lived stream:
   ```bash
   curl -sN -H "Authorization: Bearer $TOKEN" -H "Accept: text/event-stream" \
     "https://your-domain.co.uk/api/mcp"
   ```
   You should see an `event: endpoint` (with the message endpoint) emitted and
   the command should **return to your shell promptly** rather than hanging.
   If it hangs, the deployment is behaving like a persistent stream, which the
   cPanel model does not support — recheck that buffering/output flushing on the
   host lets the response complete.

6. **Confirm the process list is clean.** If your host offers a Terminal, run
   `ps -u $USER | grep artisan` — you should see nothing between cron runs. The
   only artisan processes that ever appear are the short-lived scheduler
   (`schedule:run`) and the `queue:work --stop-when-empty` worker, both of which
   exit on their own. No MCP-specific long-running process exists.

7. **Confirm queued side effects drain.** Create or reply to a ticket via
   `tools/call` (step 4), then confirm the notification/email is sent: with
   `QUEUE_CONNECTION=sync` it is sent inline during the request; with
   `QUEUE_CONNECTION=database` it appears in the `jobs` table and is sent on the
   next `queue:work` cron run (step 5). Either way the `tools/call` response
   returns the new `ticket_id` immediately without waiting for delivery
   (Requirement 14.4).

### 4. Set up the scheduler
Add this cron job in cPanel → Cron Jobs (every minute):
```
cd /home/user/ck-crm && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
```

### 5. Queue worker (optional) — also drains MCP ticket side effects

The MCP ticket write tools (`create_ticket`, `update_ticket`, `reply_ticket`)
reuse the same `TicketService` as the admin API, so they trigger the same
side effects: `TicketActivity` audit rows, customer email notifications, and
admin notifications (`NewTicketNotification`, `CriticalTicketNotification`).
Those notifications/mailables are dispatched to the queue, never sent inline
by the MCP request. This keeps the MCP write request non-blocking: the tool
persists the ticket/reply and returns the `ticket_id` + summary straight away,
and the actual sending happens when the queue drains (Requirement 14.4).

How the queue drains depends on `QUEUE_CONNECTION` in `.env`:

- **`QUEUE_CONNECTION=database` (recommended for real email/notification
  delivery):** jobs are written to the `jobs` table and sent when a worker
  runs. On cPanel (no SSH, no long-lived process) drain the queue from cron.
  Add a cron that runs every minute:
  ```
  cd /home/user/ck-crm && /usr/local/bin/php artisan queue:work --stop-when-empty >> /dev/null 2>&1
  ```
  `--stop-when-empty` makes the worker process whatever is queued and then
  exit, so no persistent daemon is required — the cron simply restarts it each
  minute. This is the same worker that drains every other queued job in the
  app (ticket emails, etc.); the MCP side effects just join the same queue.
  The `jobs` and `failed_jobs` tables ship with the app's migrations, so no
  extra setup is needed beyond running migrations (step 3).

- **`QUEUE_CONNECTION=sync` (default in the sample `.env`):** there is no
  queue — dispatched notifications/mailables run inline within the request.
  This matches the existing admin API exactly: MCP ticket writes behave the
  same way admin ticket writes already do, so no queue worker cron is needed.
  Side effects complete before the tool response is returned. Use this if you
  don't want to manage a queue worker on cPanel; use `database` if inline
  sending makes ticket writes feel slow or risks request timeouts.

Either setting honours Requirement 14.4: the MCP integration uses whatever
queue configuration is already in place and never introduces its own worker
process.

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
- Use `QUEUE_CONNECTION=sync` if you don't want to deal with queue workers on cPanel. Ticket notifications/emails (admin API and MCP alike) then send inline during the request.
- Use `QUEUE_CONNECTION=database` to offload those notifications/emails to the `jobs` table, drained by the `queue:work --stop-when-empty` cron (see step 5). This keeps ticket writes — including MCP ticket writes — non-blocking (Requirement 14.4).
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
