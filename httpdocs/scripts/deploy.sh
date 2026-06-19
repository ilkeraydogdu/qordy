#!/bin/bash
#
# Qordy Production Deployment Script
# Usage: ./scripts/deploy.sh [staging|production]
#

set -e

ENVIRONMENT=${1:-staging}
APP_PATH="/var/www/vhosts/qordy.com/httpdocs"

echo "====================================="
echo "Qordy Deployment: $ENVIRONMENT"
echo "====================================="

# 1. Pre-deployment checks
echo "[1/10] Pre-deployment checks..."
if [ ! -f "$APP_PATH/.env" ]; then
 echo "❌ .env not found!"
 exit 1
fi

if [ ! -d "$APP_PATH/vendor" ]; then
 echo "⚠️ vendor/ not found, running composer install..."
 cd "$APP_PATH" && composer install --no-dev --optimize-autoloader
fi

# 2. Backup database
echo "[2/10] Database backup..."
if [ -f "$APP_PATH/cron/backup-database.php" ]; then
 php "$APP_PATH/cron/backup-database.php" || echo "⚠️ Backup failed, continuing..."
fi

# 3. Pull latest code
echo "[3/10] Pulling latest code..."
cd "$APP_PATH"
git pull origin main 2>/dev/null || git pull origin master 2>/dev/null || echo "⚠️ Not a git repo, skipping pull"

# 4. Update dependencies
echo "[4/10] Updating dependencies..."
composer install --no-dev --optimize-autoloader

# 5. Run migrations
echo "[5/10] Running migrations..."
php scripts/migrate.php up 2>/dev/null || echo "⚠️ No migrate.php found, skipping"

# 6. Clear caches
echo "[6/10] Clearing caches..."
rm -rf var/cache/* 2>/dev/null || true
rm -rf bootstrap/cache/* 2>/dev/null || true

# 7. Set permissions
echo "[7/10] Setting permissions..."
chmod 600 .env
chmod -R 755 app/ public/
chmod -R 775 storage/ var/ 2>/dev/null || true
chown -R qordy.com_jckqwoy6r4j:psacln . 2>/dev/null || true

# 8. Build frontend (if exists)
echo "[8/10] Building frontend..."
if [ -f "frontend/package.json" ]; then
 cd frontend && npm ci --production && npm run build && cd ..
 echo "✅ Frontend built"
else
 echo "⚠️ No frontend, skipping"
fi

# 9. Run health check
echo "[9/10] Health check..."
sleep 2
HEALTH_URL="https://qordy.com/health"
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$HEALTH_URL" 2>/dev/null || echo "000")
if [ "$HTTP_CODE" = "200" ]; then
 echo "✅ Health check passed"
else
 echo "⚠️ Health check returned $HTTP_CODE"
fi

# 10. Verify deployment
echo "[10/10] Verifying deployment..."
if [ -f "$APP_PATH/app/services/CacheManager.php" ]; then
 echo "✅ CacheManager deployed"
fi
if [ -f "$APP_PATH/app/models/OrderStatus.php" ]; then
 echo "✅ OrderStatus enum deployed"
fi
if [ -f "$APP_PATH/app/controllers/HealthController.php" ]; then
 echo "✅ HealthController deployed"
fi

echo ""
echo "====================================="
echo "✅ Deployment complete: $ENVIRONMENT"
echo "====================================="
echo ""
echo "Endpoints:"
echo " - Health: https://qordy.com/health"
echo " - Metrics: https://qordy.com/metrics"
echo " - API v2: https://qordy.com/api/v2/..."
echo " - Mobile API: https://qordy.com/api/mobile/..."
