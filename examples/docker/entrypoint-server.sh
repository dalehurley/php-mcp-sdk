#!/bin/bash

# MCP Server Docker Entrypoint Script
# Handles different server types and configurations

set -e

# Default values
MCP_SERVER_TYPE=${MCP_SERVER_TYPE:-simple}
MCP_LOG_LEVEL=${MCP_LOG_LEVEL:-info}
MCP_ENVIRONMENT=${MCP_ENVIRONMENT:-production}

echo "🚀 Starting MCP Server"
echo "======================"
echo "Server Type: $MCP_SERVER_TYPE"
echo "Environment: $MCP_ENVIRONMENT"
echo "Log Level: $MCP_LOG_LEVEL"
echo ""

# Create necessary directories
mkdir -p /app/data /app/logs /app/resources

# Set up logging
if [ "$MCP_LOG_LEVEL" = "debug" ]; then
    set -x
fi

# Function to wait for dependencies
wait_for_service() {
    local host=$1
    local port=$2
    local service_name=$3
    
    echo "⏳ Waiting for $service_name ($host:$port)..."
    
    timeout=30
    while [ $timeout -gt 0 ]; do
        if nc -z "$host" "$port" 2>/dev/null; then
            echo "✅ $service_name is ready"
            return 0
        fi
        sleep 1
        timeout=$((timeout - 1))
    done
    
    echo "❌ Timeout waiting for $service_name"
    return 1
}

# Health check function
health_check() {
    local server_script=$1
    echo "🏥 Running health check for $server_script"
    
    if php -l "$server_script" >/dev/null 2>&1; then
        echo "✅ Syntax check passed"
        return 0
    else
        echo "❌ Syntax check failed"
        return 1
    fi
}

# Start the appropriate server based on type
case "$MCP_SERVER_TYPE" in
    "simple")
        SERVER_SCRIPT="/app/examples/server/simple-server.php"
        echo "🧮 Starting Simple Calculator Server"
        ;;
    
    "weather")
        SERVER_SCRIPT="/app/examples/server/weather-server.php"
        echo "🌤️  Starting Weather Server"
        
        # Check if API key is provided
        if [ "$OPENWEATHER_API_KEY" = "demo_key" ] || [ -z "$OPENWEATHER_API_KEY" ]; then
            echo "⚠️  Warning: Using demo API key. Set OPENWEATHER_API_KEY for real data."
        fi
        ;;
    
    "database")
        SERVER_SCRIPT="/app/examples/server/sqlite-server.php"
        echo "🗄️  Starting Database Server"
        
        # Initialize database if it doesn't exist
        if [ ! -f "/app/data/example.db" ]; then
            echo "📊 Initializing SQLite database..."
            # The server will create the database on first run
        fi
        ;;
    
    "oauth")
        SERVER_SCRIPT="/app/examples/server/oauth-server.php"
        echo "🔐 Starting OAuth Server"
        
        # Validate OAuth configuration
        if [ -z "$OAUTH_CLIENT_ID" ] || [ -z "$OAUTH_CLIENT_SECRET" ]; then
            echo "⚠️  Warning: OAuth credentials not fully configured"
        fi
        ;;
    
    "resource")
        SERVER_SCRIPT="/app/examples/server/resource-server.php"
        echo "📁 Starting Resource Server"
        
        # Ensure resource directory exists
        mkdir -p /app/resources/static /app/resources/dynamic
        ;;
    
    *)
        echo "❌ Unknown server type: $MCP_SERVER_TYPE"
        echo "Available types: simple, weather, database, oauth, resource"
        exit 1
        ;;
esac

# Perform health check
if ! health_check "$SERVER_SCRIPT"; then
    echo "❌ Health check failed for $SERVER_SCRIPT"
    exit 1
fi

# Set up signal handlers for graceful shutdown
cleanup() {
    echo ""
    echo "🛑 Received shutdown signal"
    echo "🧹 Cleaning up..."
    
    # Kill any background processes
    jobs -p | xargs -r kill
    
    echo "✅ Cleanup completed"
    exit 0
}

trap cleanup SIGTERM SIGINT

# Log startup information
echo "📝 Server Information:"
echo "   Script: $SERVER_SCRIPT"
echo "   PID: $$"
echo "   User: $(whoami)"
echo "   Working Directory: $(pwd)"
echo "   PHP Version: $(php -v | head -n1)"
echo ""

# Start the server with proper error handling
echo "🎯 Launching MCP server..."
echo "   Command: php $SERVER_SCRIPT"
echo ""

# Execute the server with error handling
if [ "$MCP_ENVIRONMENT" = "development" ]; then
    # Development mode - more verbose output
    exec php -d display_errors=1 -d log_errors=1 "$SERVER_SCRIPT"
else
    # Production mode
    exec php "$SERVER_SCRIPT" 2>&1 | tee "/app/logs/server-$(date +%Y%m%d).log"
fi
