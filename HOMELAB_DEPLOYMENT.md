# LoveConnect Homelab Deployment Guide

Welcome to your self-hosted LoveConnect dating app! This guide will walk you through setting up a production-grade homelab deployment with SSL, monitoring, and zero port forwarding using Cloudflare tunnels.

## 🏗️ Architecture Overview

Your homelab stack includes:
- **Traefik**: Reverse proxy with automatic SSL
- **Cloudflare Tunnels**: Public access without port forwarding
- **Prometheus + Grafana**: Monitoring and alerting
- **Redis**: Session storage and caching
- **Watchtower**: Automatic container updates
- **MariaDB**: Production-tuned database

## 🚀 Quick Start

### 1. Prerequisites
- Docker and Docker Compose installed
- A domain name (can be free from Cloudflare)
- Cloudflare account (free tier works)

### 2. Environment Setup
```bash
# Copy and customize environment file
cp .env.homelab.example .env

# Edit with your values
nano .env
```

### 3. Cloudflare Tunnel Setup
1. Go to [Cloudflare Zero Trust Dashboard](https://one.dash.cloudflare.com/)
2. Navigate to Networks > Tunnels
3. Create a new tunnel and copy the token
4. Add the token to your `.env` file

### 4. Deploy the Stack
```bash
# Start all services
docker-compose -f docker-compose.homelab.yml up -d

# Check service status
docker-compose -f docker-compose.homelab.yml ps

# View logs
docker-compose -f docker-compose.homelab.yml logs -f app
```

## 🔧 Configuration Details

### SSL Certificates
- Automatic Let's Encrypt certificates via Traefik
- Automatic renewal
- HTTPS redirect for all traffic

### Monitoring Access
- **Grafana**: `https://monitoring.your-domain.com`
  - Username: `admin`
  - Password: Set in `.env` file
- **Traefik Dashboard**: `https://traefik.your-domain.com`
  - Username: `admin` 
  - Password: Set in `.env` file

### Security Features
- Rate limiting
- Security headers
- CSP policies
- Basic auth for admin interfaces
- Redis session storage

## 📊 Monitoring

### Built-in Dashboards
- System metrics (CPU, RAM, disk)
- Application performance
- Database metrics
- Traefik metrics
- Container health

### Alerts (Future Enhancement)
- High CPU/memory usage
- Database connection issues
- SSL certificate expiration
- Application errors

## 🛠️ Maintenance

### Update Applications
```bash
# Watchtower automatically updates containers
# To force update immediately:
docker-compose -f docker-compose.homelab.yml pull
docker-compose -f docker-compose.homelab.yml up -d
```

### Backup Database
```bash
# Manual backup
docker-compose -f docker-compose.homelab.yml exec db mysqldump -u root -p loveconnect_prod > backup_$(date +%Y%m%d).sql

# Automated backups run daily at 2 AM (configured in docker-compose)
```

### View Logs
```bash
# All services
docker-compose -f docker-compose.homelab.yml logs

# Specific service
docker-compose -f docker-compose.homelab.yml logs app
docker-compose -f docker-compose.homelab.yml logs traefik
```

## 🔍 Troubleshooting

### Common Issues

**SSL Certificate Issues**
```bash
# Check Traefik logs
docker-compose -f docker-compose.homelab.yml logs traefik

# Force certificate renewal
docker-compose -f docker-compose.homelab.yml restart traefik
```

**Database Connection Issues**
```bash
# Check database status
docker-compose -f docker-compose.homelab.yml exec db mysql -u root -p -e "SHOW PROCESSLIST;"

# Check app logs
docker-compose -f docker-compose.homelab.yml logs app | grep -i database
```

**Cloudflare Tunnel Issues**
```bash
# Check tunnel status
docker-compose -f docker-compose.homelab.yml logs cloudflared

# Recreate tunnel
docker-compose -f docker-compose.homelab.yml restart cloudflared
```

### Performance Tuning

**Database Performance**
- Monitor slow queries in Grafana
- Adjust `config/mysql/my.cnf` for your hardware
- Consider upgrading RAM for larger buffer pools

**PHP Performance**
- Monitor PHP-FPM metrics
- Adjust `PHP_MEMORY_LIMIT` and worker counts
- Enable OPcache in production

## 🔐 Security Checklist

- [ ] Change default passwords in `.env`
- [ ] Enable 2FA on Cloudflare account
- [ ] Regular security updates via Watchtower
- [ ] Monitor access logs in Grafana
- [ ] Backup encryption (future enhancement)

## 📈 Scaling

When you outgrow single-server deployment:
- Load balancer with multiple app instances
- Database replication
- Redis clustering  
- Container orchestration (K8s/Swarm)

## 🆘 Support

Check out:
- Docker Compose logs
- Traefik dashboard
- Grafana metrics
- Application debug logs

---

**Enjoy your self-hosted LoveConnect dating app! 💕**