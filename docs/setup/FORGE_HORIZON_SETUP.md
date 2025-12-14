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

**Symptoms:** Horizon shows as inactive after deployment, even though `horizon:terminate` is called.

**Root Cause:** When `horizon:terminate` is called, it gracefully stops Horizon. If Horizon is set up as a Forge daemon, it should automatically restart. However, sometimes the daemon doesn't restart automatically, especially if:
- The daemon was stopped/disabled
- There's a timing issue where the daemon hasn't had time to restart
- The daemon configuration isn't set to auto-restart

**Solution:** The deployment script now includes explicit restart logic that:
1. Calls `horizon:terminate` to gracefully stop Horizon
2. Waits a few seconds for the graceful termination
3. Checks if Horizon is running
4. If not running, attempts to restart it via `supervisorctl`
5. Provides helpful error messages if restart fails

**Verification Steps:**
1. Ensure your deployment script includes: `$FORGE_PHP artisan horizon:terminate`
2. Check deployment logs in Forge to see if the command executed successfully
3. Verify Horizon daemon is enabled in Forge dashboard (Application tab → Laravel Horizon toggle)
4. Check the Daemons tab in Forge to see if the Horizon daemon is running
5. Check Horizon logs in Forge (Logs tab → Select Horizon daemon)

**Note:** If you're using `$RESTART_QUEUES()` in your deployment script, you should remove it when using Horizon. The `$RESTART_QUEUES()` macro is for regular Laravel queue workers, not Horizon. According to Forge documentation, when using Zero Downtime deployments, `$RESTART_QUEUES()` should handle Horizon automatically, but the queues documentation recommends using `horizon:terminate` instead.

### Viewing Horizon Dashboard

- Horizon dashboard is available at: `https://your-domain.com/horizon`
- Access is controlled by your Horizon authorization (see `app/Providers/HorizonServiceProvider.php`)
- Both Horizon and Pulse dashboards are accessible from Filament dashboard under "System" menu

## Related Documentation

- `HORIZON_SETUP.md` - Manual systemd setup (for non-Forge servers)
- `QUICK_START.md` - Quick reference guide
- [Laravel Forge Documentation](https://forge.laravel.com/docs)
