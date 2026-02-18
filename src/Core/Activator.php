<?php

namespace PostalWarmup\Core;

use PostalWarmup\Services\Logger;

/**
 * Fired during plugin activation.
 */
class Activator {

	public static function activate() {
		self::check_requirements();
		self::create_tables();
		self::set_default_options();
		self::schedule_cron_jobs();
		flush_rewrite_rules();
		set_transient( 'pw_activation_notice', true, 60 );
	}

	private static function check_requirements() {
		global $wp_version;
		if ( version_compare( $wp_version, '5.8', '<' ) ) {
			deactivate_plugins( plugin_basename( PW_PLUGIN_FILE ) );
			wp_die( 'This plugin requires WordPress 5.8 or higher.', 'Activation Error', [ 'back_link' => true ] );
		}
		if ( version_compare( PHP_VERSION, '8.1', '<' ) ) { // Updated requirement
			deactivate_plugins( plugin_basename( PW_PLUGIN_FILE ) );
			wp_die( 'This plugin requires PHP 8.1 or higher.', 'Activation Error', [ 'back_link' => true ] );
		}
		if ( ! function_exists( 'curl_init' ) ) {
			deactivate_plugins( plugin_basename( PW_PLUGIN_FILE ) );
			wp_die( 'This plugin requires PHP cURL extension.', 'Activation Error', [ 'back_link' => true ] );
		}
	}

