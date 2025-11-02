# Déploiement de développement sur « nc-test »

Ce guide explique comment construire et déployer rapidement l’app `video_converter_fm` sur votre instance Nextcloud de test (nc-test) depuis Windows/PowerShell.

## 🎯 Objectifs
- Builder les assets (Vite) en local
- Uploader un paquet propre vers nc-test
- Remplacer proprement l’app côté serveur et la réactiver
- Boucle de développement rapide

---

## ✅ Prérequis
- Windows 10/11 avec PowerShell 5.1+
- Node.js 20 et npm 10
- Client OpenSSH (ssh/scp dans le PATH)
- Accès SSH à nc-test avec un utilisateur pouvant écrire dans le répertoire `apps/` de Nextcloud (ou via sudo/chown)
- Chemin Nextcloud (par défaut dans ce guide) : `/var/www/nextcloud`

Variables courantes:
- APP_ID: `video_converter_fm`
- NC_PATH: `/var/www/nextcloud`
- APPS_DIR: `/var/www/nextcloud/apps`

---

## 🚀 Option A — Déploiement automatisé (recommandé)
Le dépôt fournit `deploy-clean.ps1` qui crée un ZIP à structure propre puis l’upload via `scp`.

1) Installer les dépendances et builder:
```powershell
npm ci
npm run build
```

2) Lancer le script (adaptez host/user):
```powershell
# Exemple
./deploy-clean.ps1 -RemoteHost nc-test.example.org -RemoteUser nextcloud
```
> Si votre PowerShell bloque l’exécution des scripts, lancez « PowerShell en tant qu’Admin » puis: `Set-ExecutionPolicy -Scope CurrentUser RemoteSigned`

3) Côté serveur, exécuter les commandes indiquées par le script. L’archive créée contient désormais un dossier racine `video_converter_fm` directement prêt à être extrait dans `apps/`.

---

## 🔧 Option B — Déploiement manuel pas-à-pas

### 1) Build local (Windows)
```powershell
npm ci
npm run build
```

### 2) Créer un ZIP propre (racine contenant l’app)
Vous pouvez réutiliser la logique de `deploy-clean.ps1`. Si vous préférez à la main:
```powershell
# Crée un dossier temporaire
Remove-Item -Recurse -Force .\video_converter_fm -ErrorAction SilentlyContinue
New-Item -ItemType Directory -Path .\video_converter_fm | Out-Null

# Copie des fichiers nécessaires
$include = @('appinfo','css','img','js','lib','templates','vite.config.js','package.json','README.md','CHANGELOG.md','COPYING','.gitignore')
foreach($i in $include){ Copy-Item $i -Destination .\video_converter_fm -Recurse -Force }

# Archive datée
$ts = Get-Date -Format "yyyyMMdd_HHmmss"
$zip = "video_converter_fm_$ts.zip"
Compress-Archive -Path .\video_converter_fm -DestinationPath $zip -Force
```

### 3) Upload vers le serveur
```powershell
scp $zip nextcloud@nc-test.example.org:/home/nextcloud/
```

### 4) Remplacement côté serveur (SSH)
```bash
# 4.1 Connexion
ssh nextcloud@nc-test.example.org

# 4.2 Variables de confort
APP_ID=video_converter_fm
NC_PATH=/var/www/nextcloud
APPS_DIR=$NC_PATH/apps
ZIP=~/video_converter_fm_*.zip

# 4.3 Préparation
cd "$NC_PATH"
sudo -u www-data php occ maintenance:mode --on

# 4.4 Sauvegarde et suppression de l’ancienne version (si présente)
if [ -d "$APPS_DIR/$APP_ID" ]; then
  tar -czf ~/${APP_ID}_backup_$(date +%F_%H%M%S).tar.gz -C "$APPS_DIR" "$APP_ID"
  rm -rf "$APPS_DIR/$APP_ID"
fi

# 4.5 Décompression du nouveau paquet
cd /home/nextcloud
unzip -o $ZIP -d "$APPS_DIR"

# 4.6 Droits fichiers
sudo chown -R www-data:www-data "$APPS_DIR/$APP_ID"

# 4.7 Activation/maj de l’app
cd "$NC_PATH"
sudo -u www-data php occ app:disable $APP_ID || true
sudo -u www-data php occ app:enable $APP_ID
# ou: sudo -u www-data php occ app:update $APP_ID

# 4.8 Sortie de maintenance
sudo -u www-data php occ maintenance:mode --off

# 4.9 Vérification
sudo -u www-data php occ app:list | grep $APP_ID || true
```

> Note: si vous changez le script pour produire directement un dossier racine `video_converter_fm` dans le ZIP, la phase 4.5 pourra simplement dézipper dans `apps/` sans renommage.

---

## 🔁 Boucle de dev rapide
- Editer le code localement
- Rebuild rapide: `npm run build` ou `npm run watch`
- Relancer `deploy-clean.ps1` (plus rapide qu’un ZIP manuel)
- Rafraîchir l’onglet Nextcloud

Astuces:
- Pour valider côté UI sans cache agressif, ouvrez l’onglet devtools et cochez « Disable cache ».
- Si l’app ne s’affiche pas: vérifiez que le dossier serveur s’appelle exactement `video_converter_fm` et que `appinfo/info.xml` contient `<id>video_converter_fm</id>`.

---

## 🧰 Dépannage
- « appinfo file cannot be read » → structure du ZIP incorrecte (pas de dossier racine `video_converter_fm`).
- L’action « Convert into » n’apparaît pas dans Fichiers → vérifier que `js/conversion.js` est bien injecté sur les pages Files (voir section "Améliorations" du code review pour la meilleure façon en NC 32).
- CSS/JS pas mis à jour → reconstruire (`npm run build`), redeployer, vider le cache navigateur.
- Droits fichiers → `chown -R www-data:www-data /var/www/nextcloud/apps/video_converter_fm`.

---

## ✅ Check-list finale
- [ ] `apps/video_converter_fm` existe côté serveur et correspond à l’ID de l’app
- [ ] L’app est `enabled` (`occ app:list`)
- [ ] La navigation « Conversions » apparaît et la SPA charge
- [ ] L’action « Convert into » est disponible dans Fichiers
