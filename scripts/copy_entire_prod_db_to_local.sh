#!/usr/bin/env bash

# ==============================================================================
# Full Database Copy Script: Production -> Local MySQL
# ==============================================================================
# Usage:
#   bash scripts/copy_entire_prod_db_to_local.sh [target_local_db]
# Example:
#   bash scripts/copy_entire_prod_db_to_local.sh bl
#   bash scripts/copy_entire_prod_db_to_local.sh back_backlsh
# ==============================================================================

set -e

DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null 2>&1 && pwd )"
API_DIR="$( cd "$DIR/.." >/dev/null 2>&1 && pwd )"
cd "$API_DIR"

PROD_HOST="${PROD_HOST:-173.212.213.153}"
PROD_PORT="${PROD_PORT:-3306}"
PROD_USER="${PROD_USER:-back_backlsh}"
PROD_PASS="${PROD_PASS:-Rammandir@2021}"
PROD_DB="${PROD_DB:-back_backlsh}"

LOCAL_HOST="${LOCAL_HOST:-127.0.0.1}"
LOCAL_PORT="${LOCAL_PORT:-3306}"
LOCAL_USER="${LOCAL_USER:-root}"
LOCAL_PASS="${LOCAL_PASS:-root123}"
LOCAL_DB="${1:-${LOCAL_DB:-bl}}"

TMP_DIR="$API_DIR/database/dumps"
mkdir -p "$TMP_DIR"
DUMP_FILE="$TMP_DIR/prod_dump_$(date +%Y%m%d_%H%M%S).sql"

# Check for --from-dump argument
USE_EXISTING_DUMP=0
EXISTING_DUMP=""
for arg in "$@"; do
  if [ "$arg" = "--from-dump" ]; then
    USE_EXISTING_DUMP=1
  elif [[ "$arg" == --from-dump=* ]]; then
    USE_EXISTING_DUMP=1
    EXISTING_DUMP="${arg#*=}"
  fi
done

