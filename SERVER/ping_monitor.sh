#!/bin/bash

DB_HOST="127.0.0.1"
DB_USER="exam"
DB_PASS="exam"
DB_NAME="Labguard"

for n in {2..20}; do
    CLEAN_IP="192.168.50.$n"
    FULL_IP="192.168.50.$n/24"

    # Query current status matching the exact CIDR key format in DB
    STATUS=$(mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" -D "$DB_NAME" -N -B -e "SELECT status FROM internet WHERE ip='$FULL_IP';" 2>/dev/null)

    # Ping the clean IP address
    if ping -c 1 -W 1 "$CLEAN_IP" > /dev/null 2>&1; then
        # Rule 1: Ping succeeded -> Update to 'online' if not already
        if [ "$STATUS" != "online" ]; then
            mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" -D "$DB_NAME" -e "INSERT INTO internet (ip, status) VALUES ('$FULL_IP', 'online') ON DUPLICATE KEY UPDATE status='online';" 2>/dev/null
            echo "[$(date '+%Y-%m-%d %H:%M:%S')] $CLEAN_IP is ONLINE"
        fi
    else
        # Rule 2: Ping failed -> Ignore if isolated
        if [ "$STATUS" = "isolated" ]; then
            continue
        # Rule 3: Ping failed and NOT isolated -> Update to 'offline'
        elif [ "$STATUS" != "offline" ]; then
            mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" -D "$DB_NAME" -e "INSERT INTO internet (ip, status) VALUES ('$FULL_IP', 'offline') ON DUPLICATE KEY UPDATE status='offline';" 2>/dev/null
            echo "[$(date '+%Y-%m-%d %H:%M:%S')] $CLEAN_IP is OFFLINE"
        fi
    fi
done
