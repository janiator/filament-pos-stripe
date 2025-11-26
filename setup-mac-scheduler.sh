#!/bin/bash

# Setup Laravel Scheduler for macOS
# This script sets up both cron (simple) and launchd (recommended) options

APP_PATH="/Users/jan/Herd/pos-stripe"
PHP_PATH="/Users/jan/Library/Application Support/Herd/bin/php"

echo "Setting up Laravel Scheduler for macOS..."
echo "App Path: $APP_PATH"
echo "PHP Path: $PHP_PATH"
echo ""

# Option 1: Setup using cron (simple, already done)
echo "✓ Cron job setup (runs every minute)"
echo "  To view: crontab -l"
echo "  To remove: crontab -e"
echo ""

# Option 2: Setup using launchd (recommended for macOS)
echo "Setting up launchd service (recommended for macOS)..."
echo ""

# Copy plist to LaunchAgents directory
PLIST_NAME="com.pos-stripe.scheduler.plist"
LAUNCH_AGENTS_DIR="$HOME/Library/LaunchAgents"

mkdir -p "$LAUNCH_AGENTS_DIR"
cp "$APP_PATH/$PLIST_NAME" "$LAUNCH_AGENTS_DIR/$PLIST_NAME"

# Load the service
launchctl unload "$LAUNCH_AGENTS_DIR/$PLIST_NAME" 2>/dev/null
launchctl load "$LAUNCH_AGENTS_DIR/$PLIST_NAME"

echo "✓ Launchd service installed and started"
echo ""
echo "To check status:"
echo "  launchctl list | grep com.pos-stripe"
echo ""
echo "To stop:"
echo "  launchctl unload ~/Library/LaunchAgents/$PLIST_NAME"
echo ""
echo "To start:"
echo "  launchctl load ~/Library/LaunchAgents/$PLIST_NAME"
echo ""
echo "To view logs:"
echo "  tail -f $APP_PATH/storage/logs/scheduler.log"
echo "  tail -f $APP_PATH/storage/logs/scheduler-error.log"
echo ""
echo "Setup complete! The scheduler will run every minute."

