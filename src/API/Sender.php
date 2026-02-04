<?php

namespace PostalWarmup\API;

use PostalWarmup\Models\Database;
use PostalWarmup\Services\Logger;
use PostalWarmup\Admin\TemplateManager;

/**
 * Classe d'envoi des emails via Postal
 */
class Sender {

	/**
	 * Enregistre les hooks pour Action Scheduler
	 */
	public function init() {
		add_action( 'pw_send_email_async', array( $this, 'process_queue' ), 10, 5 );
	}

	/**
	 * Envoie un email via Postal (Asynchrone via Action Scheduler)
	 */
	public static function send( $to, $domain, $prefix = null, $server = null ) {
		
		if ( ! $server ) {
			$server = Database::get_server_by_domain( $domain );
			if ( ! $server ) {
				return [ 'error' => "Serveur introuvable pour le domaine : $domain" ];
			}
		}
		
		if ( $prefix === null ) {
			$prefix = 'support';
		}
		
		if ( function_exists( 'as_schedule_single_action' ) ) {
			$args = array(
				'to'          => $to,
				'domain'      => $domain,
				'prefix'      => $prefix,
				'server_id'   => $server['id'],
				'retry_count' => 0
			);
			
			as_schedule_single_action( time(), 'pw_send_email_async', $args, 'postal-warmup' );
			
			Logger::info( "Email mis en file d'attente", [
				'to'     => $to,
				'domain' => $domain
			]);
			
			return [ 'success' => true, 'queued' => true ];
		} 
		
		// Fallback synchrone
		$sender = new self();
		return $sender->process_queue( $to, $domain, $prefix, $server['id'], 0 );
	}

	/**
	 * Worker
	 */
	public function process_queue( $to, $domain, $prefix, $server_id, $retry_count = 0 ) {
		
		$server = Database::get_server( $server_id );
		if ( ! $server ) {
			Logger::error( "Worker: Serveur introuvable ID $server_id" );
			return [ 'error' => 'Serveur introuvable' ];
		}

		$from_email = $prefix . '@' . $domain;
		
		// Charger le template
		$template = \PostalWarmup\Services\TemplateLoader::load( $prefix, $domain );
		
		// Fallback to 'null' template if specific template not found (Original behavior)
		if ( ! $template ) {
			$template = \PostalWarmup\Services\TemplateLoader::load( 'null', $domain );
		}

		$template_name = $template['name'] ?? 'unknown';

		Logger::info( "Worker: Traitement envoi email", [
			'server_id'  => $server['id'],
			'email_from' => $from_email,
			'email_to'   => $to,
			'retry'      => $retry_count,
			'template'   => $template_name
		]);

		// Ultimate fallback if 'null' template is also missing
		if ( ! $template ) {
			$template = \PostalWarmup\Services\TemplateLoader::get_fallback();
			Logger::warning( "Worker: Template introuvable ($prefix) et template 'null' absent. Utilisation du fallback système." );
		}
		
		$payload = self::build_payload( $to, $from_email, $template, $domain, $prefix );
		$result = self::send_request( $server, $payload, $retry_count + 1, $template_name );
		
		$response_time = isset( $result['response_time'] ) ? $result['response_time'] : 0;

		if ( $result['success'] ) {
			Database::increment_sent( $domain, true, $response_time );
			Database::record_stat( $server['id'], true, $response_time );
			return $result;
		}
		
		Database::increment_sent( $domain, false, $response_time );
		Database::record_stat( $server['id'], false, $response_time );
		
		$max_retries = get_option( 'pw_max_retries', 3 );
		
		if ( $retry_count < $max_retries ) {
			if ( function_exists( 'as_schedule_single_action' ) ) {
				$delay = pow( 2, $retry_count + 1 ) * 60; 
				as_schedule_single_action(
					time() + $delay, 
					'pw_send_email_async', 
					array( $to, $domain, $prefix, $server_id, $retry_count + 1 ),
					'postal-warmup'
				);
				Logger::warning( "Worker: Échec envoi, replanifié dans {$delay}s", [
					'error'    => $result['error'],
					'template' => $template_name
				] );
			}
		} else {
			Logger::error( "Worker: Abandon après $max_retries tentatives", [
				'error'    => $result['error'],
				'template' => $template_name
			] );
		}
		
		return $result;
	}

	private static function build_payload( $to, $from_email, $template, $domain, $prefix ) {
		// Use TemplateLoader for placeholders and picking random
		$subject   = \PostalWarmup\Services\TemplateLoader::pick_random( $template['subject'] );
		$text      = \PostalWarmup\Services\TemplateLoader::pick_random( $template['text'] );
		$html      = \PostalWarmup\Services\TemplateLoader::pick_random( $template['html'] );
		$from_name = \PostalWarmup\Services\TemplateLoader::pick_random( $template['from_name'] );
		
		// Attempt to decode Base64 if used for storage (must be done BEFORE placeholders)
		$subject   = self::maybe_decode( $subject );
		$text      = self::maybe_decode( $text );
		$html      = self::maybe_decode( $html );
		$from_name = self::maybe_decode( $from_name );

		$vars = [
			'email'  => $to,
			'domain' => $domain,
			'local'  => $prefix,
			'date'   => current_time( 'd/m/Y' ),
			'time'   => current_time( 'H:i' ),
		];
		
		$subject = \PostalWarmup\Services\TemplateLoader::apply_placeholders( $subject, $vars );
		$text    = \PostalWarmup\Services\TemplateLoader::apply_placeholders( $text, $vars );
		$html    = \PostalWarmup\Services\TemplateLoader::apply_placeholders( $html, $vars );
		
		$payload = [
			'to'         => [ $to ],
			'from'       => "$from_name <$from_email>",
			'subject'    => $subject,
			'plain_body' => $text,
			'html_body'  => $html,
			'headers'    => [
				'X-Warmup-Source'   => 'PostalWarmupPro-v' . PW_VERSION,
				'X-Warmup-Template' => $template['name'] ?? 'unknown'
			]
		];

		$global_tag = get_option( 'pw_global_tag', 'warmup' );
		if ( ! empty( $global_tag ) ) {
			$payload['tag'] = sanitize_text_field( $global_tag );
		}

		if ( ! empty( $template['reply_to'] ) ) {
			$reply_to = \PostalWarmup\Services\TemplateLoader::pick_random( $template['reply_to'] );
			$reply_to = self::maybe_decode( $reply_to );
			if ( ! empty( $reply_to ) ) {
				$payload['reply_to'] = \PostalWarmup\Services\TemplateLoader::apply_placeholders( $reply_to, $vars );
			}
		}

		return apply_filters( 'pw_email_payload', $payload, $template, $vars );
	}

