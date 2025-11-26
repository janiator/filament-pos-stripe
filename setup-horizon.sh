#!/bin/bash

# Setup script for Laravel Horizon and Scheduler
# Run this script with sudo to install systemd services

APP_PATH="/Users/jan/Herd/pos-stripe"
PHP_PATH="/usr/bin/php"
USER="www-data"

# Get the actual PHP path if different
if command -v php &> /dev/null; then
    PHP_PATH=$(which php)
fi

# Get current user if www-data doesn't exist
if ! id "$USER" &>/dev/null; then
    USER=$(whoami)
fi

echo "Setting up Laravel Horizon..."
echo "App Path: $APP_PATH"
echo "PHP Path: $PHP_PATH"
echo "User: $USER"

# Create Horizon systemd service
cat > /etc/systemd/system/horizon.service << EOF
[Unit]
Description=Laravel Horizon Queue Worker
After=network.target

[Service]
Type=simple
User=$USER
WorkingDirectory=$APP_PATH
ExecStart=$PHP_PATH $APP_PATH/artisan horizon
Restart=always
RestartSec=10
StandardOutput=syslog
StandardError=syslog
SyslogIdentifier=horizon

[Install]
WantedBy=multi-user.target
EOF

# Create Pulse worker systemd service
cat > /etc/systemd/system/pulse-worker.service << EOF
[Unit]
Description=Laravel Pulse Worker
After=network.target

[Service]
Type=simple
User=$USER
WorkingDirectory=$APP_PATH
ExecStart=$PHP_PATH $APP_PATH/artisan pulse:work
Restart=always
RestartSec=10
StandardOutput=syslog
StandardError=syslog
SyslogIdentifier=pulse-worker

[Install]
WantedBy=multi-user.target
EOF

# Reload systemd
systemctl daemon-reload

# Enable and start services
systemctl enable horizon.service
systemctl start horizon.service

systemctl enable pulse-worker.service
systemctl start pulse-worker.service

echo ""
echo "Horizon and Pulse services installed and started!"
echo ""
echo "To check status:"
echo "  sudo systemctl status horizon"
echo "  sudo systemctl status pulse-worker"
echo ""
echo "To view logs:"
echo "  sudo journalctl -u horizon -f"
echo "  sudo journalctl -u pulse-worker -f"
echo ""
echo "To restart:"
echo "  sudo systemctl restart horizon"
echo "  sudo systemctl restart pulse-worker"

