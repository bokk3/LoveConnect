# Docker Port Allocation Strategy

## Current Port Usage

| **Project** | **Service** | **Port** | **Purpose** | **Status** |
|-------------|-------------|----------|-------------|------------|
| **edgeforge-dev** | Frontend | 3001 | Next.js/React Dev | ✅ Active |
| **edgeforge-dev** | Vite Dev | 3002 | Vite Dev Server | ✅ Active |
| **gamble-fun** | Frontend | 3003 | Web Interface | 🔄 Planned |
| **gamble-fun** | API | 3004 | Backend API | 🔄 Planned |
| **LoveConnect** | Frontend | 3005 | Dating App UI | 🔄 Migration |
| **LoveConnect** | API | 3006 | PHP Backend | 🔄 Migration |
| **Portainer** | Web UI | 8000 | Docker Management | ✅ Active |
| **Portainer** | HTTPS | 9443 | Secure Access | ✅ Active |

## Current LoveConnect (Legacy Ports)
- **Nginx**: 8080 → Will migrate to 3005
- **MariaDB**: 3307 → Will migrate to 3306 (internal)

## Database Ports (Internal Network)
- **LoveConnect MariaDB**: 3306 (container internal)
- **gamble-fun DB**: 5432 (PostgreSQL, container internal)
- **edgeforge-dev DB**: 27017 (MongoDB, container internal)

## Port Range Allocation Strategy

### Development Projects (3000-3999)
- **3001-3002**: edgeforge-dev (Reserved)
- **3003-3004**: gamble-fun (Planned)
- **3005-3006**: LoveConnect (Migration target)
- **3007-3008**: Future project slot
- **3009-3010**: Future project slot

### System Services (8000-9999)
- **8000, 9443**: Portainer (Reserved)
- **8080**: Legacy LoveConnect (Temporary)

## Migration Plan

### Phase 1: Prepare gamble-fun
```bash
# gamble-fun docker-compose.yml
services:
  frontend:
    ports:
      - "3003:3000"
  api:
    ports: 
      - "3004:8000"
```

### Phase 2: Migrate LoveConnect
```bash
# Update LoveConnect to use 3005-3006
services:
  nginx:
    ports:
      - "3005:80"
  php-api:
    ports:
      - "3006:9000"
```

## Quick Reference Commands

```bash
# Check all running containers
docker ps --format "table {{.Names}}\t{{.Ports}}\t{{.Status}}"

# Check port availability
ss -tuln | grep -E ":(3003|3004|3005|3006)"

# Start project with specific ports
docker compose -f docker-compose.override.yml up -d
```

## Access URLs
- **edgeforge-dev**: http://localhost:3001, http://localhost:3002
- **gamble-fun**: http://localhost:3003 (planned)
- **LoveConnect**: http://localhost:3005 (planned)
- **Portainer**: http://localhost:8000, https://localhost:9443

---
*Last updated: October 15, 2025*