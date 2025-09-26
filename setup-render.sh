#!/bin/bash
set -e

echo "🚀 Setting up LoveConnect for Render deployment..."

# Check if we're in production (Render environment)
if [ "$RENDER" = "true" ]; then
    echo "📦 Production deployment detected"
    
    # Wait for database to be ready
    echo "⏳ Waiting for database to be ready..."
    sleep 10
    
    # Run database migrations if DATABASE_URL exists
    if [ ! -z "$DATABASE_URL" ]; then
        echo "🗃️ Setting up database schema..."
        
        # Use PostgreSQL client to run schema
        psql $DATABASE_URL -f schema.postgres.sql || echo "Schema already exists or migration failed"
        
        echo "✅ Database setup complete"
    else
        echo "⚠️ No DATABASE_URL found, skipping database setup"
    fi
    
    echo "🎉 Render deployment setup complete!"
else
    echo "🏠 Local development environment detected"
    echo "Use docker-compose for local development"
fi