<?php

namespace PostalWarmup\Core;

use PostalWarmup\Admin\Admin;
use PostalWarmup\Admin\AjaxHandler;
use PostalWarmup\Admin\Settings;
use PostalWarmup\API\WebhookHandler;
use PostalWarmup\API\Sender;
use PostalWarmup\Services\Logger;

/**
 * The core plugin class.
 */
class Plugin {

	protected $loader;
	protected $version;

	public function __construct() {
		$this->version = PW_VERSION;
		$this->loader = new Loader();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_api_hooks();
		$this->define_cron_hooks();
	}

	private function set_locale() {
		$plugin_i18n = new i18n();
		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );
	}

	private function define_admin_hooks() {
		$plugin_admin = new Admin( $this->version );
		$plugin_settings = new Settings();
		$ajax_handler = new AjaxHandler();

		$this->loader->add_action( 'admin_menu', $plugin_admin, 'add_admin_menu' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );
		$this->loader->add_action( 'admin_init', $plugin_settings, 'register_settings' );
		$this->loader->add_action( 'admin_notices', $plugin_admin, 'display_admin_notices' );

		// Register all AJAX hooks via the AjaxHandler
		$ajax_actions = [
			'test_server', 'regenerate_secret', 'get_dashboard_data',
			'clear_logs', 'clear_cache', 'export_stats', 'get_all_templates',
			'save_template', 'toggle_favorite', 'get_template', 'get_template_stats', 'delete_template',
			'duplicate_template', 'move_template', 'update_template_status',
			'get_template_versions', 'restore_template_version', 'reorder_templates',
			'bulk_action_templates', 'export_template', 'import_templates',
			'save_category', 'delete_category', 'get_categories',
			'get_suppression_list', 'delete_suppression', 'get_server_health',
			'get_advanced_stats', 'get_stats_table', 'get_server_detail'
		];

		foreach ( $ajax_actions as $action ) {
			$this->loader->add_action( 'wp_ajax_pw_' . $action, $ajax_handler, 'ajax_' . $action );
		}
	}

	private function define_api_hooks() {
		$webhook_handler = new WebhookHandler();
		$this->loader->add_action( 'rest_api_init', $webhook_handler, 'register_routes' );
		
		// Initialize Sender hooks (Action Scheduler)
		$this->loader->add_action( 'init', new Sender(), 'init' );

		// Initialize Mailto Shortcodes & Tracking
		$mailto = new \PostalWarmup\Services\Mailto();
		$mailto->init();
	}

	/**
	 * Register class aliases for backward compatibility with legacy views/partials.
	 */
	private function register_aliases() {
		$aliases = [
			'PW_Database'         => 'PostalWarmup\\Models\\Database',
			'PW_Stats'            => 'PostalWarmup\\Models\\Stats',
			'PW_Logger'           => 'PostalWarmup\\Services\\Logger',
			'PW_Cache'            => 'PostalWarmup\\Services\\Cache',
			'PW_Template_Manager' => 'PostalWarmup\\Admin\\TemplateManager',
			'PW_Folder_Manager'   => 'PostalWarmup\\Admin\\TemplateManager', // Alias for legacy folder logic
			'PW_Template_Loader'  => 'PostalWarmup\\Services\\TemplateLoader',
			'PW_Template_Sync'    => 'PostalWarmup\\Services\\TemplateSync',
			'PW_Activator'        => 'PostalWarmup\\Core\\Activator',
		];

		foreach ( $aliases as $alias => $original ) {
			if ( ! class_exists( $alias ) && class_exists( $original ) ) {
				class_alias( $original, $alias );
			}
		}
	}

	private function define_cron_hooks() {
		$this->loader->add_action( 'pw_cleanup_old_logs', 'PostalWarmup\Services\Logger', 'cleanup_old_logs' );
		$this->loader->add_action( 'pw_daily_report', 'PostalWarmup\Services\EmailNotifications', 'send_daily_report' );
		$this->loader->add_action( 'pw_cleanup_old_stats', 'PostalWarmup\Models\Stats', 'cleanup_old_stats' );
		$this->loader->add_action( 'pw_daily_stats_aggregation', 'PostalWarmup\Models\Stats', 'aggregate_daily_stats' );

		// Self-healing: Ensure daily report is scheduled if missing (Fix for existing installations)
		if ( ! wp_next_scheduled( 'pw_daily_report' ) ) {
			wp_schedule_event( time(), 'daily', 'pw_daily_report' );
		}
	}

	public function run() {
		$this->register_aliases();
		$this->loader->run();
	}
}
