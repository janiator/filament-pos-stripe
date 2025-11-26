# Quick Start Guide - Horizon & Scheduler

## Local Development

### Start Horizon Manually
```bash
php artisan horizon
```

### Start Pulse Worker Manually
```bash
php artisan pulse:work
```

### Test Scheduler Manually
```bash
php artisan schedule:run
```

## Production Setup

### 1. Setup Horizon Service
```bash
sudo ./setup-horizon.sh
```

This will:
- Install Horizon as a systemd service
- Install Pulse worker as a systemd service
- Start both services automatically

### 2. Setup Scheduler Cron Job
```bash
./setup-cron.sh
```

This will add the Laravel scheduler to your crontab.

### 3. Verify Everything is Running

Check Horizon:
```bash
sudo systemctl status horizon
```

Check Pulse:
```bash
sudo systemctl status pulse-worker
```

Check Cron:
```bash
crontab -l
```

## Access Dashboards

- **Horizon**: `https://your-domain.com/horizon`
- **Pulse**: `https://your-domain.com/pulse`

Both are accessible from the Filament dashboard navigation menu under "System".

## Important Notes

1. **Queue Connection**: Make sure `QUEUE_CONNECTION=database` in your `.env` file
2. **Horizon Config**: Updated to use `database` connection and handle `stripe-sync` queue
3. **Job Retries**: Jobs are configured with 3 retries and 60-second backoff
4. **Logging**: All jobs log detailed information to `storage/logs/laravel.log`

## Troubleshooting

If Horizon isn't processing jobs:
1. Check if Horizon is running: `sudo systemctl status horizon`
2. Check logs: `sudo journalctl -u horizon -f`
3. Verify queue connection in `.env`
4. Check Horizon dashboard for errors

For more details, see `HORIZON_SETUP.md`

