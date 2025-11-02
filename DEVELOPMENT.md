# Guide de développement

## Structure du projet

### Code source (à modifier)
- `src/` - Code source Vue.js
  - `app.js` - Point d'entrée
  - `routes.js` - Configuration du routeur
  - `views/` - Composants Vue

### Fichiers générés (NE PAS modifier directement)
- `js/conversions-app.js` - **Généré par Vite**
- `css/style.css` - **Généré par Vite**

### Code PHP manuel
- `lib/Controller/` - Contrôleurs écrits manuellement
- `lib/AppInfo/Application.php` - Configuration de l'app

## Workflow de développement

1. Modifier les fichiers dans `src/`
2. Exécuter `npm run build`
3. Les fichiers dans `js/` et `css/` seront régénérés automatiquement