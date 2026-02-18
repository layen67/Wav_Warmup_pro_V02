# Roadmap : Postal Warmup Pro 2025

Cette roadmap vise à amener le plugin à un niveau de stabilité, de sécurité et de performance optimal pour une publication ou une utilisation en production critique.

---

## Phase 1 : Sécurité et Stabilité (Urgent)
**Objectif :** Corriger les failles critiques identifiées dans l'audit.

1.  **Sécurisation AJAX (Hautement critique)**
    *   Modifier `src/Admin/Admin.php`.
    *   Ajouter `if ( ! current_user_can('manage_options') ) ...` dans **toutes** les méthodes `ajax_*` qui manquent de vérification (ex: `get_stats`, `get_all_templates`).
2.  **Renforcement du chiffrement**
    *   Modifier `src/Services/Encryption.php`.
    *   Implémenter un mécanisme de génération de clé unique (`pw_encryption_key`) stockée en base si `SECURE_AUTH_KEY` est absente.
    *   Ne plus utiliser `get_site_url()` comme fallback.
3.  **Conformité GDPR (Mailto)**
    *   Modifier `src/Services/Mailto.php`.
    *   Masquer le dernier octet de l'IP (`192.168.1.xxx`) avant insertion en base.
    *   Ajouter une option dans les réglages pour désactiver le tracking IP.
4.  **Localisation des Assets**
    *   Télécharger `Chart.js` et l'inclure dans `admin/assets/js/vendor/`.
    *   Supprimer la dépendance CDN jsDelivr.

---

## Phase 2 : Refactoring & Code Quality
**Objectif :** Améliorer la maintenabilité et la séparation des responsabilités.

1.  **Architecture Admin**
    *   Créer `src/Admin/AjaxHandler.php`.
    *   Migrer toutes les méthodes `ajax_*` de `Admin.php` vers cette nouvelle classe.
    *   Alléger `Admin.php` pour ne gérer que les menus et l'enqueue des scripts.
2.  **Harmonisation des Standards**
    *   Repasser sur le nommage des méthodes. Décider d'une convention stricte (camelCase vs snake_case) et s'y tenir (sauf pour les hooks WP qui attendent souvent du snake_case, mais la méthode appelée peut être camelCase).
3.  **Nettoyage des TODOs**
    *   Vérifier les commentaires et supprimer le code mort ou les aliases dépréciés (`PW_Database` alias, etc.) si la rétrocompatibilité n'est plus requise pour cette version majeure.

---

## Phase 3 : Performance & Optimisation
**Objectif :** Réduire l'empreinte serveur et accélérer le dashboard.

1.  **Optimisation du Dashboard**
    *   Créer un endpoint AJAX unique `ajax_get_dashboard_data` qui renvoie à la fois les stats, l'activité récente et les logs d'erreurs.
    *   Réduire le nombre de requêtes HTTP au chargement de la page d'administration.
2.  **Indexation Base de Données**
    *   Vérifier avec un gros volume de données (simulé) si les index actuels suffisent pour les rapports sur 30/90 jours.
    *   Ajouter un index composite sur `postal_logs(server_id, created_at)` si les filtres par serveur sont lents.

---

## Phase 4 : Nouvelles Fonctionnalités & UX
**Objectif :** Enrichir l'expérience utilisateur.

1.  **Gestion en masse des Templates**
    *   Ajouter des checkboxes dans la liste des templates.
    *   Permettre la suppression, le déplacement ou le changement de statut en masse.
2.  **Export/Import Amélioré**
    *   Ajouter un validateur JSON strict lors de l'import pour éviter de corrompre la base avec des templates mal formés.
3.  **Wizard de configuration**
    *   Ajouter un guide pas-à-pas lors de la première activation pour aider l'utilisateur à configurer son premier serveur Postal et créer une clé API.

---

## Planning Suggéré

| Semaine | Phase | Tâches Principales |
| :--- | :--- | :--- |
| **S1** | Phase 1 | Sécurisation AJAX, Encryption, GDPR, Assets locaux |
| **S2** | Phase 2 | Refactoring AjaxHandler, Nettoyage code |
| **S3** | Phase 3 | Optimisation Dashboard, Tests de charge |
| **S4** | Phase 4 | Bulk Actions Templates, Export/Import |
