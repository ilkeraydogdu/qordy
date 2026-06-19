#!/bin/bash
#
# Qordy Test Runner - Manuel Çalıştırma
# Auto-mode classifier test komutlarını engelliyor.
# Bu script'i terminal'den manuel çalıştırın.
#

set -e

APP_PATH="/var/www/vhosts/qordy.com/httpdocs"
PHP="/opt/plesk/php/8.3/bin/php"
COMPOSER="composer"

cd "$APP_PATH"

echo "=================================="
echo "QORDY TEST SUITE"
echo "=================================="
echo ""

# 1. Composer autoload regenerate
echo "[1/4] Regenerating autoload..."
$COMPOSER dump-autoload --no-interaction
echo ""

# 2. Run unit tests
echo "[2/4] Running Unit tests..."
$PHP vendor/bin/phpunit --testsuite=Unit 2>&1 | tail -50
echo ""

# 3. Run integration tests
echo "[3/4] Running Integration tests..."
$PHP vendor/bin/phpunit --testsuite=Integration 2>&1 | tail -30
echo ""

# 4. PHP syntax check on all new files
echo "[4/4] PHP syntax check (new files)..."
for f in app/core/CacheManager.php \
 app/services/RateLimiter.php \
 app/models/OrderStatus.php \
 app/controllers/HealthController.php \
 app/controllers/MetricsController.php \
 app/controllers/API/Mobile/MobileAuthController.php \
 app/controllers/API/Mobile/MobileOrderController.php \
 app/controllers/API/OrdersController.php \
 app/controllers/API/TablesController.php \
 app/controllers/Menu/CategoryController.php \
 app/controllers/Menu/MenuItemController.php; do
 RESULT=$($PHP -l "$f" 2>&1 | grep -v "No syntax errors" | grep -E "error|Error" | head -1)
 if [ -z "$RESULT" ]; then
 echo " ✅ $f"
 else
 echo " ❌ $f - $RESULT"
 fi
done

echo ""
echo "=================================="
echo "Test execution complete"
echo "=================================="
