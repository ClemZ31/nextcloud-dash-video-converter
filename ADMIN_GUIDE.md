# Administration & Installation Guide

## 📦 Installation

### 1. App Installation
Clone the repository into your Nextcloud instance's `apps` directory:

```bash
cd /var/www/nextcloud/apps
git clone [URL_DU_REPO] video_converter_fm
chown -R www-data:www-data video_converter_fm
```

### 2. Activation
Enable the app via the `occ` command line. This will automatically execute database migrations to create the `video_jobs` table.

```bash
sudo -u www-data php /var/www/nextcloud/occ app:enable video_converter_fm
```

### 3. Worker Configuration (Systemd)
To ensure conversions do not block the interface, they are processed in the background by a dedicated PHP script. You must configure a Systemd service to keep this script running.

1.  Copy the provided service file:
```bash
sudo cp /var/www/nextcloud/apps/video_converter_fm/bin/systemd/video-worker.service /etc/systemd/system/
```

2.  (Optional) Edit the file if your paths differ from `/var/www/nextcloud`:
```bash
sudo nano /etc/systemd/system/video-worker.service
```

3.  Enable and start the service:
```bash
sudo systemctl daemon-reload
sudo systemctl enable video-worker.service
sudo systemctl start video-worker.service
```

---

## 🛠️ Troubleshooting

### Check Worker Status
To verify if the worker is running correctly and processing tasks, use `systemctl` or `journalctl`.

**View service status:**
```bash
sudo systemctl status video-worker.service
```

**View real-time logs:**
```bash
# System logs
journalctl -u video-worker.service -f

# Application logs (if configured in the service file)
tail -f /var/log/nextcloud/video-worker.log
```

### Diagnostic Tool
A script is provided to check the health of the conversion system. It checks the worker process, database table, and pending jobs.

```bash
cd /var/www/nextcloud/apps/video_converter_fm/bin
sudo ./test-jobs.sh
```

### Manual Cleanup
Jobs completed or failed for more than 7 days are automatically purged by the mapper. If necessary, you can force a cleanup via SQL:

```bash
sudo -u www-data php /var/www/nextcloud/occ db:query "DELETE FROM oc_video_jobs WHERE created_at < NOW() - INTERVAL 7 DAY"
```