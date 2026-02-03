# Sécurité

## Clés API
Les clés API Postal sont chiffrées en base de données via `PostalWarmup\Services\Encryption`.
*   Algorithme : **AES-256-CBC**
*   Clé : Hash SHA-256 de `SECURE_AUTH_KEY` (si défini) ou `site_url()`.
*   Stockage : Base64 (IV + Ciphertext).

## Webhooks
Les webhooks entrants sont vérifiés via `PostalWarmup\API\WebhookHandler`.
*   Validation de signature HMAC (si configurée).
*   Pas de logging des signatures (prévention de fuite).
*   Mode Strict optionnel.

## Sanitization
Toutes les entrées utilisateur (Templates, Configuration) sont nettoyées via :
*   `sanitize_text_field`
*   `wp_kses_post` (pour le HTML des emails)
*   `esc_html` / `esc_attr` à l'affichage.
