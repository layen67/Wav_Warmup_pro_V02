<?php
/**
 * Vue de gestion des serveurs - VERSION SÉCURISÉE API KEY
 * La clé API n'est JAMAIS affichée, seulement modifiable via modal
 */

if (!defined('ABSPATH')) {
    exit;
}

// Récupérer l'action
$action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';
$server_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    check_admin_referer('pw_server_action');
    
    if (isset($_POST['pw_add_server'])) {
        // Ajout d'un serveur
        $domain = sanitize_text_field($_POST['domain']);
        $api_url = esc_url_raw($_POST['api_url']);
        $api_key = sanitize_text_field($_POST['api_key']);
        $active = !empty($_POST['active']) ? 1 : 0;
        
        if ($domain && $api_url && $api_key) {
            $result = PW_Database::insert_server(array(
                'domain' => $domain,
                'api_url' => $api_url,
                'api_key' => $api_key,
                'active' => $active
            ));
            
            if ($result) {
                echo '<div class="notice notice-success is-dismissible"><p>' . __('Serveur ajouté avec succès.', 'postal-warmup') . '</p></div>';
                $action = '';
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>' . __('Erreur lors de l\'ajout du serveur.', 'postal-warmup') . '</p></div>';
            }
        } else {
            echo '<div class="notice notice-error is-dismissible"><p>' . __('Veuillez remplir tous les champs obligatoires.', 'postal-warmup') . '</p></div>';
        }
    }
    
    if (isset($_POST['pw_edit_server']) && $server_id) {
        // Modification d'un serveur
        $domain = sanitize_text_field($_POST['domain']);
        $api_url = esc_url_raw($_POST['api_url']);
        $active = !empty($_POST['active']) ? 1 : 0;
        
        $data = array(
            'domain' => $domain,
            'api_url' => $api_url,
            'active' => $active
        );
        
        // ⭐ SÉCURITÉ : Ne mettre à jour la clé QUE si une nouvelle est fournie
        if (!empty($_POST['api_key_modified']) && $_POST['api_key_modified'] === '1') {
            $new_api_key = sanitize_text_field($_POST['api_key_new']);
            if (!empty($new_api_key)) {
                $data['api_key'] = $new_api_key;
                PW_Logger::warning("Clé API modifiée pour le serveur #$server_id");
            }
        }
        
        if ($domain && $api_url) {
            $result = PW_Database::update_server($server_id, $data);
            
            if ($result !== false) {
                echo '<div class="notice notice-success is-dismissible"><p>' . __('Serveur mis à jour avec succès.', 'postal-warmup') . '</p></div>';
                if (!empty($data['api_key'])) {
                    echo '<div class="notice notice-info is-dismissible"><p>🔑 ' . __('La clé API a été changée.', 'postal-warmup') . '</p></div>';
                }
                $action = '';
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>' . __('Erreur lors de la mise à jour du serveur.', 'postal-warmup') . '</p></div>';
            }
        }
    }
}

// Suppression
if ($action === 'delete' && $server_id) {
    check_admin_referer('pw_delete_server_' . $server_id);
    
    if (PW_Database::delete_server($server_id)) {
        echo '<div class="notice notice-success is-dismissible"><p>' . __('Serveur supprimé avec succès.', 'postal-warmup') . '</p></div>';
        $action = '';
    }
}

?>

