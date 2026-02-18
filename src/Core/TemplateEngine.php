<?php

namespace PostalWarmup\Core;

use PostalWarmup\Services\TemplateLoader;
use PostalWarmup\Models\Database;

/**
 * Centralized Template Engine
 * Handles template loading, parsing, processing, and preparation.
 */
class TemplateEngine {

	/**
	 * Load and prepare a template for sending.
	 *
	 * @param string $template_name
	 * @param string $domain
	 * @param string $prefix
	 * @param string $to
	 * @return array|WP_Error
	 */
	public static function prepare_template( $template_name, $domain, $prefix, $to ) {
		// 1. Load Template
		$template = TemplateLoader::load( $template_name, $domain );

		// 2. Fallback Logic
		if ( ! $template ) {
			$template = TemplateLoader::load( 'null', $domain );
		}
		if ( ! $template ) {
			$template = TemplateLoader::get_fallback();
			// Ensure it has a name for tracking
			$template['name'] = 'system-fallback';
		}

		// 3. Pick Variants (Random Selection)
		$subject   = self::pick_random( $template['subject'] );
		$text      = self::pick_random( $template['text'] );
		$html      = self::pick_random( $template['html'] );
		$from_name = self::pick_random( $template['from_name'] );

		// 4. Decode Content (Base64 check)
		$subject   = self::maybe_decode( $subject );
		$text      = self::maybe_decode( $text );
		$html      = self::maybe_decode( $html );
		$from_name = self::maybe_decode( $from_name );

		// 5. Prepare Variables
		$vars = [
			'email'  => $to,
			'domain' => $domain,
			'local'  => $prefix,
			'date'   => current_time( 'd/m/Y' ),
			'time'   => current_time( 'H:i' ),
		];

		// 6. Apply Placeholders
		$subject = self::apply_placeholders( $subject, $vars );
		$text    = self::apply_placeholders( $text, $vars );
		$html    = self::apply_placeholders( $html, $vars );

		// 7. Handle Reply-To
		$reply_to = '';
		if ( ! empty( $template['reply_to'] ) ) {
			$reply_to_raw = self::pick_random( $template['reply_to'] );
			$reply_to_raw = self::maybe_decode( $reply_to_raw );
			if ( ! empty( $reply_to_raw ) ) {
				$reply_to = self::apply_placeholders( $reply_to_raw, $vars );
			}
		}

		// 8. Return Normalized Structure
		return [
			'id'        => $template['id'] ?? null,
			'name'      => $template['name'] ?? $template_name,
			'from_name' => $from_name,
			'subject'   => $subject,
			'text'      => $text,
			'html'      => $html,
			'reply_to'  => $reply_to
		];
	}

	/**
	 * Decode Base64 content safely.
	 */
	public static function maybe_decode( $string ) {
		if ( ! is_string( $string ) || empty( $string ) ) return $string;

		// Optimization: If it has spaces (and not newlines), it's likely not a raw Base64 string suitable for storage
		if ( strpos( $string, ' ' ) !== false ) return $string;

		// Try to decode if it looks like Base64
		if ( preg_match( '/^[a-zA-Z0-9\/\r\n+]*={0,2}$/', $string ) ) {
			$decoded = base64_decode( $string, true );
			if ( $decoded !== false ) {
				// Robustness: Only accept if valid UTF-8
				if ( mb_check_encoding( $decoded, 'UTF-8' ) ) {
					return $decoded;
				}
			}
		}

		return $string;
	}

	public static function pick_random( $array ) {
		if ( ! is_array( $array ) || empty( $array ) ) return '';
		return $array[ array_rand( $array ) ];
	}

	public static function apply_placeholders( $text, $vars ) {
		foreach ( $vars as $key => $value ) {
			$text = str_replace( "{{{$key}}}", $value, $text );
		}
		return $text;
	}
}
