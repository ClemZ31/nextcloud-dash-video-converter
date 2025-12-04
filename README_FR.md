# Nextcloud Video Converter (PFE)

Une application Nextcloud permettant le transcodage vidéo asynchrone vers des formats de streaming adaptatif (DASH et HLS). Conçue pour être performante, non-bloquante et intégrée nativement à l'interface Fichiers.

## Contexte du projet

Cette application a été développée dans le cadre d'un **Projet de Fin d'Études (PFE)** à l'**École de Technologie Supérieure (ÉTS)** de Montréal, en partenariat avec **[Funambules Médias](https://funambulesmedias.org/)**, une coopérative de travail dédiée à la programmation de films documentaires et à la préservation du patrimoine numérique.

L'objectif est de construire un **système de diffusion vidéo entièrement open source** permettant :
- La conversion en un clic de vidéos H.264 (2-20 Go) vers HLS/MPEG-DASH dans Nextcloud
- La diffusion à grande échelle pour des centaines de spectateurs simultanés
- La souveraineté numérique grâce à des outils auto-hébergés et open source

## Fonctionnalités clés

* **Streaming adaptatif :** Génération automatique de manifestes **MPEG-DASH (.mpd)** et **HLS (.m3u8)**.
* **Multi-résolution :** Création de renditions multiples (1080p, 720p, 480p, etc.) pour s'adapter à la bande passante du client.
* **Architecture asynchrone :** Utilisation de workers PHP en arrière-plan pour ne jamais bloquer l'interface utilisateur Nextcloud.
* **Interface moderne :**
    * Intégration au menu contextuel des fichiers ("Convertir en...").
    * Tableau de bord de suivi des tâches (Vue.js).
    * Estimation en temps réel de l'espace disque requis.
* **Codecs supportés :** H.264, H.265 (HEVC) et VP9 (DASH-only).
* **Sous-titres :** Conversion automatique des `.srt` en `.vtt` pour le web.

## Architecture technique

* **Backend :** PHP (Nextcloud App Framework), FFmpeg.
* **Frontend :** Vue.js (via Vite), Vanilla JS pour l'intégration "Files".
* **Base de données :** Table dédiée `oc_video_jobs` pour la persistance des tâches.
* **Worker :** Processus `systemd` dédié (`bin/worker.php`) pour le traitement des files d'attente.

## Pré-requis

* **Nextcloud 32+** (non rétrocompatible avec les versions antérieures – pour NC < 32, utilisez la [v1.0.6 de Funambules-Medias](https://github.com/Funambules-Medias/nextcloud-dash-video-converter))
* **FFmpeg 5.x** ou supérieur installé sur le serveur
* **PHP 8.1+**
* Accès SSH pour configurer le worker systemd

## Auteurs (Équipe PFE - ÉTS Montréal, Automne 2025)
* Nicolas Thibodeau
* Simon Bigonnesse
* Clément Deffes
* Abdessamad Cherifi

*Sous la supervision du professeur Stéphane Coulombe*  

## Crédits

Ce projet est basé sur le travail de :
- **[danielfigueroajps](https://github.com/Funambules-Medias)** - Dépôts originaux de Funambules Médias
- **[PaulLereverend](https://github.com/PaulLereverend/NextcloudVideo_Converter)** - Nextcloud Video Converter original

## Licence

[AGPL-3.0](COPYING)