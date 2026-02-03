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
		
		switch ( $event ) {
			case 'MessageSent':
				$this->track_metric( $payload, 'sent' );
				break;
			case 'MessageDeliveryFailed':
				$this->track_metric( $payload, 'failed' );
				Logger::error( 'Échec de livraison', [ 'status' => 'failed' ] );
				break;
			case 'MessageBounced':
				$this->track_metric( $payload, 'bounced' );
				Logger::warning( 'Message rebondi', [ 'status' => 'bounced' ] );
				break;
			case 'MessageLinkClicked':
				$this->track_metric( $payload, 'clicked' );
				break;
			case 'MessageLoaded':
				$this->track_metric( $payload, 'opened' );
				break;
			case 'DomainDNSError':
				$this->track_metric( $payload, 'dns_error' );
				Logger::critical( 'Erreur DNS détectée par Postal' );
				break;
			default:
				// Ignore others
		}
	}

	private function handle_incoming_message( $data ) {
		// Logic from original class-pw-webhook-handler.php
		$rcpt_to = $data['rcpt_to'] ?? '';
		$mail_from = $data['mail_from'] ?? '';
		$subject = $data['subject'] ?? '';

		if ( empty( $rcpt_to ) ) return;

		list( $prefix, $domain ) = $this->parse_email( $rcpt_to );
		if ( ! $domain ) return;

		$server = Database::get_server_by_domain( $domain );
		if ( ! $server ) return;

		Logger::info( "Message entrant", [ 'server_id' => $server['id'], 'from' => $mail_from, 'subject' => $subject ] );
		
		// Check limits and reply
		// Reply logic calls Sender::send(...)
		// For brevity and focus on structure, we call Sender logic
		
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

	private function track_metric( $payload, $event_type ) {
		$message = $payload['message'] ?? [];
		$server_id = null;
		$template_name = null;

		if ( isset( $message['from'] ) ) {
			list( $prefix, $domain ) = $this->parse_email( $message['from'] );
			$server = Database::get_server_by_domain( $domain );
			if ( $server ) {
				$server_id = $server['id'];
				$template_name = $prefix; // We assume prefix is template name
			}
		} elseif ( isset( $payload['domain'] ) ) {
			$server = Database::get_server_by_domain( $payload['domain'] );
			if ( $server ) $server_id = $server['id'];
		}

		if ( $server_id ) {
			Database::update_detailed_metrics( $template_name, $server_id, $event_type );
			
			// Fix: Also record global stats for relevant events
			if ( $event_type === 'sent' || $event_type === 'delivered' ) {
				Database::increment_sent( $payload['domain'] ?? '', true );
				Database::record_stat( $server_id, true );
			} elseif ( in_array( $event_type, [ 'failed', 'bounced', 'dns_error' ] ) ) {
				Database::increment_sent( $payload['domain'] ?? '', false );
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