	private static function create_tables() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();
		
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// 1. Servers
		$table_servers = $wpdb->prefix . 'postal_servers';
		$sql_servers = "CREATE TABLE $table_servers (
			id int NOT NULL AUTO_INCREMENT,
			domain varchar(255) NOT NULL,
			api_url varchar(255) NOT NULL,
			api_key varchar(255) NOT NULL,
			active tinyint(1) DEFAULT 1 NOT NULL,
			sent_count int DEFAULT 0 NOT NULL,
			success_count int DEFAULT 0 NOT NULL,
			error_count int DEFAULT 0 NOT NULL,
			last_used datetime DEFAULT NULL,
			created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			updated_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY domain (domain),
			KEY idx_active (active)
		) $charset_collate;";
		dbDelta( $sql_servers );

		// 2. Logs
		$table_logs = $wpdb->prefix . 'postal_logs';
		$sql_logs = "CREATE TABLE $table_logs (
			id bigint NOT NULL AUTO_INCREMENT,
			server_id int NOT NULL,
			level varchar(20) NOT NULL,
			message text NOT NULL,
			context longtext DEFAULT NULL,
			email_to varchar(255) DEFAULT NULL,
			email_from varchar(255) DEFAULT NULL,
			template_used varchar(100) DEFAULT NULL,
			status varchar(50) DEFAULT NULL,
			response_time decimal(10,3) DEFAULT NULL,
			created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			PRIMARY KEY  (id),
			KEY idx_server_id (server_id),
			KEY idx_level (level),
			KEY idx_created_at (created_at),
			KEY idx_status (status),
			KEY idx_template_created (template_used, created_at),
			KEY idx_server_created (server_id, created_at)
		) $charset_collate;";
		dbDelta( $sql_logs );

		// 3. Stats
		$table_stats = $wpdb->prefix . 'postal_stats';
		$sql_stats = "CREATE TABLE $table_stats (
			id bigint NOT NULL AUTO_INCREMENT,
			server_id int NOT NULL,
			date date NOT NULL,
			hour tinyint NOT NULL,
			sent_count int DEFAULT 0 NOT NULL,
			success_count int DEFAULT 0 NOT NULL,
			error_count int DEFAULT 0 NOT NULL,
			avg_response_time decimal(10,3) DEFAULT NULL,
			created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY unique_stat (server_id, date, hour),
			KEY idx_date (date),
			KEY idx_server_id (server_id),
			KEY idx_server_date (server_id, date)
		) $charset_collate;";
		dbDelta( $sql_stats );

		// 4. Mailto Clicks (Optional)
		$table_mailto = $wpdb->prefix . 'postal_mailto_clicks';
		$sql_mailto = "CREATE TABLE $table_mailto (
			id bigint NOT NULL AUTO_INCREMENT,
			template varchar(100) NOT NULL,
			server_domain varchar(255) NOT NULL,
			page_url varchar(500) DEFAULT NULL,
			user_agent text DEFAULT NULL,
			ip_address varchar(45) DEFAULT NULL,
			clicked_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			PRIMARY KEY  (id),
			KEY idx_template (template),
			KEY idx_clicked_at (clicked_at)
		) $charset_collate;";
		dbDelta( $sql_mailto );

		// 5. Templates
		$table_templates = $wpdb->prefix . 'postal_templates';
		$sql_templates = "CREATE TABLE $table_templates (
			id bigint NOT NULL AUTO_INCREMENT,
			name varchar(100) NOT NULL,
			data longtext NOT NULL,
			folder_id bigint DEFAULT NULL,
			status varchar(20) DEFAULT 'active',
			is_favorite tinyint(1) DEFAULT 0,
			tags text DEFAULT NULL,
			stats_cache longtext DEFAULT NULL,
			last_used_at datetime DEFAULT NULL,
			usage_count int DEFAULT 0,
			menu_order int DEFAULT 0,
			created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			updated_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			created_by bigint DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY name (name),
			KEY idx_folder (folder_id),
			KEY idx_status (status)
		) $charset_collate;";
		dbDelta( $sql_templates );

		// 6. Template Folders
		$table_folders = $wpdb->prefix . 'postal_template_folders';
		$sql_folders = "CREATE TABLE $table_folders (
			id bigint NOT NULL AUTO_INCREMENT,
			name varchar(100) NOT NULL,
			parent_id bigint DEFAULT NULL,
			color varchar(7) DEFAULT '#2271b1',
			icon varchar(50) DEFAULT 'folder',
			menu_order int DEFAULT 0,
			created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			PRIMARY KEY  (id),
			KEY idx_parent (parent_id)
		) $charset_collate;";
		dbDelta( $sql_folders );

		// 7. Tags
		$table_tags = $wpdb->prefix . 'postal_template_tags';
		$sql_tags = "CREATE TABLE $table_tags (
			id bigint NOT NULL AUTO_INCREMENT,
			name varchar(50) NOT NULL,
			color varchar(7) DEFAULT '#646970',
			usage_count int DEFAULT 0,
			created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY name (name)
		) $charset_collate;";
		dbDelta( $sql_tags );

		// 8. Template Tag Relations
		$table_tag_relations = $wpdb->prefix . 'postal_template_tag_relations';
		$sql_tag_relations = "CREATE TABLE $table_tag_relations (
			template_id bigint NOT NULL,
			tag_id bigint NOT NULL,
			PRIMARY KEY  (template_id, tag_id)
		) $charset_collate;";
		dbDelta( $sql_tag_relations );

		// 9. Template Versions
		$table_versions = $wpdb->prefix . 'postal_template_versions';
		$sql_versions = "CREATE TABLE $table_versions (
			id bigint NOT NULL AUTO_INCREMENT,
			template_id bigint NOT NULL,
			data longtext NOT NULL,
			version_number int NOT NULL,
			comment text DEFAULT NULL,
			diff_summary text DEFAULT NULL,
			created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			created_by bigint NOT NULL,
			PRIMARY KEY  (id),
			KEY idx_template (template_id)
		) $charset_collate;";
		dbDelta( $sql_versions );

		// 10. Metrics
		$table_metrics = $wpdb->prefix . 'postal_metrics';
		$sql_metrics = "CREATE TABLE $table_metrics (
			id bigint NOT NULL AUTO_INCREMENT,
			template_id bigint DEFAULT NULL,
			server_id int DEFAULT NULL,
			event_type varchar(50) NOT NULL,
			count int DEFAULT 1,
			date date NOT NULL,
			created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY unique_metric (template_id, server_id, event_type, date),
			KEY idx_template (template_id),
			KEY idx_server (server_id),
			KEY idx_date (date)
		) $charset_collate;";
		dbDelta( $sql_metrics );

		// 11. Daily Stats Summary (Performance Optimization)
		$table_daily = $wpdb->prefix . 'postal_stats_daily';
		$sql_daily = "CREATE TABLE $table_daily (
			id bigint NOT NULL AUTO_INCREMENT,
			server_id int NOT NULL,
			date date NOT NULL,
			total_sent int DEFAULT 0 NOT NULL,
			total_success int DEFAULT 0 NOT NULL,
			total_error int DEFAULT 0 NOT NULL,
			avg_response_time decimal(10,3) DEFAULT NULL,
			updated_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY unique_daily (server_id, date),
			KEY idx_date (date)
		) $charset_collate;";
		dbDelta( $sql_daily );

		// 12. Permanent Stats History (New Architecture)
		$table_stats_history = $wpdb->prefix . 'postal_stats_history';
		$sql_stats_history = "CREATE TABLE $table_stats_history (
			id bigint NOT NULL AUTO_INCREMENT,
			server_id int NOT NULL,
			template_id bigint DEFAULT NULL,
			message_id varchar(255) DEFAULT NULL,
			email_from varchar(255) DEFAULT NULL,
			event_type varchar(50) NOT NULL,
			timestamp datetime NOT NULL,
			meta longtext DEFAULT NULL,
			created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			PRIMARY KEY  (id),
			KEY idx_server_id (server_id),
			KEY idx_template_id (template_id),
			KEY idx_message_id (message_id),
			KEY idx_email_from (email_from),
			KEY idx_event_type (event_type),
			KEY idx_timestamp (timestamp),
			KEY idx_composite_stats (server_id, template_id, event_type, timestamp)
		) $charset_collate;";
		dbDelta( $sql_stats_history );
	}

	private static function set_default_options() {
		add_option( 'pw_version', PW_VERSION );
		
		// Générer un secret s'il n'existe pas (utilisé comme token de validation GET)
		if ( ! get_option( 'pw_webhook_secret' ) ) {
			update_option( 'pw_webhook_secret', wp_generate_password( 64, false ) );
		}
		
		add_option( 'pw_enable_logging', true );
		add_option( 'pw_log_mode', 'file' ); // Default to file only
		add_option( 'pw_log_retention_days', 30 );
		add_option( 'pw_stats_enabled', true );
		add_option( 'pw_max_retries', 3 );
	}

	private static function schedule_cron_jobs() {
		if ( ! wp_next_scheduled( 'pw_cleanup_old_logs' ) ) {
			wp_schedule_event( time(), 'daily', 'pw_cleanup_old_logs' );
		}
		if ( ! wp_next_scheduled( 'pw_cleanup_old_stats' ) ) {
			wp_schedule_event( time(), 'weekly', 'pw_cleanup_old_stats' );
		}
		if ( ! wp_next_scheduled( 'pw_daily_stats_aggregation' ) ) {
			wp_schedule_event( time(), 'daily', 'pw_daily_stats_aggregation' );
		}
		if ( ! wp_next_scheduled( 'pw_daily_report' ) ) {
			wp_schedule_event( time(), 'daily', 'pw_daily_report' );
		}
	}
}
