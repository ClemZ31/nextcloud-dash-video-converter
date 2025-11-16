# Video Converter Funambules Media (PFE)

Utilitaire Nextcloud pour convertir des fichiers vidéo via FFmpeg. Conçu pour être simple, intégré à l'interface Nextcloud et adapté au déploiement sur un serveur Nextcloud.

## Fonctionnalités
- Conversion de vidéos vers DASH et HLS.
- Intégration native à Nextcloud (menu contextuel "Convert into" et menu d'application "Conversions")
- Basé sur FFmpeg pour la conversion

## Prérequis
- Nextcloud : versions 25–32
- PHP : 8.1+
- FFmpeg : installé et accessible depuis le serveur
- Node.js : 20+
- npm : 10+

## Installation rapide
1. Clonez ou téléchargez le dépôt.
2. Copiez le dossier de l'application dans `nextcloud/apps/`.
3. Activez l'application :
  - Via l'interface d'administration Nextcloud, ou
  - En CLI : `occ app:enable video_converter_fm`
4. Vérifiez que FFmpeg est installé et exécutable par l'utilisateur du serveur web.

## Utilisation
1. Déposez une vidéo dans un dossier Nextcloud.
2. Clic droit sur le fichier vidéo puis "Convert into".
3. Choisissez le format de sortie.
4. La conversion démarre ; le fichier converti est ajouté dans le même répertoire à la fin du processus.

## Développement

### Préparations
- Installer les dépendances :
```bash
npm install
```

### Build
- Pour produire les artefacts de production :
```bash
npm run build
```

### Mode développement (watch)
- Démarrage en dev (rechargement automatique) :
```bash
npm run dev
```

### Déploiement
Pour déployer sur un serveur de test :
```powershell
# 1. Builder
npm run build

# 2. Déployer
.\deploy-clean.ps1 -RemoteUser <user> -RemoteHost <host>
```

Voir `deploy-clean.example.ps1` pour un exemple d'utilisation.

Remarque : les commandes ci-dessus supposent que vous êtes dans le répertoire racine du projet.

## Fichiers générés
Ne modifiez pas manuellement les fichiers compilés — éditez les sources dans `src/` :
- `js/conversions-app.js` — bundle JS généré
- `css/style.css` — CSS compilé

Pour régénérer :
```bash
npm run build    # build production
npm run dev      # mode watch
```

## Contributeurs
Projet réalisé pour Funambules Média par :
- Clément Deffes — clement.deffes.1@ens.etsmtl.ca
- Simon Bigonnesse — simon.bigonnesse.1@ens.etsmtl.ca
- Nicolas Thibodeau — nicolas.thibodeau.2@etsmtl.net
- Abdessamad Cherifi — abdessamad.cherifi.1@ens.etsmtl.ca

## Support
Ouvrez une issue sur le dépôt :  
https://github.com/Funambules-Medias/nextcloud-dash-video-converter

## Licence
Ce projet est distribué sous licence AGPL. Voir le fichier LICENSE pour les détails.
