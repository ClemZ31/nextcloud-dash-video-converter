# Guide de développement

## Introduction

Ce document décrit l'organisation du code et le workflow de développement pour l'application video_converter_fm.

## Structure du projet

### Code source Vue.js

Le code source de l'interface se trouve dans le dossier `src/` :

- `src/app.js` : Point d'entrée de l'application Vue, initialise le routeur et monte l'application
- `src/routes.js` : Configuration des routes Vue Router pour la navigation
- `src/views/` : Composants Vue qui représentent les différentes pages
  - `ConversionsApp.vue` : Composant racine de l'application
  - `ConversionsContent.vue` : Page principale qui affiche la liste des conversions
  - `ConversionsNavigation.vue` : Menu de navigation latéral

### Fichiers générés automatiquement

Ces fichiers sont créés par Vite lors de la compilation et ne doivent jamais être modifiés manuellement :

- `js/conversions-app.js` : Code JavaScript compilé et optimisé
- `css/style.css` : Styles CSS compilés

### Code PHP

Le code serveur se trouve dans le dossier `lib/` :

- `lib/Controller/` : Contrôleurs qui gèrent les requêtes HTTP
  - `PageController.php` : Gère l'affichage des pages principales
  - `ConversionController.php` : API REST pour les conversions (créer, lister, statut)
  
- `lib/Service/` : Services qui contiennent la logique métier
  - `ConversionService.php` : Gère l'exécution des conversions avec FFmpeg
  
- `lib/Db/` : Couche d'accès aux données
  - `VideoJob.php` : Entité représentant un job de conversion
  - `VideoJobMapper.php` : Opérations de base de données pour les jobs
  
- `lib/Migration/` : Scripts de migration de base de données
  - `Version100600Date20250129000000.php` : Création de la table video_jobs

- `lib/AppInfo/Application.php` : Configuration et initialisation de l'application Nextcloud

### Scripts et workers

Le dossier `bin/` contient les scripts d'administration :

- `worker.php` : Worker PHP qui traite les jobs de conversion en arrière-plan
- `start-worker.sh` : Script bash pour démarrer le worker
- `stop-worker.sh` : Script bash pour arrêter le worker
- `test-jobs.sh` : Script de test pour vérifier le système de jobs
- `systemd/video-worker.service` : Configuration systemd pour lancer le worker au démarrage

### Configuration

- `appinfo/info.xml` : Métadonnées de l'application (nom, version, dépendances)
- `appinfo/routes.php` : Définition des routes HTTP de l'application
- `vite.config.js` : Configuration de l'outil de build Vite
- `package.json` : Dépendances npm et scripts de compilation

## Workflow de développement

### Développement de l'interface (Vue.js)

1. Modifier les fichiers dans `src/`
2. Compiler avec la commande :
   ```powershell
   npm run build
   ```
3. Les fichiers dans `js/` et `css/` seront automatiquement régénérés
4. Déployer sur le serveur de test avec `deploy-clean.ps1`

### Développement du backend (PHP)

1. Modifier les fichiers PHP dans `lib/`
2. Pas de compilation nécessaire pour PHP
3. Déployer directement avec `deploy-clean.ps1`
4. Tester les modifications

### Développement du worker

1. Modifier `bin/worker.php` ou `lib/Service/ConversionService.php`
2. Déployer les modifications
3. Redémarrer le worker sur le serveur :
   ```bash
   sudo systemctl restart video-worker.service
   ```
4. Surveiller les logs :
   ```bash
   sudo journalctl -u video-worker.service -f
   ```

## Commandes utiles

### Compilation

```powershell
# Installation des dépendances
npm install

# Build de production
npm run build

```

### Tests locaux

Avant de déployer, vérifier que :
- Le build se termine sans erreur
- Aucun warning important dans la console
- Les fichiers js/conversions-app.js et css/style.css ont été mis à jour

### Débogage

Pour déboguer l'interface Vue :
1. Ouvrir les outils de développement du navigateur (F12)
2. Onglet "Console" pour voir les logs JavaScript
3. Onglet "Network" pour surveiller les requêtes API
4. Vue DevTools extension pour inspecter les composants Vue

Pour déboguer le backend PHP :
1. Vérifier les logs Nextcloud : `/var/www/nextcloud/data/nextcloud.log`
2. Vérifier les logs Apache : `sudo tail -f /var/log/apache2/error.log`
3. Activer le mode debug dans Nextcloud si nécessaire

Pour déboguer le worker :
```bash
# Voir les logs en temps réel
sudo journalctl -u video-worker.service -f

# Voir les derniers logs
sudo journalctl -u video-worker.service -n 100

# Tester le worker manuellement
sudo -u www-data php /var/www/nextcloud/apps/video_converter_fm/bin/worker.php
```

## Dépendances principales

### Frontend
- Vue 3 : Framework JavaScript
- Vue Router : Gestion de la navigation
- @nextcloud/vue : Composants UI Nextcloud
- @nextcloud/axios : Client HTTP
- Vite : Outil de build

### Backend
- PHP 8.2 : Langage serveur
- Nextcloud 32 : Framework d'application
- FFmpeg : Encodage vidéo
- MySQL/PostgreSQL : Base de données

## Ressources utiles

- Documentation Nextcloud : https://docs.nextcloud.com/server/latest/developer_manual/
- Documentation Vue 3 : https://vuejs.org/guide/introduction.html
- Documentation FFmpeg : https://ffmpeg.org/documentation.html
- Exemples d'apps Nextcloud : https://github.com/nextcloud/app-tutorial
