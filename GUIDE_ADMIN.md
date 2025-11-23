# Guide d'Administration & Installation

## 📦 Installation

### 1. Installation de l'application
Cloner le dépôt dans le dossier `apps` de votre instance Nextcloud :

```bash
cd /var/www/nextcloud/apps
git clone [URL_DU_REPO] video_converter_fm
chown -R www-data:www-data video_converter_fm
```

### 2. Activation
Activer l'application via la ligne de commande `occ`. Cela exécutera automatiquement les migrations de base de données pour créer la table `video_jobs`.

```bash
sudo -u www-data php /var/www/nextcloud/occ app:enable video_converter_fm
```

### 3. Configuration du Worker (Systemd)
Pour que les conversions ne bloquent pas l'interface, elles sont traitées en arrière-plan par un script PHP dédié. Vous devez configurer un service Systemd pour que ce script tourne en permanence.

1.  Copier le fichier de service fourni :
```bash
sudo cp /var/www/nextcloud/apps/video_converter_fm/bin/systemd/video-worker.service /etc/systemd/system/
```

2.  (Optionnel) Éditer le fichier si vos chemins sont différents de `/var/www/nextcloud` :
```bash
sudo nano /etc/systemd/system/video-worker.service
```

3.  Activer et démarrer le service :
```bash
sudo systemctl daemon-reload
sudo systemctl enable video-worker.service
sudo systemctl start video-worker.service
```

---

## 🛠️ Dépannage (Troubleshooting)

### Vérifier l'état du worker
Pour voir si le worker tourne correctement et traite les tâches, utilisez `systemctl` ou `journalctl`.

**Voir le statut du service :**
```bash
sudo systemctl status video-worker.service
```

**Voir les logs en temps réel :**
```bash
# Logs système
journalctl -u video-worker.service -f

# Logs applicatifs (si configurés dans le service)
tail -f /var/log/nextcloud/video-worker.log
```

### Outil de diagnostic
Un script est fourni pour vérifier la santé du système de conversion. Il vérifie le processus, la base de données et les jobs en attente.

```bash
cd /var/www/nextcloud/apps/video_converter_fm/bin
sudo ./test-jobs.sh
```

### Nettoyage manuel
Les jobs terminés ou échoués depuis plus de 7 jours sont automatiquement purgés par le mapper. Si nécessaire, vous pouvez forcer un nettoyage via SQL :

```bash
sudo -u www-data php /var/www/nextcloud/occ db:query "DELETE FROM oc_video_jobs WHERE created_at < NOW() - INTERVAL 7 DAY"
```