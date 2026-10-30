#!/usr/bin/env bash
# =============================================================================
# Post-Deployment Smoke Test Script (scripts/deploy/smoke-test.sh)
# Employee HRIS & Leave Management System
#
# Usage:
#   ./scripts/deploy/smoke-test.sh [TARGET_URL]
# Example:
#   ./scripts/deploy/smoke-test.sh http://localhost:8080
#   ./scripts/deploy/smoke-test.sh https://hris.example.com
# =============================================================================

set -euo pipefail

TARGET_URL="${1:-http://localhost:8080}"
# Strip trailing slash if provided
TARGET_URL="${TARGET_URL%/}"

echo "===================================================================="
echo "  HRIS PRODUCTION SMOKE TEST RUNNER"
echo "  Target URL: ${TARGET_URL}"
echo "===================================================================="

FAILURES=0

test_endpoint() {
    local endpoint="$1"
    local expected_code="$2"
    local description="$3"

    echo -n "Testing ${description} (${endpoint})... "
    
    local http_code
    http_code=$(curl -s -o /dev/null -w "%{http_code}" -k --connect-timeout 5 --max-time 10 "${TARGET_URL}${endpoint}")

    if [ "${http_code}" -eq "${expected_code}" ]; then
        echo "PASS [HTTP ${http_code}] ✅"
    else
        echo "FAIL [Expected HTTP ${expected_code}, got ${http_code}] ❌"
        FAILURES=$((FAILURES + 1))
    fi
}

# 1. Health Diagnostics
test_endpoint "/health" 200 "Public Health Endpoint"
test_endpoint "/status" 200 "System Status Alias"
test_endpoint "/api/v1/health" 200 "REST API v1 Health Check"

# 2. Authentication & Views
test_endpoint "/login" 200 "Web Login Form"

# 3. Observability
test_endpoint "/metrics" 200 "Prometheus Application Metrics"

# 4. Security: Blocked Sensitive Endpoints
test_endpoint "/.env" 403 "Blocked .env Security Rule"

echo "===================================================================="
if [ "${FAILURES}" -eq 0 ]; then
    echo "  ALL SMOKE TESTS PASSED SUCCESSFULLY! ✅"
    echo "===================================================================="
    exit 0
else
    echo "  ${FAILURES} SMOKE TEST(S) FAILED! ❌"
    echo "===================================================================="
    exit 1
fi

