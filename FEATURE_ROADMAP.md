# Roadmap Fonctionnelle & Intégration Postal

Suite à l'analyse de votre plugin **Postal Warmup Pro** et des capacités natives de **Postal**, voici les opportunités d'amélioration classées par priorité.

## 1. Vue d'Ensemble
Votre plugin utilise actuellement une fraction des capacités de Postal (principalement l'envoi basique et la réception de webhooks). Postal offre des fonctionnalités puissantes de **metadata**, **tagging**, **gestion de suppression** et **monitoring** qui peuvent transformer votre plugin en une véritable suite CRM/Délivrabilité.

---

## 2. Améliorations "Quick Wins" (Facile & Impact Immédiat)

Ces fonctionnalités demandent peu de code mais apportent une valeur ajoutée immédiate pour le suivi et l'organisation.

### 🏷️ 1. Tagging des Emails (Message Tagging)
**Fonctionnalité Postal :** Postal permet d'attacher un "Tag" à chaque message pour le filtrer dans l'interface Postal.
**Proposition :** Ajouter automatiquement un tag aux emails envoyés par le plugin.
*   **Intérêt :** Permet de distinguer instantanément les emails de "warmup" des autres emails transactionnels dans l'interface Postal.
*   **Implémentation :** Modifier le payload dans `PW_Postal_Sender::build_payload`.
    ```php
    $payload['tag'] = 'warmup-pro'; // Ou dynamique selon le template
    ```

### ↩️ 2. Gestion du Reply-To
**Fonctionnalité Postal :** Support natif du header `Reply-To`.
**Proposition :** Ajouter un champ "Reply-To" dans l'éditeur de template.
*   **Intérêt :** Essentiel pour le warmup "conversationnel" où l'on veut que les réponses aillent vers une boîte spécifique.
*   **Implémentation :** Ajouter le champ en DB (table templates) et l'injecter dans le payload API.
    ```php
    $payload['reply_to'] = $template['reply_to'];
    ```

### 📋 3. Métadonnées Personnalisées (Custom Headers)
**Fonctionnalité Postal :** Possibilité d'envoyer des headers personnalisés (`X-My-Header`).
**Proposition :** Ajouter un header unique pour tracer l'origine précise.
*   **Intérêt :** Debugging facilité.
*   **Implémentation :**
    ```php
    $payload['headers'] = ['X-Warmup-Source' => 'WordPress-Plugin-v3.1'];
    ```

---

## 3. Améliorations Majeures (Valeur Élevée / Effort Moyen)

Ces fonctions exploitent l'API de Postal pour offrir une interface de gestion directement dans WordPress.

### 🚫 4. Gestionnaire de Suppression List (Suppression List API)
**Fonctionnalité Postal :** Postal maintient une liste noire (bounces, plaintes).
**Proposition :** Créer une page "Délivrabilité" dans le plugin qui liste les adresses bloquées via l'API Postal.
*   **Intérêt :** Permet à l'admin de voir quelles adresses de warmup sont grillées et de les retirer manuellement de la suppression list si c'est un faux positif.
*   **Technique :** Utiliser l'endpoint `GET /api/v1/suppression/list` et `POST /api/v1/suppression/delete`.
*   **Emplacement :** Nouveau sous-menu "Délivrabilité".

### 📊 5. Widget "Santé du Serveur" (Server Stats API)
**Fonctionnalité Postal :** L'API fournit des stats en temps réel sur le serveur (queue size, throughput).
**Proposition :** Ajouter un widget dans le Dashboard WP affichant l'état de santé du serveur Postal.
*   **Intérêt :** Monitoring proactif. Si la queue Postal explose, l'admin le voit tout de suite.
*   **Technique :** Endpoint `GET /api/v1/server` (retourne `messages_processed`, `queue_size`).

---

## 4. Fonctionnalités Avancées (Innovation / Effort Important)

Ces idées positionnent le plugin comme une solution "Enterprise".

### 🔄 6. "Rescue Mode" avec IP Pools
**Fonctionnalité Postal :** Postal permet de gérer des "IP Pools" et de choisir par quel pool envoyer un message.
**Proposition :** Si le taux de succès chute sous 80%, basculer automatiquement l'envoi sur un autre "IP Pool" configuré dans Postal.
*   **Intérêt :** Sauve la réputation d'une IP principale en délestant le trafic.
*   **Technique :**
    1. Ajouter un champ "IP Pool ID" dans la config du serveur WP.
    2. Surveiller les stats via `PW_Stats`.
    3. Si alerte, modifier le paramètre `bounce` ou `ip_pool` (si supporté par l'API Postal spécifique) dans le payload d'envoi.

### 🕵️ 7. Vérification DNS Automatique
**Fonctionnalité Postal :** Postal vérifie les records SPF/DKIM/DMARC.
**Proposition :** Un bouton "Vérifier la config DNS" dans la liste des serveurs.
*   **Intérêt :** Diagnostiquer pourquoi le warmup échoue (souvent un problème DNS).
*   **Technique :** Endpoint `GET /api/v1/domains` -> Check `dns_status`.

### 📨 8. Synchronisation Bidirectionnelle des Logs
**Fonctionnalité Postal :** L'API permet de rechercher des messages (`/api/v1/messages`).
**Proposition :** Au lieu de stocker tous les logs en local (lourd pour la DB WP), ne stocker que les ID et statuts. Pour afficher les détails (corps, headers), faire un appel API à la volée vers Postal.
*   **Intérêt :** Allège considérablement la base de données WordPress (table `postal_logs` qui grossit vite).
*   **Technique :** Refondre `PW_Logs_List_Table` pour faire un appel API `message()` quand on clique sur "Voir détails".

---

## Tableau de Priorisation (Roadmap)

| Priorité | Fonctionnalité | Difficulté | Valeur | API Postal Requise |
| :--- | :--- | :--- | :--- | :--- |
| **P1** | **Tagging "Warmup"** | Faible | Moyenne | `tag` |
| **P1** | **Support Reply-To** | Faible | Haute | `reply_to` |
| **P2** | **Gestion Suppression List** | Moyenne | Très Haute | `/suppression` |
| **P2** | **Check DNS Status** | Moyenne | Haute | `/domains` |
| **P3** | **Monitoring Queue (Dashboard)** | Faible | Moyenne | `/server` |
| **P4** | **IP Pool Switching** | Haute | Très Haute | `pool_id` (si dispo) |
| **P5** | **Logs Distants (Lazy Load)** | Haute | Haute | `/messages` |

## Conclusion
Pour la prochaine version (v3.2 ou v4.0), je recommande de se concentrer sur **le Tagging** (facile) et le **Gestionnaire de Suppression List** (forte valeur ajoutée pour un outil de délivrabilité). Cela ancrera votre plugin comme un outil de pilotage et pas seulement d'envoi.
