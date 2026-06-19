#!/bin/bash
# QORDY WebSocket Server Watchdog
# Runs via cron every minute to ensure the WebSocket server stays alive.
# Uses direct PHP process with nohup - no PM2 or systemd needed.

LOCKFILE="/tmp/qordy-ws-watchdog.lock"
PIDFILE="/var/www/vhosts/qordy.com/httpdocs/storage/websocket.pid"
LOGFILE="/var/www/vhosts/qordy.com/logs/websocket-server.log"
PHP="/opt/plesk/php/8.3/bin/php"
SERVER="/var/www/vhosts/qordy.com/httpdocs/app/websocket/server.php"
PORT=8080

# Prevent concurrent watchdog runs
exec 200>"$LOCKFILE"
flock -n 200 || exit 0

is_running() {
    if [ -f "$PIDFILE" ]; then
        local pid=$(cat "$PIDFILE" 2>/dev/null)
        if [ -n "$pid" ] && [ -d "/proc/$pid" ]; then
            return 0
        fi
    fi
    
    # Fallback: check if port is in use by our server
    local port_pid=$(lsof -ti :$PORT 2>/dev/null | head -1)
    if [ -n "$port_pid" ]; then
        local cmd=$(ps -p "$port_pid" -o cmd= 2>/dev/null)
        if echo "$cmd" | grep -q "server.php"; then
            return 0
        fi
    fi
    
    return 1
}

if is_running; then
    exit 0
fi

# Clean up stale files
rm -f "$PIDFILE" 2>/dev/null
for p in $(lsof -ti :$PORT 2>/dev/null); do
    kill -9 "$p" 2>/dev/null
done
sleep 1

# Start the server
echo "[$(date '+%Y-%m-%d %H:%M:%S')] Watchdog: Starting WebSocket server..." >> "$LOGFILE"
cd /var/www/vhosts/qordy.com/httpdocs
nohup "$PHP" "$SERVER" >> "$LOGFILE" 2>&1 &
NEWPID=$!
disown $NEWPID 2>/dev/null

sleep 2

if [ -d "/proc/$NEWPID" ]; then
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] Watchdog: Server started (PID: $NEWPID)" >> "$LOGFILE"
else
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] Watchdog: FAILED to start server" >> "$LOGFILE"
fi
