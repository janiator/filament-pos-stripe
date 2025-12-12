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
# Note: $RESTART_QUEUES() is not needed when using Horizon - Horizon manages queue workers
$FORGE_PHP artisan horizon:terminate

# Restart Pulse worker to pick up new code
# Option 1: If Pulse is set up as a Forge daemon, uncomment and replace {id} with your daemon ID:
# sudo supervisorctl restart daemon-{id}:*
# 
# Option 2: If Pulse is a systemd service, you can configure passwordless sudo for this command
# or set up Pulse as a Forge daemon instead (recommended for easier management)
