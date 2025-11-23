# Guide Utilisateur - Convertisseur Vidéo

Ce module vous permet de transformer vos vidéos brutes (MP4, MOV, MKV) en formats optimisés pour le streaming sur le web (DASH et HLS).

## 1. Lancer une conversion

1.  Naviguez dans vos fichiers Nextcloud.
2.  Faites un **clic-droit** sur un fichier vidéo (ou cliquez sur les **...**).
3.  Sélectionnez **"Convertir en profil adaptatif"**.

Une fenêtre s'ouvre avec deux modes :

### Mode Simple (Recommandé)
C'est le mode par défaut. Il applique les meilleurs paramètres pour une compatibilité maximale.
* **Résumé :** Affiche le profil qui sera appliqué.
* **Estimations :** Vous donne une estimation de l'espace disque nécessaire et du temps de traitement.
* Cliquez sur **"Démarrer la conversion"**.

### Mode Avancé
Pour les utilisateurs experts souhaitant contrôler finement le résultat.
* **Formats de sortie :** Choisissez de générer DASH, HLS, ou les deux.
* **Renditions (Qualité) :** Cochez les résolutions désirées (de 144p à 1080p). Vous pouvez ajuster le débit (bitrate) vidéo et audio pour chaque qualité.
* **Codecs :**
    * *H.264 :* Meilleure compatibilité.
    * *H.265 :* Meilleure compression (fichiers plus petits), mais moins compatible.
    * *VP9 :* Alternative open-source performante.
* **Sous-titres :** Cochez "Convertir les sous-titres" pour transformer automatiquement les fichiers `.srt` accompagnant la vidéo.

## 2. Suivre mes conversions

Une fois la conversion lancée, vous n'avez pas besoin de rester sur la page.

1.  Cliquez sur l'icône de l'application **"Conversions"** dans la barre supérieure de Nextcloud (ou via le menu Apps).
2.  Vous verrez la liste de vos tâches :
    * 🟠 **En attente :** La tâche est dans la file d'attente.
    * 🔵 **En cours :** La barre de progression indique l'avancement.
    * 🟢 **Terminé :** La vidéo est prête.
    * 🔴 **Échoué :** Une erreur est survenue (survolez pour voir le détail).

## 3. Résultat

Une fois terminé, un nouveau dossier est créé à côté de votre vidéo originale, nommé `NomDeLaVideo_dash`. Il contient :
* Les fichiers de lecture (`.mpd`, `.m3u8`).
* Les segments vidéo optimisés.
* Une miniature et les sous-titres convertis.

Vous pouvez maintenant partager ce dossier ou utiliser le lecteur vidéo intégré pour visionner le film.