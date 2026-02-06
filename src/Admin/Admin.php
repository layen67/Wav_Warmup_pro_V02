<?php

namespace PostalWarmup\Admin;

use PostalWarmup\Models\Database;
use PostalWarmup\Models\Stats;
use PostalWarmup\Services\Logger;
use PostalWarmup\Services\Encryption;
use PostalWarmup\API\Sender;
use PostalWarmup\API\Client;

/**
 * Classe de l'interface d'administration
 */
class Admin {

	private $version;

	public function __construct( $version ) {
		$this->version = $version;
	}

	public function add_admin_menu() {
		add_menu_page(
			__( 'Postal Warmup', 'postal-warmup' ),
			__( 'Postal Warmup', 'postal-warmup' ),
			'manage_options',
			'postal-warmup',
			[ $this, 'display_dashboard' ],
			'dashicons-email-alt',
			26
		);
		add_submenu_page( 'postal-warmup', __( 'Tableau de bord', 'postal-warmup' ), __( 'Tableau de bord', 'postal-warmup' ), 'manage_options', 'postal-warmup', [ $this, 'display_dashboard' ] );
		add_submenu_page( 'postal-warmup', __( 'Serveurs', 'postal-warmup' ), __( 'Serveurs', 'postal-warmup' ), 'manage_options', 'postal-warmup-servers', [ $this, 'display_servers' ] );
		add_submenu_page( 'postal-warmup', __( 'Templates', 'postal-warmup' ), __( 'Templates', 'postal-warmup' ), 'manage_options', 'postal-warmup-templates', [ $this, 'display_templates' ] );
		add_submenu_page( 'postal-warmup', __( 'Statistiques', 'postal-warmup' ), __( 'Statistiques', 'postal-warmup' ), 'manage_options', 'postal-warmup-stats', [ $this, 'display_stats' ] );
		add_submenu_page( 'postal-warmup', __( 'Logs', 'postal-warmup' ), __( 'Logs', 'postal-warmup' ), 'manage_options', 'postal-warmup-logs', [ $this, 'display_logs' ] );
		add_submenu_page( 'postal-warmup', __( 'Paramètres', 'postal-warmup' ), __( 'Paramètres', 'postal-warmup' ), 'manage_options', 'postal-warmup-settings', [ $this, 'display_settings' ] );
	}

	public function enqueue_styles( $hook ) {
		if ( strpos( $hook, 'postal-warmup' ) === false ) return;
		
		$is_dev = defined('WP_DEBUG') && WP_DEBUG === true;
		$script_version = $is_dev ? time() : WARMUP_PRO_VERSION;

		wp_enqueue_style( 'pw-admin', PW_PLUGIN_URL . 'admin/assets/css/admin.css', [], $script_version );
		if ( strpos( $hook, 'postal-warmup-templates' ) !== false || $hook === 'toplevel_page_postal-warmup' ) {
			wp_enqueue_style( 'pw-templates', PW_PLUGIN_URL . 'admin/assets/css/templates.css', [ 'pw-admin' ], $script_version );
		}
	}

	public function enqueue_scripts( $hook ) {
		if ( strpos( $hook, 'postal-warmup' ) === false ) return;
		
		$is_dev = defined('WP_DEBUG') && WP_DEBUG === true;
		$script_version = $is_dev ? time() : WARMUP_PRO_VERSION;

		// Utilisation locale de Chart.js si possible (Voir Roadmap Phase 1)
		if ( file_exists( PW_PLUGIN_DIR . 'admin/assets/js/chart.umd.min.js' ) ) {
			wp_enqueue_script( 'pw-chartjs', PW_PLUGIN_URL . 'admin/assets/js/chart.umd.min.js', [], '4.4.0', true );
		} else {
			wp_enqueue_script( 'pw-chartjs', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js', [], '4.4.0', true );
		}

		if ( strpos( $hook, 'postal-warmup-stats' ) !== false ) {
			wp_enqueue_script( 'pw-jspdf', 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js', [], '2.5.1', true );
			wp_enqueue_script( 'pw-html2canvas', 'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js', [], '1.4.1', true );
		}

		wp_enqueue_script( 'pw-admin', PW_PLUGIN_URL . 'admin/assets/js/admin.js', [ 'jquery', 'pw-chartjs' ], $script_version, true );
		
		if ( strpos( $hook, 'postal-warmup-templates' ) !== false || $hook === 'toplevel_page_postal-warmup' ) {
			wp_enqueue_script( 'pw-templates', PW_PLUGIN_URL . 'admin/assets/js/templates-manager.js', [ 'jquery', 'pw-admin' ], $script_version, true );
		}

		$uncategorized_id = TemplateManager::ensure_uncategorized_folder();

		wp_localize_script( 'pw-admin', 'pwAdmin', [
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'pw_admin_nonce' ),
			'uncategorized_id' => $uncategorized_id,
			'i18n'    => [
				'confirm_delete' => __( 'Êtes-vous sûr de vouloir supprimer cet élément ?', 'postal-warmup' ),
				'testing'        => __( 'Test en cours...', 'postal-warmup' ),
				'success'        => __( 'Succès !', 'postal-warmup' ),
				'error'          => __( 'Erreur !', 'postal-warmup' ),
			]
		]);
	}

	// Views
	public function display_dashboard() { require_once PW_ADMIN_DIR . 'partials/dashboard.php'; }
	public function display_servers() { require_once PW_ADMIN_DIR . 'partials/servers.php'; }
	public function display_templates() { require_once PW_ADMIN_DIR . 'partials/templates.php'; }
	public function display_stats() { require_once PW_ADMIN_DIR . 'partials/stats.php'; }
	public function display_logs() { require_once PW_ADMIN_DIR . 'partials/logs.php'; }
	public function display_settings() { require_once PW_ADMIN_DIR . 'partials/settings.php'; }

	public function display_admin_notices() {
		if ( get_transient( 'pw_activation_notice' ) ) {
			echo '<div class="notice notice-success is-dismissible"><p><strong>Postal Warmup Pro activé !</strong> Ajoutez vos serveurs.</p></div>';
			delete_transient( 'pw_activation_notice' );
		}
	}
}
