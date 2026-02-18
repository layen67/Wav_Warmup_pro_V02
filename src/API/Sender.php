<?php

namespace PostalWarmup\API;

use PostalWarmup\Models\Database;
use PostalWarmup\Services\Logger;
use PostalWarmup\Core\TemplateEngine;

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

		// Use TemplateEngine to prepare everything
		$prepared = TemplateEngine::prepare_template( $prefix, $domain, $prefix, $to );
		$template_name = $prepared['name'];
		$from_email = $prefix . '@' . $domain;

		Logger::info( "Worker: Traitement envoi email", [
			'server_id'  => $server['id'],
			'email_from' => $from_email,
			'email_to'   => $to,
			'retry'      => $retry_count,
			'template'   => $template_name
		]);

		$payload = [
			'to'         => [ $to ],
			'from'       => "{$prepared['from_name']} <$from_email>",
			'subject'    => $prepared['subject'],
			'plain_body' => $prepared['text'],
			'html_body'  => $prepared['html'],
			'headers'    => [
				'X-Warmup-Source'   => 'PostalWarmupPro-v' . PW_VERSION,
				'X-Warmup-Template' => $template_name
			]
		];

		if ( ! empty( $prepared['reply_to'] ) ) {
			$payload['reply_to'] = $prepared['reply_to'];
		}

		$global_tag = get_option( 'pw_global_tag', 'warmup' );
		if ( ! empty( $global_tag ) ) {
			$payload['tag'] = sanitize_text_field( $global_tag );
		}

		$payload = apply_filters( 'pw_email_payload', $payload, $prepared, [] );

		$result = self::send_request( $server, $payload, $retry_count + 1, $template_name );
		
		$response_time = isset( $result['response_time'] ) ? $result['response_time'] : 0;

		if ( $result['success'] ) {
			// New Stats Logic: Insert into History
			$message_id = $result['response']['data']['message_id'] ?? null;
			Database::insert_stat_history( [
				'server_id'   => $server['id'],
				'template_id' => $prepared['id'], // Can be null if file/system
				'message_id'  => $message_id,
				'email_from'  => $from_email,
				'event_type'  => 'sent',
				'timestamp'   => current_time( 'mysql' ),
				'meta'        => json_encode( [ 'template_name' => $template_name ] )
			] );

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
		
		$message_id = $data['data']['message_id'] ?? null;

		Logger::info( "Email envoyé avec succès", [
			'server_id'     => $server['id'],
			'email_to'      => $payload['to'][0] ?? '',
			'message_id'    => $message_id,
			'response_time' => round( $response_time, 3 ),
			'status'        => 'success',
			'template'      => $template_name
		]);
		
		// Optimization: Pre-fill postal_stats to ensure real-time accuracy even before Webhook
		if ( $message_id ) {
			// This part is handled by Logger::log -> Database::insert_log if configured for DB
			// But for strict stats accuracy, we ensure 'sent' metric is recorded immediately
			// Already done by Database::increment_sent and record_stat in process_queue,
			// but we can add message_id tracking if we had a detailed message table.
			// Current structure relies on aggregated stats.
		}

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
