<?php

namespace PostalWarmup\Core;

/**
 * Fired during plugin deactivation.
 */
class Deactivator {

	public static function deactivate() {
		wp_clear_scheduled_hook( 'pw_cleanup_old_logs' );
		wp_clear_scheduled_hook( 'pw_cleanup_old_stats' );
		wp_clear_scheduled_hook( 'pw_daily_report' );
	}
}
