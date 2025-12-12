# Enabling Horizon in Laravel Forge

This guide explains how to enable Laravel Horizon using Forge's built-in daemon management (recommended for Forge deployments).

## Quick Setup (Recommended)

### Step 1: Enable Horizon in Forge Dashboard

1. Log in to your [Laravel Forge](https://forge.laravel.com) account
2. Navigate to your site
3. Go to the **"Application"** tab
4. Find the **"Laravel Horizon"** toggle
5. Switch it to **"Enabled"**

Forge will automatically:
- Create the Horizon daemon worker
- Configure it to run `php artisan horizon`
- Set up automatic restarts if it crashes
- Add `horizon:terminate` to your deployment script (if not already present)

### Step 2: Verify Horizon is Running

After enabling, you can verify Horizon is running by:

1. **Check the Daemons tab**: Go to your site → **Daemons** tab. You should see a Horizon daemon listed.
2. **Check Horizon Dashboard**: Visit `https://your-domain.com/horizon` (requires authentication)
3. **Check Logs**: In Forge, go to your site → **Logs** → Select the Horizon daemon to view its logs

## Deployment Script

Your deployment script (`.forge/deploy.sh`) should include:

```bash
# Reload Horizon gracefully to pick up new code
$FORGE_PHP artisan horizon:terminate
```

This command gracefully reloads Horizon after deployment, allowing current jobs to finish before reloading with new code.

## Setting Up Pulse Worker (Optional)

If you also want to run Laravel Pulse as a Forge daemon:

1. Go to your site → **Daemons** tab
2. Click **"Create Daemon"**
3. Configure:
   - **Command**: `php artisan pulse:work`
   - **User**: `forge` (default)
   - **Directory**: Leave empty (uses site directory)
   - **Processes**: `1` (default)
4. Click **"Create Daemon"**

Then update your deployment script to restart Pulse:

```bash
# Restart Pulse worker (replace {id} with your daemon ID from Forge)
sudo supervisorctl restart daemon-{id}:*
```

You can find the daemon ID in the Forge dashboard under **Daemons** → your Pulse daemon.

## Benefits of Using Forge Daemons

- **Automatic Management**: Forge handles starting, stopping, and restarting
- **Easy Monitoring**: View logs and status directly in Forge dashboard
- **Deployment Integration**: Can be restarted automatically during deployments
- **No Manual Configuration**: No need to create systemd service files manually

## Alternative: Manual Systemd Setup

If you prefer to manage Horizon via systemd (not recommended for Forge), see `HORIZON_SETUP.md` for manual setup instructions.

## Troubleshooting

### Horizon Not Processing Jobs

1. **Check if Horizon is running**: Go to Forge → Your Site → Daemons → Check Horizon status
2. **Check logs**: Forge → Your Site → Logs → Select Horizon daemon
3. **Verify queue connection**: Ensure `QUEUE_CONNECTION=database` in your `.env` file
4. **Check Horizon dashboard**: Visit `/horizon` to see job status and errors

### Horizon Not Restarting After Deployment

1. Ensure your deployment script includes: `$FORGE_PHP artisan horizon:terminate`
2. Check deployment logs in Forge to see if the command executed successfully
3. Verify Horizon daemon is enabled in Forge dashboard

### Viewing Horizon Dashboard

- Horizon dashboard is available at: `https://your-domain.com/horizon`
- Access is controlled by your Horizon authorization (see `app/Providers/HorizonServiceProvider.php`)
- Both Horizon and Pulse dashboards are accessible from Filament dashboard under "System" menu

## Related Documentation

- `HORIZON_SETUP.md` - Manual systemd setup (for non-Forge servers)
- `QUICK_START.md` - Quick reference guide
- [Laravel Forge Documentation](https://forge.laravel.com/docs)
