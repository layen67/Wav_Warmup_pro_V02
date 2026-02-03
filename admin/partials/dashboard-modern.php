<?php
/**
 * Tableau de bord moderne (v3.0)
 */

if (!defined('ABSPATH')) {
    exit;
}

$stats = PW_Stats::get_overall_stats();
$recent_logs = PW_Database::get_logs([], 10);
$servers = PW_Database::get_servers(true);
$folders = class_exists('PW_Template_Manager') ? PW_Template_Manager::get_folders_tree() : [];
?>

<div class="wrap pw-modern-admin pw-dashboard">
    <div class="pw-header">
        <h1>
            <span class="dashicons dashicons-dashboard"></span>
            <?php _e('Monitoring Temps Réel Postal', 'postal-warmup'); ?>
            <span class="pw-status-dot pulse"></span>
        </h1>
        <div class="pw-actions">
            <a href="<?php echo admin_url('admin.php?page=postal-warmup-servers&action=add'); ?>" class="button button-primary">
                <span class="dashicons dashicons-plus" style="margin-top:3px;"></span>
                <?php _e('Ajouter un serveur', 'postal-warmup'); ?>
            </a>
            <button class="button" id="pw-refresh-stats-btn">
                <span class="dashicons dashicons-update"></span>
                <?php _e('Rafraîchir', 'postal-warmup'); ?>
            </button>
        </div>
    </div>

    <?php if (!empty($stats['dns_errors'])) : ?>
        <div class="notice notice-error" style="margin-bottom: 20px;">
            <p><strong><span class="dashicons dashicons-warning"></span> ALERTE DNS :</strong> 
            Postal a détecté <?php echo $stats['dns_errors']; ?> erreurs de configuration DNS. 
            Veuillez vérifier vos domaines dans Postal.</p>
        </div>
    <?php endif; ?>

    <!-- Widgets récapitulatifs -->
    <div class="pw-stats-widgets">
        <div class="pw-stat-card">
            <div class="pw-stat-icon sent"><span class="dashicons dashicons-email-alt"></span></div>
            <div class="pw-stat-info">
                <span class="pw-stat-label"><?php _e('Total Envoyés', 'postal-warmup'); ?></span>
                <span class="pw-stat-value" id="pw-total-sent"><?php echo number_format($stats['total_sent']); ?></span>
            </div>
        </div>
        <div class="pw-stat-card">
            <div class="pw-stat-icon success"><span class="dashicons dashicons-yes-alt"></span></div>
            <div class="pw-stat-info">
                <span class="pw-stat-label"><?php _e('Delivered', 'postal-warmup'); ?></span>
                <span class="pw-stat-value" id="pw-delivered-count"><?php echo number_format($stats['delivered'] ?? 0); ?></span>
            </div>
        </div>
        <div class="pw-stat-card">
            <div class="pw-stat-icon performance" style="background: #e3f2fd; color: #0d47a1;"><span class="dashicons dashicons-visibility"></span></div>
            <div class="pw-stat-info">
                <span class="pw-stat-label"><?php _e('Opened', 'postal-warmup'); ?></span>
                <span class="pw-stat-value" id="pw-opened-count"><?php echo number_format($stats['opened'] ?? 0); ?></span>
            </div>
        </div>
        <div class="pw-stat-card">
            <div class="pw-stat-icon warning"><span class="dashicons dashicons-warning"></span></div>
            <div class="pw-stat-info">
                <span class="pw-stat-label"><?php _e('Bounces', 'postal-warmup'); ?></span>
                <span class="pw-stat-value" id="pw-bounce-count"><?php echo number_format($stats['bounces'] ?? 0); ?></span>
            </div>
        </div>
    </div>

    <div class="pw-dashboard-grid">
        <!-- Graphique d'activité -->
        <div class="pw-card pw-chart-card">
            <div class="pw-card-header">
                <h3><?php _e('Activité des 24 dernières heures', 'postal-warmup'); ?></h3>
            </div>
            <div class="pw-card-body">
                <canvas id="pw-activity-chart" height="300"></canvas>
            </div>
        </div>

        <!-- Flux d'activité en temps réel -->
        <div class="pw-card pw-activity-card">
            <div class="pw-card-header">
                <h3><?php _e('Dernières Activités', 'postal-warmup'); ?></h3>
                <span class="pw-live-badge">LIVE</span>
            </div>
            <div class="pw-card-body">
                <ul class="pw-activity-list" id="pw-realtime-activity">
                    <?php 
                    $enriched_activity = PW_Database::get_enriched_activity(10);
                    if (empty($enriched_activity)) : ?>
                        <li class="pw-activity-item">
                            <p class="description"><?php _e('Aucune activité récente.', 'postal-warmup'); ?></p>
                        </li>
                    <?php endif; ?>
                    <?php foreach ($enriched_activity as $log) : ?>
                        <li class="pw-activity-item <?php echo strtolower($log['level']); ?>">
                            <div class="pw-activity-header">
                                <span class="pw-activity-time"><?php echo date('H:i:s', strtotime($log['created_at'])); ?></span>
                                <span class="pw-activity-server" title="<?php echo esc_attr($log['server_domain']); ?>">
                                    [<?php echo esc_html(str_replace('www.', '', $log['server_domain'])); ?>]
                                </span>
                            </div>
                            <div class="pw-activity-content">
                                <span class="pw-activity-msg"><?php echo esc_html($log['message']); ?></span>
                                
                                <?php 
                                $ctx = !empty($log['context']) ? json_decode($log['context'], true) : [];
                                if (!empty($ctx['subject'])) : ?>
                                    <span class="pw-activity-subject">"<?php echo esc_html($ctx['subject']); ?>"</span>
                                <?php endif; ?>

                                <div class="pw-activity-meta">
                                    <?php if ($log['email_from']) : ?>
                                        <span class="pw-activity-from" title="<?php echo esc_attr($log['email_from']); ?>">
                                            <span class="dashicons dashicons-arrow-right-alt"></span>
                                            <strong><?php echo esc_html(explode('@', $log['email_from'])[0]); ?></strong> 
                                            <?php if ($log['server_domain']) : ?>
                                                @ <?php echo esc_html($log['server_domain']); ?>
                                            <?php endif; ?>
                                        </span>
                                    <?php endif; ?>

                                    <?php if ($log['email_to']) : ?>
                                        <span class="pw-activity-to"> -> <?php echo esc_html($log['email_to']); ?></span>
                                    <?php endif; ?>

                                    <?php if ($log['status']) : ?>
                                        <span class="pw-activity-status badge-<?php echo esc_attr($log['status']); ?>">
                                            <?php echo esc_html($log['status']); ?>
                                        </span>
                                    <?php endif; ?>

                                    <?php if (!empty($ctx['bounce_type'])) : ?>
                                        <span class="pw-activity-badge warning"><?php echo esc_html($ctx['bounce_type']); ?></span>
                                    <?php endif; ?>

                                    <?php if (!empty($ctx['message_id'])) : ?>
                                        <span class="pw-activity-id" title="Postal Message ID">#<?php echo esc_html(substr($ctx['message_id'], 0, 8)); ?>...</span>
                                    <?php endif; ?>
                                </div>

                                <?php if (!empty($ctx['details'])) : ?>
                                    <div class="pw-activity-details"><?php echo esc_html($ctx['details']); ?></div>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>

    <div class="pw-dashboard-grid secondary">
        <!-- État des serveurs -->
        <div class="pw-card">
            <div class="pw-card-header">
                <h3><span class="dashicons dashicons-admin-site-alt3" style="font-size:18px; margin-right:5px;"></span> <?php _e('État des Serveurs', 'postal-warmup'); ?></h3>
                <a href="<?php echo admin_url('admin.php?page=postal-warmup-servers'); ?>" class="button button-small"><?php _e('Voir tout', 'postal-warmup'); ?></a>
            </div>
            <div class="pw-card-body">
                <!-- Health Widget Container -->
                <div id="pw-server-health-widget" style="margin-bottom: 15px; border-bottom: 1px solid #f0f0f1; padding-bottom: 15px;">
                    <strong>Postal Queue :</strong> <span id="pw-health-queue">...</span> | 
                    <strong>Throughput :</strong> <span id="pw-health-throughput">...</span>/h
                </div>

                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Domaine', 'postal-warmup'); ?></th>
                            <th><?php _e('Envoyés', 'postal-warmup'); ?></th>
                            <th><?php _e('Succès', 'postal-warmup'); ?></th>
                            <th><?php _e('Dernier Envoi', 'postal-warmup'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($servers as $server) : ?>
                            <tr>
                                <td><strong><?php echo esc_html($server['domain']); ?></strong></td>
                                <td><?php echo $server['sent_count']; ?></td>
                                <td><?php echo $server['success_count']; ?></td>
                                <td><?php echo $server['last_used'] ? human_time_diff(strtotime($server['last_used']), current_time('timestamp')) . ' ' . __('ago', 'postal-warmup') : '-'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Performance par Template -->
        <div class="pw-card">
            <div class="pw-card-header">
                <h3><span class="dashicons dashicons-email-alt" style="font-size:18px; margin-right:5px;"></span> <?php _e('Performance par Template', 'postal-warmup'); ?></h3>
                <a href="<?php echo admin_url('admin.php?page=postal-warmup-templates'); ?>" class="button button-small"><?php _e('Gérer', 'postal-warmup'); ?></a>
            </div>
            <div class="pw-card-body" id="pw-template-performance">
                <?php 
                $performance = PW_Stats::get_template_performance(30);
                if (empty($performance)) : ?>
                    <p class="description"><?php _e('Aucune donnée de performance disponible pour le moment.', 'postal-warmup'); ?></p>
                <?php else : ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Template</th>
                                <th>Sent</th>
                                <th>Open</th>
                                <th>Bounce</th>
                                <th style="text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($performance as $name => $p) : ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html($name); ?></strong>
                                        <?php if ($name === 'null') : ?>
                                            <span class="pw-system-badge" style="font-size: 8px; padding: 1px 4px;">SYSTEM</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $p['sent']; ?></td>
                                    <td><?php echo $p['opened']; ?></td>
                                    <td><?php echo $p['bounced']; ?></td>
                                    <td style="text-align:right;">
                                        <button type="button" class="pw-btn-icon-sm pw-edit-template-btn" data-template-name="<?php echo esc_attr($name); ?>" title="Modifier">
                                            <span class="dashicons dashicons-edit"></span>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.pw-dashboard {
    margin-right: 20px;
}
.pw-status-dot {
    height: 10px;
    width: 10px;
    background-color: #46b450;
    border-radius: 50%;
    display: inline-block;
    margin-left: 10px;
}
.pw-status-dot.pulse {
    box-shadow: 0 0 0 rgba(70, 180, 80, 0.4);
    animation: pulse 2s infinite;
}
@keyframes pulse {
    0% { box-shadow: 0 0 0 0 rgba(70, 180, 80, 0.4); }
    70% { box-shadow: 0 0 0 10px rgba(70, 180, 80, 0); }
    100% { box-shadow: 0 0 0 0 rgba(70, 180, 80, 0); }
}

.pw-stats-widgets {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}
.pw-stat-card {
    background: #fff;
    border-radius: 12px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}
.pw-stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.pw-stat-icon .dashicons { font-size: 24px; width: 24px; height: 24px; }
.pw-stat-icon.sent { background: #e7f0ff; color: #2271b1; }
.pw-stat-icon.success { background: #e7f5e9; color: #1e4620; }
.pw-stat-icon.warning { background: #fff8e1; color: #ff8f00; }
.pw-stat-icon.performance { background: #f3e5f5; color: #7b1fa2; }

.pw-stat-label { display: block; color: #646970; font-size: 13px; margin-bottom: 5px; }
.pw-stat-value { display: block; font-size: 24px; font-weight: bold; color: #1d2327; }

.pw-dashboard-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 25px;
    margin-bottom: 25px;
}
.pw-dashboard-grid.secondary {
    grid-template-columns: 1fr 1fr;
}

.pw-card {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    overflow: hidden;
}
.pw-card-header {
    padding: 15px 20px;
    border-bottom: 1px solid #f0f0f1;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.pw-card-header h3 { margin: 0; font-size: 16px; }
.pw-card-body { padding: 20px; }

.pw-live-badge {
    background: #d63638;
    color: #fff;
    font-size: 10px;
    padding: 2px 6px;
    border-radius: 4px;
    font-weight: bold;
}

.pw-activity-list {
    margin: 0;
    padding: 0;
    list-style: none;
    max-height: 400px;
    overflow-y: auto;
}
.pw-activity-item {
    padding: 12px 0;
    border-bottom: 1px solid #f0f0f1;
    font-size: 13px;
    display: flex;
    flex-direction: column;
    gap: 5px;
}
.pw-activity-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2px; }
.pw-activity-time { color: #8c8f94; font-size: 11px; }
.pw-activity-server { color: var(--pw-primary); font-size: 10px; font-weight: bold; }
.pw-activity-msg { font-weight: 500; }
.pw-activity-subject { font-style: italic; color: #646970; font-size: 12px; margin-left: 5px; }
.pw-activity-meta { margin-top: 5px; font-size: 11px; display: flex; flex-wrap: wrap; align-items: center; gap: 8px; color: #646970; }
.pw-activity-from strong { color: #1d2327; }
.pw-activity-badge { padding: 1px 5px; border-radius: 3px; font-size: 9px; font-weight: bold; background: #f0f0f1; }
.pw-activity-badge.warning { background: #fff8e1; color: #ff8f00; }
.pw-activity-id { font-family: monospace; opacity: 0.6; }
.pw-activity-details { margin-top: 5px; font-size: 11px; color: #d63638; background: #fff5f5; padding: 4px 8px; border-radius: 4px; }
.pw-activity-to { color: #2271b1; }
.pw-activity-status { padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: bold; text-transform: uppercase; }
.pw-activity-status.badge-success { background: #e7f5e9; color: #1e4620; }
.pw-activity-status.badge-error { background: #fdeaea; color: #d63638; }

.pw-activity-item.error .pw-activity-msg { color: #d63638; }

@media (max-width: 1200px) {
    .pw-stats-widgets { grid-template-columns: 1fr 1fr; }
    .pw-dashboard-grid { grid-template-columns: 1fr; }
}
</style>

<?php 
// Include Editor Modal for the "Performance par Template" widget actions
include PW_ADMIN_DIR . 'partials/template-editor-modal.php'; 
?>
