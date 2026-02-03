<?php

namespace PostalWarmup\API;

use PostalWarmup\Models\Database;
use PostalWarmup\Services\Logger;
use WP_Error;

/**
 * Client unifié pour l'API Postal
 */
class Client {

	/**
	 * Send a request to Postal API
	 *
	 * @param int $server_id The ID of the server in WP database.
	 * @param string $endpoint The API endpoint (e.g., 'messages', 'suppression/list').
	 * @param string $method GET, POST, DELETE.
	 * @param array $data Data to send (for POST) or query params (for GET).
	 * @return array|WP_Error Response data or error.
	 */
	public static function request( $server_id, $endpoint, $method = 'GET', $data = [] ) {
		
		$server = Database::get_server( $server_id );
		if ( ! $server ) {
			return new WP_Error( 'invalid_server', __( 'Serveur introuvable.', 'postal-warmup' ) );
		}

		$api_url = rtrim( $server['api_url'], '/' ) . '/' . ltrim( $endpoint, '/' );
		// Note: Database::get_server already handles decryption of api_key
		$api_key = $server['api_key'];

		$args = [
			'headers' => [
				'X-Server-API-Key' => $api_key,
				'Content-Type'     => 'application/json',
				'Accept'           => 'application/json'
			],
			'method'  => $method,
			'timeout' => 30
		];

		if ( $method === 'GET' && ! empty( $data ) ) {
			$api_url = add_query_arg( $data, $api_url );
		} elseif ( $method !== 'GET' && ! empty( $data ) ) {
			$args['body'] = json_encode( $data );
		}

		$response = wp_remote_request( $api_url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$result = json_decode( $body, true );

		if ( $code >= 400 ) {
			$msg = isset( $result['data']['message'] ) ? $result['data']['message'] : ( isset( $result['message'] ) ? $result['message'] : 'Erreur API' );
			
			Logger::error( "Erreur API Postal ($code)", [
				'server_id' => $server_id,
				'url'       => $api_url,
				'method'    => $method,
				'response'  => $body
			]);

			return new WP_Error( 'api_error', "HTTP $code: $msg" );
		}

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			Logger::error( "Erreur JSON Postal", [ 'body' => $body ] );
			return new WP_Error( 'json_error', __( 'Réponse JSON invalide.', 'postal-warmup' ) );
		}

		if ( isset( $result['status'] ) && $result['status'] === 'success' ) {
			return $result['data'] ?? [];
		}
		
		return $result;
	}
}
