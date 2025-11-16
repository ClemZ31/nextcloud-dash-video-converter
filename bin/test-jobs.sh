#!/bin/bash
# Script de test et monitoring pour le système de jobs asynchrones
# Usage: ./bin/test-jobs.sh

NC_PATH="/var/www/nextcloud"
APP_ID="video_converter_fm"

echo "========================================="
echo "  Video Converter - Job Monitoring"
echo "========================================="
echo ""

# 1. Vérifier si le worker tourne
echo "[1] Worker Status"
echo "-----------------"
WORKER_PID=$(pgrep -f "worker.php")
if [ -n "$WORKER_PID" ]; then
    echo "✅ Worker is running (PID: $WORKER_PID)"
    ps aux | grep worker.php | grep -v grep
else
    echo "❌ Worker is NOT running"
    echo "   Start it with: nohup php bin/worker.php >> /var/log/nextcloud/video-worker.log 2>&1 &"
fi
echo ""

# 2. Vérifier les tables
echo "[2] Database Tables"
echo "-------------------"
sudo -u www-data php $NC_PATH/occ db:query "SELECT COUNT(*) as total FROM oc_video_jobs" 2>/dev/null
if [ $? -eq 0 ]; then
    echo "✅ Table 'oc_video_jobs' exists"
else
    echo "❌ Table 'oc_video_jobs' not found"
    echo "   Run migration: sudo -u www-data php $NC_PATH/occ migrations:execute $APP_ID Version100600Date20250129000000"
fi
echo ""

# 3. Lister les jobs récents
echo "[3] Recent Jobs"
echo "---------------"
sudo -u www-data php $NC_PATH/occ db:query "
    SELECT id, user_id, status, progress, created_at, error_message 
    FROM oc_video_jobs 
    ORDER BY created_at DESC 
    LIMIT 10
" 2>/dev/null || echo "❌ Failed to query jobs"
echo ""

# 4. Statistiques par statut
echo "[4] Job Statistics"
echo "------------------"
sudo -u www-data php $NC_PATH/occ db:query "
    SELECT status, COUNT(*) as count 
    FROM oc_video_jobs 
    GROUP BY status
" 2>/dev/null || echo "❌ Failed to get statistics"
echo ""

# 5. Jobs en attente
echo "[5] Pending Jobs"
echo "----------------"
PENDING=$(sudo -u www-data php $NC_PATH/occ db:query "SELECT COUNT(*) FROM oc_video_jobs WHERE status='pending'" 2>/dev/null | tail -n 1)
echo "Pending jobs: $PENDING"
echo ""

# 6. Logs récents
echo "[6] Recent Worker Logs"
echo "----------------------"
if [ -f /var/log/nextcloud/video-worker.log ]; then
    tail -n 20 /var/log/nextcloud/video-worker.log
else
    echo "⚠️  No worker log file found at /var/log/nextcloud/video-worker.log"
fi
echo ""

# 7. Commandes utiles
echo "[7] Useful Commands"
echo "-------------------"
echo "Start worker:     nohup php $NC_PATH/apps/$APP_ID/bin/worker.php >> /var/log/nextcloud/video-worker.log 2>&1 &"
echo "Stop worker:      pkill -f worker.php"
echo "View logs:        tail -f /var/log/nextcloud/video-worker.log"
echo "Clear old jobs:   sudo -u www-data php $NC_PATH/occ db:query \"DELETE FROM oc_video_jobs WHERE status IN ('completed','failed') AND finished_at < NOW() - INTERVAL 7 DAY\""
echo ""
