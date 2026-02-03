# Architecture du Plugin

## Structure des Dossiers (PSR-4)

```
src/
├── Core/       # Chargement, Hooks, Activation
├── Admin/      # Interface d'administration
├── API/        # Communication avec Postal
├── Models/     # Accès aux données (DB)
└── Services/   # Logique métier (Logger, Encryption)
```

## Flux de Données

1.  **Frontend/Worker** : `PostalWarmup\API\Sender` gère l'envoi via `ActionScheduler`.
2.  **Base de Données** : `PostalWarmup\Models\Database` centralise toutes les requêtes SQL.
3.  **Logs** : `PostalWarmup\Services\Logger` écrit les logs (fichier ou DB).

## Patterns Utilisés

*   **Singleton/Static** : Utilisé pour les helpers (`Logger`, `Encryption`) et l'accès DB (`Database`).
*   **Dependency Injection** : Le `Plugin` instancie et injecte les dépendances principales (`Loader`).
*   **Action Scheduler** : Pour la gestion asynchrone des envois.