	private static function maybe_decode( $string ) {
		if ( ! is_string( $string ) || empty( $string ) ) return $string;

		// Optimization: If it has spaces (and not newlines), it's likely not a raw Base64 string suitable for storage
		if ( strpos( $string, ' ' ) !== false ) return $string;

		// Try to decode if it looks like Base64 (alphanumeric + / + = + whitespace)
		if ( preg_match( '/^[a-zA-Z0-9\/\r\n+]*={0,2}$/', $string ) ) {
			$decoded = base64_decode( $string, true );
			if ( $decoded !== false ) {
				// Robustness: Only accept if valid UTF-8
				// This prevents false positives like "Hello" decoding to binary garbage
				if ( mb_check_encoding( $decoded, 'UTF-8' ) ) {
					return $decoded;
				}
			}
		}

		return $string;
	}

	private static function send_request( $server, $payload, $attempt, $template_name = null ) {
		$api_url = rtrim( $server['api_url'], '/' );
		$api_key = $server['api_key']; // Already decrypted by Database model
		$url = $api_url . '/send/message';
		
		$start_time = microtime( true );
		
		$response = wp_remote_post( $url, [
			'headers' => [
				'Content-Type'     => 'application/json',
				'X-Server-API-Key' => $api_key
			],
			'body'      => json_encode( $payload ),
			'timeout'   => 30,
			'sslverify' => true
		]);
		
		$response_time = microtime( true ) - $start_time;
		
		if ( is_wp_error( $response ) ) {
			Logger::error( "Erreur HTTP (tentative $attempt)", [
				'server_id'     => $server['id'],
				'error'         => $response->get_error_message(),
				'response_time' => round( $response_time, 3 )
			]);
			return [ 'success' => false, 'error' => $response->get_error_message(), 'response_time' => $response_time ];
		}
		
		$http_code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		
		Logger::debug( "Réponse Postal", [
			'server_id'     => $server['id'],
			'http_code'     => $http_code,
			'response_time' => round( $response_time, 3 )
		]);
		
		if ( $http_code !== 200 ) {
			$error_msg = "HTTP $http_code";
			$json = json_decode( $body, true );
			if ( $json && isset( $json['data']['message'] ) ) {
				$error_msg .= ' - ' . $json['data']['message'];
			} elseif ( $json && isset( $json['message'] ) ) {
				$error_msg .= ' - ' . $json['message'];
			}

			Logger::error( "Erreur API Postal ($http_code)", [
				'server_id' => $server['id'],
				'response'  => $body
			]);

			return [ 'success' => false, 'error' => $error_msg, 'response_time' => $response_time ];
		}
		
		$data = json_decode( $body, true );
		if ( ! $data || ( isset( $data['status'] ) && $data['status'] !== 'success' ) ) {
			return [ 'success' => false, 'error' => $data['message'] ?? 'Réponse API invalide', 'response_time' => $response_time ];
		}
		
		Logger::info( "Email envoyé avec succès", [
			'server_id'     => $server['id'],
			'email_to'      => $payload['to'][0] ?? '',
			'message_id'    => $data['data']['message_id'] ?? null,
			'response_time' => round( $response_time, 3 ),
			'status'        => 'success',
			'template'      => $template_name
		]);
		
		return [ 'success' => true, 'response' => $data, 'response_time' => $response_time ];
	}

	public static function test_connection( $server_id ) {
		$server = Database::get_server( $server_id );
		if ( ! $server ) {
			return [ 'success' => false, 'message' => __( 'Serveur introuvable', 'postal-warmup' ) ];
		}
		
		$test_email = get_option( 'admin_email' );
		$test_payload = [
			'to'         => [ $test_email ],
			'from'       => "Test <test@{$server['domain']}>",
			'subject'    => 'Test Postal Warmup',
			'plain_body' => "Test OK.\nServeur : {$server['domain']}",
			'html_body'  => "<p>Test OK.</p><p><strong>Serveur :</strong> {$server['domain']}</p>"
		];
		
		$result = self::send_request( $server, $test_payload, 1 );
		
		if ( $result['success'] ) {
			return [ 'success' => true, 'message' => sprintf( __( 'Test réussi ! Email envoyé à %s', 'postal-warmup' ), $test_email ) ];
		}
		return [ 'success' => false, 'message' => sprintf( __( 'Test échoué : %s', 'postal-warmup' ), $result['error'] ) ];
	}
}
