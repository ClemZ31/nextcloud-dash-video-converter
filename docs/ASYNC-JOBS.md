# Guide : Système de Jobs Asynchrones

## Vue d'ensemble

Le système de conversion vidéo fonctionne maintenant de manière **asynchrone** :
1. L'utilisateur demande une conversion (via le menu contextuel)
2. Un **job** est créé dans la base de données avec le statut `pending`
3. Un **worker** tourne en arrière-plan et traite les jobs un par un
4. Le statut du job est mis à jour (`processing` → `completed` ou `failed`)

## Installation et démarrage

### 1. Déployer l'application

```bash
# Depuis Windows (après build)
npm run build
.\deploy-clean.ps1 -RemoteUser cdeffes -RemoteHost funambules-nc-test.koumbit.net
```

### 2. Appliquer la migration SQL

Sur le serveur :
```bash
sudo -u www-data php /var/www/nextcloud/occ migrations:execute video_converter_fm Version100600Date20250129000000
```

Vérifier que la table a été créée :
```bash
sudo -u www-data php /var/www/nextcloud/occ db:query "SHOW TABLES LIKE 'oc_video_jobs'"
```

### 3. Démarrer le worker

#### Option A : Mode manuel (pour tests)

* IMPORTANT : Toujours se placer dans le dossier de l'app d'abord !**

```bash
# Méthode recommandée : utiliser le script de démarrage
sudo bash /var/www/nextcloud/apps/video_converter_fm/bin/start-worker.sh

# OU manuellement (avec chemins absolus)
sudo -u www-data nohup php /var/www/nextcloud/apps/video_converter_fm/bin/worker.php >> /var/log/nextcloud/video-worker.log 2>&1 &
echo $! | sudo tee /tmp/video-worker.pid

# OU si vous êtes déjà dans le dossier de l'app
cd /var/www/nextcloud/apps/video_converter_fm
sudo -u www-data nohup php bin/worker.php >> /var/log/nextcloud/video-worker.log 2>&1 &
echo $! | sudo tee /tmp/video-worker.pid

# Vérifier qu'il tourne
ps aux | grep worker.php | grep -v grep

# Voir les logs en temps réel
tail -f /var/log/nextcloud/video-worker.log

# Arrêter le worker
sudo bash bin/stop-worker.sh
# OU manuellement : sudo kill $(cat /tmp/video-worker.pid)
```

#### Option B : Mode systemd (production recommandée)
```bash
# Copier le fichier service
sudo cp bin/systemd/video-worker.service /etc/systemd/system/

# Recharger systemd
sudo systemctl daemon-reload

# Activer le service (démarre automatiquement au boot)
sudo systemctl enable video-worker.service

# Démarrer le service
sudo systemctl start video-worker.service

# Vérifier le statut
sudo systemctl status video-worker.service
```

## 🔍 Vérifier que tout fonctionne

### Méthode 1 : Script de monitoring automatisé

```bash
cd /var/www/nextcloud/apps/video_converter_fm
chmod +x bin/test-jobs.sh
./bin/test-jobs.sh
```

Ce script affiche :
- Si le worker tourne (PID)
- Si la table existe
- Liste des 10 derniers jobs
- Statistiques par statut
- Logs récents

### Méthode 2 : Vérifications manuelles

#### a) Worker actif ?
```bash
ps aux | grep worker.php | grep -v grep
```
Si une ligne apparaît avec un PID, le worker tourne. Sinon :
```bash
nohup php bin/worker.php >> /var/log/nextcloud/video-worker.log 2>&1 &
```

#### b) Un job vient d'être créé ?
```bash
sudo -u www-data php /var/www/nextcloud/occ db:query "
    SELECT id, user_id, status, progress, created_at 
    FROM oc_video_jobs 
    ORDER BY created_at DESC 
    LIMIT 5
"
```

