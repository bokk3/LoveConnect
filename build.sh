# Build script for LoveConnect Dating App
#!/bin/bash

set -e

echo "🚀 Building LoveConnect Dating App..."

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Configuration
IMAGE_NAME="loveconnect-app"
CONTAINER_NAME="loveconnect-container"
TAG=${1:-latest}

# Security check for .env file
if [ ! -f ".env" ]; then
    echo -e "${RED}❌ ERROR: .env file not found!${NC}"
    echo -e "${YELLOW}Please copy .env.example to .env and configure your environment variables:${NC}"
    echo "cp .env.example .env"
    echo "# Then edit .env with your secure passwords and configuration"
    exit 1
fi

# Check for default passwords
if grep -q "CHANGE_THIS" .env || grep -q "rootpassword" .env; then
    echo -e "${RED}❌ SECURITY WARNING: Default passwords detected in .env file!${NC}"
    echo -e "${YELLOW}Please change all default passwords before building:${NC}"
    grep -n "CHANGE_THIS\|rootpassword" .env || true
    echo -e "${YELLOW}Edit your .env file and replace all default values.${NC}"
    exit 1
fi

echo -e "${YELLOW}Building Docker image: ${IMAGE_NAME}:${TAG}${NC}"

# Build the Docker image
docker build -f Dockerfile.production -t ${IMAGE_NAME}:${TAG} .

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ Docker image built successfully!${NC}"
else
    echo -e "${RED}❌ Docker build failed!${NC}"
    exit 1
fi

# Optional: Run the container
if [ "$2" = "--run" ]; then
    echo -e "${YELLOW}Starting container: ${CONTAINER_NAME}${NC}"
    
    # Stop and remove existing container if it exists
    docker stop ${CONTAINER_NAME} 2>/dev/null || true
    docker rm ${CONTAINER_NAME} 2>/dev/null || true
    
    # Run the new container
    docker run -d \
        --name ${CONTAINER_NAME} \
        -p 8080:80 \
        -p 3306:3306 \
        --env-file .env \
        -v loveconnect-data:/var/lib/mysql \
        ${IMAGE_NAME}:${TAG}
    
    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✅ Container started successfully!${NC}"
        echo -e "${GREEN}🌐 App available at: http://localhost:8080${NC}"
        echo -e "${GREEN}📊 Database available at: localhost:3306${NC}"
        
        # Wait a moment and check health
        echo -e "${YELLOW}Waiting for services to start...${NC}"
        sleep 10
        
        echo -e "${YELLOW}Checking health status...${NC}"
        curl -s http://localhost:8080/health.php | jq . || echo "Health check endpoint not ready yet"
        
    else
        echo -e "${RED}❌ Container failed to start!${NC}"
        exit 1
    fi
fi

echo -e "${GREEN}🎉 Build process completed!${NC}"
echo ""
echo "Available commands:"
echo "  docker run -d -p 8080:80 --env-file .env ${IMAGE_NAME}:${TAG}   # Run container"
echo "  docker logs ${CONTAINER_NAME}                                    # View logs"
echo "  docker exec -it ${CONTAINER_NAME} /bin/sh                      # Shell access"
echo "  docker stop ${CONTAINER_NAME}                                   # Stop container"