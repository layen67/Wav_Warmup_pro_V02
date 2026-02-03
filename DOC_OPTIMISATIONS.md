# Documentation Technique - Optimisations Performance (v3.3.0)

## 1. Gestion des Logs Optimisée

### Problème Précédent
Les logs étaient écrits systématiquement en base de données (`wp_postal_logs`) ET dans des fichiers physiques, causant une surcharge de la base de données sur les gros volumes d'envoi.

### Solution
Une nouvelle option "Mode de stockage" a été ajoutée dans **Postal Warmup > Paramètres**.

### Modes Disponibles
*   **Fichier uniquement (Recommandé)** : Écrit uniquement dans `/wp-content/uploads/postal-warmup-logs/`. Performance maximale.
*   **Fichier + BDD (Erreurs seulement)** : Écrit tout dans le fichier, mais n'enregistre que les erreurs (ERROR, CRITICAL) en base de données pour affichage dans le dashboard. Bon compromis.
*   **Les deux (Debug)** : Comportement original (déconseillé en production intensive).
*   **Base de données uniquement**.

### Nettoyage
Le processus de nettoyage automatique (`pw_cleanup_old_logs`) a été optimisé pour supprimer les vieux logs par paquets de 1000 lignes, évitant les verrous de table sur les hébergements mutualisés.

---

## 2. Agrégation des Statistiques

### Problème Précédent
Le tableau de bord calculait les totaux (envoyés, succès, erreurs) en additionnant en temps réel toutes les lignes de la table `wp_postal_stats` (qui contient une ligne par heure et par serveur). Avec des années d'historique, cela devenait très lent.

### Solution
Une nouvelle table `wp_postal_stats_daily` a été créée.
Un nouveau CRON `pw_daily_stats_aggregation` tourne chaque nuit pour :
1.  Lire les stats horaires de la veille.
2.  Les agréger en une seule ligne par serveur et par jour dans `wp_postal_stats_daily`.

### Impact
Les requêtes du tableau de bord interrogent désormais cette table pré-calculée pour tout l'historique, et n'additionnent en temps réel que la journée en cours. Le chargement du dashboard est quasi-instantané quelle que soit la taille de l'historique.

---

## 3. Guide de Migration

Si vous mettez à jour un plugin existant avec beaucoup de données :

1.  La table `wp_postal_stats_daily` sera créée automatiquement à l'activation.
2.  Elle sera vide au début. Les stats d'historique n'apparaîtront pas immédiatement sur le dashboard (seul l'aujourd'hui sera visible).
3.  **Pour récupérer l'historique immédiatement**, exécutez le script fourni `migrate_stats.php` (via navigateur ou WP-CLI) ou attendez que le CRON nocturne traite progressivement les données (ou forcez l'exécution du hook `pw_daily_stats_aggregation`).

### Commande WP-CLI suggérée
```bash
wp eval 'PostalWarmup\Models\Stats::aggregate_daily_stats();'
```
