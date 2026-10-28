#!/usr/bin/env bash

# ==============================================================================
# Shell Script to execute bot admin cleanup on Production or Local Database
# ==============================================================================
# Usage:
#   bash scripts/delete_bot_admins_prod.sh [prod|local]
# ==============================================================================

set -e

DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null 2>&1 && pwd )"
API_DIR="$( cd "$DIR/.." >/dev/null 2>&1 && pwd )"
SQL_FILE="$API_DIR/database/sql/delete_bot_admin_users.sql"

TARGET="${1:-prod}"

if [ "$TARGET" = "local" ]; then
    DB_HOST="127.0.0.1"
    DB_PORT="3306"
    DB_USER="root"
    DB_PASS="root123"
    DB_NAME="bl"
else
    DB_HOST="${PROD_HOST:-173.212.213.153}"
    DB_PORT="${PROD_PORT:-3306}"
    DB_USER="${PROD_USER:-back_backlsh}"
    DB_PASS="${PROD_PASS:-Rammandir@2021}"
    DB_NAME="${PROD_DB:-back_backlsh}"
fi

echo "======================================================="
echo "  🧹 DELETING BOT ADMIN USERS: $TARGET DATABASE"
echo "======================================================="
echo "Target: $DB_USER@$DB_HOST:$DB_PORT/$DB_NAME"
echo "SQL Script: $SQL_FILE"
echo "======================================================="

# Test connection
echo "📡 Testing connection..."
ERR=$(mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASS" --ssl=0 -e "SELECT 1;" 2>&1 || true)

if echo "$ERR" | grep -q "ERROR"; then
    CURRENT_IP=$(curl -s --max-time 3 https://ifconfig.me 2>/dev/null || echo "Unknown")
    echo "❌ Cannot connect directly to $DB_HOST:$DB_PORT"
    echo "   Details: $ERR"
    echo ""
    echo "💡 How to run this on production:"
    echo "   Option A: Run via Artisan on production server:"
    echo "     php artisan users:delete-bot-admins --force"
    echo ""
    echo "   Option B: Run via MySQL CLI on production server:"
    echo "     mysql -u $DB_USER -p $DB_NAME < database/sql/delete_bot_admin_users.sql"
    echo ""
    echo "   Option C: Whitelist your IP ($CURRENT_IP) in 'Remote MySQL' on the server,"
    echo "             then re-run this script."
    exit 1
fi

echo "  ✅ Connected successfully."
echo "⏳ Executing cleanup SQL script..."
mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASS" --ssl=0 "$DB_NAME" < "$SQL_FILE"

echo ""
echo "======================================================="
echo "  🎉 BOT ADMIN USERS DELETED SUCCESSFULLY!"
echo "======================================================="
