#!/usr/bin/env pwsh
# Script de déploiement avec structure propre
# Usage: .\deploy-clean.ps1

param(
    [string]$RemoteUser = "cdeffes",
    [string]$RemoteHost = "funambules-nc-test.koumbit.net"
)

$ErrorActionPreference = "Stop"

Write-Host "================================================" -ForegroundColor Cyan
Write-Host "   Deploiement Video Converter (Clean)" -ForegroundColor Cyan
Write-Host "================================================" -ForegroundColor Cyan
Write-Host ""

# Étape 1: Création d'une structure propre
Write-Host "[1/4] Creation de la structure temporaire..." -ForegroundColor Yellow

$timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
$tempDir = "video_converter_fm_temp"
$archiveName = "video_converter_fm_${timestamp}.zip"

# Nettoyer le dossier temp s'il existe
if (Test-Path $tempDir) {
    Remove-Item $tempDir -Recurse -Force
}

# Créer la structure avec le nom du dossier app
New-Item -ItemType Directory -Path $tempDir -Force | Out-Null

# Copier les fichiers nécessaires
$filesToInclude = @(
    "appinfo",
    "css",
    "img",
    "js",
    "lib",
    "templates",
    "COPYING",
    "README.md",
    "CHANGELOG.md"
)

foreach ($item in $filesToInclude) {
    if (Test-Path $item) {
        Copy-Item -Path $item -Destination $tempDir -Recurse -Force
        Write-Host "  Copie: $item" -ForegroundColor Gray
    }
}

Write-Host "[OK] Structure creee" -ForegroundColor Green
Write-Host ""

# Étape 2: Création de l'archive
Write-Host "[2/4] Creation de l'archive..." -ForegroundColor Yellow

# Créer le ZIP depuis le dossier parent pour avoir la bonne structure
Compress-Archive -Path $tempDir -DestinationPath $archiveName -Force

Write-Host "[OK] Archive creee: $archiveName" -ForegroundColor Green
Write-Host ""

# Nettoyer le dossier temporaire
Remove-Item $tempDir -Recurse -Force

# Étape 3: Upload
Write-Host "[3/4] Upload vers le serveur..." -ForegroundColor Yellow

scp $archiveName "${RemoteUser}@${RemoteHost}:/home/${RemoteUser}/"

if ($LASTEXITCODE -ne 0) {
    Write-Host "[ERREUR] Erreur lors de l'upload" -ForegroundColor Red
    Remove-Item $archiveName
    exit 1
}

Write-Host "[OK] Upload reussi" -ForegroundColor Green
Write-Host ""

# Étape 4: Instructions pour le serveur
Write-Host "[4/4] Commandes a executer sur le serveur:" -ForegroundColor Yellow
Write-Host ""
Write-Host "ssh ${RemoteUser}@${RemoteHost}" -ForegroundColor Cyan
Write-Host ""
Write-Host "# Ensuite, executez ces commandes:" -ForegroundColor Gray
Write-Host ""

$commands = @"
# Variables
APP_ID=video_converter_fm
APP_DIR=/var/www/nextcloud/apps/\$APP_ID
ZIP_FILE=~/video_converter_fm_${timestamp}.zip

# 1. Maintenance ON
sudo -u www-data php /var/www/nextcloud/occ maintenance:mode --on

# 2. Backup si existe (optionnel)
if [ -d "\$APP_DIR" ]; then
  mkdir -p ~/backups
  sudo tar -czf ~/backups/backup_\${APP_ID}_${timestamp}.tar.gz -C /var/www/nextcloud/apps \$APP_ID
fi

# 3. Nettoyer les anciennes versions
sudo rm -rf /var/www/nextcloud/apps/video_converter*

# 4. Extraire le nouveau ZIP
cd ~
rm -rf ~/deploy-temp
mkdir -p ~/deploy-temp
cd ~/deploy-temp
unzip -q "\$ZIP_FILE"

# 5. Vérifier la structure
echo "Structure extraite:"
ls -la video_converter_fm_temp/

# 6. Déployer
sudo mv ~/deploy-temp/video_converter_fm_temp "\$APP_DIR"
sudo chown -R www-data:www-data "\$APP_DIR"
sudo find "\$APP_DIR" -type d -exec chmod 755 {} +
sudo find "\$APP_DIR" -type f -exec chmod 644 {} +

# 7. Vérifier info.xml
echo "Verification info.xml:"
sudo cat "\$APP_DIR/appinfo/info.xml" | grep -E '<id>|<version>'

# 8. Activer l'app
sudo -u www-data php /var/www/nextcloud/occ app:enable \$APP_ID

# 9. Reload services
sudo systemctl reload php8.2-fpm
sudo systemctl reload apache2

# 10. Maintenance OFF
sudo -u www-data php /var/www/nextcloud/occ maintenance:mode --off

# 11. Vérifier le résultat
echo ""
echo "Apps installees:"
sudo -u www-data php /var/www/nextcloud/occ app:list | grep video_converter

# 12. Nettoyage
rm -f "\$ZIP_FILE"
rm -rf ~/deploy-temp

echo ""
echo "Deploiement termine !"
echo "Ouvrir: https://funambules-nc-test.koumbit.net/apps/video_converter_fm/"
"@

Write-Host $commands -ForegroundColor White
Write-Host ""

# Copier dans le presse-papier
if (Get-Command Set-Clipboard -ErrorAction SilentlyContinue) {
    $commands | Set-Clipboard
    Write-Host "[OK] Commandes copiees dans le presse-papier !" -ForegroundColor Green
}

# Nettoyage local (garder le ZIP pour debug)
Write-Host ""
Write-Host "================================================" -ForegroundColor Cyan
Write-Host "[OK] Pret pour le deploiement !" -ForegroundColor Green
Write-Host "================================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Archive locale: $archiveName" -ForegroundColor Yellow
Write-Host "Connectez-vous au serveur et collez les commandes." -ForegroundColor White
Write-Host ""
