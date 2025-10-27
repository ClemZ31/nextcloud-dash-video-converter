# Video Converter (PFE)

Ce projet de convertisseur vidéo permet aux utilisateurs de convertir des fichiers vidéo dans différents formats directement depuis l'interface de Nextcloud.

## Contexte

Le convertisseur vidéo a été conçu pour faciliter la gestion et la conversion de fichiers multimédias au sein de l'environnement Nextcloud. Grâce à l'intégration de FFmpeg, cet outil offre une solution simple et efficace pour les utilisateurs souhaitant convertir leurs vidéos en différents formats.

## Fonctionnalités

* Conversion de vidéos dans plusieurs formats
* Interface utilisateur intuitive intégrée à Nextcloud
* Support pour les formats de sortie suivants :
  * MP4
  * AVI
  * WEBM
  * M4V
  * DASH (MPD et HLS)

## Exigences

* **Nextcloud** : Version 25 à 32
* **FFmpeg** : Assurez-vous que FFmpeg est installé sur votre serveur Nextcloud.

## Installation

1. Clonez ou téléchargez ce dépôt.
2. Placez le dossier de l'application dans le répertoire **nextcloud/apps/**.
3. Activez l'application via l'interface d'administration de Nextcloud.

## Comment utiliser

1. Créez un répertoire et téléchargez-y le fichier vidéo à convertir.
2. Faites un clic droit sur le fichier vidéo et sélectionnez "Convert into".
3. Choisissez le format de sortie souhaité.
4. La conversion commencera et le fichier converti sera disponible dans le même répertoire une fois le processus terminé.

## Contributeurs

Ce projet a été réalisé par :

* Clément Deffes (clement.deffes.1@ens.etsmtl.ca)
* Simon Bigonnesse (simon.bigonnesse.1@ens.etsmtl.ca)
* Nicolas Thibodeau (nicolas.thibodeau.2@etsmtl.net)
* Abdessamad Cherifi (Abdessamad.cherifi.1@ens.etsmtl.ca)

## Support

Pour toute question ou problème, veuillez ouvrir une issue sur le [dépôt GitHub](https://github.com/Funambules-Medias/nextcloud-dash-video-converter).

## License

Ce projet est sous licence AGPL. Veuillez consulter le fichier LICENSE pour plus de détails.