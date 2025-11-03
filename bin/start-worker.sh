#!/bin/bash

# Script de démarrage du worker video_converter_fm
# Usage: sudo ./start-worker.sh

set -e

# Configuration
APP_PATH="/var/www/nextcloud/apps/video_converter_fm"
LOG_DIR="/var/log/nextcloud"
LOG_FILE="$LOG_DIR/video-worker.log"
PID_FILE="/tmp/video-worker.pid"

echo "=== Démarrage du worker video_converter_fm ==="

# Vérifier que le script existe
if [ ! -f "$APP_PATH/bin/worker.php" ]; then
    echo "❌ ERREUR: $APP_PATH/bin/worker.php n'existe pas!"
    echo "Contenu de $APP_PATH/bin/:"
    ls -la "$APP_PATH/bin/"
    exit 1
fi

# Créer le répertoire de logs s'il n'existe pas
if [ ! -d "$LOG_DIR" ]; then
    echo "Création du répertoire de logs: $LOG_DIR"
    mkdir -p "$LOG_DIR"
fi

# S'assurer que www-data peut écrire dans le fichier de log
touch "$LOG_FILE"
chown www-data:www-data "$LOG_FILE"
chmod 644 "$LOG_FILE"

# Arrêter l'ancien worker s'il existe
if [ -f "$PID_FILE" ]; then
    OLD_PID=$(cat "$PID_FILE")
    if ps -p "$OLD_PID" > /dev/null 2>&1; then
        echo "Arrêt de l'ancien worker (PID: $OLD_PID)..."
        kill "$OLD_PID"
        sleep 2
    fi
    rm -f "$PID_FILE"
fi

# Vérifier qu'aucun worker ne tourne déjà
EXISTING_WORKER=$(ps aux | grep "[w]orker.php" | awk '{print $2}')
if [ -n "$EXISTING_WORKER" ]; then
    echo "⚠️  Un worker tourne déjà (PID: $EXISTING_WORKER)"
    echo "Voulez-vous l'arrêter? (y/n)"
    read -r response
    if [ "$response" = "y" ]; then
        kill "$EXISTING_WORKER"
        sleep 2
    else
        echo "Annulation."
        exit 0
    fi
fi

# Démarrer le worker
echo "Démarrage du worker..."
sudo -u www-data nohup php "$APP_PATH/bin/worker.php" >> "$LOG_FILE" 2>&1 &
WORKER_PID=$!

# Sauvegarder le PID
echo "$WORKER_PID" > "$PID_FILE"

# Attendre un peu et vérifier que le worker tourne
sleep 2
if ps -p "$WORKER_PID" > /dev/null 2>&1; then
    echo "✅ Worker démarré avec succès!"
    echo "   PID: $WORKER_PID"
    echo "   Logs: $LOG_FILE"
    echo ""
    echo "Commandes utiles:"
    echo "  - Voir les logs: tail -f $LOG_FILE"
    echo "  - Vérifier le statut: ps -p $WORKER_PID"
    echo "  - Arrêter: kill $WORKER_PID"
    echo ""
    echo "Premières lignes du log:"
    tail -n 5 "$LOG_FILE"
else
    echo "❌ ERREUR: Le worker n'a pas démarré correctement"
    echo "Dernières lignes du log:"
    tail -n 20 "$LOG_FILE"
    rm -f "$PID_FILE"
    exit 1
fi
