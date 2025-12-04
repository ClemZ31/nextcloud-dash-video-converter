# Guide d'Administration & Installation

## Prérequis

### Sur le serveur Nextcloud
- **Nextcloud 32+** (non compatible avec les versions antérieures)
- **FFmpeg 5.x** ou supérieur
- **PHP 8.1+**
- Accès SSH

### Sur votre machine de développement (Windows)
- **Node.js** (pour `npm install` et `npm run build`)
- **PowerShell** 5.1+
- **SSH** configuré avec clé (pour `scp` sans mot de passe)

---

## Déploiement (Méthode recommandée)

Le script `deploy-clean.ps1` automatise le déploiement depuis Windows vers le serveur. Il compile, archive et upload l'application en une commande.

### 1. Compiler et déployer

Depuis le dossier du projet sur votre machine Windows :

```powershell
# Installer les dépendances et compiler le frontend
npm install
npm run build

# Déployer vers le serveur
powershell -ExecutionPolicy Bypass -File .\deploy-clean.ps1 -RemoteUser "votre_user" -RemoteHost "votre-serveur.com"
```

**Exemple concret :**
```powershell
powershell -ExecutionPolicy Bypass -File .\deploy-clean.ps1 -RemoteUser "jdoe" -RemoteHost "funambules-nc-test.koumbit.net"
```

### 2. Exécuter les commandes sur le serveur

Le script affiche les commandes à exécuter et les **copie automatiquement dans le presse-papier**.

1. Connectez-vous au serveur :
   ```bash
   ssh votre_user@votre-serveur.com
   ```

2. Collez les commandes (Ctrl+Shift+V dans le terminal SSH) et appuyez sur Entrée.

Le script effectue automatiquement :
- Mise en maintenance de Nextcloud
- Backup de l'ancienne version
- Extraction et installation de la nouvelle version
- Activation de l'app
- Rechargement de PHP-FPM et Apache
- Désactivation du mode maintenance

### 3. Configuration initiale du Worker (première installation uniquement)

Si c'est la **première installation**, configurez le service systemd :

```bash
# Copier le fichier de service
sudo cp /var/www/nextcloud/apps/video_converter_fm/bin/systemd/video-worker.service /etc/systemd/system/

# Créer le dossier de logs
sudo mkdir -p /var/log/nextcloud
sudo chown www-data:www-data /var/log/nextcloud

# Activer et démarrer le service
sudo systemctl daemon-reload
sudo systemctl enable video-worker.service
sudo systemctl start video-worker.service
```

---

## 🔄 Mises à jour

Pour les mises à jour ultérieures, répétez simplement les étapes 1 et 2.

**Important :** Si vous modifiez du code PHP (backend, worker), vous devez redémarrer le worker :

```bash
# Arrêter le worker (les jobs en cours seront interrompus)
sudo /var/www/nextcloud/apps/video_converter_fm/bin/stop-worker.sh
# ou
sudo systemctl stop video-worker.service

# Après le déploiement, redémarrer
sudo /var/www/nextcloud/apps/video_converter_fm/bin/start-worker.sh
# ou
sudo systemctl start video-worker.service
```

> ⚠️ **Note :** L'arrêt du worker interrompt les conversions en cours. Elles resteront en statut "En cours" et devront être relancées manuellement ou supprimées.

---

## 🛠️ Dépannage (Troubleshooting)

### Surveiller le worker en temps réel

C'est la commande la plus utile pour voir ce que fait le worker :

```bash
tail -f /var/log/nextcloud/video-worker.log
```

Pour voir les erreurs :
```bash
tail -f /var/log/nextcloud/video-worker-error.log
```

### Vérifier l'état du worker

```bash
sudo systemctl status video-worker.service
```

### Redémarrer le worker

```bash
sudo systemctl restart video-worker.service
```

### Outil de diagnostic

Un script vérifie la santé globale du système :

```bash
cd /var/www/nextcloud/apps/video_converter_fm/bin
sudo -u www-data ./test-jobs.sh
```

### Vérifier FFmpeg

```bash
ffmpeg -version
# L'utilisateur www-data doit pouvoir l'exécuter
sudo -u www-data ffmpeg -version
```

### Nettoyage manuel des jobs

Les jobs ne sont pas purgés automatiquement. Pour supprimer les anciens jobs, connectez-vous à MySQL/MariaDB.

**Trouver les identifiants de la base de données :**
```bash
sudo grep -E "dbuser|dbpassword|dbname" /var/www/nextcloud/config/config.php
```

**Supprimer les jobs de plus de 30 jours :**
```bash
mysql -u <dbuser> -p -e "DELETE FROM oc_video_jobs WHERE status IN ('completed', 'failed') AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);" <dbname>
```

---

## 🗄️ Base de données

### Migration automatique

La table `oc_video_jobs` est créée automatiquement lors de l'activation de l'app (`app:enable`). Nextcloud exécute le fichier de migration `lib/Migration/Version100600Date20250129000000.php`.

### Structure de la table `oc_video_jobs`

| Colonne | Type | Description |
|---------|------|-------------|
| `id` | bigint | Identifiant unique (auto-increment) |
| `file_id` | string | ID du fichier source |
| `user_id` | string | Utilisateur propriétaire du job |
| `status` | string | `pending`, `processing`, `completed`, `failed` |
| `input_path` | string | Chemin du fichier source |
| `output_formats` | text | Paramètres de conversion (JSON) |
| `created_at` | datetime | Date de création |
| `started_at` | datetime | Date de début de traitement |
| `finished_at` | datetime | Date de fin |
| `progress` | integer | Progression (0-100) |
| `retry_count` | integer | Nombre de tentatives |
| `error_message` | text | Message d'erreur si échec |
| `worker_host` | string | Nom du serveur worker |

### Exécuter la migration manuellement (si nécessaire)

Si la table n'existe pas après l'activation :
```bash
sudo -u www-data php /var/www/nextcloud/occ migrations:execute video_converter_fm Version100600Date20250129000000
```

---

## 📁 Structure des fichiers importants

| Fichier/Dossier | Description |
|-----------------|-------------|
| `bin/worker.php` | Worker de traitement des jobs |
| `bin/start-worker.sh` | Script pour démarrer le worker |
| `bin/stop-worker.sh` | Script pour arrêter le worker |
| `bin/test-jobs.sh` | Script de diagnostic |
| `bin/systemd/video-worker.service` | Configuration systemd |
| `/var/log/nextcloud/video-worker.log` | Logs du worker |
| `/var/log/nextcloud/video-worker-error.log` | Erreurs du worker |