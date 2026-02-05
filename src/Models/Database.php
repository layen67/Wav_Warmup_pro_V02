<?php

namespace PostalWarmup\Models;

use PostalWarmup\Services\Encryption;

/**
 * Classe de gestion de la base de données
 */
class Database {

	/**
	 * Récupère tous les serveurs
	 */
	public static function get_servers( bool $only_active = false, string $orderby = 'sent_count', string $order = 'ASC' ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'postal_servers';
		
		$where = $only_active ? "WHERE active = 1" : "";
		$allowed_cols = [ 'id', 'domain', 'api_url', 'sent_count', 'success_count', 'error_count', 'last_used' ];
		
		if ( ! in_array( $orderby, $allowed_cols ) ) {
			$orderby = 'sent_count';
		}
		
		$order = ( strtoupper( $order ) === 'DESC' ) ? 'DESC' : 'ASC';
		
		$results = $wpdb->get_results( "SELECT * FROM $table $where ORDER BY $orderby $order", ARRAY_A );

		// Décrypter les clés API
		if ( $results ) {
			foreach ( $results as &$server ) {
				$server['api_key'] = Encryption::decrypt( $server['api_key'] );
			}
		}

		return $results;
	}

	/**
	 * Récupère un serveur par ID
	 */
	public static function get_server( int $id ): ?array {
		global $wpdb;
		$table = $wpdb->prefix . 'postal_servers';
		
		$server = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ), ARRAY_A );

		if ( $server ) {
			$server['api_key'] = Encryption::decrypt( $server['api_key'] );
		}

		return $server;
	}

	/**
	 * Récupère un serveur par domaine
	 */
	public static function get_server_by_domain( string $domain ): ?array {
		global $wpdb;
		$table = $wpdb->prefix . 'postal_servers';
		
		$server = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE domain = %s AND active = 1", $domain ), ARRAY_A );
		
		if ( $server ) {
			$server['api_key'] = Encryption::decrypt( $server['api_key'] );
		}

		return $server;
	}

	/**
	 * Insère un nouveau serveur
	 */
	public static function insert_server( array $data ): int|bool {
		global $wpdb;
		$table = $wpdb->prefix . 'postal_servers';
		
		$defaults = [
			'active'        => 1,
			'sent_count'    => 0,
			'success_count' => 0,
			'error_count'   => 0,
			'created_at'    => current_time( 'mysql' ),
			'updated_at'    => current_time( 'mysql' )
		];
		
		$data = wp_parse_args( $data, $defaults );

		// Chiffrer la clé API
		if ( ! empty( $data['api_key'] ) ) {
			$data['api_key'] = Encryption::encrypt( $data['api_key'] );
		}
		
		$result = $wpdb->insert( $table, $data );
		
		if ( $result ) {
			do_action( 'pw_server_added', $wpdb->insert_id );
			return $wpdb->insert_id;
		}
		
		return false;
	}

	/**
	 * Met à jour un serveur
	 */
	public static function update_server( int $id, array $data ): bool {
		global $wpdb;
		$table = $wpdb->prefix . 'postal_servers';
		
		$data['updated_at'] = current_time( 'mysql' );

		// Chiffrer la clé API si elle est mise à jour
		if ( ! empty( $data['api_key'] ) ) {
			// Vérifier si elle est déjà chiffrée ou non (on re-chiffre toujours pour être sûr)
			// Idéalement on ne devrait la chiffrer que si elle a changé, mais encrypt() est deterministe avec même key/iv (mais IV random)
			$data['api_key'] = Encryption::encrypt( $data['api_key'] );
		}
		
		$result = $wpdb->update( $table, $data, [ 'id' => $id ] );
		
		if ( $result !== false ) {
			do_action( 'pw_server_updated', $id );
			return true;
		}
		
		return false;
	}

	/**
	 * Supprime un serveur
	 */
	public static function delete_server( int $id ): bool {
		global $wpdb;
		$table = $wpdb->prefix . 'postal_servers';
		
		$result = $wpdb->delete( $table, [ 'id' => $id ] );
		
		if ( $result ) {
			do_action( 'pw_server_deleted', $id );
			return true;
		}
		
		return false;
	}

	/**
	 * Incrémente le compteur d'envois
	 */
	public static function increment_sent( string $domain, bool $success = true, ?float $response_time = null ): void {
		global $wpdb;
		$table = $wpdb->prefix . 'postal_servers';
		
		$field = $success ? 'success_count' : 'error_count';
		
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE $table 
				SET sent_count = sent_count + 1,
					$field = $field + 1,
					last_used = NOW()
				WHERE domain = %s",
				$domain
			)
		);
	}

	/**
	 * Calcule les statistiques d'un serveur
	 */
	public static function get_server_stats( int $server_id, int $days = 7 ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'postal_stats';
		
		$date_from = date( 'Y-m-d', strtotime( "-$days days" ) );
		
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
					date,
					SUM(sent_count) as total_sent,
					SUM(success_count) as total_success,
					SUM(error_count) as total_errors,
					AVG(avg_response_time) as avg_time
				FROM $table
				WHERE server_id = %d AND date >= %s
				GROUP BY date
				ORDER BY date ASC",
				$server_id,
				$date_from
			),
			ARRAY_A
		) ?: [];
	}

	/**
	 * Enregistre des statistiques
	 */
	public static function record_stat( int $server_id, bool $success = true, ?float $response_time = null ): void {
		global $wpdb;
		$table = $wpdb->prefix . 'postal_stats';
		
		$date = current_time( 'Y-m-d' );
		$hour = (int) current_time( 'H' );
		$success_field = $success ? 'success_count' : 'error_count';
		
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO $table 
				(server_id, date, hour, sent_count, $success_field, avg_response_time, created_at)
				VALUES (%d, %s, %d, 1, 1, %f, NOW())
				ON DUPLICATE KEY UPDATE
				sent_count = sent_count + 1,
				$success_field = $success_field + 1,
				avg_response_time = (avg_response_time + %f) / 2",
				$server_id,
				$date,
				$hour,
				$response_time,
				$response_time
			)
		);
	}

	/**
	 * Récupère les logs
	 */
	public static function get_logs( array $filters = [], int $per_page = 50, int $page = 1 ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'postal_logs';
		
		$where_clauses = [];
		$where_values = [];
		
		if ( ! empty( $filters['server_id'] ) ) {
			$where_clauses[] = "server_id = %d";
			$where_values[] = $filters['server_id'];
		}
		if ( ! empty( $filters['level'] ) ) {
			$where_clauses[] = "level = %s";
			$where_values[] = $filters['level'];
		}
		
		$where = '';
		if ( ! empty( $where_clauses ) ) {
			$where = 'WHERE ' . implode( ' AND ', $where_clauses );
		}
		
		$offset = ( $page - 1 ) * $per_page;
		$where_values[] = $per_page;
		$where_values[] = $offset;
		
		$sql = "SELECT * FROM $table $where ORDER BY created_at DESC LIMIT %d OFFSET %d";
		
		if ( ! empty( $where_values ) ) {
			$sql = $wpdb->prepare( $sql, $where_values );
		}
		
		return $wpdb->get_results( $sql, ARRAY_A );
	}

	/**
	 * Insère un log (Restore legacy logic)
	 */
	public static function insert_log( array $data ): bool {
		global $wpdb;
		$table = $wpdb->prefix . 'postal_logs';
		
		$defaults = [
			'created_at' => current_time( 'mysql' )
		];
		$data = wp_parse_args( $data, $defaults );
		
		// Encode context if array
		if ( isset( $data['context'] ) && is_array( $data['context'] ) ) {
			$data['context'] = json_encode( $data['context'], JSON_UNESCAPED_UNICODE );
		}

		return (bool) $wpdb->insert( $table, $data );
	}

	public static function get_servers_count( string $search = '' ): int {
		global $wpdb;
		$table = $wpdb->prefix . 'postal_servers';
		$where = '';
		if ( ! empty( $search ) ) {
			$search = '%' . $wpdb->esc_like( $search ) . '%';
			$where = $wpdb->prepare( "WHERE domain LIKE %s OR api_url LIKE %s", $search, $search );
		}
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table $where" );
	}
	
	public static function get_enriched_activity( int $limit = 15 ): array {
		global $wpdb;
		$logs_table = $wpdb->prefix . 'postal_logs';
		$servers_table = $wpdb->prefix . 'postal_servers';
		
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT l.*, s.domain as server_domain
			FROM $logs_table l 
			LEFT JOIN $servers_table s ON l.server_id = s.id 
			ORDER BY l.created_at DESC 
			LIMIT %d",
			$limit
		), ARRAY_A );
	}

	public static function update_detailed_metrics( ?string $template_name, ?int $server_id, string $event_type ): void {
		global $wpdb;
		$table = $wpdb->prefix . 'postal_metrics';
		$table_tpl = $wpdb->prefix . 'postal_templates';

		$template_id = null;
		if ( $template_name ) {
			$template_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table_tpl WHERE name = %s", $template_name ) );
		}

		$date = current_time( 'Y-m-d' );

		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO $table (template_id, server_id, event_type, count, date, created_at)
				VALUES (%d, %d, %s, 1, %s, NOW())
				ON DUPLICATE KEY UPDATE count = count + 1",
				$template_id,
				$server_id,
				$event_type,
				$date
			)
		);
	}

	public static function insert_stat_history( array $data ): bool {
		global $wpdb;
		$table = $wpdb->prefix . 'postal_stats_history';

		$defaults = [
			'created_at' => current_time( 'mysql' )
		];
		$data = wp_parse_args( $data, $defaults );

		return (bool) $wpdb->insert( $table, $data );
	}
}
