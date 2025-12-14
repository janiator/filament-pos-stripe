#!/bin/bash

$CREATE_RELEASE()

cd $FORGE_RELEASE_DIRECTORY

$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader

npm ci && npm run build

$FORGE_PHP artisan optimize

$FORGE_PHP artisan storage:link

$FORGE_PHP artisan migrate --force

$ACTIVATE_RELEASE()

# Reload Horizon gracefully to pick up new code
# This allows Horizon to finish processing current jobs before reloading
# When Horizon is set up as a Forge daemon, this will gracefully reload it
# Note: $RESTART_QUEUES() is not needed when using Horizon - Horizon manages queue workers
$FORGE_PHP artisan horizon:terminate || true

# Wait a moment for Horizon to gracefully terminate and restart
sleep 3

# Verify Horizon is running after termination
# If Horizon is set up as a Forge daemon, it should auto-restart after terminate
# However, if it's not running, we'll attempt to restart it via supervisorctl
if ! pgrep -f "artisan horizon" > /dev/null; then
    echo "Horizon is not running after terminate. Attempting to restart..."
    
    # Try to find and restart Horizon daemon via supervisorctl
    # Forge daemons are typically named "daemon-{id}" and can be found in supervisorctl
    if command -v supervisorctl > /dev/null 2>&1; then
        HORIZON_DAEMON=$(sudo supervisorctl status 2>/dev/null | grep -i "horizon\|artisan horizon" | awk '{print $1}' | head -1)
        
        if [ -n "$HORIZON_DAEMON" ]; then
            echo "Found Horizon daemon: $HORIZON_DAEMON. Restarting..."
            sudo supervisorctl restart "$HORIZON_DAEMON" 2>/dev/null || true
        else
            # Try to find any daemon that might be Horizon (sometimes named differently)
            ALL_DAEMONS=$(sudo supervisorctl status 2>/dev/null | grep "^daemon-" | awk '{print $1}')
            for daemon in $ALL_DAEMONS; do
                # Check if this daemon runs horizon
                if sudo supervisorctl status "$daemon" 2>/dev/null | grep -q "artisan horizon"; then
                    echo "Found Horizon daemon: $daemon. Restarting..."
                    sudo supervisorctl restart "$daemon" 2>/dev/null || true
                    break
                fi
            done
        fi
    fi
    
    # Final check - if still not running, log a warning
    sleep 2
    if ! pgrep -f "artisan horizon" > /dev/null; then
        echo "Warning: Horizon is still not running after restart attempt."
        echo "Please check:"
        echo "  1. Horizon is enabled in Forge dashboard (Application tab → Laravel Horizon toggle)"
        echo "  2. Check Horizon daemon status in Forge (Daemons tab)"
        echo "  3. Check Horizon logs in Forge (Logs tab)"
    else
        echo "Horizon successfully restarted."
    fi
fi

# Restart Pulse worker to pick up new code
# Option 1: If Pulse is set up as a Forge daemon, uncomment and replace {id} with your daemon ID:
# sudo supervisorctl restart daemon-{id}:*
# 
# Option 2: If Pulse is a systemd service, you can configure passwordless sudo for this command
# or set up Pulse as a Forge daemon instead (recommended for easier management)
