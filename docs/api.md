# API Client

La classe `PostalWarmup\API\Client` gère les appels HTTP vers Postal.

## Utilisation

```php
use PostalWarmup\API\Client;

$response = Client::request($server_id, 'messages', 'GET', ['limit' => 10]);
```

## Gestion des Erreurs
*   Les erreurs HTTP >= 400 retournent un `WP_Error`.
*   Les erreurs JSON sont catchées.
*   Tout est loggué via `Logger::error`.
