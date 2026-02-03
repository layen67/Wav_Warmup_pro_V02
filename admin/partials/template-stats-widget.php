<?php
/**
 * admin/partials/template-stats-widget.php
 * Stats Widget for Sidebar v3.1
 */

if (!defined('ABSPATH')) exit;
?>

<div class="pw-sidebar-section pw-stats-widget">
    <h3>📊 Statistiques</h3>
    <div class="pw-stat-row">
        <span>Total envoyés</span>
        <strong><?php echo number_format($stats_global['total_sent']); ?></strong>
    </div>
    <div class="pw-stat-row">
        <span>Taux succès moyen</span>
        <strong><?php echo $stats_global['avg_success_rate']; ?>%</strong>
    </div>
    <div class="pw-stat-row">
        <span>Template le + utilisé</span>
        <strong title="<?php echo esc_attr($stats_global['top_template']); ?>">
            <?php 
            $top = $stats_global['top_template'];
            echo (strlen($top) > 15) ? esc_html(substr($top, 0, 12)) . '...' : esc_html($top); 
            ?>
        </strong>
    </div>
</div>