if [ "$USE_EXISTING_DUMP" -eq 1 ]; then
  if [ -z "$EXISTING_DUMP" ]; then
    # Find latest dump in dumps dir
    EXISTING_DUMP=$(ls -t "$TMP_DIR"/*.sql 2>/dev/null | head -n 1)
  fi
  if [ -n "$EXISTING_DUMP" ] && [ -f "$EXISTING_DUMP" ]; then
    DUMP_FILE="$EXISTING_DUMP"
    echo "Using existing local dump file: $DUMP_FILE"
  fi
fi

echo "======================================================="
echo "  🚀 FULL DATABASE CLONE: PRODUCTION -> LOCAL"
echo "======================================================="
echo "Source: $PROD_USER@$PROD_HOST:$PROD_PORT/$PROD_DB"
echo "Target: $LOCAL_USER@$LOCAL_HOST:$LOCAL_PORT/$LOCAL_DB"
echo "Dump file: $DUMP_FILE"
echo "======================================================="

# 1. Test prod connection (only if not using existing dump)
if [ "$USE_EXISTING_DUMP" -eq 0 ]; then
  echo "📡 Checking connection to Production ($PROD_HOST:$PROD_PORT)..."
  PROD_ERR=$(mysql -h "$PROD_HOST" -P "$PROD_PORT" -u "$PROD_USER" -p"$PROD_PASS" --ssl=0 -e "SELECT 1;" 2>&1 || true)
  if echo "$PROD_ERR" | grep -q "ERROR"; then
    CURRENT_IP=$(curl -s --max-time 3 https://ifconfig.me 2>/dev/null || echo "Unknown")
    echo "❌ Error: Could not connect to Production MySQL on $PROD_HOST:$PROD_PORT"
    echo "   Details: $PROD_ERR"
    echo ""
    echo "💡 Why this happens:"
    echo "   1. Contabo / cPanel / CyberPanel firewall only allows whitelisted IPs for Remote MySQL."
    echo "      Your current public IP is: $CURRENT_IP"
    echo "      Add '$CURRENT_IP' to 'Remote MySQL' or whitelist port 3306 in the server firewall."
    LATEST_DUMP=$(ls -t "$TMP_DIR"/*.sql 2>/dev/null | head -n 1)
    if [ -n "$LATEST_DUMP" ] && [ -f "$LATEST_DUMP" ]; then
      echo ""
      echo "   2. A complete production database dump is already available locally!"
      echo "      Run this command to restore it directly without remote access:"
      echo "      ./scripts/copy_entire_prod_db_to_local.sh $LOCAL_DB --from-dump"
    fi
    exit 1
  fi
  echo "  ✅ Production connection OK."
fi

# 2. Test local connection
echo "📡 Checking connection to Local MySQL..."
mysql -h "$LOCAL_HOST" -P "$LOCAL_PORT" -u "$LOCAL_USER" -p"$LOCAL_PASS" -e "SELECT 1;" >/dev/null 2>&1 || {
  echo "❌ Error: Could not connect to Local MySQL at $LOCAL_HOST:$LOCAL_PORT."
  exit 1
}
echo "  ✅ Local connection OK."

# 3. Increase max_allowed_packet locally
mysql -h "$LOCAL_HOST" -P "$LOCAL_PORT" -u "$LOCAL_USER" -p"$LOCAL_PASS" -e "SET GLOBAL max_allowed_packet=536870912;" >/dev/null 2>&1 || true

# 4. Dump entire database from production (if not using existing dump)
if [ "$USE_EXISTING_DUMP" -eq 0 ]; then
  echo "⏳ Dumping all tables from Production (using compression & single-transaction)..."
  mysqldump \
    -h "$PROD_HOST" \
    -P "$PROD_PORT" \
    -u "$PROD_USER" \
    -p"$PROD_PASS" \
    --ssl=0 \
    --single-transaction \
    --quick \
    --compress \
    --default-character-set=utf8mb4 \
    --max_allowed_packet=512M \
    "$PROD_DB" > "$DUMP_FILE"

  DUMP_SIZE=$(du -h "$DUMP_FILE" | cut -f1)
  echo "  ✅ Production dump completed ($DUMP_SIZE)."
else
  DUMP_SIZE=$(du -h "$DUMP_FILE" | cut -f1)
  echo "  ℹ️ Using existing local dump ($DUMP_SIZE): $DUMP_FILE"
fi

# 5. Ensure local target database exists
echo "🔨 Ensuring local database '$LOCAL_DB' exists..."
mysql -h "$LOCAL_HOST" -P "$LOCAL_PORT" -u "$LOCAL_USER" -p"$LOCAL_PASS" -e "CREATE DATABASE IF NOT EXISTS \`$LOCAL_DB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 6. Import into local database
echo "⏳ Importing dump into local '$LOCAL_DB'..."
mysql \
  -h "$LOCAL_HOST" \
  -P "$LOCAL_PORT" \
  -u "$LOCAL_USER" \
  -p"$LOCAL_PASS" \
  --max_allowed_packet=512M \
  "$LOCAL_DB" < "$DUMP_FILE"

echo "  ✅ Imported all tables and data into local '$LOCAL_DB'."

# 7. Check / Run migrations to ensure latest tables exist (e.g. recognition_prompts)
echo "🔧 Checking migrations and schema in local database..."
php artisan migrate --force || true

# 8. Seed sample activity feed shoutouts ("Arti gave Siddhant a shoutout 👏") and UI test prompts
echo "✨ Ensuring rich recognition activity feed & manager prompts exist locally..."
php artisan recognition:sync-from-prod --local-db="$LOCAL_DB" --seed-sample-feed=1 --seed-prompts=1 || true

echo "======================================================="
echo "  🎉 ENTIRE PRODUCTION DATABASE COPIED TO LOCAL ($LOCAL_DB)!"
echo "  Dump backup kept at: $DUMP_FILE"
echo "======================================================="
