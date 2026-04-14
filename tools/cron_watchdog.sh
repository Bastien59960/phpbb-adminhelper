#!/bin/bash
# cron_watchdog.sh
# Watchdog système pour le cron_lock phpBB.
#
# Rôle : Si le cron_lock phpBB est orphelin (processus mort sans libérer le lock),
#         ce script le détecte et le réinitialise avant que le timeout de 3600s ne
#         soit atteint. Limite le blocage à MAX_LOCK_AGE_SECONDS secondes (défaut: 300).
#
# Installation recommandée (crontab root ou www-data):
#   */5 * * * * bash /var/www/forum/ext/bastien59960/adminhelper/tools/cron_watchdog.sh
#
# Logs : /var/log/phpbb_cron_watchdog.log

FORUM_ROOT="/var/www/forum"
DB_USER="forum"
DB_PASS="cO4Q3b67qf0s1viJfcqr"
DB_NAME="forum"
LOG_FILE="/var/log/phpbb_cron_watchdog.log"
MAX_LOCK_AGE_SECONDS=600  # 10 min — plus long que geo_async (~250s), plus court que le timeout phpBB (3600s)

MYSQL="mysql -u${DB_USER} -p${DB_PASS} ${DB_NAME} --silent --skip-column-names"

# Lire la valeur du lock
LOCK_VALUE=$($MYSQL -e "SELECT config_value FROM phpbb3_config WHERE config_name='cron_lock';" 2>/dev/null)

if [ -z "$LOCK_VALUE" ] || [ "$LOCK_VALUE" = "0" ]; then
    exit 0  # Pas de lock actif
fi

# Extraire le timestamp du lock (format: "timestamp session_id")
LOCK_TS=$(echo "$LOCK_VALUE" | awk '{print $1}')
NOW=$(date +%s)
AGE=$((NOW - LOCK_TS))

if [ "$AGE" -gt "$MAX_LOCK_AGE_SECONDS" ]; then
    # Lock trop ancien : réinitialiser
    $MYSQL -e "UPDATE phpbb3_config SET config_value='0' WHERE config_name='cron_lock';" 2>/dev/null
    echo "$(date '+%Y-%m-%d %H:%M:%S') [WATCHDOG] Lock orphelin de ${AGE}s réinitialisé. Valeur: $LOCK_VALUE" >> "$LOG_FILE"
fi
