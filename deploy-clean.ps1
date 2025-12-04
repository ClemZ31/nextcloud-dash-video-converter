#!/usr/bin/env pwsh
# Script de déploiement avec structure propre
# Usage: .\deploy-clean.ps1

param(
    [string]$RemoteUser,
    [string]$RemoteHost
)

$ErrorActionPreference = "Stop"

# Couleurs et styles
function Write-Header {
    param([string]$Text)
    Write-Host ""
    Write-Host "======================================================================" -ForegroundColor Cyan
    Write-Host "   $Text" -ForegroundColor Cyan
    Write-Host "======================================================================" -ForegroundColor Cyan
}

function Write-Step {
    param([string]$Step, [string]$Text)
    Write-Host ""
    Write-Host "----------------------------------------------------------------------" -ForegroundColor DarkGray
    Write-Host "  [$Step] $Text" -ForegroundColor Yellow
    Write-Host "----------------------------------------------------------------------" -ForegroundColor DarkGray
}

function Write-Success {
    param([string]$Text)
    Write-Host "  [OK] $Text" -ForegroundColor Green
}

function Write-Info {
    param([string]$Text)
    Write-Host "    -> $Text" -ForegroundColor Gray
}

function Write-Separator {
    Write-Host ""
    Write-Host "----------------------------------------------------------------------" -ForegroundColor DarkGray
}

# Valider les paramètres obligatoires
if (-not $RemoteUser -or -not $RemoteHost) {
    Write-Host ""
    Write-Host "  [ERREUR] Parametres manquants" -ForegroundColor Red
    Write-Host ""
    Write-Host "  Usage:" -ForegroundColor Yellow
    Write-Host "    .\deploy-clean.ps1 -RemoteUser <user> -RemoteHost <host>" -ForegroundColor White
    Write-Host ""
    Write-Host "  Exemple:" -ForegroundColor Yellow
    Write-Host "    .\deploy-clean.ps1 -RemoteUser cdeffes -RemoteHost funambules-nc-test.koumbit.net" -ForegroundColor White
    Write-Host ""
    exit 1
}

Write-Header "Deploiement Video Converter FM"
Write-Host ""
Write-Host "  Serveur: " -NoNewline -ForegroundColor Gray
Write-Host "$RemoteUser@$RemoteHost" -ForegroundColor White

# Étape 1: Création d'une structure propre
Write-Step "1/4" "Creation de la structure temporaire"

$timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
# Crée un dossier racine avec le NOM EXACT de l'app pour que l'archive
# décompresse directement en apps/video_converter_fm côté serveur
$tempDir = "video_converter_fm"
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
    "bin",
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
        Write-Info "Copie: $item"
    }
}

Write-Success "Structure creee"

# Étape 2: Création de l'archive
Write-Step "2/4" "Creation de l'archive ZIP"

# Créer le ZIP avec des chemins Unix (forward slashes)
# On doit convertir les chemins pour éviter les backslashes Windows
Add-Type -AssemblyName System.IO.Compression.FileSystem
Add-Type -AssemblyName System.IO.Compression

# Créer le ZIP avec compression et chemins Unix
$zipPath = Join-Path $PWD $archiveName
if (Test-Path $zipPath) {
    Remove-Item $zipPath -Force
}

$zip = [System.IO.Compression.ZipFile]::Open($zipPath, 'Create')

try {
    Get-ChildItem -Path $tempDir -Recurse -File | ForEach-Object {
        $relativePath = $_.FullName.Substring((Get-Item $tempDir).FullName.Length + 1)
        # Convertir les backslashes en forward slashes pour Unix
        $entryName = "$tempDir/$($relativePath.Replace('\', '/'))"
        
        $entry = $zip.CreateEntry($entryName, 'Optimal')
        $entryStream = $entry.Open()
        $fileStream = [System.IO.File]::OpenRead($_.FullName)
        $fileStream.CopyTo($entryStream)
        $fileStream.Close()
        $entryStream.Close()
    }
    
    # Ajouter les dossiers vides
    Get-ChildItem -Path $tempDir -Recurse -Directory | ForEach-Object {
        if (@(Get-ChildItem -Path $_.FullName).Count -eq 0) {
            $relativePath = $_.FullName.Substring((Get-Item $tempDir).FullName.Length + 1)
            $entryName = "$tempDir/$($relativePath.Replace('\', '/'))/"
            $zip.CreateEntry($entryName) | Out-Null
        }
    }
}
finally {
    $zip.Dispose()
}

