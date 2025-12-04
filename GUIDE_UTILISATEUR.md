# Guide Utilisateur - Convertisseur Vidéo

Ce module vous permet de transformer vos vidéos brutes (MP4, MOV, MKV, etc.) en formats optimisés pour le streaming adaptatif sur le web (DASH et HLS).

## 1. Lancer une conversion

1.  Naviguez dans vos fichiers Nextcloud.
2.  Faites un **clic-droit** sur un fichier vidéo (ou cliquez sur les **...**).
3.  Sélectionnez **"Convertir en profil adaptatif"**.

Une fenêtre s'ouvre avec deux modes :

### Mode Simple (Recommandé)
C'est le mode par défaut. Il applique les meilleurs paramètres pour une compatibilité maximale.
* **Résumé :** Affiche le profil qui sera appliqué (codecs, résolutions).
* **Estimations :** Vous donne une estimation de l'espace disque nécessaire et du temps de traitement.
* Cliquez sur **"Démarrer la conversion"**.

### Mode Avancé
Pour les utilisateurs experts souhaitant contrôler finement le résultat.
* **Renditions (Qualité) :** Cochez les résolutions désirées (de 144p à 1080p). Vous pouvez ajuster le débit (bitrate) vidéo et audio pour chaque qualité.
* **Codecs :**
    * *H.264 :* Meilleure compatibilité (recommandé).
    * *H.265 :* Meilleure compression (fichiers plus petits), mais moins compatible avec certains navigateurs.
    * *VP9 :* Alternative open-source performante (génère uniquement DASH, pas de HLS).
* **Sous-titres :** Cochez "Convertir les sous-titres" pour transformer automatiquement les fichiers `.srt` accompagnant la vidéo en format WebVTT.
    > **Convention de nommage recommandée :** Pour que la langue s'affiche correctement dans le lecteur vidéo, nommez vos fichiers de sous-titres avec un suffixe de code langue :
    > - `mon_film_fr.srt` pour Français
    > - `mon_film_en.srt` pour English
    > - `mon_film_es.srt` pour Español
    > - `mon_film_de.srt` pour Deutsch
    > - etc.
    > 
    > Sans ce suffixe, le lecteur affichera "Langue indéterminée".

> **Note :** Les formats DASH et HLS sont toujours générés ensemble pour assurer une compatibilité maximale avec tous les lecteurs.

## 2. Suivre mes conversions

Une fois la conversion lancée, vous n'avez pas besoin de rester sur la page.

1.  Cliquez sur l'icône de l'application **"Conversions"** dans la barre de navigation de Nextcloud.
2.  Vous verrez la liste de vos tâches avec leur statut :
    * **En attente :** La tâche est dans la file d'attente du worker.
    * **En cours :** La barre de progression indique l'avancement en pourcentage.
    * **Terminé :** La vidéo est prête. Un lien 🔗 vous permet d'ouvrir le dossier de sortie.
    * **Échoué :** Une erreur est survenue (le message d'erreur s'affiche).

3.  Vous pouvez **supprimer** une tâche terminée ou échouée, ou **annuler** une tâche en cours/en attente.

## 3. Résultat

Une fois terminé, un nouveau dossier horodaté est créé à côté de votre vidéo originale :

```
📁 NomDeVotreFilm_2025_12_04_14_30_00/
   └── 📁 NomDeVotreFilm/
       ├── NomDeVotreFilm.mpd          (manifeste DASH)
       ├── NomDeVotreFilm.m3u8           (manifeste HLS)
       ├── 📁 segments/          (segments vidéo/audio)
       └── NomDeVotreFilm_fr.vtt      (sous-titres convertis)
```