<div class="wrap">
    <h1>
        <?php _e('Serveurs Postal', 'postal-warmup'); ?>
        <?php if ($action !== 'add' && $action !== 'edit') { ?>
            <a href="<?php echo admin_url('admin.php?page=postal-warmup-servers&action=add'); ?>" class="page-title-action">
                <?php _e('Ajouter un serveur', 'postal-warmup'); ?>
            </a>
        <?php } ?>
    </h1>
    
    <?php if ($action === 'add') { ?>
        <!-- Formulaire d'ajout -->
        <div class="pw-form-section">
            <h2><?php _e('Ajouter un serveur Postal', 'postal-warmup'); ?></h2>
            <form method="post" id="pw-server-form">
                <?php wp_nonce_field('pw_server_action'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="domain"><?php _e('Domaine', 'postal-warmup'); ?> *</label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="domain" 
                                   name="domain" 
                                   class="regular-text" 
                                   required 
                                   placeholder="check.example.com">
                            <p class="description">
                                <?php _e('Le domaine configuré dans Postal (ex: check.example.com)', 'postal-warmup'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="api_url"><?php _e('API URL', 'postal-warmup'); ?> *</label>
                        </th>
                        <td>
                            <input type="url" 
                                   id="api_url" 
                                   name="api_url" 
                                   class="regular-text" 
                                   required 
                                   placeholder="https://postal.example.com/api/v1">
                            <p class="description">
                                <?php _e('URL de l\'API Postal (doit se terminer par /api/v1)', 'postal-warmup'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="api_key"><?php _e('API Key', 'postal-warmup'); ?> *</label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="api_key" 
                                   name="api_key" 
                                   class="regular-text" 
                                   required 
                                   placeholder="server-xxxxxxxxxxxxxxxxxxxxx">
                            <p class="description">
                                <?php _e('Clé API serveur Postal (Settings > Credentials)', 'postal-warmup'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Statut', 'postal-warmup'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="active" value="1" checked>
                                <?php _e('Serveur actif', 'postal-warmup'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="submit" name="pw_add_server" class="button button-primary">
                        <?php _e('Ajouter le serveur', 'postal-warmup'); ?>
                    </button>
                    <a href="<?php echo admin_url('admin.php?page=postal-warmup-servers'); ?>" class="button">
                        <?php _e('Annuler', 'postal-warmup'); ?>
                    </a>
                </p>
            </form>
        </div>
        
    <?php } elseif ($action === 'edit' && $server_id) { ?>
        <!-- Formulaire d'édition -->
        <?php
        $server = PW_Database::get_server($server_id);
        if (!$server) {
            echo '<div class="notice notice-error"><p>' . __('Serveur introuvable.', 'postal-warmup') . '</p></div>';
        } else {
        ?>
        <div class="pw-form-section">
            <h2><?php _e('Modifier le serveur', 'postal-warmup'); ?></h2>
            <form method="post" id="pw-server-form">
                <?php wp_nonce_field('pw_server_action'); ?>
                
                <!-- Champs cachés pour la gestion de la clé API -->
                <input type="hidden" name="api_key_modified" id="api_key_modified" value="0">
                <input type="hidden" name="api_key_new" id="api_key_new" value="">
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="domain"><?php _e('Domaine', 'postal-warmup'); ?> *</label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="domain" 
                                   name="domain" 
                                   class="regular-text" 
                                   required 
                                   value="<?php echo esc_attr($server['domain']); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="api_url"><?php _e('API URL', 'postal-warmup'); ?> *</label>
                        </th>
                        <td>
                            <input type="url" 
                                   id="api_url" 
                                   name="api_url" 
                                   class="regular-text" 
                                   required 
                                   value="<?php echo esc_attr($server['api_url']); ?>">
                        </td>
                    </tr>
                    
                    <!-- ⭐ SÉCURITÉ MAXIMALE : Clé API masquée -->
                    <tr>
                        <th scope="row">
                            <label><?php _e('API Key', 'postal-warmup'); ?></label>
                        </th>
                        <td>
                            <div class="pw-api-key-secure">
                                <!-- Affichage masqué -->
                                <input type="password" 
                                       value="••••••••••••••••••••••••••••••••••••••••" 
                                       class="regular-text" 
                                       readonly 
                                       disabled
                                       style="background: #f6f7f7; cursor: not-allowed;">
                                
                                <!-- Bouton pour changer -->
                                <button type="button" 
                                        class="button" 
                                        id="pw-change-api-key-btn"
                                        style="margin-top: 10px;">
                                    <span class="dashicons dashicons-lock" style="margin-top: 3px;"></span>
                                    <?php _e('Changer la clé API', 'postal-warmup'); ?>
                                </button>
                                
                                <!-- Message de sécurité -->
                                <p class="description" style="margin-top: 10px;">
                                    <span class="dashicons dashicons-shield" style="color: #46b450;"></span>
                                    <strong><?php _e('Sécurité renforcée :', 'postal-warmup'); ?></strong>
                                    <?php _e('La clé API actuelle n\'est jamais affichée. Vous pouvez uniquement la remplacer par une nouvelle.', 'postal-warmup'); ?>
                                </p>
                            </div>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Statut', 'postal-warmup'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="active" value="1" <?php checked($server['active'], 1); ?>>
                                <?php _e('Serveur actif', 'postal-warmup'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Statistiques', 'postal-warmup'); ?></th>
                        <td>
                            <p>
                                <strong><?php _e('Emails envoyés :', 'postal-warmup'); ?></strong> 
                                <?php echo number_format_i18n($server['sent_count']); ?>
                            </p>
                            <p>
                                <strong><?php _e('Succès :', 'postal-warmup'); ?></strong> 
                                <?php echo number_format_i18n($server['success_count']); ?>
                            </p>
                            <p>
                                <strong><?php _e('Erreurs :', 'postal-warmup'); ?></strong> 
                                <?php echo number_format_i18n($server['error_count']); ?>
                            </p>
                            <?php 
                            if (!empty($server['last_used'])) {
                                $last_used_timestamp = strtotime($server['last_used']);
                                $date_format = get_option('date_format') . ' ' . get_option('time_format');
                                $formatted_date = date_i18n($date_format, $last_used_timestamp);
                            ?>
                                <p>
                                    <strong><?php _e('Dernière utilisation :', 'postal-warmup'); ?></strong> 
                                    <?php echo $formatted_date; ?>
                                </p>
                            <?php } ?>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="submit" name="pw_edit_server" class="button button-primary">
                        <?php _e('Mettre à jour', 'postal-warmup'); ?>
                    </button>
                    <button type="button" class="button pw-test-server-btn" data-server-id="<?php echo $server['id']; ?>">
                        <?php _e('Tester la connexion', 'postal-warmup'); ?>
                    </button>
                    <a href="<?php echo admin_url('admin.php?page=postal-warmup-servers'); ?>" class="button">
                        <?php _e('Retour', 'postal-warmup'); ?>
                    </a>
                </p>
            </form>
        </div>
        <?php } ?>
        
    <?php } else { ?>
        <!-- Liste des serveurs -->
        <?php
        $servers = PW_Database::get_servers();
        ?>
        <div class="pw-servers-list">
            <?php if (empty($servers)) { ?>
                <div class="pw-no-data">
                    <span class="dashicons dashicons-email-alt"></span>
                    <p><?php _e('Aucun serveur configuré.', 'postal-warmup'); ?></p>
                    <a href="<?php echo admin_url('admin.php?page=postal-warmup-servers&action=add'); ?>" class="button button-primary">
                        <?php _e('Ajouter votre premier serveur', 'postal-warmup'); ?>
                    </a>
                </div>
            <?php } else { ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Domaine', 'postal-warmup'); ?></th>
                            <th><?php _e('API URL', 'postal-warmup'); ?></th>
                            <th><?php _e('Statut', 'postal-warmup'); ?></th>
                            <th><?php _e('Envoyés', 'postal-warmup'); ?></th>
                            <th><?php _e('Succès', 'postal-warmup'); ?></th>
                            <th><?php _e('Taux', 'postal-warmup'); ?></th>
                            <th><?php _e('Actions', 'postal-warmup'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        foreach ($servers as $server) {
                            $success_rate = 0;
                            if (isset($server['sent_count']) && $server['sent_count'] > 0) {
                                $success_rate = round(($server['success_count'] / $server['sent_count']) * 100, 2);
                            }
                            
                            $badge_class = 'error';
                            if ($success_rate >= 90) {
                                $badge_class = 'success';
                            } elseif ($success_rate >= 70) {
                                $badge_class = 'warning';
                            }
                            
                            $delete_url = wp_nonce_url(
                                admin_url('admin.php?page=postal-warmup-servers&action=delete&id=' . $server['id']), 
                                'pw_delete_server_' . $server['id']
                            );
                            $delete_msg = esc_js(__('Êtes-vous sûr de vouloir supprimer ce serveur ?', 'postal-warmup'));
                        ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($server['domain']); ?></strong>
                                </td>
                                <td>
                                    <code style="font-size: 11px;">
                                        <?php echo esc_html($server['api_url']); ?>
                                    </code>
                                </td>
                                <td>
                                    <?php if ($server['active']) { ?>
                                        <span class="pw-badge success">
                                            <span class="dashicons dashicons-yes-alt"></span>
                                            <?php _e('Actif', 'postal-warmup'); ?>
                                        </span>
                                    <?php } else { ?>
                                        <span class="pw-badge error">
                                            <span class="dashicons dashicons-dismiss"></span>
                                            <?php _e('Inactif', 'postal-warmup'); ?>
                                        </span>
                                    <?php } ?>
                                </td>
                                <td><?php echo number_format_i18n($server['sent_count']); ?></td>
                                <td><?php echo number_format_i18n($server['success_count']); ?></td>
                                <td>
                                    <span class="pw-badge <?php echo $badge_class; ?>">
                                        <?php echo $success_rate; ?>%
                                    </span>
                                </td>
                                <td>
                                    <div class="pw-server-actions">
                                        <a href="<?php echo admin_url('admin.php?page=postal-warmup-servers&action=edit&id=' . $server['id']); ?>" class="button button-small">
                                            <?php _e('Modifier', 'postal-warmup'); ?>
                                        </a>
                                        <button type="button" class="button button-small pw-test-server-btn" data-server-id="<?php echo $server['id']; ?>">
                                            <?php _e('Tester', 'postal-warmup'); ?>
                                        </button>
                                        <a href="<?php echo $delete_url; ?>" 
                                           class="button button-small button-link-delete" 
                                           onclick="return confirm('<?php echo $delete_msg; ?>');">
                                            <?php _e('Supprimer', 'postal-warmup'); ?>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            <?php } ?>
        </div>
    <?php } ?>
</div>

<!-- ⭐ MODAL DE CHANGEMENT DE CLÉ API -->
<div id="pw-api-key-modal" class="pw-modal" style="display: none;">
    <div class="pw-modal-overlay"></div>
    <div class="pw-modal-content">
        <div class="pw-modal-header">
            <h2>
                <span class="dashicons dashicons-lock" style="color: #f0b849;"></span>
                <?php _e('Changer la clé API', 'postal-warmup'); ?>
            </h2>
            <button type="button" class="pw-modal-close" id="pw-modal-close">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>
        
        <div class="pw-modal-body">
            <div class="pw-modal-warning">
                <span class="dashicons dashicons-warning" style="color: #f0b849; font-size: 20px;"></span>
                <div>
                    <strong><?php _e('Attention :', 'postal-warmup'); ?></strong>
                    <?php _e('Cette action remplacera définitivement la clé API actuelle. Assurez-vous d\'avoir la nouvelle clé à portée de main.', 'postal-warmup'); ?>
                </div>
            </div>
            
            <div class="pw-modal-field">
                <label for="pw-new-api-key">
                    <?php _e('Nouvelle clé API :', 'postal-warmup'); ?> *
                </label>
                <input type="text" 
                       id="pw-new-api-key" 
                       class="regular-text" 
                       placeholder="server-xxxxxxxxxxxxxxxxxxxxx"
                       autocomplete="off">
                <p class="description">
                    <?php _e('Copiez la nouvelle clé depuis Postal > Settings > Credentials', 'postal-warmup'); ?>
                </p>
            </div>
            
            <div class="pw-modal-field">
                <label for="pw-confirm-api-key">
                    <?php _e('Confirmer la clé API :', 'postal-warmup'); ?> *
                </label>
                <input type="text" 
                       id="pw-confirm-api-key" 
                       class="regular-text" 
                       placeholder="server-xxxxxxxxxxxxxxxxxxxxx"
                       autocomplete="off">
                <p class="description">
                    <?php _e('Saisissez à nouveau la clé pour confirmation', 'postal-warmup'); ?>
                </p>
            </div>
        </div>
        
        <div class="pw-modal-footer">
            <button type="button" class="button" id="pw-modal-cancel">
                <span class="dashicons dashicons-no"></span>
                <?php _e('Annuler', 'postal-warmup'); ?>
            </button>
            <button type="button" class="button button-primary" id="pw-modal-confirm">
                <span class="dashicons dashicons-yes"></span>
                <?php _e('Confirmer le changement', 'postal-warmup'); ?>
            </button>
        </div>
    </div>
</div>