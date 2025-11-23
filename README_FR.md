# Nextcloud Video Converter (PFE)

Une application Nextcloud permettant le transcodage vidéo asynchrone vers des formats de streaming adaptatif (DASH et HLS). Conçue pour être performante, non-bloquante et intégrée nativement à l'interface Fichiers.

## 🌟 Fonctionnalités Clés

* **Streaming Adaptatif :** Génération automatique de manifestes **MPEG-DASH (.mpd)** et **HLS (.m3u8)**.
* **Multi-Résolution :** Création de renditions multiples (1080p, 720p, 480p, etc.) pour s'adapter à la bande passante du client.
* **Architecture Asynchrone :** Utilisation de workers PHP en arrière-plan pour ne jamais bloquer l'interface utilisateur Nextcloud.
* **Interface Moderne :**
    * Intégration au menu contextuel des fichiers ("Convertir en...").
    * Tableau de bord de suivi des tâches (Vue.js).
    * Estimation en temps réel de l'espace disque requis.
* **Support Codecs :** H.264, H.265 (HEVC) et VP9.
* **Sous-titres :** Conversion automatique des `.srt` en `.vtt` pour le web.

## 🛠️ Architecture Technique

* **Backend :** PHP (Nextcloud App Framework), FFmpeg.
* **Frontend :** Vue.js (via Vite), Vanilla JS pour l'intégration "Files".
* **Base de données :** Table dédiée `oc_video_jobs` pour la persistance des tâches.
* **Worker :** Processus `systemd` dédié (`bin/worker.php`) pour le traitement des files d'attente.

## 📋 Pré-requis

* Nextcloud 25 à 33.
* **FFmpeg** installé sur le serveur (`/usr/bin/ffmpeg` ou dans le PATH).
* Accès SSH pour configurer le worker Systemd.

## 👥 Auteurs (Équipe PFE)
* Nicolas Thibodeau
* Simon Bigonnesse
* Clément Deffes
* Abdessamad Cherifi