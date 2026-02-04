<?php

namespace PostalWarmup\Models;

use PostalWarmup\Models\Database;

class Stats {

	public static function get_dashboard_stats() {
		// Try cache first
		$cached = get_transient( 'pw_dashboard_stats' );
		if ( $cached !== false ) return $cached;

		global $wpdb;
		$servers_table = $wpdb->prefix . 'postal_servers';
		$stats_table = $wpdb->prefix . 'postal_stats';
		
		$today = current_time( 'Y-m-d' );
		$yesterday = date( 'Y-m-d', strtotime( '-1 day', current_time( 'timestamp' ) ) );
		
		// General stats from servers table (cumulative)
		$general = $wpdb->get_row( "SELECT COUNT(*) as total_servers, SUM(CASE WHEN active = 1 THEN 1 ELSE 0 END) as active_servers FROM $servers_table", ARRAY_A );
		
		// === Optimisation : Utilisation de la table d'agrégation journalière si disponible ===
		$daily_table = $wpdb->prefix . 'postal_stats_daily';
		
		// Total Sent (Historical + Today)
		// On prend le total de l'historique archivé + le total du jour (pas encore archivé)
		$history_total = (int) $wpdb->get_var( "SELECT SUM(total_sent) FROM $daily_table" );
		$today_total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(sent_count) FROM $stats_table WHERE date = %s", $today ) );
		$total_sent = $history_total + $today_total;

		// Total Success
		$history_success = (int) $wpdb->get_var( "SELECT SUM(total_success) FROM $daily_table" );
		$today_success = (int) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(success_count) FROM $stats_table WHERE date = %s", $today ) );
		$total_success = $history_success + $today_success;

		// Yesterday (Utilise la table daily si l'agrégation a tourné, sinon fallback stats_table)
		// Si le CRON a tourné, hier est dans daily_table
		$sent_yesterday = (int) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(total_sent) FROM $daily_table WHERE date = %s", $yesterday ) );
		if ( $sent_yesterday === 0 ) {
			// Fallback si pas encore agrégé
			$sent_yesterday = (int) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(sent_count) FROM $stats_table WHERE date = %s", $yesterday ) );
		}
		
		$sent_today = $today_total;
		
		$success_rate = 0;
		if ( $total_sent > 0 ) {
			$success_rate = round( ( (int) ( $general['total_success'] ?? 0 ) / $total_sent ) * 100, 2 );
		}
		
		$evolution = 0;
		if ( $sent_yesterday > 0 ) {
			$evolution = round( ( ( $sent_today - $sent_yesterday ) / $sent_yesterday ) * 100, 1 );
		} elseif ( $sent_today > 0 ) {
			$evolution = 100;
		}
		
		$results = [
			'total_sent'     => $total_sent,
			'total_success'  => (int) ( $general['total_success'] ?? 0 ),
			'success_rate'   => $success_rate,
			'total_servers'  => (int) ( $general['total_servers'] ?? 0 ),
			'active_servers' => (int) ( $general['active_servers'] ?? 0 ),
			'sent_today'     => $sent_today,
			'evolution'      => $evolution
		];

		set_transient( 'pw_dashboard_stats', $results, 1 * MINUTE_IN_SECONDS );
		return $results;
	}

