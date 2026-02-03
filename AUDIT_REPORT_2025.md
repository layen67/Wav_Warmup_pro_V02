# Audit Technique : Postal Warmup Pro (v3.2.0)

**Date :** 2025-05-20
**Version analysée :** 3.2.0
**Auteur de l'audit :** Jules (Expert WordPress)

---

## 1. Résumé Global
Le plugin **Postal Warmup Pro** est une solution robuste pour gérer le "warmup" d'emails via plusieurs serveurs Postal. Il est construit sur une architecture moderne (PSR-4, Composer) et intègre des fonctionnalités avancées comme le suivi des statistiques, la gestion des templates, et l'envoi asynchrone via Action Scheduler.

Le code est généralement propre et bien structuré, mais présente quelques **failles de sécurité critiques** au niveau de l'interface d'administration (AJAX) et de la gestion des clés de chiffrement, qui doivent être corrigées immédiatement avant toute mise en production ou distribution publique.

---

## 2. Points Forts
*   **Architecture Moderne :** Utilisation de namespaces PHP, autoloading PSR-4 et Composer.
*   **Performance :**
    *   Agrégation quotidienne des statistiques (`Stats::aggregate_daily_stats`) pour éviter de scanner des tables énormes.
    *   Utilisation d'**Action Scheduler** pour l'envoi d'emails en arrière-plan (non-bloquant).
*   **Sécurité (Points positifs) :**
    *   Vérification des signatures Webhook via `hash_equals`.
    *   Utilisation correcte de `$wpdb->prepare` pour éviter les injections SQL.
    *   Utilisation de nonces pour la plupart des actions AJAX.
*   **Fonctionnalités :**
    *   Système de templates flexible avec fallback.
    *   Gestionnaire de fichiers/dossiers pour les templates.
    *   Support du shortcode `mailto` avec tracking.

---

## 3. Failles de Sécurité (CRITIQUE)

### 3.1. Manque de vérification des capacités (Capabilities)
Plusieurs endpoints AJAX dans `src/Admin/Admin.php` vérifient le nonce mais **pas** les permissions de l'utilisateur (`current_user_can`). Cela signifie qu'un utilisateur connecté avec des droits faibles (ex: Abonné) pourrait potentiellement accéder à des données sensibles s'il devine le nonce ou s'il y a une fuite de nonce.

*   **Fichiers affectés :** `src/Admin/Admin.php`
*   **Actions concernées :**
    *   `ajax_get_stats`
    *   `ajax_get_latest_activity`
    *   `ajax_get_template`
    *   `ajax_get_all_templates`
    *   `ajax_get_template_versions`
*   **Recommandation :** Ajouter `if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [ 'message' => 'Forbidden' ] );` au début de chaque handler.

### 3.2. Gestion des clés de chiffrement
Dans `src/Services/Encryption.php`, la méthode `get_key()` utilise un hash de `get_site_url()` si la constante `SECURE_AUTH_KEY` n'est pas définie.
*   **Risque :** L'URL du site est publique. Si un attaquant accède à la base de données (ex: via une injection SQL ailleurs ou un backup volé), il peut déchiffrer toutes les clés API Postal car il connaît l'URL du site.
*   **Recommandation :** Générer une clé aléatoire forte lors de l'activation du plugin, la stocker dans `wp_options` (ou un fichier hors webroot), et l'utiliser comme sel. Forcer l'utilisation de constantes définies dans `wp-config.php`.

### 3.3. Données Personnelles (GDPR)
Le module `Mailto` (`src/Services/Mailto.php`) enregistre l'adresse IP des utilisateurs qui cliquent sur les liens (`track_click`).
*   **Risque :** Non-conformité RGPD si l'utilisateur n'a pas consenti ou si l'IP n'est pas anonymisée.
*   **Recommandation :** Anonymiser l'IP avant stockage (ex: masquer le dernier octet) ou ajouter une option pour désactiver le tracking IP.

---

## 4. Points Faibles Techniques & Architecture

### 4.1. Classe Admin monolithique
La classe `PostalWarmup\Admin\Admin` gère à la fois l'affichage des pages, l'enqueue des assets, et tous les handlers AJAX. Elle commence à être trop chargée.
*   **Recommandation :** Déplacer les handlers AJAX vers une classe dédiée `PostalWarmup\API\AjaxController` ou `PostalWarmup\Admin\AjaxHandler`.

### 4.2. Dépendances Frontend
Le plugin charge Chart.js (`https://cdn.jsdelivr.net/...`) depuis un CDN externe.
*   **Risque :** Dépendance à un service tiers, problèmes potentiels de confidentialité (GDPR) et de disponibilité.
*   **Recommandation :** Inclure la librairie Chart.js directement dans les assets du plugin (`admin/assets/js/libs/`).

### 4.3. Performance Base de Données
La méthode `Database::get_servers_count` utilise `LIKE %...%` sur le domaine. Sur une table très large, cela ne sera pas indexé.
*   **Recommandation :** Peu critique pour le moment vu le nombre probable de serveurs, mais à surveiller.

---

## 5. Analyse des Performances

*   **Global :** Bonne. L'utilisation des tables d'agrégation (`postal_stats_daily`) est une excellente pratique.
*   **Temps de réponse :** L'envoi d'email est asynchrone, ce qui ne ralentit pas l'utilisateur final.
*   **Requêtes :** Le dashboard fait plusieurs requêtes AJAX au chargement (`get_stats`, `get_latest_activity`). Cela pourrait être regroupé en une seule requête pour réduire la charge serveur.

---

## 6. Conformité WordPress

*   **Prefixes :** Le code utilise correctement les préfixes `pw_` et `postal_` pour les tables et options.
*   **Internationalisation :** Le code est prêt pour la traduction (`__`, `_e`), mais le text-domain doit être cohérent.
*   **Coding Standards :** Le code respecte globalement les PSR, mais le mélange avec les standards WP (snake_case vs camelCase) est parfois présent (ex: méthodes en snake_case dans des classes PSR). C'est acceptable dans l'écosystème WP mais mérite d'être harmonisé.

---

## 7. Conclusion
Postal Warmup Pro est un plugin de qualité supérieure à la moyenne, mais qui nécessite une **passe de sécurité immédiate** avant d'être considéré comme "production-ready" pour un environnement sensible. Une fois les trous de sécurité comblés, il constituera une base solide.
