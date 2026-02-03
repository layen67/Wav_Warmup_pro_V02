# Webhooks

URL du webhook : `https://votre-site.com/wp-json/postal-warmup/v1/webhook`

## Événements Gérés

*   `MessageSent`
*   `MessageDeliveryFailed`
*   `MessageBounced`
*   `MessageLinkClicked`
*   `MessageLoaded` (Ouverture)
*   `DomainDNSError`

## Configuration Postal

1.  Dans Postal, aller sur le serveur > **Webhooks**.
2.  Ajouter un webhook vers l'URL ci-dessus.
3.  Cocher tous les événements.
4.  Copier la clé publique (si RSA) ou configurer un secret HTTP (recommandé pour ce plugin).
5.  Configurer le secret dans le plugin WordPress.
