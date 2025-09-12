#!/bin/bash

# MCP Client Docker Entrypoint Script
# Handles different client modes and utilities

set -e

# Default values
MCP_CLIENT_MODE=${MCP_CLIENT_MODE:-interactive}
MCP_TARGET_SERVER=${MCP_TARGET_SERVER:-simple-server}
MCP_LOG_LEVEL=${MCP_LOG_LEVEL:-info}

echo "🔌 Starting MCP Client"
echo "======================"
echo "Client Mode: $MCP_CLIENT_MODE"
echo "Target Server: $MCP_TARGET_SERVER"
echo "Log Level: $MCP_LOG_LEVEL"
echo ""

# Create necessary directories
mkdir -p /app/logs /app/reports

# Set up logging
if [ "$MCP_LOG_LEVEL" = "debug" ]; then
    set -x
fi

# Function to wait for server
wait_for_server() {
    local server_host=$1
    local timeout=${2:-30}
    
    echo "⏳ Waiting for server $server_host..."
    
    while [ $timeout -gt 0 ]; do
        # Try to connect to the server (this is a simple check)
        if ping -c 1 "$server_host" >/dev/null 2>&1; then
            echo "✅ Server $server_host is reachable"
            return 0
        fi
        sleep 1
        timeout=$((timeout - 1))
    done
    
    echo "❌ Timeout waiting for server $server_host"
    return 1
}

# Set up signal handlers for graceful shutdown
cleanup() {
    echo ""
    echo "🛑 Received shutdown signal"
    echo "🧹 Cleaning up client..."
    
    # Kill any background processes
    jobs -p | xargs -r kill
    
    echo "✅ Client cleanup completed"
    exit 0
}

trap cleanup SIGTERM SIGINT

# Determine server script path based on target
get_server_script() {
    case "$MCP_TARGET_SERVER" in
        "simple-server"|"simple")
            echo "/app/examples/server/simple-server.php"
            ;;
        "weather-server"|"weather")
            echo "/app/examples/server/weather-server.php"
            ;;
        "database-server"|"database")
            echo "/app/examples/server/sqlite-server.php"
            ;;
        "oauth-server"|"oauth")
            echo "/app/examples/server/oauth-server.php"
            ;;
        "resource-server"|"resource")
            echo "/app/examples/server/resource-server.php"
            ;;
        *)
            # Assume it's a custom path or network address
            echo "$MCP_TARGET_SERVER"
            ;;
    esac
}

SERVER_SCRIPT=$(get_server_script)

echo "📝 Client Information:"
echo "   Mode: $MCP_CLIENT_MODE"
echo "   Target: $SERVER_SCRIPT"
echo "   PID: $$"
echo "   User: $(whoami)"
echo "   Working Directory: $(pwd)"
echo "   PHP Version: $(php -v | head -n1)"
echo ""

