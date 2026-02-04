<?php
/**
 * Vue de la page des paramètres
 */

if (!defined('ABSPATH')) {
    exit;
}

?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <?php settings_errors('postal-warmup-settings'); ?>
    
    <form method="post" action="options.php">
        <?php
        settings_fields('postal-warmup-settings');
        do_settings_sections('postal-warmup-settings');
        submit_button();
        ?>
    </form>
    
    <!-- Section d'informations système -->
    <div class="pw-form-section" style="margin-top: 40px;">
        <h2><?php _e('Informations Système', 'postal-warmup'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('Version du plugin', 'postal-warmup'); ?></th>
                <td><strong><?php echo PW_VERSION; ?></strong></td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Version WordPress', 'postal-warmup'); ?></th>
                <td><?php echo get_bloginfo('version'); ?></td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Version PHP', 'postal-warmup'); ?></th>
                <td><?php echo PHP_VERSION; ?></td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Extension cURL', 'postal-warmup'); ?></th>
                <td>
                    <?php if (function_exists('curl_version')) : ?>
                        <span style="color: #46b450;">✓ <?php _e('Installée', 'postal-warmup'); ?></span>
                        <?php
                        $curl_version = curl_version();
                        echo ' (v' . esc_html($curl_version['version']) . ')';
                        ?>
                    <?php else : ?>
                        <span style="color: #dc3232;">✗ <?php _e('Non installée', 'postal-warmup'); ?></span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Répertoire des logs', 'postal-warmup'); ?></th>
                <td>
                    <?php
                    $upload_dir = wp_upload_dir();
                    $log_dir = $upload_dir['basedir'] . '/postal-warmup-logs';
                    ?>
                    <code><?php echo esc_html($log_dir); ?></code>
                    <?php if (is_writable($log_dir)) : ?>
                        <span style="color: #46b450;"> ✓ <?php _e('Accessible en écriture', 'postal-warmup'); ?></span>
                    <?php else : ?>
                        <span style="color: #dc3232;"> ✗ <?php _e('Non accessible en écriture', 'postal-warmup'); ?></span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Endpoint de test', 'postal-warmup'); ?></th>
                <td>
                    <a href="<?php echo esc_url(rest_url('postal-warmup/v1/test')); ?>" target="_blank">
                        <?php echo esc_html(rest_url('postal-warmup/v1/test')); ?>
                    </a>
                </td>
            </tr>
        </table>
    </div>
    
    <!-- Section des tâches CRON -->
    <div class="pw-form-section" style="margin-top: 20px;">
        <h2><?php _e('Tâches Planifiées (CRON)', 'postal-warmup'); ?></h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Tâche', 'postal-warmup'); ?></th>
                    <th><?php _e('Fréquence', 'postal-warmup'); ?></th>
                    <th><?php _e('Prochaine exécution', 'postal-warmup'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php
                $cron_jobs = [
                    'pw_cleanup_old_logs' => __('Nettoyage des logs', 'postal-warmup'),
                    'pw_cleanup_old_stats' => __('Nettoyage des statistiques', 'postal-warmup'),
                    'pw_daily_report' => __('Rapport quotidien', 'postal-warmup'),
                ];
                
                foreach ($cron_jobs as $hook => $label) :
                    $next_run = wp_next_scheduled($hook);
                ?>
                    <tr>
                        <td><strong><?php echo esc_html($label); ?></strong></td>
                        <td>
                            <?php
                            $schedules = wp_get_schedules();
                            $schedule = wp_get_schedule($hook);
                            echo isset($schedules[$schedule]) ? esc_html($schedules[$schedule]['display']) : __('Non planifiée', 'postal-warmup');
                            ?>
                        </td>
                        <td>
                            <?php
                            if ($next_run) {
                                echo human_time_diff($next_run, current_time('timestamp')) . ' ' . __('(depuis maintenant)', 'postal-warmup');
                                echo '<br><small>' . date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $next_run) . '</small>';
                            } else {
                                echo '<span style="color: #dc3232;">' . __('Non planifiée', 'postal-warmup') . '</span>';
                            }
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Actions de maintenance -->
    <div class="pw-form-section" style="margin-top: 20px;">
        <h2><?php _e('Maintenance', 'postal-warmup'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('Cache', 'postal-warmup'); ?></th>
                <td>
                    <button type="button" class="button" id="pw-clear-cache-btn">
                        <?php _e('Vider le cache', 'postal-warmup'); ?>
                    </button>
                    <p class="description">
                        <?php _e('Vide le cache des serveurs et des statistiques.', 'postal-warmup'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Logs', 'postal-warmup'); ?></th>
                <td>
                    <button type="button" class="button" id="pw-clear-logs-btn">
                        <?php _e('Supprimer tous les logs', 'postal-warmup'); ?>
                    </button>
                    <p class="description">
                        <?php _e('Supprime tous les logs (fichiers et base de données).', 'postal-warmup'); ?>
                    </p>
                </td>
            </tr>
        </table>
    </div>
</div>