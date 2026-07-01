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

### 2. Create the storage symlink
Since you can't run `php artisan storage:link`, create it manually:
- In File Manager, go to `public/`
- Create a symbolic link named `storage` pointing to `../storage/app/public`
- OR: create a `public/storage/` folder and upload files there directly

### 3. Run migrations
Use cPanel's **Cron Jobs** to run a one-off migration:
```
cd /home/user/ck-crm && /usr/local/bin/php artisan migrate --force
```
(Set it to run once, then delete the cron)

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
- **Storage links**: If uploads don't show, verify the `public/storage` symlink
- **Artisan commands**: Use cPanel's Terminal (if available) or cron jobs
