<?php

namespace PostalWarmup\Services;

use PostalWarmup\Models\Database;
use PostalWarmup\Services\TemplateLoader;

class Mailto {

	public function init() {
		add_shortcode( 'postal_warmup', array( $this, 'render_shortcode' ) );
		add_shortcode( 'warmup_mailto', array( $this, 'render_shortcode' ) );
		add_action( 'wp_ajax_pw_mailto_click', array( $this, 'track_click' ) );
		add_action( 'wp_ajax_nopriv_pw_mailto_click', array( $this, 'track_click' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	public function enqueue_scripts() {
		wp_enqueue_script( 'pw-mailto', PW_PLUGIN_URL . 'admin/assets/js/mailto-tracker.js', array( 'jquery' ), PW_VERSION, true );
		wp_localize_script( 'pw-mailto', 'pwMailto', array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'pw_mailto_click' )
		) );
	}

	public function render_shortcode( $atts, $content = null ) {
		$atts = shortcode_atts( array(
			'template' => 'support',
			'label'    => 'Nous contacter',
			'email'    => '', // Override destination email
			'subject'  => '', // Override subject
			'body'     => '', // Override body
			'style'    => '', // Custom CSS style
			'class'    => '', // Custom CSS class
			'track'    => 'true', // Enable click tracking
			'display'  => 'button', // link, button, badge
			'preset'   => 'primary', // primary, success, danger, warning, minimal, gradient
			'server'   => '' // Force a specific server domain
		), $atts, 'postal_warmup' );

		// Load template
		$template_data = TemplateLoader::load( $atts['template'] );
		
		if ( ! $template_data ) {
			// Fallback to system default if specific template is missing
			$template_data = TemplateLoader::get_fallback();
		}

		if ( ! $template_data ) {
			return '<!-- Postal Warmup: Template not found -->';
		}

		// Select Server (Round Robin / Least Used)
		$server = $this->get_server_random( $atts['server'] );
		
		if ( ! $server ) {
			return '<!-- Postal Warmup: No active server found -->';
		}

		// Prepare mailto parts
		$email_prefix = 'contact';
		// Use template name as email prefix if it's a valid simple slug (e.g. "support", "billing") and not a complex ID
		if ( ! empty( $atts['template'] ) && preg_match( '/^[a-z0-9_.-]+$/i', $atts['template'] ) && $atts['template'] !== 'null' ) {
			$email_prefix = $atts['template'];
		}
		
		$email_to = ! empty( $atts['email'] ) ? $atts['email'] : $email_prefix . '@' . $server['domain'];
		$subject = ! empty( $atts['subject'] ) ? $atts['subject'] : TemplateLoader::pick_random( $template_data['mailto_subject'] ?? $template_data['subject'] );
		$body = ! empty( $atts['body'] ) ? $atts['body'] : TemplateLoader::pick_random( $template_data['mailto_body'] ?? $template_data['text'] );

		// Process variables
		$subject = $this->process_variables( $subject );
		$body = $this->process_variables( $body );

		// Build URL
		$mailto_url = 'mailto:' . sanitize_email( $email_to ) . '?subject=' . rawurlencode( $subject ) . '&body=' . rawurlencode( $body );

		// Priority 1: Template Default Label (Overrides content and attribute if set)
		if ( ! empty( $template_data['default_label'] ) ) {
			$atts['label'] = $template_data['default_label'];
		} 
		// Priority 2: Shortcode Content (if no template label)
		elseif ( ! empty( $content ) ) {
			$atts['label'] = do_shortcode( $content );
		}
		// Priority 3: Default Attribute ('Nous contacter') - already set via shortcode_atts

		return $this->generate_html( $mailto_url, $atts, $server );
	}

	private function get_server_random( $force_domain = '' ) {
		if ( ! empty( $force_domain ) ) {
			return Database::get_server_by_domain( $force_domain );
		}
		
		$servers = Database::get_servers( true ); // Only active
		if ( empty( $servers ) ) return null;

		// Sort by usage (least used first)
		usort( $servers, function( $a, $b ) {
			return $a['sent_count'] - $b['sent_count'];
		} );

		// Pick from top 3
		$pool_size = min( 3, count( $servers ) );
		$pool = array_slice( $servers, 0, $pool_size );
		
		return $pool[ array_rand( $pool ) ];
	}

	private function process_variables( $text ) {
		if ( empty( $text ) ) return $text;
		
		$variables = [
			'{{page_title}}' => get_the_title(),
			'{{page_url}}'   => get_permalink(),
			'{{site_name}}'  => get_bloginfo( 'name' ),
			'{{site_url}}'   => get_bloginfo( 'url' ),
			'{{date}}'       => current_time( 'd/m/Y' ),
			'{{time}}'       => current_time( 'H:i' ),
			'{{year}}'       => current_time( 'Y' ),
		];

		if ( is_user_logged_in() ) {
			$user = wp_get_current_user();
			$variables['{{user_name}}'] = $user->display_name;
			$variables['{{user_email}}'] = $user->user_email;
			$variables['{{user_firstname}}'] = $user->first_name;
			$variables['{{user_lastname}}'] = $user->last_name;
		} else {
			$variables['{{user_name}}'] = '';
			$variables['{{user_email}}'] = '';
			$variables['{{user_firstname}}'] = '';
			$variables['{{user_lastname}}'] = '';
		}

		return str_replace( array_keys( $variables ), array_values( $variables ), $text );
	}

	private function generate_html( $mailto_url, $atts, $server ) {
		$label = esc_html( $atts['label'] );
		
		$display = $atts['display'];
		$custom_style = $atts['style'];
		$custom_class = $atts['class'];
		$preset = $atts['preset'];
		$track = $atts['track'] === 'true';

		$preset_styles = [
			'primary' => 'background: #2271b1; color: white; padding: 12px 24px; border-radius: 4px; text-decoration: none; display: inline-block; font-weight: 600; transition: background 0.3s;',
			'success' => 'background: #46b450; color: white; padding: 12px 24px; border-radius: 4px; text-decoration: none; display: inline-block; font-weight: 600; transition: background 0.3s;',
			'danger'  => 'background: #dc3232; color: white; padding: 12px 24px; border-radius: 4px; text-decoration: none; display: inline-block; font-weight: 600; transition: background 0.3s;',
			'warning' => 'background: #f0b849; color: white; padding: 12px 24px; border-radius: 4px; text-decoration: none; display: inline-block; font-weight: 600; transition: background 0.3s;',
			'minimal' => 'background: transparent; color: #2271b1; padding: 12px 24px; border: 2px solid #2271b1; border-radius: 4px; text-decoration: none; display: inline-block; font-weight: 600; transition: all 0.3s;',
			'gradient'=> 'background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 12px 24px; border-radius: 8px; text-decoration: none; display: inline-block; font-weight: 600; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4); transition: all 0.3s;',
		];

		if ( $display === 'link' ) {
			$base_style = 'color: #2271b1; text-decoration: underline;';
		} elseif ( $display === 'badge' ) {
			$base_style = 'background: #f0f6fc; color: #2271b1; padding: 4px 12px; border-radius: 12px; font-size: 12px; text-decoration: none; display: inline-block;';
		} else {
			$base_style = $preset_styles[$preset] ?? $preset_styles['primary'];
		}

		$final_style = $base_style . ( ! empty( $custom_style ) ? ' ' . $custom_style : '' );
		$classes = 'pw-mailto-link' . ( ! empty( $custom_class ) ? ' ' . esc_attr( $custom_class ) : '' );

		$data_attrs = '';
		if ( $track ) {
			$data_attrs = sprintf(
				'data-track="true" data-template="%s" data-server="%s"',
				esc_attr( $atts['template'] ),
				esc_attr( $server['domain'] )
			);
		}

		return sprintf(
			'<a href="%s" class="%s" style="%s" %s>%s</a>',
			esc_url( $mailto_url ),
			$classes,
			esc_attr( $final_style ),
			$data_attrs,
			$label
		);
	}

	public function track_click() {
		check_ajax_referer( 'pw_mailto_click', 'nonce' );
		
		$template = isset( $_POST['template'] ) ? sanitize_text_field( $_POST['template'] ) : '';
		$server_domain = isset( $_POST['server'] ) ? sanitize_text_field( $_POST['server'] ) : '';
		$page_url = isset( $_POST['page_url'] ) ? esc_url_raw( $_POST['page_url'] ) : '';
		
		if ( empty( $template ) || empty( $server_domain ) ) {
			wp_send_json_error( array( 'message' => 'Invalid data' ) );
		}
		
		global $wpdb;
		$table = $wpdb->prefix . 'postal_mailto_clicks';

		// GDPR : Anonymisation ou désactivation
		$ip_address = '';
		if ( ! get_option( 'pw_disable_ip_logging', false ) ) {
			$raw_ip = $this->get_client_ip();
			$ip_address = function_exists( 'wp_privacy_anonymize_ip' ) ? wp_privacy_anonymize_ip( $raw_ip ) : $raw_ip;
		}
		
		$result = $wpdb->insert( $table, array(
			'template'      => $template,
			'server_domain' => $server_domain,
			'page_url'      => $page_url,
			'user_agent'    => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ) : '',
			'ip_address'    => $ip_address,
			'clicked_at'    => current_time( 'mysql' )
		) );
		
		if ( $result ) {
			wp_send_json_success( array( 'message' => 'Click tracked' ) );
		} else {
			wp_send_json_error( array( 'message' => 'Database error' ) );
		}
	}

	private function get_client_ip() {
		$ip = '';
		if ( isset( $_SERVER['HTTP_CLIENT_IP'] ) ) $ip = $_SERVER['HTTP_CLIENT_IP'];
		elseif ( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
		elseif ( isset( $_SERVER['REMOTE_ADDR'] ) ) $ip = $_SERVER['REMOTE_ADDR'];
		return sanitize_text_field( $ip );
	}
}
