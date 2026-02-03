<?php
/**
 * Vue du tableau de bord
 */

if (!defined('ABSPATH')) {
    exit;
}

// Récupérer les stats
$stats = PW_Stats::get_dashboard_stats();
$recent_errors = PW_Stats::get_recent_errors(5);

?>
<div class="wrap pw-dashboard">
    <h1>
        <?php _e('Tableau de bord - Postal Warmup', 'postal-warmup'); ?>
        <a href="<?php echo admin_url('admin.php?page=postal-warmup-servers&action=add'); ?>" class="page-title-action">
            <?php _e('Ajouter un serveur', 'postal-warmup'); ?>
        </a>
    </h1>

    <!-- Statistiques principales -->
    <div class="pw-stats-cards">
        <div class="pw-stat-card">
            <div class="pw-stat-icon">
                <span class="dashicons dashicons-email-alt"></span>
            </div>
            <div class="pw-stat-content">
                <div class="pw-stat-value"><?php echo number_format_i18n($stats['total_sent']); ?></div>
                <div class="pw-stat-label"><?php _e('Emails envoyés', 'postal-warmup'); ?></div>
            </div>
        </div>

        <div class="pw-stat-card">
            <div class="pw-stat-icon success">
                <span class="dashicons dashicons-yes-alt"></span>
            </div>
            <div class="pw-stat-content">
                <div class="pw-stat-value"><?php echo $stats['success_rate']; ?>%</div>
                <div class="pw-stat-label"><?php _e('Taux de succès', 'postal-warmup'); ?></div>
            </div>
        </div>

        <div class="pw-stat-card">
            <div class="pw-stat-icon primary">
                <span class="dashicons dashicons-admin-site-alt3"></span>
            </div>
            <div class="pw-stat-content">
                <div class="pw-stat-value">
                    <?php echo $stats['active_servers']; ?> / <?php echo $stats['total_servers']; ?>
                </div>
                <div class="pw-stat-label"><?php _e('Serveurs actifs', 'postal-warmup'); ?></div>
            </div>
        </div>

        <div class="pw-stat-card">
            <div class="pw-stat-icon <?php echo $stats['evolution'] >= 0 ? 'success' : 'error'; ?>">
                <span class="dashicons dashicons-chart-line"></span>
            </div>
            <div class="pw-stat-content">
                <div class="pw-stat-value">
                    <?php echo $stats['sent_today']; ?>
                    <small style="font-size: 14px; color: <?php echo $stats['evolution'] >= 0 ? '#46b450' : '#dc3232'; ?>">
                        (<?php echo $stats['evolution'] >= 0 ? '+' : ''; ?><?php echo $stats['evolution']; ?>%)
                    </small>
                </div>
                <div class="pw-stat-label"><?php _e('Aujourd\'hui', 'postal-warmup'); ?></div>
            </div>
        </div>
    </div>

    <div class="pw-dashboard-grid">
        
        <!-- Graphique des envois -->
        <div class="pw-dashboard-widget">
            <div class="pw-widget-header">
                <h2><?php _e('Envois des 7 derniers jours', 'postal-warmup'); ?></h2>
                <select id="pw-chart-period" class="pw-period-select">
                    <option value="7"><?php _e('7 jours', 'postal-warmup'); ?></option>
                    <option value="14"><?php _e('14 jours', 'postal-warmup'); ?></option>
                    <option value="30"><?php _e('30 jours', 'postal-warmup'); ?></option>
                </select>
            </div>
            <div class="pw-widget-content">
                <canvas id="pw-sends-chart" width="400" height="200"></canvas>
            </div>
        </div>

        <!-- Serveurs actifs -->
        <div class="pw-dashboard-widget">
            <div class="pw-widget-header">
                <h2><?php _e('Serveurs actifs', 'postal-warmup'); ?></h2>
                <a href="<?php echo admin_url('admin.php?page=postal-warmup-servers'); ?>">
                    <?php _e('Voir tous', 'postal-warmup'); ?> &rarr;
                </a>
            </div>
            <div class="pw-widget-content">
                <?php
                $servers = PW_Stats::get_servers_stats();
                if (empty($servers)) :
                ?>
                    <p class="pw-no-data">
                        <?php _e('Aucun serveur configuré', 'postal-warmup'); ?>
                        <a href="<?php echo admin_url('admin.php?page=postal-warmup-servers&action=add'); ?>">
                            <?php _e('Ajouter un serveur', 'postal-warmup'); ?>
                        </a>
                    </p>
                <?php else : ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Domaine', 'postal-warmup'); ?></th>
                                <th><?php _e('Envoyés', 'postal-warmup'); ?></th>
                                <th><?php _e('Succès', 'postal-warmup'); ?></th>
                                <th><?php _e('Taux', 'postal-warmup'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($servers, 0, 5) as $server) : ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html($server['domain']); ?></strong>
                                    </td>
                                    <td><?php echo number_format_i18n($server['sent_count']); ?></td>
                                    <td><?php echo number_format_i18n($server['success_count']); ?></td>
                                    <td>
                                        <span class="pw-badge <?php echo $server['success_rate'] >= 90 ? 'success' : 'warning'; ?>">
                                            <?php echo $server['success_rate']; ?>%
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Erreurs récentes -->
        <div class="pw-dashboard-widget">
            <div class="pw-widget-header">
                <h2><?php _e('Erreurs récentes', 'postal-warmup'); ?></h2>
                <a href="<?php echo admin_url('admin.php?page=postal-warmup-logs'); ?>">
                    <?php _e('Voir tous les logs', 'postal-warmup'); ?> &rarr;
                </a>
            </div>
            <div class="pw-widget-content">
                <?php if (empty($recent_errors)) : ?>
                    <p class="pw-no-data">
                        <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                        <?php _e('Aucune erreur récente', 'postal-warmup'); ?>
                    </p>
                <?php else : ?>
                    <ul class="pw-errors-list">
                        <?php foreach ($recent_errors as $error) : ?>
                            <li class="pw-error-item">
                                <div class="pw-error-level">
                                    <span class="pw-badge error"><?php echo esc_html($error['level']); ?></span>
                                </div>
                                <div class="pw-error-details">
                                    <div class="pw-error-message"><?php echo esc_html($error['message']); ?></div>
                                    <div class="pw-error-meta">
                                        <?php if ($error['server_domain']) : ?>
                                            <span><?php echo esc_html($error['server_domain']); ?></span> •
                                        <?php endif; ?>
                                        <span><?php echo human_time_diff(strtotime($error['created_at']), current_time('timestamp')); ?> <?php _e('ago', 'postal-warmup'); ?></span>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <!-- Informations système -->
        <div class="pw-dashboard-widget">
            <div class="pw-widget-header">
                <h2><?php _e('Informations système', 'postal-warmup'); ?></h2>
            </div>
            <div class="pw-widget-content">
                <table class="pw-info-table">
                    <tr>
                        <td><?php _e('Version du plugin', 'postal-warmup'); ?></td>
                        <td><strong><?php echo PW_VERSION; ?></strong></td>
                    </tr>
                    <tr>
                        <td><?php _e('URL du webhook', 'postal-warmup'); ?></td>
                        <td>
                            <code style="font-size: 11px;">
                                <?php echo rest_url('postal-warmup/v1/webhook'); ?>
                            </code>
                            <button type="button" class="button button-small pw-copy-btn" data-clipboard="<?php echo esc_attr(rest_url('postal-warmup/v1/webhook')); ?>">
                                <?php _e('Copier', 'postal-warmup'); ?>
                            </button>
                        </td>
                    </tr>
                    <tr>
                        <td><?php _e('Logs actifs', 'postal-warmup'); ?></td>
                        <td>
                            <?php if (get_option('pw_enable_logging', true)) : ?>
                                <span class="pw-badge success"><?php _e('Activé', 'postal-warmup'); ?></span>
                            <?php else : ?>
                                <span class="pw-badge error"><?php _e('Désactivé', 'postal-warmup'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td><?php _e('Limite quotidienne', 'postal-warmup'); ?></td>
                        <td>
                            <?php 
                            $daily_limit = get_option('pw_daily_limit', 0);
                            echo $daily_limit > 0 ? number_format_i18n($daily_limit) : __('Illimité', 'postal-warmup');
                            ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Graphique des envois
    const ctx = document.getElementById('pw-sends-chart').getContext('2d');
    let chart;
    
    function loadChartData(days) {
        $.post(ajaxurl, {
            action: 'pw_get_stats',
            nonce: pwAdmin.nonce,
            days: days
        }, function(response) {
            if (response.success) {
                const stats = response.data.stats;
                const labels = stats.map(s => s.date);
                const sentData = stats.map(s => parseInt(s.total_sent));
                const successData = stats.map(s => parseInt(s.total_success));
                const errorData = stats.map(s => parseInt(s.total_errors));
                
                if (chart) {
                    chart.destroy();
                }
                
                chart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [
                            {
                                label: '<?php _e('Envoyés', 'postal-warmup'); ?>',
                                data: sentData,
                                borderColor: '#2271b1',
                                backgroundColor: 'rgba(34, 113, 177, 0.1)',
                                tension: 0.4
                            },
                            {
                                label: '<?php _e('Succès', 'postal-warmup'); ?>',
                                data: successData,
                                borderColor: '#46b450',
                                backgroundColor: 'rgba(70, 180, 80, 0.1)',
                                tension: 0.4
                            },
                            {
                                label: '<?php _e('Erreurs', 'postal-warmup'); ?>',
                                data: errorData,
                                borderColor: '#dc3232',
                                backgroundColor: 'rgba(220, 50, 50, 0.1)',
                                tension: 0.4
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'top',
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            }
        });
    }
    
    loadChartData(7);
    
    $('#pw-chart-period').on('change', function() {
        loadChartData($(this).val());
    });
    
    // Copier dans le presse-papier
    $('.pw-copy-btn').on('click', function() {
        const text = $(this).data('clipboard');
        navigator.clipboard.writeText(text).then(function() {
            alert('<?php _e('Copié !', 'postal-warmup'); ?>');
        });
    });
});
</script>