Chercher un job avec :
- `status = 'pending'` → En attente
- `status = 'processing'` → En cours
- `status = 'completed'` → Terminé
- `status = 'failed'` → Échec

#### c) Logs du worker
```bash
tail -f /var/log/nextcloud/video-worker.log
```

Tu devrais voir :
```
Video conversion worker started
Processing job #123: /files/user/video.mp4
Job #123 completed successfully
```

#### d) Tester la création d'un job (API)

Depuis Nextcloud (via Files) :
1. Clic droit sur une vidéo → "Convert into"
2. Choisir le format
3. → Un job est créé (réponse JSON avec `job_id`)

Puis vérifie dans la base :
```bash
sudo -u www-data php /var/www/nextcloud/occ db:query "
    SELECT * FROM oc_video_jobs WHERE id = <job_id>
"
```

## API REST pour surveiller les jobs

### Lister tous les jobs de l'utilisateur
```bash
curl -u user:password "https://funambules-nc-test.koumbit.net/apps/video_converter_fm/api/jobs"
```

### Récupérer le statut d'un job spécifique
```bash
curl -u user:password "https://funambules-nc-test.koumbit.net/apps/video_converter_fm/api/jobs/123"
```

Réponse JSON :
```json
{
  "id": 123,
  "status": "completed",
  "progress": 100,
  "created_at": "2025-11-02 14:30:00",
  "started_at": "2025-11-02 14:30:05",
  "finished_at": "2025-11-02 14:35:12",
  "error_message": null
}
```

## Dépannage

### Le worker ne démarre pas
```bash
# Vérifier les erreurs
php bin/worker.php
# Si erreur, corriger puis relancer en background
```

### Les jobs restent en "pending"
1. Vérifier que le worker tourne : `ps aux | grep worker.php`
2. Vérifier les logs : `tail -f /var/log/nextcloud/video-worker.log`
3. Redémarrer le worker :
   ```bash
   pkill -f worker.php
   nohup php bin/worker.php >> /var/log/nextcloud/video-worker.log 2>&1 &
   ```

### Job en "failed"
Consulter `error_message` :
```bash
sudo -u www-data php /var/www/nextcloud/occ db:query "
    SELECT id, error_message 
    FROM oc_video_jobs 
    WHERE status='failed' 
    ORDER BY created_at DESC 
    LIMIT 5
"
```

Causes fréquentes :
- FFmpeg non installé ou introuvable
- Fichier source supprimé
- Permissions incorrectes
- Espace disque insuffisant

### Nettoyer les vieux jobs
```bash
sudo -u www-data php /var/www/nextcloud/occ db:query "
    DELETE FROM oc_video_jobs 
    WHERE status IN ('completed','failed') 
    AND finished_at < NOW() - INTERVAL 7 DAY
"
```

## Commandes utiles

| Action | Commande |
|--------|----------|
| Démarrer worker | `nohup php bin/worker.php >> /var/log/nextcloud/video-worker.log 2>&1 &` |
| Arrêter worker | `pkill -f worker.php` |
| Voir logs | `tail -f /var/log/nextcloud/video-worker.log` |
| Compter jobs pending | `sudo -u www-data php /var/www/nextcloud/occ db:query "SELECT COUNT(*) FROM oc_video_jobs WHERE status='pending'"` |
| Réinitialiser un job | `sudo -u www-data php /var/www/nextcloud/occ db:query "UPDATE oc_video_jobs SET status='pending', retry_count=0 WHERE id=123"` |

## Checklist de validation

-  Migration SQL appliquée (table `oc_video_jobs` existe)
-  Worker démarré et visible dans `ps aux`
-  Logs du worker accessibles et actifs
-  Création d'un job de test réussie (via UI Files)
-  Job passe de `pending` → `processing` → `completed`
-  Fichier converti apparaît dans Nextcloud Files
-  API `/api/jobs` retourne la liste des jobs
-  API `/api/jobs/{id}` retourne le détail d'un job
