# Horizon and Scheduler Setup Guide

This guide will help you set up Laravel Horizon and the Laravel Scheduler on your server.

## Prerequisites

- PHP 8.2+ installed
- Composer dependencies installed
- Database configured
- Queue connection set to `database` in `.env`

## 1. Configure Horizon

Horizon is already configured to use the `database` queue connection and handle the `stripe-sync` queue.

## 2. Setup Horizon Service (Linux/macOS with systemd)

### Option A: Use the setup script (Recommended)

```bash
sudo ./setup-horizon.sh
```

### Option B: Manual setup

1. Copy the service file:
```bash
sudo cp horizon.service /etc/systemd/system/horizon.service
```

2. Edit the service file to match your paths:
```bash
sudo nano /etc/systemd/system/horizon.service
```

3. Update these values:
   - `WorkingDirectory`: Your application path
   - `ExecStart`: Path to PHP and artisan
   - `User`: Your web server user (www-data, nginx, etc.)

4. Reload and start:
```bash
sudo systemctl daemon-reload
sudo systemctl enable horizon.service
sudo systemctl start horizon.service
```

## 3. Setup Pulse Worker (Optional but Recommended)

Pulse worker is included in the setup script. To manually set it up:

```bash
sudo systemctl enable pulse-worker.service
sudo systemctl start pulse-worker.service
```

## 4. Setup Laravel Scheduler Cron Job

### Option A: Use the setup script (Recommended)

```bash
./setup-cron.sh
```

### Option B: Manual setup

Add this line to your crontab:

```bash
crontab -e
```

Add:
```
* * * * * cd /path/to/your/app && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

Replace `/path/to/your/app` with your actual application path and `/usr/bin/php` with your PHP path.

## 5. Verify Everything is Running

### Check Horizon Status
```bash
sudo systemctl status horizon
```

### Check Pulse Worker Status
```bash
sudo systemctl status pulse-worker
```

### Check Cron Job
```bash
crontab -l
```

### View Horizon Dashboard
Visit: `https://your-domain.com/horizon`

### View Pulse Dashboard
Visit: `https://your-domain.com/pulse`

## 6. Useful Commands

### Horizon Commands
```bash
# Start Horizon
sudo systemctl start horizon

# Stop Horizon
sudo systemctl stop horizon

# Restart Horizon
sudo systemctl restart horizon

# View logs
sudo journalctl -u horizon -f

# Pause Horizon
php artisan horizon:pause

# Continue Horizon
php artisan horizon:continue

# Terminate Horizon
php artisan horizon:terminate
```

### Pulse Commands
```bash
# Start Pulse Worker
sudo systemctl start pulse-worker

# Stop Pulse Worker
sudo systemctl stop pulse-worker

# Restart Pulse Worker
sudo systemctl restart pulse-worker

# View logs
sudo journalctl -u pulse-worker -f
```

## 7. Troubleshooting

### Horizon not starting
1. Check logs: `sudo journalctl -u horizon -f`
2. Verify PHP path: `which php`
3. Verify application path is correct
4. Check permissions on storage/logs directory

### Jobs not processing
1. Verify Horizon is running: `sudo systemctl status horizon`
2. Check queue connection in `.env`: `QUEUE_CONNECTION=database`
3. Check Horizon dashboard for errors
4. Verify database connection

### Scheduler not running
1. Verify cron job exists: `crontab -l`
2. Check Laravel logs: `tail -f storage/logs/laravel.log`
3. Test scheduler manually: `php artisan schedule:run`

## 8. Production Considerations

- Set `APP_ENV=production` in `.env`
- Configure proper Redis connection if using Redis queues
- Set up log rotation
- Monitor Horizon dashboard regularly
- Set up alerts for failed jobs
- Configure proper memory limits in `config/horizon.php`

