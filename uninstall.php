<?php
/**
 * Désinstallation du plugin
 * Supprime toutes les données du plugin
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// Supprimer les tables
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}postal_servers");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}postal_logs");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}postal_stats");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}postal_mailto_clicks");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}postal_templates");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}postal_template_folders");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}postal_template_tags");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}postal_template_tag_relations");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}postal_template_versions");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}postal_template_saved_searches");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}postal_metrics");

// Supprimer les options
$options = [
    'pw_version',
    'pw_webhook_secret',
    'pw_webhook_strict_mode',
    'pw_enable_logging',
    'pw_log_retention_days',
    'pw_max_retries',
    'pw_stats_enabled',
    'pw_stats_retention_days',
    'pw_email_notifications',
    'pw_notification_email',
    'pw_daily_report',
    'pw_notify_on_error',
    'pw_daily_limit',
    'pw_rate_limit_per_hour',
    'pw_default_from_name',
    'pw_default_subject',
    'pw_feature_flags',
    'pw_public_stats'
];

foreach ($options as $option) {
    delete_option($option);
}

// Supprimer tous les transients du plugin
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_pw_%'");
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_pw_%'");

// Supprimer les fichiers de logs
$upload_dir = wp_upload_dir();
$log_dir = $upload_dir['basedir'] . '/postal-warmup-logs';

if (is_dir($log_dir)) {
    $files = glob($log_dir . '/*');
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    rmdir($log_dir);
}

// Supprimer les capabilities
$roles = ['administrator', 'editor'];
foreach ($roles as $role_name) {
    $role = get_role($role_name);
    if ($role) {
        $role->remove_cap('manage_postal_warmup');
        $role->remove_cap('view_postal_stats');
        $role->remove_cap('edit_postal_templates');
    }
}

// Supprimer les cron jobs
wp_clear_scheduled_hook('pw_cleanup_old_logs');
wp_clear_scheduled_hook('pw_cleanup_old_stats');
wp_clear_scheduled_hook('pw_daily_report');