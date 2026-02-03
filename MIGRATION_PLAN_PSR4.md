# Plan de Migration PSR-4 et Refactorisation

Ce document détaille les étapes pour passer d'une architecture WordPress legacy à une architecture 100% PSR-4 propre, modulaire et orientée objet.

## 1. État des Lieux (Vérification Initiale)

### Fichiers `require_once` résiduels
*   **Nettoyé :** `includes/class-postal-warmup.php` ne contient plus de `require_once` manuels pour les classes.
*   **Légitime :** `postal-warmup.php` (plugin root) charge `vendor/autoload.php` et conditionnellement les activateurs/désactivateurs.
*   **Légitime :** `admin/class-postal-warmup-admin.php` charge des fichiers de vue (partials), ce qui est correct car ce ne sont pas des classes.

### Doublons détectés
*   **Redondance Critique :** `admin/class-postal-warmup-admin.php` et `admin/class-pw-admin.php` semblent avoir des responsabilités similaires (gestion des menus, assets).
    *   *Action :* Analyser lequel est réellement instancié par le cœur du plugin et supprimer l'autre.

## 2. Plan de Restructuration des Dossiers (Target: v4.0)

L'objectif est de déplacer tout le code PHP logique dans `src/` et de ne garder que les vues et assets dans `admin/` et `public/`.

```
postal-warmup/
├── src/                        # Namespace: PostalWarmup\
│   ├── Admin/                  # Classes spécifiques à l'admin
│   │   ├── AdminController.php # Remplace class-postal-warmup-admin.php
│   │   ├── Settings.php        # Remplace class-pw-settings.php
│   │   ├── Menu/               # Gestion des sous-menus
│   │   └── View/               # Helpers pour l'affichage
│   ├── Core/                   # Cœur du plugin
│   │   ├── Activator.php
│   │   ├── Deactivator.php
│   │   ├── Loader.php
│   │   └── Plugin.php          # Remplace class-postal-warmup.php
│   ├── Database/               # Gestion DB
│   │   ├── Database.php
│   │   └── Migrations.php
│   ├── Service/                # Logique métier
│   │   ├── Logger.php
│   │   ├── Mailer.php          # Remplace PW_Postal_Sender
│   │   ├── Stats.php
│   │   └── WebhookHandler.php
│   ├── Template/               # Gestion des templates
│   │   ├── TemplateManager.php
│   │   └── TemplateLoader.php
│   └── Public/                 # Classes spécifiques au front
│       ├── Frontend.php
│       └── Shortcodes.php
├── admin/
│   ├── css/
│   ├── js/
│   └── partials/               # Vues PHP uniquement (pas de logique)
├── public/
│   ├── css/
│   └── js/
├── vendor/                     # Composer dependencies
└── postal-warmup.php           # Point d'entrée
```

## 3. Classes à Refactoriser (Priorité Haute)

### A. Contrôleurs de Vues (Separation of Concerns)
Actuellement, les fichiers dans `admin/partials/` contiennent de la logique PHP (traitement de formulaires `$_POST`, requêtes DB, redirections).
**Problème :** Difficile à tester, sécurité dispersée, non conforme MVC.
**Solution :** Créer des Controllers.

*   **`admin/partials/templates.php`**
    *   *Logique à extraire :* Traitement de `$_POST['pw_action']` (fix_db, create_test_template).
    *   *Destination :* `PostalWarmup\Admin\Controller\TemplateController::handle_actions()`.
    *   *Le fichier partial* ne doit plus contenir que de l'affichage HTML et des boucles `foreach`.

### B. Gestionnaire de Paramètres
*   **`admin/class-pw-settings.php`**
    *   *Problème :* Mélange la déclaration des settings (`register_setting`) et le rendu HTML des champs (`echo '<input ...>').
    *   *Solution :* Séparer la logique d'enregistrement dans `src/Admin/Settings.php` et mettre le HTML des champs dans `admin/partials/settings-fields.php` ou utiliser une classe `FormHelper`.

### C. List Tables
*   **`admin/class-pw-servers-list-table.php`** et **`class-pw-logs-list-table.php`**
    *   Ces classes héritent de `WP_List_Table`. Elles sont déjà orientées objet mais doivent être déplacées dans `src/Admin/ListTable/` et namespacées.

## 4. Plan de Migration Détaillé

### Phase 1 : Consolidation (Immédiat)
1.  **Supprimer le fichier mort :** Confirmer et supprimer `admin/class-pw-admin.php` s'il n'est pas utilisé.
2.  **Nettoyage des Partials :** Identifier toute logique critique dans `admin/partials/*.php` et la documenter via des commentaires `@todo Refactor to Controller`.

### Phase 2 : Introduction des Namespaces (v3.2)
1.  **Créer le dossier `src/`**.
2.  **Migrer classe par classe :**
    *   Prendre une classe simple (ex: `PW_Logger`).
    *   La déplacer dans `src/Service/Logger.php`.
    *   Lui donner le namespace `PostalWarmup\Service`.
    *   Mettre à jour tous les appels `PW_Logger::log` par `Logger::log` (ou importer la classe).
    *   *Astuce de transition :* Créer un alias de classe dans l'ancien fichier pour maintenir la rétro-compatibilité temporaire :
        ```php
        // includes/class-pw-logger.php
        class_alias('PostalWarmup\Service\Logger', 'PW_Logger');
        ```

### Phase 3 : MVC & Refonte Admin (v4.0)
1.  **Implémenter un Router simple** pour l'admin qui dirige les actions (`page=postal-warmup&action=edit`) vers des méthodes de Controller.
2.  **Vider les partials** de toute logique métier.
3.  **Standardiser l'injection de dépendances** : Passer les instances (Database, Logger) via le constructeur au lieu d'utiliser des appels statiques partout (`PW_Database::get_server`).

## 5. Impact sur le Frontend et l'Admin

*   **Admin :** La structure sera plus claire. Le risque de conflit de noms de classes avec d'autres plugins disparaîtra grâce aux namespaces.
*   **Public :** Aucun changement visible pour l'utilisateur. Le chargement des assets sera géré par `src/Public/Frontend.php`.
*   **Performance :** L'autoloader PSR-4 est très rapide et standard. L'organisation du code facilitera l'ajout de cache d'objets ou d'autres optimisations futures.

Ce plan permet une transition en douceur sans casser le plugin existant, en utilisant la puissance de Composer et des standards modernes PHP.
