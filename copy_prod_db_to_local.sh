#!/bin/bash

# CONFIG
SERVER_IP="173.212.213.153"
SERVER_USER="root"

REMOTE_DB="back_backlsh"
REMOTE_DB_USER="back_backlsh"

LOCAL_DB="bl"
LOCAL_DB_USER="root"

DUMP_FILE="$HOME/backup.sql"

echo "================================="
echo "STEP 1 - Creating dump on server"
echo "================================="

ssh ${SERVER_USER}@${SERVER_IP} \
"mysqldump \
--single-transaction \
--routines \
--triggers \
--events \
-u ${REMOTE_DB_USER} \
-p ${REMOTE_DB} > ~/backup.sql"

if [ $? -ne 0 ]; then
    echo "❌ Failed creating dump on server"
    exit 1
fi

echo ""
echo "================================="
echo "STEP 2 - Copy dump to local"
echo "================================="

scp ${SERVER_USER}@${SERVER_IP}:~/backup.sql ${DUMP_FILE}

if [ $? -ne 0 ]; then
    echo "❌ Failed copying dump"
    exit 1
fi

echo ""
echo "Dump downloaded:"
ls -lh ${DUMP_FILE}

echo ""
echo "================================="
echo "STEP 3 - Recreate local DB"
echo "================================="

mariadb -u ${LOCAL_DB_USER} -p -e "
DROP DATABASE IF EXISTS ${LOCAL_DB};
CREATE DATABASE ${LOCAL_DB}
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;
"

echo ""
echo "================================="
echo "STEP 4 - Import dump"
echo "================================="

mariadb -u ${LOCAL_DB_USER} -p ${LOCAL_DB} < ${DUMP_FILE}

if [ $? -eq 0 ]; then
    echo ""
    echo "✅ Production database imported successfully."
else
    echo ""
    echo "❌ Import failed."
fi
