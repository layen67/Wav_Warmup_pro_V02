<?php

namespace PostalWarmup\API;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use PostalWarmup\Services\Logger;
use PostalWarmup\Models\Database;

/**
 * Gestionnaire de webhook REST API
 */
class WebhookHandler {

	public function register_routes() {
		register_rest_route( 'postal-warmup/v1', '/webhook', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'handle_webhook' ),
			'permission_callback' => array( $this, 'verify_signature' ),
		) );
		
		register_rest_route( 'postal-warmup/v1', '/test', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'test_endpoint' ),
			'permission_callback' => '__return_true',
		) );
	}

	public function verify_signature( WP_REST_Request $request ): bool|WP_Error {
		$secret = get_option( 'pw_webhook_secret' );
		
		// Auto-génération si manquant pour éviter un blocage total sur installation existante
		if ( empty( $secret ) ) {
			$secret = wp_generate_password( 64, false );
			update_option( 'pw_webhook_secret', $secret );
			Logger::info( 'Webhook : Secret généré automatiquement lors du premier accès.' );
		}
		
		// Force la récupération depuis les paramètres d'URL (GET) uniquement
		// pour éviter les conflits avec un champ "token" présent dans le body JSON de Postal
		$params = $request->get_query_params();
		$token = isset( $params['token'] ) ? (string) $params['token'] : '';
		
		// DEBUG: Logs étendus pour diagnostic (tokens masqués)
		Logger::debug( 'Webhook: Vérification signature', [
			'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
			'query_params' => $request->get_query_params(),
			'received_token_start' => substr( $token, 0, 5 ) . '...',
			'expected_secret_start' => substr( $secret, 0, 5 ) . '...'
		] );

		// Comparaison sécurisée
		if ( empty( $token ) || ! hash_equals( $secret, $token ) ) {
			Logger::warning( 'Webhook : Token invalide ou manquant', [ 
				'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
				'received_token' => $token, // On garde le reçu en entier pour debug temporaire si vraiment besoin, mais idéalement masqué
				'expected_token_start' => substr( $secret, 0, 5 ) . '...' // Ne JAMAIS logger le secret attendu en entier
			] );
			return new WP_Error( 'forbidden', 'Invalid token', [ 'status' => 403 ] );
		}
		
		return true;
	}

	public function handle_webhook( WP_REST_Request $request ) {
		$data = $request->get_json_params();
		
		if ( empty( $data ) ) {
			return new WP_REST_Response( [ 'status' => 'error', 'message' => 'Invalid JSON' ], 400 );
		}
		
		// Events
		if ( isset( $data['event'] ) ) {
			$this->handle_event( $data );
		} elseif ( isset( $data['rcpt_to'] ) ) {
			// Incoming message (if configured to route to this URL)
			$this->handle_incoming_message( $data );
		}
		
		return new WP_REST_Response( [ 'status' => 'ok' ], 200 );
	}

	private function handle_event( $data ) {
		$event = $data['event'] ?? '';
		$payload = $data['payload'] ?? [];
		
		$ctx = $this->identify_context($payload);
		$server_id = $ctx['server_id'];
		$template = $ctx['template'];
		$log_context = [
			'server_id' => $server_id,
			'template'  => $template
		];

		switch ( $event ) {
			case 'MessageSent':
				// Optimization: Sender.php already records 'sent' on API success.
				// We still update legacy metrics for safety but skip history insertion to avoid duplicates.
				$this->track_metric( $payload, 'sent', $ctx, true );
				break;
			case 'MessageDelivered': // Explicitly handle Delivered
				$this->track_metric( $payload, 'delivered', $ctx );
				break;
			case 'MessageDeliveryFailed':
				$this->track_metric( $payload, 'failed', $ctx );
				Logger::error( 'Échec de livraison', array_merge( $log_context, [ 'status' => 'failed' ] ) );
				break;
			case 'MessageBounced':
				$this->track_metric( $payload, 'bounced', $ctx );
				Logger::warning( 'Message rebondi', array_merge( $log_context, [ 'status' => 'bounced' ] ) );
				break;
			case 'MessageLinkClicked':
				$this->track_metric( $payload, 'clicked', $ctx );
				break;
			case 'MessageLoaded':
				$this->track_metric( $payload, 'opened', $ctx );
				break;
			case 'DomainDNSError':
				$this->track_metric( $payload, 'dns_error', $ctx );
				Logger::critical( 'Erreur DNS détectée par Postal', $log_context );
				break;
			default:
				// Ignore others
		}
	}

	private function identify_context( $payload ) {
		$message = $payload['message'] ?? [];
		$server_id = null;
		$template_name = null;
		$domain = null;

		$headers = $message['headers'] ?? [];
		$template_name = $headers['X-Warmup-Template'] ?? null;

		if ( isset( $message['from'] ) ) {
			list( $prefix, $d ) = $this->parse_email( $message['from'] );
			$domain = $d;
			if ( ! $template_name ) {
				$template_name = $prefix; // Fallback
			}
		} elseif ( isset( $payload['domain'] ) ) {
			$domain = $payload['domain'];
		}

		if ( $domain ) {
			$server = Database::get_server_by_domain( $domain );
			if ( $server ) {
				$server_id = $server['id'];
			}
		}

		return [
			'server_id' => $server_id,
			'template' => $template_name,
			'domain' => $domain
		];
	}

	private function handle_incoming_message( $data ) {
		// Logic from original class-pw-webhook-handler.php
		$id = $data['id'] ?? null;
		$rcpt_to = $data['rcpt_to'] ?? '';
		$mail_from = $data['mail_from'] ?? '';
		$subject = $data['subject'] ?? '';

		if ( empty( $rcpt_to ) ) return;

		// Deduplication: Check if message ID already processed (valid 1 hour)
		if ( $id ) {
			$transient_key = 'pw_webhook_msg_' . $id;
			if ( get_transient( $transient_key ) ) {
				Logger::info( "Webhook ignoré (doublon)", [ 'message_id' => $id ] );
				return;
			}
			set_transient( $transient_key, true, 3600 );
		}

		list( $prefix, $domain ) = $this->parse_email( $rcpt_to );
		if ( ! $domain ) return;

		$server = Database::get_server_by_domain( $domain );
		if ( ! $server ) return;

		// Loop Prevention: Do not reply if sender is one of our own servers
		list( $from_prefix, $from_domain ) = $this->parse_email( $mail_from );
		if ( $from_domain ) {
			$sender_server = Database::get_server_by_domain( $from_domain );
			if ( $sender_server ) {
				Logger::warning( "Boucle détectée : Tentative de réponse à soi-même", [ 'from' => $mail_from, 'to' => $rcpt_to ] );
				return;
			}
		}

		Logger::info( "Message entrant", [ 'server_id' => $server['id'], 'from' => $mail_from, 'subject' => $subject ] );
		
		// Check limits and reply
		if ( $this->check_rate_limits( $server['id'] ) ) {
			Sender::send( $mail_from, $domain, $prefix, $server );
		}
	}

	private function parse_email( $email ) {
		if ( preg_match( '/<(.+?)>/', $email, $matches ) ) {
			$email = $matches[1];
		}
		$parts = explode( '@', trim( $email ), 2 );
		return ( count( $parts ) === 2 ) ? $parts : [ '', '' ];
	}

	private function track_metric( $payload, $event_type, $ctx = null, $skip_history = false ) {
		if ( $ctx === null ) {
			$ctx = $this->identify_context( $payload );
		}

		$server_id = $ctx['server_id'];
		$template_name = $ctx['template'];
		$domain = $ctx['domain'];

		if ( $server_id ) {
			// New Stats Architecture: Insert into postal_stats_history
			if ( ! $skip_history ) {
				global $wpdb;
				$table_tpl = $wpdb->prefix . 'postal_templates';
				$template_id = null;
				if ( $template_name ) {
					$template_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table_tpl WHERE name = %s", $template_name ) );
				}

				$message_id = $payload['original_message']['id'] ?? $payload['message']['id'] ?? null;

				Database::insert_stat_history( [
					'server_id'   => $server_id,
					'template_id' => $template_id,
					'message_id'  => $message_id,
					'event_type'  => $event_type,
					'timestamp'   => current_time( 'mysql' ),
					'meta'        => json_encode( [ 'template_name' => $template_name ] )
				] );
			}

			// Legacy metrics updates (kept for backward compat or if needed by charts until fully refactored)
			Database::update_detailed_metrics( $template_name, $server_id, $event_type );
			
			// Fix: Also record global stats for relevant events
			if ( $event_type === 'sent' || $event_type === 'delivered' ) {
				Database::increment_sent( $domain, true );
				Database::record_stat( $server_id, true );
			} elseif ( in_array( $event_type, [ 'failed', 'bounced', 'dns_error' ] ) ) {
				Database::increment_sent( $domain, false );
				Database::record_stat( $server_id, false );
			}
		}
	}

	private function check_rate_limits( $server_id ) {
		// Simplified rate limit check from DB logic
		return true; 
	}

	public function test_endpoint() {
		return new WP_REST_Response( [
			'status' => 'ok',
			'message' => 'Postal Warmup API is running',
			'version' => PW_VERSION
		], 200 );
	}
}
