# Audit Technique : Postal Warmup Pro (wp18)

**Date :** 24 Mai 2024
**Version analysée :** 3.1.0
**Auteur :** Expert WordPress (Jules)

## 1. Résumé Global

Le plugin **Postal Warmup Pro** est une solution avancée pour "chauffer" (warmup) des serveurs d'emailing via l'API Postal. Il permet de gérer plusieurs serveurs, de définir des templates d'emails, de suivre les statistiques d'envoi et de réception, et de gérer les webhooks pour le tracking des événements (livraison, ouverture, clic).

L'architecture repose sur le "WordPress Plugin Boilerplate" mais a considérablement évolué vers une structure plus moderne (bien que transitionnelle) intégrant Composer, Action Scheduler et une interface admin réactive (AJAX).

Globalement, le plugin est **fonctionnel et riche en fonctionnalités**, mais souffre d'une **dette technique structurelle** (fichiers dupliqués, standards PSR-4 non respectés) et de quelques **faiblesses de sécurité** au niveau des Webhooks et du stockage des clés API.

---

## 2. Points Forts

*   **Fonctionnalités Riches :** Support multi-serveurs, templates JSON, stats détaillées, gestion des suppressions.
*   **Architecture Asynchrone :** Utilisation de `Action Scheduler` (WooCommerce) pour gérer les tâches de fond, ce qui est une excellente pratique pour la performance et la fiabilité.
*   **Sécurité des Logs :** Protection du dossier de logs par `.htaccess` et fichiers index vides.
*   **Performance BDD :** Utilisation de tables personnalisées (`postal_servers`, `postal_logs`, `postal_stats`) plutôt que de surcharger la table `wp_options` ou `wp_posts`.
*   **UX Admin :** Interface réactive utilisant largement AJAX pour éviter les rechargements de page.
*   **Nettoyage Automatique :** Tâches CRON en place pour nettoyer les vieux logs et statistiques.

---

## 3. Points Faibles & Problèmes Techniques

### A. Architecture & Structure
1.  **Duplication de Code (Majeur) :** Deux classes d'administration coexistent :
    *   `admin/class-postal-warmup-admin.php` (La version active V3.2).
    *   `admin/class-pw-admin.php` (Version legacy, chargée mais probablement inutilisée ou redondante).
    *   *Risque :* Confusion pour les développeurs, maintenance difficile, bugs potentiels si l'ancienne classe intercepte des hooks.
2.  **Autoloading Confus :**
    *   Le fichier `composer.json` déclare un namespace PSR-4 `"PostalWarmup\\": "src/"`, mais le dossier `src/` **n'existe pas** à la racine.
    *   Le plugin repose sur un mélange de `classmap` (Composer) et de `require_once` manuels dans `postal-warmup.php` et `PW_Loader`.
3.  **Nommage Incohérent :** Mélange de préfixes `Postal_Warmup_` et `PW_`. Bien que `PW_` semble être la nouvelle convention, l'ancienne persiste.

### B. Sécurité
1.  **Webhooks (Majeur) :**
    *   La classe `PW_Webhook_Handler` loggue la signature attendue vs reçue en cas d'erreur : `PW_Logger::error(..., ['expected' => $expected, 'received' => $signature])`.
    *   *Risque :* Bien que ce soit du HMAC, fuiter des informations de sécurité dans les logs est une mauvaise pratique (Information Leakage).
    *   Option "permissive" : Le code permet de bypasser la vérification de signature si le secret est vide. C'est dangereux par défaut.
2.  **Stockage des Clés API :**
    *   Les clés API Postal sont stockées en clair dans la table `wp_postal_servers`.
    *   *Recommandation :* Elles devraient être chiffrées (au moins obfusquées) en base de données.
3.  **Sanitization :**
    *   Dans l'ensemble correcte (`prepare`, `sanitize_text_field`), mais la validation des templates JSON (provenant de l'utilisateur) doit être rigoureuse pour éviter des XSS stockés si ces données sont ré-affichées sans échappement dans l'admin (à vérifier dans les vues JS).

### C. Performance
1.  **Logging Verbeux :**
    *   `PW_Logger` écrit **à la fois** dans un fichier et dans la base de données (`wp_postal_logs`) pour chaque action.
    *   Sur un serveur à fort trafic, cela double les I/O et fait grossir la BDD très vite.
    *   *Note :* La rotation est en place, mais l'écriture double est coûteuse.

---

## 4. Recommandations Concrètes

### Priorité 1 : Nettoyage & Structure (Refactoring)
*   [ ] **Supprimer** le fichier `admin/class-pw-admin.php` et migrer toute logique restante vers `class-postal-warmup-admin.php`. Renommer ensuite cette classe pour suivre la convention (ex: `PW_Admin_Controller`).
*   [ ] **Réparer** le `composer.json` : Créer le dossier `src/` et y déplacer les classes avec le namespace `PostalWarmup`.
*   [ ] **Standardiser** le chargement : Tout passer par l'autoloader Composer et supprimer les `require_once` conditionnels dans le fichier principal.

### Priorité 2 : Sécurité
*   [ ] **Durcir les Webhooks :** Ne jamais logger la signature attendue. Rendre le secret obligatoire par défaut.
*   [ ] **Chiffrement :** Utiliser `openssl_encrypt` (avec `SECURE_AUTH_KEY` de WP comme salt) pour stocker les clés API en base.
*   [ ] **Audit XSS :** Vérifier que toutes les sorties de données (notamment les logs affichés dans l'admin) sont échappées avec `esc_html` ou `esc_attr`.

### Priorité 3 : Optimisation
*   [ ] **Option Logging :** Ajouter un paramètre pour choisir la destination des logs (Fichier OU BDD OU Les deux). Par défaut : Fichier uniquement (plus performant).
*   [ ] **Minification :** Les fichiers JS/CSS admin ne sont pas minifiés. Ajouter une étape de build (npm script) pour générer des assets `.min.css` et `.min.js`.

---

## 5. Roadmap Suggérée

### Phase 1 : Stabilisation (Semaine 1)
1.  Suppression du code mort (`admin/class-pw-admin.php`).
2.  Correction du `composer.json` et de la structure de dossiers.
3.  Application des correctifs de sécurité Webhook.
4.  Test complet de non-régression.

### Phase 2 : Optimisation & Sécurité (Semaine 2)
1.  Implémentation du chiffrement des clés API (avec script de migration pour les clés existantes).
2.  Refonte du système de logs (Option "File only").
3.  Revue des requêtes SQL lourdes (index manquants sur `server_id` dans les tables de stats ?).

### Phase 3 : Release & Documentation (Semaine 3)
1.  Rédaction du `README.txt` au format WordPress standard.
2.  Documentation utilisateur (PDF ou Wiki Github) pour l'installation et la configuration des Webhooks Postal.
3.  Soumission/Tag de la version 3.2.0 stable.

---

**Conclusion :** Le plugin est une base solide qui nécessite juste une phase de "rigueur" (nettoyage, sécurité, normes) pour devenir un produit professionnel maintenable à long terme.
