<?php
/**
 * admin/partials/suppression-list.php
 * Interface de gestion de la liste de suppression Postal
 */

if (!defined('ABSPATH')) exit;

// Récupérer les serveurs pour le select
$servers = PW_Database::get_servers(true);
?>

<div class="wrap pw-suppression-wrap">
    <h1>
        <span class="dashicons dashicons-shield"></span>
        <?php _e('Liste de Suppression Postal', 'postal-warmup'); ?>
    </h1>
    
    <?php if (empty($servers)): ?>
        <div class="notice notice-warning">
            <p><?php _e('Veuillez configurer au moins un serveur actif pour gérer la suppression.', 'postal-warmup'); ?></p>
        </div>
    <?php else: ?>
        
        <!-- Toolbar -->
        <div class="pw-toolbar" style="margin-top: 20px; background: #fff; padding: 15px; border: 1px solid #c3c4c7; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
            <div class="pw-filter-group" style="display: flex; gap: 15px; align-items: center;">
                <label for="pw-suppression-server"><strong><?php _e('Serveur :', 'postal-warmup'); ?></strong></label>
                <select id="pw-suppression-server">
                    <?php foreach ($servers as $server): ?>
                        <option value="<?php echo esc_attr($server['id']); ?>"><?php echo esc_html($server['domain']); ?></option>
                    <?php endforeach; ?>
                </select>
                
                <button id="pw-refresh-suppression" class="button button-secondary">
                    <span class="dashicons dashicons-update"></span> <?php _e('Actualiser', 'postal-warmup'); ?>
                </button>
            </div>
        </div>

        <!-- Table Container -->
        <div id="pw-suppression-container" style="margin-top: 20px;">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Adresse Email', 'postal-warmup'); ?></th>
                        <th><?php _e('Raison', 'postal-warmup'); ?></th>
                        <th><?php _e('Source', 'postal-warmup'); ?></th>
                        <th><?php _e('Date', 'postal-warmup'); ?></th>
                        <th><?php _e('Actions', 'postal-warmup'); ?></th>
                    </tr>
                </thead>
                <tbody id="pw-suppression-list-body">
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 20px;">
                            <span class="spinner is-active" style="float:none; margin:0;"></span> 
                            <?php _e('Chargement...', 'postal-warmup'); ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Template Row (JS) -->
        <script type="text/template" id="pw-suppression-row-tpl">
            <tr>
                <td><strong><%- address %></strong></td>
                <td><span class="pw-badge pw-badge-error"><%- type %></span></td>
                <td><%- source %></td>
                <td><%- timestamp %></td>
                <td>
                    <button class="button button-small pw-delete-suppression" data-address="<%- address %>">
                        <span class="dashicons dashicons-trash"></span> <?php _e('Retirer', 'postal-warmup'); ?>
                    </button>
                </td>
            </tr>
        </script>

        <style>
            .pw-badge { padding: 3px 8px; border-radius: 3px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
            .pw-badge-error { background: #fbe5e5; color: #d63638; }
        </style>

    <?php endif; ?>
</div>