Write-Success "Archive creee: $archiveName"

# Nettoyer le dossier temporaire
Remove-Item $tempDir -Recurse -Force

# Étape 3: Upload
Write-Step "3/4" "Upload vers le serveur"

scp $archiveName "${RemoteUser}@${RemoteHost}:/home/${RemoteUser}/"

if ($LASTEXITCODE -ne 0) {
    Write-Host "  [ERREUR] Erreur lors de l'upload" -ForegroundColor Red
    Remove-Item $archiveName
    exit 1
}

Write-Success "Upload reussi"

# Étape 4: Instructions pour le serveur
Write-Step "4/4" "Commandes a executer sur le serveur"

Write-Host ""
Write-Host "  Connectez-vous avec:" -ForegroundColor Gray
Write-Host "  ssh ${RemoteUser}@${RemoteHost}" -ForegroundColor Cyan
Write-Separator
Write-Host ""

$commands = @'
echo "========================================================================"

# Variables
APP_ID=video_converter_fm
APP_DIR=/var/www/nextcloud/apps/$APP_ID
ZIP_FILE=$(ls -t ~/video_converter_fm_*.zip 2>/dev/null | head -n1)

# Vérifier que le ZIP existe
if [ -z "$ZIP_FILE" ]; then
  echo "ERREUR: Aucun ZIP video_converter_fm_*.zip trouvé dans ~/"
  exit 1
fi

echo "ZIP trouvé: $ZIP_FILE"

# 1. Maintenance ON
sudo -u www-data php /var/www/nextcloud/occ maintenance:mode --on

# 2. Backup si existe (optionnel)
if [ -d $APP_DIR ]; then
  mkdir -p ~/backups
  sudo tar -czf ~/backups/backup_${APP_ID}_$(date +%Y%m%d_%H%M%S).tar.gz -C /var/www/nextcloud/apps $APP_ID
fi

# 3. Nettoyer les anciennes versions
sudo rm -rf /var/www/nextcloud/apps/video_converter*

# 4. Extraire le nouveau ZIP
rm -rf ~/deploy-temp
mkdir -p ~/deploy-temp
cd ~/deploy-temp
unzip -q "$ZIP_FILE"

# 5. Vérifier la structure
echo "Structure extraite:"
ls -la video_converter_fm/

# Vérifier que le dossier a bien été extrait
if [ ! -d "video_converter_fm" ]; then
  echo "ERREUR: Le dossier video_converter_fm n'existe pas dans le ZIP"
  sudo -u www-data php /var/www/nextcloud/occ maintenance:mode --off
  exit 1
fi

# 6. Déployer
sudo mv -f ~/deploy-temp/video_converter_fm $APP_DIR
sudo chown -R www-data:www-data $APP_DIR
sudo find $APP_DIR -type d -exec chmod 755 {} \;
sudo find $APP_DIR -type f -exec chmod 644 {} \;

# 7. Vérifier info.xml
echo "Verification info.xml:"
sudo cat $APP_DIR/appinfo/info.xml | grep -E '<id>|<version>'

# 8. Activer l'app
sudo -u www-data php /var/www/nextcloud/occ app:enable $APP_ID

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
rm -f "$ZIP_FILE"
rm -rf ~/deploy-temp

echo ""
echo "========================================================================"
echo "  Deploiement termine !"
echo "  Ouvrir: https://funambules-nc-test.koumbit.net/apps/video_converter_fm/"
echo "========================================================================"
echo ""

'@

Write-Host $commands -ForegroundColor White

Write-Separator

# Copier dans le presse-papier
if (Get-Command Set-Clipboard -ErrorAction SilentlyContinue) {
    $commands | Set-Clipboard
    Write-Success "Commandes copiees dans le presse-papier !"
}

# Résumé final
Write-Host ""
Write-Host "======================================================================" -ForegroundColor Green
Write-Host "  [OK] PRET POUR LE DEPLOIEMENT" -ForegroundColor Green
Write-Host "======================================================================" -ForegroundColor Green
Write-Host ""
Write-Host "  Archive locale: " -NoNewline -ForegroundColor Gray
Write-Host "$archiveName" -ForegroundColor Yellow
Write-Host "  Serveur:        " -NoNewline -ForegroundColor Gray
Write-Host "$RemoteUser@$RemoteHost" -ForegroundColor Yellow
Write-Host ""
Write-Host "  Connectez-vous au serveur et collez les commandes (CTRL+V)" -ForegroundColor White
Write-Host ""
