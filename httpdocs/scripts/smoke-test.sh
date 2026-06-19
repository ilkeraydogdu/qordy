#!/bin/bash
#
# Qordy Production Smoke Test
# Verifies critical endpoints work after deployment
#

set -e

BASE_URL=${1:-"https://qordy.com"}
FAILED=0
PASSED=0

check_endpoint() {
 local name="$1"
 local url="$2"
 local expected="${3:-200}"

 HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" --max-time 10 "$url" 2>/dev/null || echo "000")

 if [ "$HTTP_CODE" = "$expected" ]; then
 echo " ✅ $name: $HTTP_CODE"
 PASSED=$((PASSED + 1))
 else
 echo " ❌ $name: expected $expected, got $HTTP_CODE"
 FAILED=$((FAILED + 1))
 fi
}

check_contains() {
 local name="$1"
 local url="$2"
 local pattern="$3"

 CONTENT=$(curl -s --max-time 10 "$url" 2>/dev/null)
 if echo "$CONTENT" | grep -q "$pattern"; then
 echo " ✅ $name: contains '$pattern'"
 PASSED=$((PASSED + 1))
 else
 echo " ❌ $name: missing '$pattern'"
 FAILED=$((FAILED + 1))
 fi
}

echo "====================================="
echo "Qordy Smoke Test"
echo "Base URL: $BASE_URL"
echo "====================================="
echo ""

# 1. Health check
echo "=== HEALTH & METRICS ==="
check_endpoint "Health Check" "$BASE_URL/health" 200
check_contains "Health JSON" "$BASE_URL/health" '"status"'
check_contains "DB Check" "$BASE_URL/health" '"database"'
check_endpoint "Metrics" "$BASE_URL/metrics" 200
check_contains "Prometheus" "$BASE_URL/metrics" "# TYPE"

echo ""
echo "=== PUBLIC API v2 ==="
check_endpoint "API v2 Menu" "$BASE_URL/api/v2/menu" "200"
check_endpoint "API v2 Orders" "$BASE_URL/api/v2/orders" "200"
check_endpoint "API v2 Tables" "$BASE_URL/api/v2/tables" "200"
check_endpoint "API v2 Zones" "$BASE_URL/api/v2/zones" "200"
check_endpoint "API v2 Floors" "$BASE_URL/api/v2/floors" "200"

echo ""
echo "=== MOBILE API v2 ==="
# Mobile endpoints require auth, expect 401
check_endpoint "Mobile Login (no creds)" "$BASE_URL/api/mobile/auth/login" "400"
check_endpoint "Mobile Orders" "$BASE_URL/api/mobile/orders" "401"
check_endpoint "Mobile Refresh" "$BASE_URL/api/mobile/auth/refresh" "401"

echo ""
echo "=== MENU API v2 ==="
check_endpoint "Menu Categories" "$BASE_URL/api/v2/menu/categories" "200"
check_endpoint "Menu Items" "$BASE_URL/api/v2/menu/items" "200"

echo ""
echo "=== STATIC ASSETS ==="
check_endpoint "Landing" "$BASE_URL/" 200
check_endpoint "Login Page" "$BASE_URL/login" 200
check_endpoint "Pricing" "$BASE_URL/pricing" 200

echo ""
echo "====================================="
echo "RESULTS: $PASSED passed, $FAILED failed"
echo "====================================="

if [ $FAILED -gt 0 ]; then
 echo "❌ SMOKE TEST FAILED"
 exit 1
else
 echo "✅ SMOKE TEST PASSED"
 exit 0
fi