# Execute based on client mode
case "$MCP_CLIENT_MODE" in
    "interactive")
        echo "🎮 Starting Interactive Client"
        echo "Use 'help' for available commands"
        echo ""
        
        # Wait for server if it's a network address
        if [[ "$MCP_TARGET_SERVER" == *"://"* ]] || [[ "$MCP_TARGET_SERVER" == *":"* ]]; then
            SERVER_HOST=$(echo "$MCP_TARGET_SERVER" | cut -d: -f1)
            wait_for_server "$SERVER_HOST"
        fi
        
        exec php /app/examples/client/simple-stdio-client.php
        ;;
    
    "parallel")
        echo "⚡ Starting Parallel Tools Client"
        echo ""
        
        exec php /app/examples/client/parallel-tools-client.php
        ;;
    
    "oauth")
        echo "🔐 Starting OAuth Client"
        echo ""
        
        exec php /app/examples/client/oauth-client.php
        ;;
    
    "http")
        echo "🌐 Starting HTTP Client"
        echo ""
        
        # Set default HTTP server URL if not provided
        MCP_HTTP_SERVER_URL=${MCP_HTTP_SERVER_URL:-http://localhost:3000}
        export MCP_HTTP_SERVER_URL
        
        exec php /app/examples/client/http-client.php
        ;;
    
    "multiple")
        echo "🔀 Starting Multiple Servers Client"
        echo ""
        
        exec php /app/examples/client/multiple-servers-client.php
        ;;
    
    "inspector")
        echo "🔍 Starting MCP Inspector"
        echo ""
        
        INSPECTOR_ARGS=""
        
        # Add common inspector arguments
        if [ -n "$MCP_INSPECTOR_INTERACTIVE" ]; then
            INSPECTOR_ARGS="$INSPECTOR_ARGS --interactive"
        fi
        
        if [ -n "$MCP_INSPECTOR_REPORT" ]; then
            INSPECTOR_ARGS="$INSPECTOR_ARGS --report"
        fi
        
        if [ -n "$MCP_INSPECTOR_TEST_ALL" ]; then
            INSPECTOR_ARGS="$INSPECTOR_ARGS --test-all"
        fi
        
        if [ -n "$MCP_INSPECTOR_OUTPUT" ]; then
            INSPECTOR_ARGS="$INSPECTOR_ARGS --output=$MCP_INSPECTOR_OUTPUT"
        fi
        
        if [ -n "$MCP_INSPECTOR_FORMAT" ]; then
            INSPECTOR_ARGS="$INSPECTOR_ARGS --format=$MCP_INSPECTOR_FORMAT"
        fi
        
        exec php /app/examples/utils/inspector.php --server="$SERVER_SCRIPT" $INSPECTOR_ARGS
        ;;
    
    "monitor")
        echo "📊 Starting MCP Monitor"
        echo ""
        
        MONITOR_ARGS=""
        
        # Add monitor arguments
        if [ -n "$MCP_MONITOR_INTERVAL" ]; then
            MONITOR_ARGS="$MONITOR_ARGS --interval=$MCP_MONITOR_INTERVAL"
        fi
        
        if [ -n "$MCP_MONITOR_DURATION" ]; then
            MONITOR_ARGS="$MONITOR_ARGS --duration=$MCP_MONITOR_DURATION"
        fi
        
        if [ -n "$MCP_MONITOR_ALERTS" ]; then
            MONITOR_ARGS="$MONITOR_ARGS --alerts"
        fi
        
        if [ -n "$MCP_MONITOR_LOG" ]; then
            MONITOR_ARGS="$MONITOR_ARGS --log=$MCP_MONITOR_LOG"
        fi
        
        if [ -n "$MCP_MONITOR_DASHBOARD" ]; then
            MONITOR_ARGS="$MONITOR_ARGS --dashboard"
        fi
        
        if [ -n "$MCP_MONITOR_JSON" ]; then
            MONITOR_ARGS="$MONITOR_ARGS --json"
        fi
        
        exec php /app/examples/utils/monitor.php --server="$SERVER_SCRIPT" $MONITOR_ARGS
        ;;
    
    "test")
        echo "🧪 Running Client Tests"
        echo ""
        
        # Run a series of tests against the server
        echo "Testing basic connection..."
        php /app/examples/client/simple-stdio-client.php || echo "❌ Basic client test failed"
        
        echo "Testing inspector..."
        php /app/examples/utils/inspector.php --server="$SERVER_SCRIPT" --test-all || echo "❌ Inspector test failed"
        
        echo "Testing monitor (short duration)..."
        php /app/examples/utils/monitor.php --server="$SERVER_SCRIPT" --duration=30 --json || echo "❌ Monitor test failed"
        
        echo "✅ All tests completed"
        ;;
    
    "shell")
        echo "🐚 Starting Interactive Shell"
        echo "Available commands:"
        echo "  - php /app/examples/client/simple-stdio-client.php"
        echo "  - php /app/examples/utils/inspector.php --server=$SERVER_SCRIPT --help"
        echo "  - php /app/examples/utils/monitor.php --server=$SERVER_SCRIPT --help"
        echo ""
        
        exec /bin/bash
        ;;
    
    *)
        echo "❌ Unknown client mode: $MCP_CLIENT_MODE"
        echo "Available modes:"
        echo "  - interactive: Interactive MCP client"
        echo "  - parallel: Parallel tools client"
        echo "  - oauth: OAuth authentication client"
        echo "  - http: HTTP transport client"
        echo "  - multiple: Multiple servers client"
        echo "  - inspector: Server inspection utility"
        echo "  - monitor: Server monitoring utility"
        echo "  - test: Run all client tests"
        echo "  - shell: Interactive shell"
        echo ""
        echo "Environment variables:"
        echo "  MCP_CLIENT_MODE: Set the client mode"
        echo "  MCP_TARGET_SERVER: Target server (simple, weather, database, oauth, resource, or custom path)"
        echo "  MCP_HTTP_SERVER_URL: HTTP server URL for HTTP client mode"
        echo "  MCP_INSPECTOR_*: Inspector configuration options"
        echo "  MCP_MONITOR_*: Monitor configuration options"
        exit 1
        ;;
esac
