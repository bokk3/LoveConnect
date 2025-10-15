#!/bin/bash

# LoveConnect Port Migration Script
# Migrates from legacy ports (8080, 3307) to standardized ports (3005, 3006)

set -e

echo "🚀 LoveConnect Port Migration Script"
echo "===================================="

# Check if Docker is running
if ! docker info >/dev/null 2>&1; then
    echo "❌ Docker is not running. Please start Docker first."
    exit 1
fi

# Check current LoveConnect status
echo "📊 Checking current LoveConnect status..."
CURRENT_CONTAINERS=$(docker ps --filter "name=login_" --format "{{.Names}}" | wc -l)

if [ "$CURRENT_CONTAINERS" -gt 0 ]; then
    echo "🔄 Found running LoveConnect containers. Stopping them..."
    docker compose down
    echo "✅ Stopped legacy containers"
else
    echo "ℹ️  No running LoveConnect containers found"
fi

# Check if new ports are available
echo "🔍 Checking port availability..."
if ss -tuln | grep -q ":3005 "; then
    echo "❌ Port 3005 is already in use"
    exit 1
fi

if ss -tuln | grep -q ":3006 "; then
    echo "❌ Port 3006 is already in use"
    exit 1
fi

echo "✅ Ports 3005 and 3006 are available"

# Start with new configuration
echo "🚀 Starting LoveConnect with standardized ports..."
docker compose -f docker-compose.yml -f docker-compose.override.yml up -d

# Wait for services to start
echo "⏳ Waiting for services to start..."
sleep 10

# Test connectivity
echo "🧪 Testing connectivity..."
if curl -s -o /dev/null -w "%{http_code}" http://localhost:3005 | grep -q "200"; then
    echo "✅ LoveConnect is accessible at http://localhost:3005"
else
    echo "❌ LoveConnect is not responding. Check logs:"
    docker compose logs --tail=10
    exit 1
fi

# Display new configuration
echo ""
echo "🎉 Migration completed successfully!"
echo "=================================="
echo "New Access URLs:"
echo "🌐 LoveConnect Web: http://localhost:3005"
echo "🔧 PHP-FPM API: http://localhost:3006 (optional)"
echo "🗄️  MariaDB: localhost:3307 (unchanged)"
echo ""
echo "Container Status:"
docker ps --filter "name=loveconnect_" --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}"

echo ""
echo "📝 To revert to legacy ports, run:"
echo "   docker compose -f docker-compose.yml up -d"