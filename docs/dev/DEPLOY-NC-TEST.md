# Déploiement de développement sur « nc-test »

Ce guide explique comment construire et déployer rapidement `video_converter_fm` sur l'instance Nextcloud de test depuis PowerShell.

## Objectifs
- Builder les assets (Vite) en local
- Uploader un paquet propre vers nc-test
- Remplacer proprement l’app côté serveur et la réactiver
- Boucle de développement rapide

---

## Prérequis
- PowerShell
- Node.js 20 et npm 10
- Accès SSH à nc-test avec un utilisateur pouvant écrire dans le répertoire `apps/` de Nextcloud (ou via sudo/chown)
- Chemin Nextcloud (par défaut dans ce guide) : `/var/www/nextcloud`

Variables courantes:
- APP_ID: `video_converter_fm`
- NC_PATH: `/var/www/nextcloud`
- APPS_DIR: `/var/www/nextcloud/apps`

---

## Déploiement automatisé (RECOMMANDÉ)
Le dépôt fournit le fichier `deploy-clean.ps1` qui crée un ZIP à structure propre puis l’upload via `scp`.

1) Installer les dépendances et builder:
```powershell
npm ci
npm install
npm run build
```

2) Lancer le script (à adapter):
```powershell
# Exemple
Set-ExecutionPolicy -Scope Process -ExecutionPolicy Bypass; .\deploy-clean.ps1 -RemoteUser "user_name" -RemoteHost "funambules-nc.example.com"
```


3) Côté serveur, exécuter les commandes indiquées par le script **(à la fin de l'exécution de la dernière commande, elles sont directement copiées dans votre presse-papiers)**.
L’archive créée contient désormais un dossier racine `video_converter_fm` directement prêt à être extrait dans `apps/`.

---

## Boucle de dev rapide
- Editer le code localement
- Rebuild rapide: `npm run build` ou `npm run watch`
- Relancer `deploy-clean.ps1` (plus rapide qu’un ZIP manuel)
- Se reconnecter en SSH
- Coller et exécuter les commandes fournies par le script
- Rafraîchir l’onglet Nextcloud (faire un Ctrl+F5 si nécessaire ET/OU un Ctrl+Shift+Delete pour vider le cache)

---

## Dépannage
- « appinfo file cannot be read » : structure du ZIP incorrecte (pas de dossier racine `video_converter_fm`).
- L’action « Convert into » n’apparaît pas dans Fichiers : vérifier que `js/conversion.js` est bien injecté sur les pages Files (voir section "Améliorations" du code review pour la meilleure façon en NC 32).
- CSS/JS pas mis à jour : reconstruire (`npm run build`), redeployer, vider le cache navigateur.
- Droits fichiers : `chown -R www-data:www-data /var/www/nextcloud/apps/video_converter_fm`.
