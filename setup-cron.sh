#!/bin/bash

# Setup script for Laravel Scheduler Cron Job
# Run this script to add the Laravel scheduler to crontab

APP_PATH="/Users/jan/Herd/pos-stripe"
PHP_PATH="/usr/bin/php"

# Get the actual PHP path if different
if command -v php &> /dev/null; then
    PHP_PATH=$(which php)
fi

echo "Setting up Laravel Scheduler cron job..."
echo "App Path: $APP_PATH"
echo "PHP Path: $PHP_PATH"
echo ""

# Check if cron job already exists
CRON_CMD="* * * * * cd $APP_PATH && $PHP_PATH artisan schedule:run >> /dev/null 2>&1"

if crontab -l 2>/dev/null | grep -q "schedule:run"; then
    echo "Cron job already exists. Skipping..."
else
    # Add cron job
    (crontab -l 2>/dev/null; echo "$CRON_CMD") | crontab -
    echo "Cron job added successfully!"
fi

echo ""
echo "Current crontab:"
crontab -l
echo ""
echo "The Laravel scheduler will now run every minute."

