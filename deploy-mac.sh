#!/bin/bash

# Script de déploiement pour Mac/Linux (Version /tmp)
# Usage: ./deploy-mac.sh <user> <host>

REMOTE_USER=$1
REMOTE_HOST=$2

# Couleurs pour l'affichage
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Validation des paramètres
if [ -z "$REMOTE_USER" ] || [ -z "$REMOTE_HOST" ]; then
    echo -e "${RED}ERREUR: Parametres manquants${NC}"
    echo "Usage:"
    echo "  ./deploy-mac.sh <user> <host>"
    exit 1
fi

echo -e "${CYAN}================================================${NC}"
echo -e "${CYAN}   Deploiement Video Converter (via /tmp)${NC}"
echo -e "${CYAN}================================================${NC}"
echo ""

# Étape 1: Création structure propre
echo -e "${YELLOW}[1/4] Creation de la structure temporaire...${NC}"

TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
TEMP_DIR="video_converter_fm"
ARCHIVE_NAME="video_converter_fm_${TIMESTAMP}.zip"

# Nettoyage préventif
rm -rf "$TEMP_DIR"
rm -f "$ARCHIVE_NAME"

# Création du dossier
mkdir -p "$TEMP_DIR"

# Liste des fichiers à inclure
FILES_TO_INCLUDE=(
    "appinfo"
    "bin"
    "css"
    "img"
    "js"
    "lib"
    "templates"
    "COPYING"
    "README.md"
    "CHANGELOG.md"
)

for item in "${FILES_TO_INCLUDE[@]}"; do
    if [ -e "$item" ]; then
        cp -R "$item" "$TEMP_DIR/"
        echo "  Copie: $item"
    fi
done

echo -e "${GREEN}[OK] Structure creee${NC}"
echo ""

# Étape 2: ZIP
echo -e "${YELLOW}[2/4] Creation de l'archive...${NC}"
zip -r -q "$ARCHIVE_NAME" "$TEMP_DIR"
rm -rf "$TEMP_DIR"

echo -e "${GREEN}[OK] Archive creee: $ARCHIVE_NAME${NC}"
echo ""

# Étape 3: Upload vers /tmp
echo -e "${YELLOW}[3/4] Upload vers le serveur (/tmp)...${NC}"

# CHANGEMENT ICI : Destination /tmp/
scp "$ARCHIVE_NAME" "${REMOTE_USER}@${REMOTE_HOST}:/tmp/"

if [ $? -ne 0 ]; then
    echo -e "${RED}[ERREUR] Erreur lors de l'upload${NC}"
    rm -f "$ARCHIVE_NAME"
    exit 1
fi

echo -e "${GREEN}[OK] Upload reussi dans /tmp/${NC}"
echo ""

# Étape 4: Commandes Serveur
echo -e "${YELLOW}[4/4] Commandes a executer sur le serveur:${NC}"
echo ""
echo -e "${CYAN}ssh ${REMOTE_USER}@${REMOTE_HOST}${NC}"
echo ""
echo -e "Copie et colle tout ce bloc ci-dessous une fois connecté :"
echo "--------------------------------------------------------"

cat << 'EOF'
# Variables
APP_ID=video_converter_fm
APP_DIR=/var/www/nextcloud/apps/$APP_ID
# CHANGEMENT ICI : On cherche dans /tmp
ZIP_FILE=$(ls -t /tmp/video_converter_fm_*.zip 2>/dev/null | head -n1)

# Vérifier que le ZIP existe
if [ -z "$ZIP_FILE" ]; then
  echo "ERREUR: Aucun ZIP video_converter_fm_*.zip trouvé dans /tmp/"
  exit 1
fi

echo "ZIP trouvé: $ZIP_FILE"

# 1. Maintenance ON
sudo -u www-data php /var/www/nextcloud/occ maintenance:mode --on

# 2. Backup si existe (optionnel) - On stocke le backup dans /tmp aussi car pas d'accès home
if [ -d $APP_DIR ]; then
  mkdir -p /tmp/backups_vc
  sudo tar -czf /tmp/backups_vc/backup_${APP_ID}_$(date +%Y%m%d_%H%M%S).tar.gz -C /var/www/nextcloud/apps $APP_ID
fi

# 3. Nettoyer les anciennes versions
sudo rm -rf /var/www/nextcloud/apps/video_converter*

# 4. Extraire le nouveau ZIP
rm -rf /tmp/deploy-temp
mkdir -p /tmp/deploy-temp
cd /tmp/deploy-temp
unzip -q "$ZIP_FILE"

# 5. Vérifier la structure
echo "Structure extraite:"
ls -la video_converter_fm/

if [ ! -d "video_converter_fm" ]; then
  echo "ERREUR: Le dossier video_converter_fm n'existe pas dans le ZIP"
  sudo -u www-data php /var/www/nextcloud/occ maintenance:mode --off
  exit 1
fi

# 6. Déployer
sudo mv -f /tmp/deploy-temp/video_converter_fm $APP_DIR
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
rm -rf /tmp/deploy-temp

echo ""
echo "Deploiement termine !"
echo "Ouvrir: https://funambules-nc-test.koumbit.net/apps/video_converter_fm/"
EOF

echo "--------------------------------------------------------"
echo -e "${YELLOW}Archive locale conservée pour debug: $ARCHIVE_NAME${NC}"