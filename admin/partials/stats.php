<?php
/**
 * Vue des statistiques détaillées (Refonte Professionnelle V3)
 * Structure Accordéon Lazy-Load
 */

if (!defined('ABSPATH')) {
    exit;
}

// Période sélectionnée
$days = isset($_GET['days']) ? (int) $_GET['days'] : 30;
$days = max(1, min(365, $days));

$server_id = isset($_GET['server']) ? (int) $_GET['server'] : null;

// Initial Data Fetching
// 1. Global Stats for Charts (Lightweight)
$global_stats = $server_id
    ? PW_Database::get_server_stats($server_id, $days)
    : PW_Stats::get_global_stats($days);

// 2. Server List & Dropdown
$servers = PW_Database::get_servers();

// 3. Top Templates (Global)
$top_templates = PW_Stats::get_top_templates($days, 10);

// 4. Server Headers (Summary for Accordion) - Replaces heavy full load
$server_headers = PW_Stats::get_server_stats_summary_filtered($days);

?>

<div class="wrap pw-stats-page">
    <div class="pw-header-actions" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1 class="wp-heading-inline">
            <?php _e('Statistiques Détaillées', 'postal-warmup'); ?>
        </h1>
        <div class="pw-actions-group">
            <button type="button" id="pw-dark-mode-toggle" class="button">
                <span class="dashicons dashicons-moon"></span> <?php _e('Mode Sombre', 'postal-warmup'); ?>
            </button>
            <button type="button" id="pw-export-pdf-btn" class="button">
                <span class="dashicons dashicons-pdf"></span> <?php _e('Export PDF', 'postal-warmup'); ?>
            </button>
            <button type="button" id="pw-export-csv-btn" class="button">
                <span class="dashicons dashicons-media-spreadsheet"></span> <?php _e('Export CSV', 'postal-warmup'); ?>
            </button>
        </div>
    </div>
    
    <!-- Filtres -->
    <div class="pw-stats-filters pw-card">
        <form id="pw-stats-filter-form">
            <div class="pw-filters-row">
                <div class="pw-filter-group">
                    <label for="filter-days"><?php _e('Période', 'postal-warmup'); ?></label>
                    <select name="days" id="filter-days" class="pw-select">
                        <option value="1" <?php selected($days, 1); ?>><?php _e('Aujourd\'hui', 'postal-warmup'); ?></option>
                        <option value="7" <?php selected($days, 7); ?>><?php _e('7 derniers jours', 'postal-warmup'); ?></option>
                        <option value="30" <?php selected($days, 30); ?>><?php _e('30 derniers jours', 'postal-warmup'); ?></option>
                        <option value="365" <?php selected($days, 365); ?>><?php _e('12 derniers mois', 'postal-warmup'); ?></option>
                    </select>
                </div>
                
                <!-- Server filter hidden if we want to show all in accordion, or used to filter accordion list -->
                <div class="pw-filter-group">
                    <label for="filter-server"><?php _e('Serveur (Focus)', 'postal-warmup'); ?></label>
                    <select name="server" id="filter-server" class="pw-select">
                        <option value=""><?php _e('Tous les serveurs', 'postal-warmup'); ?></option>
                        <?php foreach ($servers as $server) : ?>
                            <option value="<?php echo $server['id']; ?>" <?php selected($server_id, $server['id']); ?>>
                                <?php echo esc_html($server['domain']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </form>
    </div>

    <!-- Tabs Navigation -->
    <h2 class="nav-tab-wrapper">
        <a href="#tab-general" class="nav-tab nav-tab-active" data-tab="general"><?php _e('Général', 'postal-warmup'); ?></a>
        <a href="#tab-charts" class="nav-tab" data-tab="charts"><?php _e('Graphiques Avancés', 'postal-warmup'); ?></a>
        <a href="#tab-heatmap" class="nav-tab" data-tab="heatmap"><?php _e('Heatmap', 'postal-warmup'); ?></a>
    </h2>

    <div id="pw-stats-export-area">

        <!-- Tab: General -->
        <div id="tab-general" class="pw-tab-content active">

            <!-- Graphique principal -->
            <div class="pw-dashboard-widget pw-card" style="margin-top: 20px;">
                <div class="pw-widget-header">
                    <h2><?php _e('Évolution Globale', 'postal-warmup'); ?></h2>
                </div>
                <div class="pw-widget-content" style="height: 300px;">
                    <canvas id="pw-evolution-chart"></canvas>
                </div>
            </div>

            <!-- ACCORDEON: Performance par Serveur -->
            <div class="pw-dashboard-widget pw-card" style="margin-top: 20px;">
                <div class="pw-widget-header">
                    <h2><?php _e('Performance par Serveur et Préfixe Email', 'postal-warmup'); ?></h2>
                    <small style="color: #666;"><?php _e('Cliquez sur un serveur pour voir le détail.', 'postal-warmup'); ?></small>
                </div>

                <div class="pw-accordion-container">
                    <?php if (empty($server_headers)) : ?>
                        <p class="pw-no-data"><?php _e('Aucune donnée pour la période sélectionnée.', 'postal-warmup'); ?></p>
                    <?php else :
                        foreach ($server_headers as $sh) :
                            if ($server_id && $sh['id'] != $server_id) continue;

                            $sent = (int)$sh['total_sent'];
                            $rate = $sent > 0 ? round(($sh['total_success'] / $sent) * 100, 1) : 0;
                            $errors = (int)$sh['total_errors'];
                    ?>
                        <div class="pw-accordion-item" data-server-id="<?php echo $sh['id']; ?>">
                            <div class="pw-accordion-header">
                                <div class="pw-header-title">
                                    <span class="dashicons dashicons-networking"></span>
                                    <strong><?php echo esc_html($sh['domain']); ?></strong>
                                </div>
                                <div class="pw-header-stats">
                                    <span class="pw-stat-pill sent"><?php echo number_format_i18n($sent); ?> sent</span>
                                    <span class="pw-stat-pill <?php echo $rate > 90 ? 'success' : 'warning'; ?>"><?php echo $rate; ?>% success</span>
                                    <?php if ($errors > 0) : ?>
                                        <span class="pw-stat-pill error"><?php echo $errors; ?> err</span>
                                    <?php endif; ?>
                                    <span class="dashicons dashicons-arrow-down-alt2 pw-chevron"></span>
                                </div>
                            </div>
                            <div class="pw-accordion-body">
                                <div class="pw-loading-placeholder">
                                    <span class="spinner is-active" style="float:none;"></span> <?php _e('Chargement des détails...', 'postal-warmup'); ?>
                                </div>
                                <!-- Content populated via AJAX -->
                            </div>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>

            <!-- Bottom Widgets -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">
                <!-- Templates les plus utilisés -->
                <div class="pw-dashboard-widget pw-card">
                    <div class="pw-widget-header">
                        <h2><?php _e('Top Templates', 'postal-warmup'); ?></h2>
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
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($top_templates as $template) : ?>
                                        <tr>
                                            <td><code><?php echo esc_html($template['template_used']); ?></code></td>
                                            <td><?php echo number_format_i18n($template['usage_count']); ?></td>
                                            <td><?php echo number_format_i18n($template['success_count']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Détails par jour (Résumé) -->
                <div class="pw-dashboard-widget pw-card">
                    <div class="pw-widget-header">
                        <h2><?php _e('Historique Récent', 'postal-warmup'); ?></h2>
                    </div>
                    <div class="pw-widget-content">
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php _e('Date', 'postal-warmup'); ?></th>
                                    <th><?php _e('Envoyés', 'postal-warmup'); ?></th>
                                    <th><?php _e('Taux', 'postal-warmup'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $recent_days = array_slice(array_reverse($global_stats), 0, 10);
                                if (empty($recent_days)) : ?>
                                    <tr><td colspan="3"><?php _e('Aucune donnée', 'postal-warmup'); ?></td></tr>
                                <?php else :
                                    foreach ($recent_days as $day) :
                                        $rate = $day['total_sent'] > 0 ? round(($day['total_success'] / $day['total_sent']) * 100) : 0;
                                ?>
                                    <tr>
                                        <td><?php echo date_i18n('d M', strtotime($day['date'])); ?></td>
                                        <td><?php echo number_format_i18n($day['total_sent']); ?></td>
                                        <td>
                                            <span class="pw-badge <?php echo $rate >= 90 ? 'success' : 'warning'; ?>"><?php echo $rate; ?>%</span>
                                        </td>
                                    </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Tab: Advanced Charts -->
        <div id="tab-charts" class="pw-tab-content" style="display: none;">
            <div class="pw-charts-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">
                <div class="pw-chart-card pw-card">
                    <h3><?php _e('Volume d\'envois par jour', 'postal-warmup'); ?></h3>
                    <div class="pw-chart-container"><canvas id="pw-chart-volume"></canvas></div>
                </div>
                <div class="pw-chart-card pw-card">
                    <h3><?php _e('Taux de Délivrabilité', 'postal-warmup'); ?></h3>
                    <div class="pw-chart-container"><canvas id="pw-chart-deliverability"></canvas></div>
                </div>
                <div class="pw-chart-card pw-card">
                    <h3><?php _e('Taux d\'Ouverture', 'postal-warmup'); ?></h3>
                    <div class="pw-chart-container"><canvas id="pw-chart-openrate"></canvas></div>
                </div>
                <div class="pw-chart-card pw-card">
                    <h3><?php _e('Erreurs (Bounces + Failures)', 'postal-warmup'); ?></h3>
                    <div class="pw-chart-container"><canvas id="pw-chart-errors"></canvas></div>
                </div>
            </div>
        </div>

        <!-- Tab: Heatmap -->
        <div id="tab-heatmap" class="pw-tab-content" style="display: none;">
            <div class="pw-dashboard-widget pw-card" style="margin-top: 20px;">
                <div class="pw-widget-header">
                    <h2><?php _e('Activité Horaire par Template', 'postal-warmup'); ?></h2>
                </div>
                <div class="pw-widget-content">
                    <div id="pw-heatmap-container" style="overflow-x: auto;">
                        <div class="pw-loading-placeholder"><?php _e('Chargement de la heatmap...', 'postal-warmup'); ?></div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Updated CSS for Accordion UI -->
<style>
    /* Cards */
    .pw-card {
        background: #fff;
        border: 1px solid #c3c4c7;
        box-shadow: 0 1px 1px rgba(0,0,0,.04);
        padding: 15px;
    }

    /* Filters & Tabs (reused) */
    .pw-filters-row { display: flex; gap: 20px; align-items: flex-end; }
    .pw-filter-group { display: flex; flex-direction: column; gap: 5px; }
    .pw-filter-group label { font-weight: 600; font-size: 13px; color: #1d2327; }
    .pw-select { min-width: 150px; }
    .pw-tab-content { display: none; }
    .pw-tab-content.active { display: block; }

    /* Accordion Styles */
    .pw-accordion-container {
        border-top: 1px solid #f0f0f1;
    }
    .pw-accordion-item {
        border: 1px solid #c3c4c7;
        margin-bottom: 10px;
        background: #fff;
        border-radius: 4px;
        overflow: hidden;
    }
    .pw-accordion-header {
        padding: 15px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: #f6f7f7;
        cursor: pointer;
        transition: background 0.2s ease;
    }
    .pw-accordion-header:hover {
        background: #f0f0f1;
    }
    .pw-accordion-header.active {
        background: #f0f0f1;
        border-bottom: 1px solid #c3c4c7;
    }
    .pw-header-title {
        font-size: 14px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .pw-header-stats {
        display: flex;
        align-items: center;
        gap: 15px;
    }
    .pw-stat-pill {
        background: #e5e5e5;
        border-radius: 12px;
        padding: 2px 10px;
        font-size: 12px;
        font-weight: 500;
        color: #50575e;
    }
    .pw-stat-pill.sent { background: #e6f6ff; color: #0073aa; }
    .pw-stat-pill.success { background: #edf9ef; color: #46b450; }
    .pw-stat-pill.warning { background: #fdf2d8; color: #dba617; }
    .pw-stat-pill.error { background: #fbeaea; color: #d63638; }

    .pw-chevron {
        transition: transform 0.3s ease;
        color: #a7aaad;
    }
    .pw-accordion-header.active .pw-chevron {
        transform: rotate(180deg);
    }

    .pw-accordion-body {
        display: none; /* Lazy load: hidden by default */
        padding: 0;
    }
    .pw-accordion-body table {
        border: none;
        box-shadow: none;
        margin-top: 0;
    }
    .pw-loading-placeholder {
        padding: 20px;
        text-align: center;
        color: #646970;
    }

    /* Table & Progress (reused) */
    th.pw-sortable { cursor: pointer; position: relative; }
    th.pw-sortable:hover { background: #f0f0f1; color: #2271b1; }
    th.pw-sortable.is-sorted .dashicons { opacity: 1; color: #2271b1; }
    .pw-progress-bar { background: #f0f0f1; border-radius: 3px; height: 20px; width: 100%; position: relative; overflow: hidden; display: flex; align-items: center; }
    .pw-progress-fill { height: 100%; position: absolute; left: 0; top: 0; }
    .pw-progress-fill.success { background: #d1e4dd; }
    .pw-progress-fill.warning { background: #fcefdc; }
    .pw-progress-bar span { position: relative; z-index: 1; padding-left: 8px; font-size: 11px; font-weight: 600; color: #1d2327; }
    .pw-rate { color: #646970; font-size: 11px; }
    .pw-chart-container { position: relative; height: 250px; width: 100%; }
    .pw-heatmap-table { width: 100%; border-collapse: collapse; font-size: 11px; }
    .pw-heatmap-table th, .pw-heatmap-table td { border: 1px solid #ddd; padding: 5px; text-align: center; }
    .pw-heatmap-table th.tpl-name { text-align: left; min-width: 150px; background: #f9f9f9; font-weight: bold; }
    .pw-heatmap-cell { width: 30px; height: 20px; display: block; }

    /* Dark Mode Overrides */
    body.pw-dark-mode { background: #1e1e1e; color: #e0e0e0; }
    body.pw-dark-mode .wrap h1, body.pw-dark-mode .pw-filter-group label { color: #e0e0e0; }
    body.pw-dark-mode .pw-card { background: #2d2d2d; border-color: #444; color: #e0e0e0; }
    body.pw-dark-mode .pw-accordion-item { border-color: #444; background: #2d2d2d; }
    body.pw-dark-mode .pw-accordion-header { background: #333; color: #e0e0e0; }
    body.pw-dark-mode .pw-accordion-header:hover { background: #3d3d3d; }
    body.pw-dark-mode .pw-accordion-header.active { background: #3d3d3d; border-bottom-color: #444; }
    body.pw-dark-mode .pw-stat-pill { background: #444; color: #ccc; }
    body.pw-dark-mode .pw-stat-pill.sent { background: #132b3a; color: #72aee6; }
    body.pw-dark-mode .pw-stat-pill.success { background: #1b3a24; color: #46b450; }
    body.pw-dark-mode .pw-stat-pill.warning { background: #3a2e15; color: #f0c33c; }
    body.pw-dark-mode .pw-stat-pill.error { background: #3a1515; color: #d63638; }
    body.pw-dark-mode .wp-list-table { background: #2d2d2d; border-color: #444; color: #e0e0e0; }
    body.pw-dark-mode .wp-list-table th, body.pw-dark-mode .wp-list-table td { border-color: #444; color: #e0e0e0; }
    body.pw-dark-mode .wp-list-table tr:nth-child(odd) { background-color: #2d2d2d; }
    body.pw-dark-mode .wp-list-table tr:nth-child(even) { background-color: #333; }
    body.pw-dark-mode .pw-progress-bar { background: #444; }
    body.pw-dark-mode .pw-progress-bar span { color: #fff; }
    body.pw-dark-mode .nav-tab-wrapper { border-bottom-color: #444; }
    body.pw-dark-mode .nav-tab { background: #2d2d2d; border-color: #444; color: #ccc; }
    body.pw-dark-mode .nav-tab-active { background: #3d3d3d; border-bottom-color: #3d3d3d; color: #fff; }
</style>

<!-- Initial Chart Script -->
<script>
jQuery(document).ready(function($) {
    if ($('#pw-evolution-chart').length) {
        const ctx = document.getElementById('pw-evolution-chart').getContext('2d');
        const data = <?php echo json_encode(array_reverse($global_stats)); ?>;
        if(window.pwInitEvolutionChart) window.pwInitEvolutionChart(ctx, data);
    }
});
</script>
