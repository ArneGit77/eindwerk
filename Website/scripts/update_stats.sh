#!/bin/bash
#
# SportFlow - System stats verzamelaar
#
# Dit script meet:
#   - CPU temperatuur
#   - CPU gebruik (%)
#   - RAM gebruik (MB)
#   - Vrije schijfruimte (GB)
#   - Database grootte (MB)
#   - Totaal aantal trainingen
#   - Uptime (dagen)
#
# En stopt alles in de system_stats tabel.
# Wordt automatisch elke 5 minuten gestart door cron.

# ➤ Database instellingen — pas aan indien nodig
DB_USER="root"
DB_PASS=""
DB_NAME="sportflow"

# ─── Metingen ──────────────────────────────────────────

# CPU temperatuur in graden Celsius (Raspberry Pi specifiek)
CPU_TEMP=$(vcgencmd measure_temp | grep -oE '[0-9]+\.[0-9]+')

# CPU gebruik (gemiddeld over alle cores, als percentage geheel getal)
CPU_USAGE=$(top -bn1 | grep "Cpu(s)" | awk '{print 100 - $8}' | awk '{printf "%d", $1}')

# RAM gebruik in MB
RAM_USAGE=$(free -m | awk '/^Mem:/ {print $3}')

# Vrije schijfruimte op root partitie in GB
DISK_FREE=$(df -BG / | awk 'NR==2 {gsub("G",""); print $4}')

# Database grootte in MB
DB_SIZE=$(mysql -u"$DB_USER" -p"$DB_PASS" -N -B -e \
    "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) FROM information_schema.tables WHERE table_schema = '$DB_NAME';")

# Totaal aantal trainingen
TOTAL_TRAININGS=$(mysql -u"$DB_USER" -p"$DB_PASS" -N -B -e \
    "SELECT COUNT(*) FROM trainings;" "$DB_NAME")

# Uptime in dagen (afgerond)
UPTIME_DAYS=$(awk '{printf "%d", $1/86400}' /proc/uptime)

# Lege waardes vervangen door 0 om SQL-fouten te voorkomen
CPU_TEMP=${CPU_TEMP:-0}
CPU_USAGE=${CPU_USAGE:-0}
RAM_USAGE=${RAM_USAGE:-0}
DISK_FREE=${DISK_FREE:-0}
DB_SIZE=${DB_SIZE:-0}
TOTAL_TRAININGS=${TOTAL_TRAININGS:-0}
UPTIME_DAYS=${UPTIME_DAYS:-0}

# ─── Wegschrijven naar database ─────────────────────────
mysql -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" <<SQL
INSERT INTO system_stats
    (cpu_temp, cpu_usage, ram_usage_mb, disk_free_gb, db_size_mb, total_trainings, uptime_days)
VALUES
    ($CPU_TEMP, $CPU_USAGE, $RAM_USAGE, $DISK_FREE, $DB_SIZE, $TOTAL_TRAININGS, $UPTIME_DAYS);
SQL

echo "[$(date)] Stats opgeslagen: temp=$CPU_TEMP°C, cpu=$CPU_USAGE%, ram=${RAM_USAGE}MB, disk=${DISK_FREE}GB"
