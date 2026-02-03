<?php
/**
 * Vue de la page des logs
 * FICHIER COMPLET - Version fonctionnelle
 */

if (!defined('ABSPATH')) {
    exit;
}

// Filtres
$level_filter = isset($_GET['level']) ? sanitize_text_field($_GET['level']) : '';
$server_filter = isset($_GET['server']) ? (int) $_GET['server'] : 0;
$per_page = 50;
$page = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;

// Récupérer les logs
$filters = [];
if ($level_filter) {
    $filters['level'] = $level_filter;
}
if ($server_filter) {
    $filters['server_id'] = $server_filter;
}

$logs = [];
$total_logs = 0;

if (class_exists('PW_Database')) {
    $logs = PW_Database::get_logs($filters, $per_page, $page);
    // Compter le total (approximatif)
    global $wpdb;
    $table = $wpdb->prefix . 'postal_logs';
    $total_logs = $wpdb->get_var("SELECT COUNT(*) FROM $table");
}

$servers = class_exists('PW_Database') ? PW_Database::get_servers() : [];

$total_pages = ceil($total_logs / $per_page);

?>

<div class="wrap">
    <h1>
        <?php _e('Logs', 'postal-warmup'); ?>
        <button type="button" class="page-title-action" id="pw-clear-logs-btn">
            <?php _e('Vider les logs', 'postal-warmup'); ?>
        </button>
    </h1>
    
    <!-- Filtres -->
    <div class="tablenav top">
        <form method="get" class="alignleft" style="margin: 8px 0; display: flex; gap: 10px;">
            <input type="hidden" name="page" value="postal-warmup-logs">
            
            <select name="level">
                <option value=""><?php _e('Tous les niveaux', 'postal-warmup'); ?></option>
                <option value="DEBUG" <?php selected($level_filter, 'DEBUG'); ?>>DEBUG</option>
                <option value="INFO" <?php selected($level_filter, 'INFO'); ?>>INFO</option>
                <option value="WARNING" <?php selected($level_filter, 'WARNING'); ?>>WARNING</option>
                <option value="ERROR" <?php selected($level_filter, 'ERROR'); ?>>ERROR</option>
                <option value="CRITICAL" <?php selected($level_filter, 'CRITICAL'); ?>>CRITICAL</option>
            </select>
            
            <select name="server">
                <option value="0"><?php _e('Tous les serveurs', 'postal-warmup'); ?></option>
                <?php foreach ($servers as $server) : ?>
                    <option value="<?php echo $server['id']; ?>" <?php selected($server_filter, $server['id']); ?>>
                        <?php echo esc_html($server['domain']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <button type="submit" class="button"><?php _e('Filtrer', 'postal-warmup'); ?></button>
            
            <?php if ($level_filter || $server_filter) : ?>
                <a href="<?php echo admin_url('admin.php?page=postal-warmup-logs'); ?>" class="button">
                    <?php _e('Réinitialiser', 'postal-warmup'); ?>
                </a>
            <?php endif; ?>
        </form>
        
        <div class="tablenav-pages">
            <span class="displaying-num">
                <?php printf(__('%s entrées', 'postal-warmup'), number_format_i18n($total_logs)); ?>
            </span>
            <?php if ($total_pages > 1) : ?>
                <?php
                $page_links = paginate_links([
                    'base' => add_query_arg('paged', '%#%'),
                    'format' => '',
                    'prev_text' => '&laquo;',
                    'next_text' => '&raquo;',
                    'total' => $total_pages,
                    'current' => $page
                ]);
                echo $page_links;
                ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Table des logs -->
    <?php if (empty($logs)) : ?>
        <div class="pw-no-data">
            <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
            <p><?php _e('Aucun log trouvé.', 'postal-warmup'); ?></p>
        </div>
    <?php else : ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 150px;"><?php _e('Date', 'postal-warmup'); ?></th>
                    <th style="width: 80px;"><?php _e('Niveau', 'postal-warmup'); ?></th>
                    <th style="width: 120px;"><?php _e('Serveur', 'postal-warmup'); ?></th>
                    <th><?php _e('Message', 'postal-warmup'); ?></th>
                    <th style="width: 100px;"><?php _e('Template', 'postal-warmup'); ?></th>
                    <th style="width: 80px;"><?php _e('Temps', 'postal-warmup'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log) : 
                    $level_class = '';
                    switch ($log['level']) {
                        case 'ERROR':
                        case 'CRITICAL':
                            $level_class = 'error';
                            break;
                        case 'WARNING':
                            $level_class = 'warning';
                            break;
                        case 'INFO':
                            $level_class = 'info';
                            break;
                        case 'DEBUG':
                            $level_class = 'debug';
                            break;
                    }
                    
                    // Récupérer le domaine du serveur
                    $server_domain = '';
                    if ($log['server_id']) {
                        foreach ($servers as $s) {
                            if ($s['id'] == $log['server_id']) {
                                $server_domain = $s['domain'];
                                break;
                            }
                        }
                    }
                ?>
                    <tr>
                        <td>
                            <small><?php echo esc_html(date_i18n('d/m/Y H:i:s', strtotime($log['created_at']))); ?></small>
                        </td>
                        <td>
                            <span class="pw-badge <?php echo $level_class; ?>">
                                <?php echo esc_html($log['level']); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($server_domain) : ?>
                                <small><?php echo esc_html($server_domain); ?></small>
                            <?php else : ?>
                                <small style="color: #999;">—</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="margin-bottom: 5px;">
                                <?php echo esc_html($log['message']); ?>
                            </div>
                            <?php if ($log['email_to'] || $log['email_from']) : ?>
                                <div style="font-size: 11px; color: #666;">
                                    <?php if ($log['email_from']) : ?>
                                        <strong>De:</strong> <?php echo esc_html($log['email_from']); ?>
                                    <?php endif; ?>
                                    <?php if ($log['email_to']) : ?>
                                        → <strong>À:</strong> <?php echo esc_html($log['email_to']); ?>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($log['template_used']) : ?>
                                <code style="font-size: 11px;"><?php echo esc_html($log['template_used']); ?></code>
                            <?php else : ?>
                                <small style="color: #999;">—</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($log['response_time']) : ?>
                                <small><?php echo number_format($log['response_time'], 3); ?>s</small>
                            <?php else : ?>
                                <small style="color: #999;">—</small>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <!-- Pagination en bas -->
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <?php if ($total_pages > 1) : ?>
                    <?php echo $page_links; ?>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
.pw-badge.debug {
    background: #d1ecf1;
    color: #0c5460;
}
.pw-badge.info {
    background: #d4edda;
    color: #155724;
}
.pw-badge.warning {
    background: #fff3cd;
    color: #856404;
}
.pw-badge.error,
.pw-badge.critical {
    background: #f8d7da;
    color: #721c24;
}
</style>