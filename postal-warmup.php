<?php
/**
 * Plugin Name: Postal Warmup Pro
 * Plugin URI: https://elianova.com/postal-warmup
 * Description: Plugin professionnel de warmup multi-serveurs Postal avec gestion avancée des templates, statistiques détaillées et monitoring en temps réel.
 * Version: 3.2.1
 * Requires at least: 5.8
 * Requires PHP: 8.1
 * Author: Elianova
 * Author URI: https://elianova.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: postal-warmup
 * Domain Path: /languages
 */

use PostalWarmup\Core\Plugin;
use PostalWarmup\Core\Activator;
use PostalWarmup\Core\Deactivator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Constantes
define( 'PW_VERSION', '3.2.1' );
define( 'WARMUP_PRO_VERSION', '3.2.1' ); // Alias for script versioning
define( 'PW_PLUGIN_FILE', __FILE__ );
define( 'PW_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PW_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'PW_TEMPLATES_DIR', PW_PLUGIN_DIR . 'templates/' );
define( 'PW_ADMIN_DIR', PW_PLUGIN_DIR . 'admin/' ); // Maintain admin dir for assets and views if needed

// Autoloading
if ( file_exists( PW_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once PW_PLUGIN_DIR . 'vendor/autoload.php';
} else {
	add_action( 'admin_notices', function() {
		?>
		<div class="notice notice-error">
			<p><strong>Postal Warmup Pro :</strong> Les dépendances Composer sont manquantes. Veuillez exécuter <code>composer install</code>.</p>
		</div>
		<?php
	});
	return;
}

// Initialisation d'Action Scheduler (si présent dans vendor)
if ( file_exists( PW_PLUGIN_DIR . 'vendor/woocommerce/action-scheduler/action-scheduler.php' ) ) {
	require_once PW_PLUGIN_DIR . 'vendor/woocommerce/action-scheduler/action-scheduler.php';
}

/**
 * Activation
 */
function activate_postal_warmup() {
	Activator::activate();
}
register_activation_hook( __FILE__, 'activate_postal_warmup' );

/**
 * Désactivation
 */
function deactivate_postal_warmup() {
	Deactivator::deactivate();
}
register_deactivation_hook( __FILE__, 'deactivate_postal_warmup' );

/**
 * Démarrage
 */
function run_postal_warmup() {
	$plugin = new Plugin();
	$plugin->run();
}
run_postal_warmup();
