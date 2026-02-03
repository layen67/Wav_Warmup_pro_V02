# Rapport de Structure et Autoloading PSR-4

## 1. État Actuel
Le plugin utilise principalement des classes préfixées (`PW_`, `Postal_Warmup`) dans le namespace global, ce qui est une pratique "legacy" WordPress.
Seul le fichier `includes/enums/class-pw-log-level.php` utilise un namespace (`PostalWarmup\Enums`).

L'autoloader généré par `composer.json` utilise actuellement la section `classmap` pour scanner `includes/`, `admin/`, et `public/`. C'est une approche hybride fonctionnelle mais qui ne bénéficie pas de la performance et de l'organisation stricte de PSR-4.

## 2. Fichiers à Nettoyer (Suppression des `require_once`)
Les fichiers suivants chargent manuellement des dépendances qui devraient être gérées par l'autoloader.
**Action Recommandée :** Supprimer les lignes `require_once` pointant vers des classes du plugin.

### `includes/class-postal-warmup.php`
Ce fichier est le "chef d'orchestre" et contient la plus longue liste de chargements manuels.
*   `require_once PW_INCLUDES_DIR . 'class-pw-loader.php';`
*   `require_once PW_INCLUDES_DIR . 'class-pw-i18n.php';`
*   `require_once PW_INCLUDES_DIR . 'class-pw-database.php';`
*   `require_once PW_INCLUDES_DIR . 'class-pw-logger.php';`
*   `require_once PW_INCLUDES_DIR . 'class-pw-cache.php';`
*   `require_once PW_INCLUDES_DIR . 'class-pw-template-loader.php';`
*   `require_once PW_INCLUDES_DIR . 'class-pw-template-storage.php';`
*   `require_once PW_INCLUDES_DIR . 'class-pw-template-sync.php';`
*   `require_once PW_INCLUDES_DIR . 'class-pw-postal-sender.php';`
*   `require_once PW_INCLUDES_DIR . 'class-pw-webhook-handler.php';`
*   `require_once PW_INCLUDES_DIR . 'class-pw-stats.php';`
*   `require_once PW_INCLUDES_DIR . 'class-pw-email-notifications.php';`
*   `require_once PW_INCLUDES_DIR . 'class-pw-warmup-mailto.php';`
*   `require_once PW_ADMIN_DIR . 'class-postal-warmup-admin.php';`
*   `require_once PW_ADMIN_DIR . 'class-pw-settings.php';`
*   `require_once PW_ADMIN_DIR . 'class-pw-servers-list-table.php';`
*   `require_once PW_ADMIN_DIR . 'class-pw-logs-list-table.php';`
*   `require_once PW_ADMIN_DIR . 'class-pw-template-manager.php';`
*   `require_once PW_PUBLIC_DIR . 'class-pw-public.php';`
*   `require_once PW_PUBLIC_DIR . 'class-pw-shortcodes.php';`

### `admin/class-postal-warmup-admin.php`
*   Ligne 143, 148, etc : Les appels à `require_once` pour charger des fichiers de vue (partials) comme `partials/dashboard.php` ou `partials/servers.php` **DOIVENT RESTER**. Ce ne sont pas des classes, l'autoloader ne peut pas les gérer.

### `postal-warmup.php`
*   Les `require_once` vers `class-pw-activator.php` et `class-pw-deactivator.php` dans les fonctions d'activation/désactivation peuvent être supprimés si l'autoloader est chargé avant l'activation (ce qui est le cas avec notre modification précédente).

## 3. Structure Recommandée (Migration PSR-4 Complète)
Pour passer à une structure 100% PSR-4 (`src/` + Namespaces), voici la transformation à opérer progressivement :

**Namespace Racine :** `PostalWarmup\`

| Ancien Fichier | Nouveau Fichier (src/) | Nouveau Namespace |
| :--- | :--- | :--- |
| `includes/class-pw-database.php` | `src/Database/Database.php` | `PostalWarmup\Database` |
| `includes/class-pw-postal-sender.php` | `src/Service/PostalSender.php` | `PostalWarmup\Service` |
| `admin/class-postal-warmup-admin.php` | `src/Admin/Admin.php` | `PostalWarmup\Admin` |
| `public/class-pw-public.php` | `src/Public/Frontend.php` | `PostalWarmup\Public` |

**Impact :** Cette migration briserait tout le code existant qui fait `new PW_Database()`.
**Recommandation :** Conserver la structure actuelle (Classmap) pour la v3.x et planifier la migration PSR-4 stricte pour la v4.0.

## 4. Classes Nécessitant Attention

*   **`PW_Loader`** (`includes/class-pw-loader.php`) : Cette classe gère les hooks. Elle instancie souvent des classes dynamiquement. Vérifier qu'elle est compatible avec l'autoloading (généralement oui si elle fait `new $class_name()`).
*   **`PW_Activator` / `PW_Deactivator`** : Ces classes sont appelées statiquement par WordPress lors de l'activation. Il faut s'assurer que `vendor/autoload.php` est chargé *avant* que WordPress n'exécute le hook d'activation.

## 5. Impact Admin / Public
*   **Aucun impact fonctionnel** si le fichier `composer.json` est bien généré (`composer dump-autoload -o`).
*   L'autoloader améliorera légèrement les performances en ne chargeant que les classes utilisées sur la page courante (ex: ne pas charger `PW_Admin` sur le frontend).

## 6. Prochaines Étapes Techniques
1.  **Supprimer** le bloc de `require_once` dans `includes/class-postal-warmup.php`.
2.  **Exécuter** `composer dump-autoload` pour regénérer la classmap.
3.  **Tester** l'activation sur un site propre pour valider que l'autoloader prend bien le relais.
