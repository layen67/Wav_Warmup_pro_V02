<?php

namespace PostalWarmup\Core;

/**
 * Define the internationalization functionality.
 */
class i18n {

	public function load_plugin_textdomain() {
		load_plugin_textdomain(
			'postal-warmup',
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
		);
	}
}
