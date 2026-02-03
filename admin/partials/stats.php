<?php
/**
 * Vue des statistiques détaillées
 */

if (!defined('ABSPATH')) {
    exit;
}

// Période sélectionnée
$days = isset($_GET['days']) ? (int) $_GET['days'] : 30;
$days = max(1, min(365, $days));

// Serveur sélectionné
$server_id = isset($_GET['server']) ? (int) $_GET['server'] : null;

// Récupérer les données
$stats = $server_id 
    ? PW_Database::get_server_stats($server_id, $days)
    : PW_Stats::get_global_stats($days);

$servers = PW_Database::get_servers();
$servers_stats = PW_Stats::get_servers_stats();
$top_templates = PW_Stats::get_top_templates($days, 10);

?>

<div class="wrap">
    <h1>
        <?php _e('Statistiques Détaillées', 'postal-warmup'); ?>
        <button type="button" class="page-title-action" id="pw-export-stats-btn">
            <?php _e('Exporter CSV', 'postal-warmup'); ?>
        </button>
    </h1>
    
    <!-- Filtres -->
    <div class="pw-stats-filters" style="background: #fff; padding: 15px; margin: 20px 0; border: 1px solid #c3c4c7;">
        <form method="get">
            <input type="hidden" name="page" value="postal-warmup-stats">
            
            <div style="display: flex; gap: 15px; align-items: flex-end;">
                <div>
                    <label for="filter-days" style="display: block; margin-bottom: 5px;">
                        <?php _e('Période', 'postal-warmup'); ?>
                    </label>
                    <select name="days" id="filter-days">
                        <option value="7" <?php selected($days, 7); ?>>7 <?php _e('jours', 'postal-warmup'); ?></option>
                        <option value="14" <?php selected($days, 14); ?>>14 <?php _e('jours', 'postal-warmup'); ?></option>
                        <option value="30" <?php selected($days, 30); ?>>30 <?php _e('jours', 'postal-warmup'); ?></option>
                        <option value="60" <?php selected($days, 60); ?>>60 <?php _e('jours', 'postal-warmup'); ?></option>
                        <option value="90" <?php selected($days, 90); ?>>90 <?php _e('jours', 'postal-warmup'); ?></option>
                    </select>
                </div>
                
                <div>
                    <label for="filter-server" style="display: block; margin-bottom: 5px;">
                        <?php _e('Serveur', 'postal-warmup'); ?>
                    </label>
                    <select name="server" id="filter-server">
                        <option value=""><?php _e('Tous les serveurs', 'postal-warmup'); ?></option>
                        <?php foreach ($servers as $server) : ?>
                            <option value="<?php echo $server['id']; ?>" <?php selected($server_id, $server['id']); ?>>
                                <?php echo esc_html($server['domain']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <button type="submit" class="button button-primary">
                        <?php _e('Actualiser', 'postal-warmup'); ?>
                    </button>
                </div>
            </div>
        </form>
    </div>
    
    <!-- Graphique principal -->
    <div class="pw-dashboard-widget" style="margin-bottom: 30px;">
        <div class="pw-widget-header">
            <h2><?php printf(__('Évolution sur %d jours', 'postal-warmup'), $days); ?></h2>
        </div>
        <div class="pw-widget-content" style="height: 300px;">
            <canvas id="pw-evolution-chart" width="400" height="80"></canvas>
        </div>
    </div>
    
    <div class="pw-stats-content" style="display: flex; flex-direction: column; gap: 30px;">
        
        <!-- Performance par Serveur et Préfixe -->
        <div class="pw-dashboard-widget">
            <div class="pw-widget-header">
                <h2><?php _e('Performance par Serveur et Préfixe Email (Détail Postal)', 'postal-warmup'); ?></h2>
            </div>
            <div class="pw-widget-content">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Serveur / Préfixe', 'postal-warmup'); ?></th>
                            <th title="Total Sent (Plugin perspective)"><?php _e('Sent', 'postal-warmup'); ?></th>
                            <th title="Delivered (Postal notification)"><?php _e('Delivered', 'postal-warmup'); ?></th>
                            <th title="Opened (Postal pixel tracking)"><?php _e('Opened', 'postal-warmup'); ?></th>
                            <th title="Clicked (Postal link tracking)"><?php _e('Clicked', 'postal-warmup'); ?></th>
                            <th title="Bounced (Postal notification)"><?php _e('Bounced', 'postal-warmup'); ?></th>
                            <th title="Delayed (Temporary issue)"><?php _e('Delayed', 'postal-warmup'); ?></th>
                            <th title="Held (Limit reached)"><?php _e('Held', 'postal-warmup'); ?></th>
                            <th title="Average Response Time"><?php _e('Latency', 'postal-warmup'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $prefix_stats = PW_Stats::get_server_performance_by_prefix($days);
                        
                        // Fetch postal metrics to merge
                        $postal_perf = PW_Stats::get_template_performance($days);

                        if (empty($prefix_stats)) : ?>
                            <tr><td colspan="9"><?php _e('Aucune donnée détaillée disponible.', 'postal-warmup'); ?></td></tr>
                        <?php else : 
                            $current_server = '';
                            foreach ($prefix_stats as $s) : 
                                $prefix = explode('@', $s['email_from'])[0];
                                $is_new_server = ($current_server !== $s['server_domain']);
                                if ($is_new_server) $current_server = $s['server_domain'];
                                
                                // Merge with postal metrics using prefix as key
                                $p = $postal_perf[$prefix] ?? ['delivered' => 0, 'opened' => 0, 'clicked' => 0, 'bounced' => 0, 'delayed' => 0, 'held' => 0];
                        ?>
                            <?php if ($is_new_server) : ?>
                                <tr class="pw-server-header-row" style="background: #f0f6fc;">
                                    <td colspan="9"><strong><span class="dashicons dashicons-networking"></span> <?php echo esc_html($s['server_domain']); ?></strong></td>
                                </tr>
                            <?php endif; ?>
                            <tr>
                                <td style="padding-left: 25px;"><code><?php echo esc_html($prefix); ?></code></td>
                                <td><?php echo number_format_i18n($s['total_sent']); ?></td>
                                <td><span class="pw-count delivered"><?php echo number_format_i18n($p['delivered']); ?></span></td>
                                <td><span class="pw-count opened"><?php echo number_format_i18n($p['opened']); ?></span></td>
                                <td><span class="pw-count clicked"><?php echo number_format_i18n($p['clicked']); ?></span></td>
                                <td><span class="pw-count bounced"><?php echo number_format_i18n($p['bounced']); ?></span></td>
                                <td><span class="pw-count delayed"><?php echo number_format_i18n($p['delayed']); ?></span></td>
                                <td><span class="pw-count held"><?php echo number_format_i18n($p['held']); ?></span></td>
                                <td><?php echo number_format($s['avg_response_time'], 3); ?>s</td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <!-- Performance par serveur (Résumé) -->
            <div class="pw-dashboard-widget">
                <div class="pw-widget-header">
                    <h2><?php _e('Résumé par Serveur', 'postal-warmup'); ?></h2>
                </div>
                <div class="pw-widget-content">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Serveur', 'postal-warmup'); ?></th>
                                <th><?php _e('Envoyés', 'postal-warmup'); ?></th>
                                <th><?php _e('Succès', 'postal-warmup'); ?></th>
                                <th><?php _e('Taux', 'postal-warmup'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($servers_stats as $server) : ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html($server['domain']); ?></strong>
                                    </td>
                                    <td><?php echo number_format_i18n($server['sent_count']); ?></td>
                                    <td><?php echo number_format_i18n($server['success_count']); ?></td>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <span class="pw-badge <?php echo $server['success_rate'] >= 90 ? 'success' : ($server['success_rate'] >= 70 ? 'warning' : 'error'); ?>">
                                                <?php echo $server['success_rate']; ?>%
                                            </span>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        
        <!-- Templates les plus utilisés -->
        <div class="pw-dashboard-widget">
            <div class="pw-widget-header">
                <h2><?php _e('Templates les Plus Utilisés', 'postal-warmup'); ?></h2>
            </div>
            <div class="pw-widget-content">
                <?php if (empty($top_templates)) : ?>
                    <p class="pw-no-data"><?php _e('Aucune donnée disponible', 'postal-warmup'); ?></p>
                <?php else : ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Template', 'postal-warmup'); ?></th>
                                <th><?php _e('Utilisations', 'postal-warmup'); ?></th>
                                <th><?php _e('Succès', 'postal-warmup'); ?></th>
                                <th><?php _e('Temps moy.', 'postal-warmup'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top_templates as $template) : ?>
                                <tr>
                                    <td>
                                        <code><?php echo esc_html($template['template_used']); ?></code>
                                    </td>
                                    <td><?php echo number_format_i18n($template['usage_count']); ?></td>
                                    <td><?php echo number_format_i18n($template['success_count']); ?></td>
                                    <td><?php echo number_format($template['avg_response_time'], 3); ?>s</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        
    </div>
    
    <!-- Détails par jour -->
    <div class="pw-dashboard-widget" style="margin-top: 30px;">
        <div class="pw-widget-header">
            <h2><?php _e('Détails par Jour', 'postal-warmup'); ?></h2>
        </div>
        <div class="pw-widget-content">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Date', 'postal-warmup'); ?></th>
                        <th><?php _e('Envoyés', 'postal-warmup'); ?></th>
                        <th><?php _e('Succès', 'postal-warmup'); ?></th>
                        <th><?php _e('Erreurs', 'postal-warmup'); ?></th>
                        <th><?php _e('Taux de succès', 'postal-warmup'); ?></th>
                        <th><?php _e('Temps moyen', 'postal-warmup'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($stats)) : ?>
                        <tr>
                            <td colspan="6" class="pw-no-data">
                                <?php _e('Aucune donnée pour cette période', 'postal-warmup'); ?>
                            </td>
                        </tr>
                    <?php else : ?>
                        <?php foreach (array_reverse($stats) as $day) : 
                            $success_rate = $day['total_sent'] > 0 
                                ? round(($day['total_success'] / $day['total_sent']) * 100, 2)
                                : 0;
                        ?>
                            <tr>
                                <td>
                                    <strong><?php echo date_i18n('l j F Y', strtotime($day['date'])); ?></strong>
                                </td>
                                <td><?php echo number_format_i18n($day['total_sent']); ?></td>
                                <td style="color: #46b450;">
                                    <strong><?php echo number_format_i18n($day['total_success']); ?></strong>
                                </td>
                                <td style="color: #dc3232;">
                                    <?php echo number_format_i18n($day['total_errors']); ?>
                                </td>
                                <td>
                                    <span class="pw-badge <?php echo $success_rate >= 90 ? 'success' : ($success_rate >= 70 ? 'warning' : 'error'); ?>">
                                        <?php echo $success_rate; ?>%
                                    </span>
                                </td>
                                <td><?php echo number_format($day['avg_time'], 3); ?>s</td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<input type="hidden" id="pw-export-days" value="<?php echo $days; ?>">

<script>
jQuery(document).ready(function($) {
    // Graphique d'évolution
    const ctx = document.getElementById('pw-evolution-chart').getContext('2d');
    
    const data = <?php echo json_encode(array_reverse($stats)); ?>;
    const labels = data.map(d => d.date);
    const sentData = data.map(d => parseInt(d.total_sent));
    const successData = data.map(d => parseInt(d.total_success));
    const errorData = data.map(d => parseInt(d.total_errors));
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: '<?php _e('Envoyés', 'postal-warmup'); ?>',
                    data: sentData,
                    borderColor: '#2271b1',
                    backgroundColor: 'rgba(34, 113, 177, 0.1)',
                    tension: 0.4,
                    fill: true
                },
                {
                    label: '<?php _e('Succès', 'postal-warmup'); ?>',
                    data: successData,
                    borderColor: '#46b450',
                    backgroundColor: 'rgba(70, 180, 80, 0.1)',
                    tension: 0.4,
                    fill: true
                },
                {
                    label: '<?php _e('Erreurs', 'postal-warmup'); ?>',
                    data: errorData,
                    borderColor: '#dc3232',
                    backgroundColor: 'rgba(220, 50, 50, 0.1)',
                    tension: 0.4,
                    fill: true
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            },
            interaction: {
                mode: 'nearest',
                axis: 'x',
                intersect: false
            }
        }
    });
});
</script>