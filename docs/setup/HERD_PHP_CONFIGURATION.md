# Herd PHP Configuration

## Increasing PHP Max Execution Time

If you need to increase the PHP `max_execution_time` for Herd, you have several options:

### Option 1: Using Herd's PHP Configuration (Recommended)

1. Open Herd application
2. Go to **Settings** → **PHP**
3. Click on the PHP version you're using (e.g., PHP 8.3)
4. Click **Edit Config** or **Open Config**
5. Find or add the `max_execution_time` setting:
   ```ini
   max_execution_time = 300  ; 5 minutes (or higher if needed)
   ```
6. Save the file and restart Herd

### Option 2: Create a Custom PHP INI File

1. Create a file at: `~/Library/Application Support/Herd/config/php/8.3/custom.ini` (adjust version as needed)
2. Add:
   ```ini
   max_execution_time = 300
   memory_limit = 512M
   ```
3. Restart Herd

### Option 3: Set in Laravel Application (Temporary)

You can also set it programmatically in your Laravel application, but this is less ideal:

```php
// In bootstrap/app.php or a service provider
ini_set('max_execution_time', 300);
set_time_limit(300);
```

## Better Solution: Use Queued Jobs

**Note:** The sync operations have been updated to run as background jobs to avoid timeout issues. This is the recommended approach rather than increasing execution time.

The `SyncEverythingFromStripe` action is now dispatched as a queued job (`SyncEverythingFromStripeJob`) which:
- Runs in the background
- Spawns smaller individual sync jobs in batches (50 jobs per batch)
- Each individual sync job handles one sync type (customers, products, subscriptions, etc.) for one store
- The main job has a 5-minute timeout (reduced since it only dispatches other jobs)
- Individual sync jobs have a 10-minute timeout
- Retries up to 3 times on failure
- Sends notifications when complete

Make sure your queue worker is running:
```bash
php artisan queue:work
```

Or if using Horizon:
```bash
php artisan horizon
```

## Checking Current PHP Settings

To check your current PHP settings in Herd:

```bash
php -i | grep max_execution_time
```

Or create a simple PHP file:
```php
<?php
phpinfo();
```

Then visit it in your browser to see all PHP configuration.