	public static function get_recent_errors( $limit = 5 ) {
		global $wpdb;
		$logs_table = $wpdb->prefix . 'postal_logs';
		$servers_table = $wpdb->prefix . 'postal_servers';
		
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT l.*, s.domain as server_domain 
			FROM $logs_table l 
			LEFT JOIN $servers_table s ON l.server_id = s.id 
			WHERE l.level IN ('ERROR', 'CRITICAL') 
			ORDER BY l.created_at DESC 
			LIMIT %d",
			$limit
		), ARRAY_A ) ?: [];
	}

	public static function get_servers_stats() {
		// Reuse Database model but ensuring rate calculation
		$servers = Database::get_servers();
		$stats = [];
		foreach ( $servers as $server ) {
			$rate = 0;
			if ( $server['sent_count'] > 0 ) {
				$rate = round( ( $server['success_count'] / $server['sent_count'] ) * 100, 1 );
			}
			$server['success_rate'] = $rate;
			$stats[] = $server;
		}
		return $stats;
	}
	
	public static function get_activity_24h() {
		// Logic to return chart data for dashboard
		global $wpdb;
		$stats_table = $wpdb->prefix . 'postal_stats';
		$date_limit = date( 'Y-m-d', strtotime( '-7 days' ) );
		
		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT date, SUM(sent_count) as total_sent, SUM(success_count) as total_success, SUM(error_count) as total_errors
			FROM $stats_table 
			WHERE date >= %s 
			GROUP BY date 
			ORDER BY date ASC",
			$date_limit
		), ARRAY_A );
		
		return [
			'labels' => array_column( $results, 'date' ),
			'data' => $results
		];
	}

	public static function get_detailed_metrics( $days = 7 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'postal_metrics';
		$date_from = date( 'Y-m-d', strtotime( "-$days days" ) );
		return $wpdb->get_results( $wpdb->prepare( "SELECT event_type, SUM(count) as total FROM $table WHERE date >= %s GROUP BY event_type", $date_from ), ARRAY_A ) ?: [];
	}

	public static function get_overall_stats() {
		$stats = self::get_dashboard_stats();
		$detailed = self::get_detailed_metrics( 30 );
		
		// Initialize extended stats
		$defaults = [ 'bounces' => 0, 'delivered' => 0, 'opened' => 0, 'clicked' => 0, 'complaints' => 0, 'delayed' => 0, 'held' => 0, 'dns_errors' => 0 ];
		$stats = array_merge( $stats, $defaults );

		if ( is_array( $detailed ) ) {
			foreach ( $detailed as $m ) {
				$val = (int) $m['total'];
				switch ( $m['event_type'] ) {
					case 'bounced': $stats['bounces'] = $val; break;
					case 'delivered': 
					case 'sent': $stats['delivered'] += $val; break;
					case 'opened': $stats['opened'] = $val; break;
					case 'clicked': $stats['clicked'] = $val; break;
					case 'complaint': $stats['complaints'] = $val; break;
					case 'delayed': $stats['delayed'] = $val; break;
					case 'held': $stats['held'] = $val; break;
					case 'dns_error': $stats['dns_errors'] = $val; break;
				}
			}
		}
		
		$stats['avg_response_time'] = self::get_avg_response_time();
		return $stats;
	}

	public static function get_avg_response_time( $days = 30 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'postal_stats';
		$date_from = date( 'Y-m-d', strtotime( "-$days days" ) );
		return (float) $wpdb->get_var( $wpdb->prepare( "SELECT AVG(avg_response_time) FROM $table WHERE date >= %s", $date_from ) );
	}

	public static function get_templates_global_stats() {
		global $wpdb;
		$logs_table = $wpdb->prefix . 'postal_logs';
		$templates_table = $wpdb->prefix . 'postal_templates';
		$servers_table = $wpdb->prefix . 'postal_servers';
		
		// Total Stats
		$stats = $wpdb->get_row( "SELECT SUM(sent_count) as total_sent, AVG(CASE WHEN sent_count > 0 THEN (success_count / sent_count) * 100 ELSE 0 END) as avg_success_rate FROM $servers_table", ARRAY_A );
		
		// Top Template
		$top_template = 'Aucun';
		$has_usage_count = $wpdb->get_results( "SHOW COLUMNS FROM `$templates_table` LIKE 'usage_count'" );
		if ( ! empty( $has_usage_count ) ) {
			$top_template = $wpdb->get_var( "SELECT name FROM $templates_table ORDER BY usage_count DESC LIMIT 1" ) ?: 'Aucun';
		}
		
		return [
			'total_sent'       => (int) ( $stats['total_sent'] ?? 0 ),
			'avg_success_rate' => round( (float) ( $stats['avg_success_rate'] ?? 0 ), 2 ),
			'top_template'     => $top_template
		];
	}
	
	public static function export_csv( $days = 30 ) {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Forbidden' );
		
		global $wpdb;
		$table = $wpdb->prefix . 'postal_stats';
		$date_from = date( 'Y-m-d', strtotime( "-$days days" ) );
		
		$data = $wpdb->get_results( $wpdb->prepare(
			"SELECT date, hour, SUM(sent_count) as sent, SUM(success_count) as success, SUM(error_count) as errors, AVG(avg_response_time) as avg_time 
			FROM $table WHERE date >= %s GROUP BY date, hour ORDER BY date DESC, hour DESC",
			$date_from
		), ARRAY_A );
		
		header( 'Content-Type: text/csv' );
		header( 'Content-Disposition: attachment; filename="postal-stats-' . date( 'Y-m-d' ) . '.csv"' );
		
		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, [ 'Date', 'Hour', 'Sent', 'Success', 'Errors', 'Avg Time (s)' ] );
		
		foreach ( $data as $row ) {
			fputcsv( $out, $row );
		}
		
		fclose( $out );
		exit;
	}
	
	public static function cleanup_old_stats() {
		$days = get_option( 'pw_stats_retention_days', 90 );
		global $wpdb;
		$table = $wpdb->prefix . 'postal_stats';
		$date = date( 'Y-m-d', strtotime( "-$days days" ) );
		return $wpdb->query( $wpdb->prepare( "DELETE FROM $table WHERE date < %s", $date ) );
	}

	public static function get_top_templates( $days = 7, $limit = 10 ) {
		global $wpdb;
		$logs_table = $wpdb->prefix . 'postal_logs';
		$date_from = date( 'Y-m-d H:i:s', strtotime( "-$days days" ) );
		// Exclude 'Worker: Traitement...' logs to avoid double counting (Attempt + Result)
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT template_used, COUNT(*) as usage_count, SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success_count, AVG(response_time) as avg_response_time
			FROM $logs_table
			WHERE template_used IS NOT NULL
			AND created_at >= %s
			AND message != 'Worker: Traitement envoi email'
			GROUP BY template_used ORDER BY usage_count DESC LIMIT %d",
			$date_from, $limit
		), ARRAY_A ) ?: [];
	}

	public static function get_all_templates_summary( $days = 30 ) {
		global $wpdb;
		$logs_table = $wpdb->prefix . 'postal_logs';
		$date_from = date( 'Y-m-d H:i:s', strtotime( "-$days days" ) );

		// Exclude 'Worker: Traitement...' logs to avoid double counting
		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT template_used, COUNT(*) as usage_count, SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success_count, AVG(response_time) as avg_response_time
			FROM $logs_table
			WHERE template_used IS NOT NULL
			AND created_at >= %s
			AND message != 'Worker: Traitement envoi email'
			GROUP BY template_used",
			$date_from
		), ARRAY_A ) ?: [];

		$stats = [];
		foreach ( $results as $row ) {
			$stats[$row['template_used']] = $row;
		}
		return $stats;
	}

	public static function get_server_performance_by_prefix( $days = 30 ) {
		global $wpdb;
		$logs_table = $wpdb->prefix . 'postal_logs';
		$servers_table = $wpdb->prefix . 'postal_servers';
		$date_from = date( 'Y-m-d H:i:s', strtotime( "-$days days" ) );

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT 
				s.domain as server_domain,
				l.email_from,
				COUNT(*) as total_sent,
				SUM(CASE WHEN l.status = 'success' THEN 1 ELSE 0 END) as success_count,
				SUM(CASE WHEN l.status = 'delayed' THEN 1 ELSE 0 END) as delayed_count,
				SUM(CASE WHEN l.status = 'held' THEN 1 ELSE 0 END) as held_count,
				SUM(CASE WHEN l.status NOT IN ('success', 'delayed', 'held') THEN 1 ELSE 0 END) as error_count,
				AVG(l.response_time) as avg_response_time
			FROM $logs_table l
			JOIN $servers_table s ON l.server_id = s.id
			WHERE l.created_at >= %s AND l.email_from IS NOT NULL
			GROUP BY s.domain, l.email_from
			ORDER BY s.domain ASC, total_sent DESC",
			$date_from
		), ARRAY_A ) ?: [];
	}

	public static function get_template_performance( $days = 30 ) {
		global $wpdb;
		$table_metrics = $wpdb->prefix . 'postal_metrics';
		$table_tpl = $wpdb->prefix . 'postal_templates';
		$date_from = date( 'Y-m-d', strtotime( "-$days days" ) );

		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT t.name, m.event_type, SUM(m.count) as total
			FROM $table_metrics m
			JOIN $table_tpl t ON m.template_id = t.id
			WHERE m.date >= %s
			GROUP BY t.name, m.event_type
			ORDER BY total DESC",
			$date_from
		), ARRAY_A ) ?: [];

		$performance = [];
		foreach ( $results as $r ) {
			if ( ! isset( $performance[$r['name']] ) ) {
				$performance[$r['name']] = [
					'sent' => 0, 'delivered' => 0, 'opened' => 0, 'clicked' => 0, 'bounced' => 0, 'delayed' => 0, 'held' => 0
				];
			}
			$type = $r['event_type'];
			$mapped_type = ( $type === 'sent' ) ? 'delivered' : $type;
			if ( isset( $performance[$r['name']][$mapped_type] ) ) {
				$performance[$r['name']][$mapped_type] += (int) $r['total'];
			}
		}
		return $performance;
	}

	public static function get_template_stats( $template_name, $days = 30 ) {
		global $wpdb;
		$table_metrics = $wpdb->prefix . 'postal_metrics';
		$table_tpl = $wpdb->prefix . 'postal_templates';
		$date_from = date( 'Y-m-d', strtotime( "-$days days" ) );

		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT m.event_type, SUM(m.count) as total
			FROM $table_metrics m
			JOIN $table_tpl t ON m.template_id = t.id
			WHERE t.name = %s AND m.date >= %s
			GROUP BY m.event_type",
			$template_name,
			$date_from
		), ARRAY_A ) ?: [];

		$stats = [
			'sent' => 0, 'delivered' => 0, 'opened' => 0, 'clicked' => 0, 'bounced' => 0, 'delayed' => 0, 'held' => 0
		];

		foreach ( $results as $r ) {
			$type = $r['event_type'];

			// Always add to the specific type found
			if ( isset( $stats[$type] ) ) {
				$stats[$type] += (int) $r['total'];
			}

			// Map 'sent' to 'delivered' as per Postal conventions (Optimistic delivery)
			// because Postal primarily sends 'MessageSent' which we log as 'sent'.
			if ( $type === 'sent' ) {
				$stats['delivered'] += (int) $r['total'];
			}
		}

		// If we tracked 'delivered' separately from 'sent' in webhook, we might want to sum them or treat them distinctly.
		// For now, let's assume 'sent' webhook event contributes to delivered count.

		// Calculate rates
		$total_delivered = $stats['delivered'] ?? 0;
		$stats['open_rate'] = $total_delivered > 0 ? round( ( $stats['opened'] / $total_delivered ) * 100, 1 ) : 0;
		$stats['click_rate'] = $total_delivered > 0 ? round( ( $stats['clicked'] / $total_delivered ) * 100, 1 ) : 0;
		$stats['bounce_rate'] = $total_delivered > 0 ? round( ( $stats['bounced'] / $total_delivered ) * 100, 1 ) : 0;

		return $stats;
	}

	public static function get_global_stats( $days = 30 ) {
		global $wpdb;
		$stats_table = $wpdb->prefix . 'postal_stats';
		$daily_table = $wpdb->prefix . 'postal_stats_daily';
		$date_from = date( 'Y-m-d', strtotime( "-$days days" ) );
		
		// Cette requête combine les données archivées (daily) et les données récentes (stats)
		// Cependant, pour simplifier et comme 'postal_stats' garde aussi l'historique (sauf si purgé),
		// on devrait idéalement interroger daily_table pour le passé et stats_table pour aujourd'hui.
		
		// Stratégie hybride : 
		// Jours < Aujourd'hui => daily_table
		// Aujourd'hui => stats_table
		
		$today = current_time( 'Y-m-d' );
		
		$sql = "
			(SELECT date, SUM(total_sent) as total_sent, SUM(total_success) as total_success, SUM(total_error) as total_errors, AVG(avg_response_time) as avg_time
			 FROM $daily_table
			 WHERE date >= %s AND date < %s
			 GROUP BY date)
			UNION ALL
			(SELECT date, SUM(sent_count) as total_sent, SUM(success_count) as total_success, SUM(error_count) as total_errors, AVG(avg_response_time) as avg_time
			 FROM $stats_table
			 WHERE date = %s
			 GROUP BY date)
			ORDER BY date ASC
		";
		
		return $wpdb->get_results( $wpdb->prepare( $sql, $date_from, $today, $today ), ARRAY_A ) ?: [];
	}

	public static function aggregate_daily_stats() {
		global $wpdb;
		$source_table = $wpdb->prefix . 'postal_stats';
		$target_table = $wpdb->prefix . 'postal_stats_daily';
		
		// On agrège tout ce qui n'est pas "Aujourd'hui" (car aujourd'hui bouge encore)
		$yesterday = date( 'Y-m-d', strtotime( 'yesterday' ) );
		
		// On récupère les dates présentes dans stats mais pas dans daily
		// Ou on fait un INSERT ... ON DUPLICATE KEY UPDATE massif
		
		$sql = "INSERT INTO $target_table (server_id, date, total_sent, total_success, total_error, avg_response_time, updated_at)
				SELECT server_id, date, SUM(sent_count), SUM(success_count), SUM(error_count), AVG(avg_response_time), NOW()
				FROM $source_table
				WHERE date <= %s
				GROUP BY server_id, date
				ON DUPLICATE KEY UPDATE
				total_sent = VALUES(total_sent),
				total_success = VALUES(total_success),
				total_error = VALUES(total_error),
				avg_response_time = VALUES(avg_response_time),
				updated_at = NOW()";
				
		$wpdb->query( $wpdb->prepare( $sql, $yesterday ) );
		
		// Optionnel : Purge des données horaires très vieilles (si on veut gagner de la place)
		// $retention_detailed = 90; 
		// $date_purge = date('Y-m-d', strtotime("-$retention_detailed days"));
		// $wpdb->query( $wpdb->prepare( "DELETE FROM $source_table WHERE date < %s", $date_purge ) );
	}
}
