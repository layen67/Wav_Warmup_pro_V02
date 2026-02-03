<?php
/**
 * Page des statistiques des clics mailto
 * À ajouter dans le menu admin
 */

if (!defined('ABSPATH')) {
    exit;
}

$days = isset($_GET['days']) ? (int) $_GET['days'] : 30;
$days = max(1, min(365, $days));

// Récupérer les stats
$clicks_by_template = PW_Warmup_Mailto::get_clicks_by_template($days);
$clicks_by_page = PW_Warmup_Mailto::get_clicks_by_page($days, 10);
$all_stats = PW_Warmup_Mailto::get_click_stats($days);

// Calculer le total
$total_clicks = 0;
foreach ($clicks_by_template as $stat) {
    $total_clicks += $stat['total_clicks'];
}

?>

<div class="wrap">
    <h1>
        <?php _e('Statistiques Mailto Warmup', 'postal-warmup'); ?>
    </h1>
    
    <!-- Filtres -->
    <div style="background: #fff; padding: 15px; margin: 20px 0; border: 1px solid #c3c4c7;">
        <form method="get">
            <input type="hidden" name="page" value="postal-warmup-mailto-stats">
            <div style="display: flex; gap: 15px; align-items: flex-end;">
                <div>
                    <label for="filter-days" style="display: block; margin-bottom: 5px;">
                        <?php _e('Période', 'postal-warmup'); ?>
                    </label>
                    <select name="days" id="filter-days">
                        <option value="7" <?php selected($days, 7); ?>>7 <?php _e('jours', 'postal-warmup'); ?></option>
                        <option value="30" <?php selected($days, 30); ?>>30 <?php _e('jours', 'postal-warmup'); ?></option>
                        <option value="90" <?php selected($days, 90); ?>>90 <?php _e('jours', 'postal-warmup'); ?></option>
                        <option value="365" <?php selected($days, 365); ?>>1 <?php _e('an', 'postal-warmup'); ?></option>
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
    
    <!-- Total -->
    <div class="pw-stat-card" style="background: #fff; border: 1px solid #c3c4c7; padding: 20px; margin-bottom: 20px;">
        <div style="display: flex; align-items: center; gap: 15px;">
            <div style="width: 60px; height: 60px; border-radius: 50%; background: #2271b1; display: flex; align-items: center; justify-content: center;">
                <span class="dashicons dashicons-email-alt" style="color: white; font-size: 28px;"></span>
            </div>
            <div>
                <div style="font-size: 32px; font-weight: 600; color: #2271b1;">
                    <?php echo number_format_i18n($total_clicks); ?>
                </div>
                <div style="color: #646970;">
                    <?php printf(__('Clics sur les %d derniers jours', 'postal-warmup'), $days); ?>
                </div>
            </div>
        </div>
    </div>
    
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
        
        <!-- Clics par template -->
        <div class="pw-dashboard-widget">
            <div class="pw-widget-header">
                <h2><?php _e('Clics par Template', 'postal-warmup'); ?></h2>
            </div>
            <div class="pw-widget-content">
                <?php if (empty($clicks_by_template)) { ?>
                    <p class="pw-no-data"><?php _e('Aucun clic enregistré.', 'postal-warmup'); ?></p>
                <?php } else { ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Template', 'postal-warmup'); ?></th>
                                <th><?php _e('Clics', 'postal-warmup'); ?></th>
                                <th><?php _e('Pages', 'postal-warmup'); ?></th>
                                <th><?php _e('Dernier clic', 'postal-warmup'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($clicks_by_template as $stat) { ?>
                                <tr>
                                    <td>
                                        <strong><code><?php echo esc_html($stat['template']); ?></code></strong>
                                    </td>
                                    <td>
                                        <span class="pw-badge success">
                                            <?php echo number_format_i18n($stat['total_clicks']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo number_format_i18n($stat['pages_used']); ?></td>
                                    <td>
                                        <small><?php echo human_time_diff(strtotime($stat['last_click']), current_time('timestamp')); ?> <?php _e('ago', 'postal-warmup'); ?></small>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                <?php } ?>
            </div>
        </div>
        
        <!-- Top pages -->
        <div class="pw-dashboard-widget">
            <div class="pw-widget-header">
                <h2><?php _e('Top 10 Pages', 'postal-warmup'); ?></h2>
            </div>
            <div class="pw-widget-content">
                <?php if (empty($clicks_by_page)) { ?>
                    <p class="pw-no-data"><?php _e('Aucun clic enregistré.', 'postal-warmup'); ?></p>
                <?php } else { ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Page', 'postal-warmup'); ?></th>
                                <th><?php _e('Clics', 'postal-warmup'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($clicks_by_page as $i => $stat) { ?>
                                <tr>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <span style="background: #2271b1; color: white; border-radius: 50%; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 600;">
                                                <?php echo $i + 1; ?>
                                            </span>
                                            <a href="<?php echo esc_url($stat['page_url']); ?>" target="_blank" style="text-decoration: none;">
                                                <?php 
                                                $page_title = get_the_title(url_to_postid($stat['page_url']));
                                                echo $page_title ? esc_html($page_title) : esc_html(parse_url($stat['page_url'], PHP_URL_PATH));
                                                ?>
                                                <span class="dashicons dashicons-external" style="font-size: 14px; margin-top: 2px;"></span>
                                            </a>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="pw-badge info">
                                            <?php echo number_format_i18n($stat['clicks']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                <?php } ?>
            </div>
        </div>
        
    </div>
    
    <!-- Graphique d'évolution -->
    <?php if (!empty($all_stats)) { ?>
        <div class="pw-dashboard-widget" style="margin-top: 20px;">
            <div class="pw-widget-header">
                <h2><?php _e('Évolution des Clics', 'postal-warmup'); ?></h2>
            </div>
            <div class="pw-widget-content">
                <canvas id="pw-mailto-chart" width="400" height="150"></canvas>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            const ctx = document.getElementById('pw-mailto-chart').getContext('2d');
            
            // Préparer les données
            const stats = <?php echo json_encode(array_reverse($all_stats)); ?>;
            
            // Grouper par date
            const dateMap = {};
            stats.forEach(function(stat) {
                if (!dateMap[stat.click_date]) {
                    dateMap[stat.click_date] = 0;
                }
                dateMap[stat.click_date] += parseInt(stat.clicks);
            });
            
            const labels = Object.keys(dateMap);
            const data = Object.values(dateMap);
            
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: '<?php _e('Clics', 'postal-warmup'); ?>',
                        data: data,
                        borderColor: '#2271b1',
                        backgroundColor: 'rgba(34, 113, 177, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    }
                }
            });
        });
        </script>
    <?php } ?>
</div>