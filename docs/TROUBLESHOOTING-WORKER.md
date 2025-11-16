# 🐛 Dépannage Worker - Problèmes courants

## Problème 1 : "Could not open input file: bin/worker.php"

### Cause
Vous n'étiez pas dans le bon répertoire quand vous avez lancé le worker.

### Solution
**Utilisez toujours des chemins absolus !**

```bash
# MAUVAIS (chemin relatif sans être dans le bon dossier)
sudo -u www-data nohup php bin/worker.php >> /var/log/nextcloud/video-worker.log 2>&1 &

# BON (chemin absolu, fonctionne depuis n'importe où)
sudo -u www-data nohup php /var/www/nextcloud/apps/video_converter_fm/bin/worker.php >> /var/log/nextcloud/video-worker.log 2>&1 &

# BON (chemin relatif mais vous êtes dans le bon dossier)
cd /var/www/nextcloud/apps/video_converter_fm
sudo -u www-data nohup php bin/worker.php >> /var/log/nextcloud/video-worker.log 2>&1 &
```

**Ou utilisez le script de démarrage qui gère tout ça automatiquement :**

```bash
sudo bash /var/www/nextcloud/apps/video_converter_fm/bin/start-worker.sh
```

---

## Problème 2 : "Permission denied" sur /var/log/nextcloud/video-worker.log

### Cause
Le fichier de log n'existe pas ou n'appartient pas à l'utilisateur qui lance la commande.

### Solution 1 : Créer le fichier avec les bonnes permissions

```bash
# Créer le répertoire et le fichier
sudo mkdir -p /var/log/nextcloud
sudo touch /var/log/nextcloud/video-worker.log
sudo chown www-data:www-data /var/log/nextcloud/video-worker.log
sudo chmod 644 /var/log/nextcloud/video-worker.log

# Maintenant lancer le worker
cd /var/www/nextcloud/apps/video_converter_fm
sudo -u www-data nohup php bin/worker.php >> /var/log/nextcloud/video-worker.log 2>&1 &
```

### Solution 2 : Utiliser le script de démarrage

Le script `start-worker.sh` crée automatiquement le fichier de log avec les bonnes permissions :

```bash
sudo bash /var/www/nextcloud/apps/video_converter_fm/bin/start-worker.sh
```

### Solution 3 : Logger ailleurs temporairement

Si vous ne pouvez pas modifier `/var/log/nextcloud/`, loggez dans votre home :

```bash
cd /var/www/nextcloud/apps/video_converter_fm
sudo -u www-data nohup php bin/worker.php >> ~/video-worker.log 2>&1 &
tail -f ~/video-worker.log
```

---

## Problème 3 : Le worker "tourne à l'infini"

### Ce n'est PAS un problème !

C'est le **comportement normal** du worker. Il tourne en continu dans une boucle infinie pour traiter les jobs.

```php
// Dans bin/worker.php
while (true) {
    // Chercher un job pending
    // L'exécuter
    // Attendre 5 secondes
    // Recommencer
}
```

### Comment l'arrêter proprement ?

```bash
# Méthode 1 : Script d'arrêt
sudo bash /var/www/nextcloud/apps/video_converter_fm/bin/stop-worker.sh

# Méthode 2 : Manuellement avec le PID
sudo kill $(cat /tmp/video-worker.pid)

# Méthode 3 : Si vous êtes en foreground
Ctrl+C
```

### Comment le lancer en background ?

```bash
# Le worker doit tourner en background pour ne pas bloquer le terminal
cd /var/www/nextcloud/apps/video_converter_fm
sudo -u www-data nohup php bin/worker.php >> /var/log/nextcloud/video-worker.log 2>&1 &

# Vérifier qu'il tourne
ps aux | grep worker.php | grep -v grep

# Voir les logs en temps réel
tail -f /var/log/nextcloud/video-worker.log
```

---

## Problème 4 : Le worker ne traite pas les jobs

### Diagnostic

```bash
# 1. Le worker tourne-t-il ?
ps aux | grep worker.php | grep -v grep

# 2. Y a-t-il des jobs en attente ?
sudo -u www-data php /var/www/nextcloud/occ db:query "SELECT * FROM oc_video_jobs WHERE status='pending'"

# 3. Que disent les logs ?
tail -f /var/log/nextcloud/video-worker.log

# 4. Y a-t-il des erreurs PHP ?
sudo tail -f /var/log/apache2/error.log
sudo tail -f /var/log/php8.2-fpm.log
```

### Solutions possibles

1. **Le worker n'est pas lancé**
   ```bash
   sudo bash /var/www/nextcloud/apps/video_converter_fm/bin/start-worker.sh
   ```

2. **Erreurs dans les logs**
   - Vérifier les permissions sur les fichiers
   - Vérifier que FFmpeg est installé : `which ffmpeg`
   - Vérifier que l'utilisateur www-data peut écrire dans les dossiers de destination

3. **Jobs bloqués en 'processing'**
   ```bash
   # Réinitialiser manuellement
   sudo -u www-data php /var/www/nextcloud/occ db:query "UPDATE oc_video_jobs SET status='pending' WHERE status='processing'"
   ```

---

## Commandes utiles pour surveiller le worker

```bash
# Statut complet avec le script de monitoring
cd /var/www/nextcloud/apps/video_converter_fm
sudo bash bin/test-jobs.sh

# Voir les logs en temps réel
tail -f /var/log/nextcloud/video-worker.log

# Voir combien de jobs sont en attente
sudo -u www-data php /var/www/nextcloud/occ db:query "SELECT status, COUNT(*) as count FROM oc_video_jobs GROUP BY status"

# Voir les 5 derniers jobs
sudo -u www-data php /var/www/nextcloud/occ db:query "SELECT id, status, progress, created_at FROM oc_video_jobs ORDER BY created_at DESC LIMIT 5"

# Redémarrer le worker
sudo bash bin/stop-worker.sh
sudo bash bin/start-worker.sh
```

---

## Checklist de démarrage du worker

- [ ] Je suis connecté au serveur en SSH
- [ ] Je me place dans le dossier de l'app : `cd /var/www/nextcloud/apps/video_converter_fm`
- [ ] Le fichier `bin/worker.php` existe : `ls -la bin/worker.php`
- [ ] Le répertoire de logs existe : `sudo mkdir -p /var/log/nextcloud`
- [ ] Je lance le worker : `sudo bash bin/start-worker.sh`
- [ ] Je vérifie qu'il tourne : `ps aux | grep worker.php | grep -v grep`
- [ ] Je surveille les logs : `tail -f /var/log/nextcloud/video-worker.log`
- [ ] Je teste une conversion dans l'interface Nextcloud
- [ ] Je vérifie que le job est traité dans les logs

---

## En cas de doute

**Toujours utiliser les scripts de démarrage/arrêt plutôt que les commandes manuelles :**

```bash
# Démarrage
sudo bash /var/www/nextcloud/apps/video_converter_fm/bin/start-worker.sh

# Arrêt
sudo bash /var/www/nextcloud/apps/video_converter_fm/bin/stop-worker.sh

# Monitoring
sudo bash /var/www/nextcloud/apps/video_converter_fm/bin/test-jobs.sh
```

Ces scripts gèrent automatiquement :
- Les permissions sur les logs
- La vérification que le worker n'est pas déjà lancé
- La sauvegarde du PID pour l'arrêt
- L'affichage du statut et des logs
