#!/bin/bash

# Script d'arrêt du worker video_converter_fm
# Usage: sudo ./stop-worker.sh

set -e

PID_FILE="/tmp/video-worker.pid"

echo "=== Arrêt du worker video_converter_fm ==="

# Chercher le PID
if [ -f "$PID_FILE" ]; then
    PID=$(cat "$PID_FILE")
    echo "PID trouvé dans le fichier: $PID"
    
    if ps -p "$PID" > /dev/null 2>&1; then
        echo "Arrêt du worker (PID: $PID)..."
        kill "$PID"
        
        # Attendre que le processus se termine
        for i in {1..10}; do
            if ! ps -p "$PID" > /dev/null 2>&1; then
                echo "✅ Worker arrêté avec succès"
                rm -f "$PID_FILE"
                exit 0
            fi
            sleep 1
        done
        
        # Force kill si nécessaire
        echo "⚠️  Le worker ne répond pas, arrêt forcé..."
        kill -9 "$PID"
        rm -f "$PID_FILE"
        echo "✅ Worker arrêté de force"
    else
        echo "⚠️  Le PID $PID n'existe plus"
        rm -f "$PID_FILE"
    fi
else
    echo "Aucun fichier PID trouvé, recherche manuelle..."
fi

# Chercher tous les workers qui tournent
WORKERS=$(ps aux | grep "[w]orker.php" | awk '{print $2}')
if [ -n "$WORKERS" ]; then
    echo "Workers trouvés:"
    ps aux | grep "[w]orker.php"
    echo ""
    for PID in $WORKERS; do
        echo "Arrêt du worker PID: $PID"
        kill "$PID"
    done
    sleep 2
    echo "✅ Tous les workers arrêtés"
else
    echo "ℹ️  Aucun worker en cours d'exécution"
fi

rm -f "$PID_FILE"
