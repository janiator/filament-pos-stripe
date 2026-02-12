#!/bin/bash

# Development environment startup script
# Starts expose share, npm dev server, and Laravel Horizon

set -e

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# PID file to track running processes
PID_FILE=".dev-pids"

# Log rotation settings
MAX_LOG_SIZE_MB=1
MAX_ROTATED_LOGS=5

# Function to rotate log file if it exceeds size limit
rotate_log() {
    local log_file=$1
    if [ -f "$log_file" ]; then
        # Get file size in bytes (works on both macOS and Linux)
        local size_bytes
        if [[ "$OSTYPE" == "darwin"* ]]; then
            size_bytes=$(stat -f%z "$log_file" 2>/dev/null || echo 0)
        else
            size_bytes=$(stat -c%s "$log_file" 2>/dev/null || echo 0)
        fi

        local size_mb=$((size_bytes / 1024 / 1024))

        if [ "$size_mb" -ge "$MAX_LOG_SIZE_MB" ]; then
            local timestamp=$(date +%Y%m%d_%H%M%S)
            mv "$log_file" "${log_file}.${timestamp}"
            echo -e "${YELLOW}Rotated ${log_file} (was ${size_mb}MB)${NC}"

            # Keep only the last N rotated logs
            ls -t "${log_file}".* 2>/dev/null | tail -n +$((MAX_ROTATED_LOGS + 1)) | xargs rm -f 2>/dev/null || true
        fi
    fi
}

# Function to cleanup on exit
cleanup() {
    echo -e "\n${YELLOW}Stopping all services...${NC}"
    if [ -f "$PID_FILE" ]; then
        while read -r pid; do
            if kill -0 "$pid" 2>/dev/null; then
                kill "$pid" 2>/dev/null || true
            fi
        done < "$PID_FILE"
        rm -f "$PID_FILE"
    fi
    echo -e "${GREEN}All services stopped.${NC}"
    exit 0
}

# Trap Ctrl+C and cleanup
trap cleanup SIGINT SIGTERM

# Remove old PID file if it exists
rm -f "$PID_FILE"

# Rotate logs if they exceed size limit
echo -e "${YELLOW}Checking log files...${NC}"
rotate_log "expose.log"
rotate_log "npm.log"
rotate_log "horizon.log"

echo -e "${GREEN}Starting development environment...${NC}\n"

# Start expose share
echo -e "${YELLOW}Starting expose share...${NC}"
expose share https://pos-stripe.test --domain=share.visivo.no --server=eu-1 > expose.log 2>&1 &
EXPOSE_PID=$!
echo "$EXPOSE_PID" >> "$PID_FILE"
echo -e "${GREEN}Expose share started (PID: $EXPOSE_PID)${NC}\n"

# Start npm dev server
echo -e "${YELLOW}Starting npm dev server...${NC}"
npm run dev > npm.log 2>&1 &
NPM_PID=$!
echo "$NPM_PID" >> "$PID_FILE"
echo -e "${GREEN}NPM dev server started (PID: $NPM_PID)${NC}\n"

# Start Laravel Horizon
echo -e "${YELLOW}Starting Laravel Horizon...${NC}"
php artisan horizon > horizon.log 2>&1 &
HORIZON_PID=$!
echo "$HORIZON_PID" >> "$PID_FILE"
echo -e "${GREEN}Laravel Horizon started (PID: $HORIZON_PID)${NC}\n"

echo -e "${GREEN}All services started!${NC}"
echo -e "${YELLOW}Logs:${NC}"
echo -e "  - Expose: tail -f expose.log"
echo -e "  - NPM: tail -f npm.log"
echo -e "  - Horizon: tail -f horizon.log"
echo -e "\n${YELLOW}Press Ctrl+C to stop all services${NC}\n"

# Wait for all background processes
wait
