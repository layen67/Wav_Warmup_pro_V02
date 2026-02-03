<?php

namespace PostalWarmup\Admin;

use PostalWarmup\Models\Database;
use PostalWarmup\Models\Stats;
use PostalWarmup\Services\Logger;
use PostalWarmup\API\Sender;
use PostalWarmup\API\Client;
use PostalWarmup\Admin\TemplateManager;

/**
 * Gestionnaire des requêtes AJAX
 */
class AjaxHandler {

	public function ajax_test_server() {
		check_ajax_referer( 'pw_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [ 'message' => 'Forbidden' ] );
		$server_id = (int) $_POST['server_id'];
		$result = Sender::test_connection( $server_id );
		if ( $result['success'] ) wp_send_json_success( $result );
		else wp_send_json_error( $result );
	}

	public function ajax_regenerate_secret() {
		check_ajax_referer( 'pw_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [ 'message' => 'Forbidden' ] );
		$secret = wp_generate_password( 64, false );
		update_option( 'pw_webhook_secret', $secret );
		wp_send_json_success( [ 'secret' => $secret, 'message' => __( 'Secret régénéré.', 'postal-warmup' ) ] );
	}

	public function ajax_get_dashboard_data() {
		check_ajax_referer( 'pw_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [ 'message' => 'Forbidden' ] );

		$days = isset( $_POST['days'] ) ? (int) $_POST['days'] : 7;

		$logs = Database::get_enriched_activity( 15 );
		$summary = Stats::get_dashboard_stats();
		$chart = Stats::get_global_stats( $days );
		$errors = Stats::get_recent_errors( 5 );

		wp_send_json_success( [
			'logs' => $logs,
			'summary' => $summary,
			'chart' => $chart,
			'errors' => $errors
		] );
	}

	public function ajax_clear_logs() {
		check_ajax_referer( 'pw_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [ 'message' => 'Forbidden' ] );
		Logger::clear_all_logs();
		wp_send_json_success( [ 'message' => __( 'Logs supprimés.', 'postal-warmup' ) ] );
	}

	public function ajax_get_all_templates() {
		check_ajax_referer( 'pw_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [ 'message' => 'Forbidden' ] );
		wp_send_json_success( [ 'templates' => TemplateManager::get_all_with_meta() ] );
	}

	public function ajax_save_template() {
		check_ajax_referer( 'pw_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [ 'message' => 'Forbidden' ] );

		$name = sanitize_text_field( $_POST['name'] ?? '' );
		$data = [
			'subject'   => array_map( 'sanitize_text_field', $_POST['variants']['subject'] ?? [] ),
			'text'      => array_map( 'sanitize_textarea_field', $_POST['variants']['text'] ?? [] ),
			'html'      => array_map( 'wp_kses_post', $_POST['variants']['html'] ?? [] ),
			'from_name' => array_map( 'sanitize_text_field', $_POST['variants']['from_name'] ?? [] ),
			'mailto_subject'   => array_map( 'sanitize_text_field', $_POST['variants']['mailto_subject'] ?? [] ),
			'mailto_body'      => array_map( 'sanitize_textarea_field', $_POST['variants']['mailto_body'] ?? [] ),
			'mailto_from_name' => array_map( 'sanitize_text_field', $_POST['variants']['mailto_from_name'] ?? [] ),
			'default_label' => sanitize_text_field( $_POST['default_label'] ?? '' ),
		];
		$meta = [
			'id'        => (int) ( $_POST['id'] ?? 0 ),
			'folder_id' => (int) ( $_POST['folder_id'] ?? 0 ),
			'status'    => sanitize_text_field( $_POST['status'] ?? 'active' ),
			'tags'      => array_map( 'sanitize_text_field', explode( ',', $_POST['tags'] ?? '' ) )
		];

		$result = TemplateManager::save_template( $name, $data, $meta );
		if ( is_wp_error( $result ) ) wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		else wp_send_json_success( [ 'message' => 'Saved' ] );
	}

	public function ajax_delete_template() {
		check_ajax_referer( 'pw_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [ 'message' => 'Forbidden' ] );
		$result = TemplateManager::delete_template( sanitize_text_field( $_POST['name'] ) );
		if ( is_wp_error( $result ) ) wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		else wp_send_json_success();
	}

	public function ajax_duplicate_template() {
		check_ajax_referer( 'pw_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [ 'message' => 'Forbidden' ] );

		$name = sanitize_text_field( $_POST['name'] ?? '' );
		$new_name = sanitize_text_field( $_POST['new_name'] ?? '' );

		$result = TemplateManager::duplicate_template( $name, $new_name );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		} else {
			wp_send_json_success( [ 'id' => $result ] );
		}
	}

	public function ajax_get_template() {
		check_ajax_referer( 'pw_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [ 'message' => 'Forbidden' ] );
		$tpl = TemplateManager::get_template( sanitize_text_field( $_POST['name'] ) );
		if ( $tpl ) wp_send_json_success( $tpl );
		else wp_send_json_error( [ 'message' => 'Not found' ] );
	}

	public function ajax_save_category() {
		check_ajax_referer( 'pw_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [ 'message' => 'Forbidden' ] );
		$id = TemplateManager::save_category(
			sanitize_text_field( $_POST['name'] ),
			(int)$_POST['parent_id'],
			sanitize_hex_color( $_POST['color'] ),
			(int)$_POST['id']
		);
		wp_send_json_success( [ 'id' => $id ] );
	}

	public function ajax_delete_category() {
		check_ajax_referer( 'pw_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [ 'message' => 'Forbidden' ] );
		TemplateManager::delete_category( (int)$_POST['id'] );
		wp_send_json_success();
	}

	public function ajax_get_categories() {
		check_ajax_referer( 'pw_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [ 'message' => 'Forbidden' ] );
		wp_send_json_success( [ 'tree' => TemplateManager::get_folders_tree() ] );
	}

	public function ajax_toggle_favorite() {
		check_ajax_referer( 'pw_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [ 'message' => 'Forbidden' ] );
		TemplateManager::toggle_favorite( (int)$_POST['template_id'], filter_var( $_POST['favorite'], FILTER_VALIDATE_BOOLEAN ) );
		wp_send_json_success();
	}

	public function ajax_move_template() {
		check_ajax_referer( 'pw_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [ 'message' => 'Forbidden' ] );
		TemplateManager::move_template( (int)$_POST['template_id'], (int)$_POST['folder_id'] );
		wp_send_json_success();
	}

	public function ajax_update_template_status() {
		check_ajax_referer( 'pw_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [ 'message' => 'Forbidden' ] );
		TemplateManager::update_status( (int)$_POST['template_id'], sanitize_key( $_POST['status'] ) );
		wp_send_json_success();
	}

	public function ajax_get_template_versions() {
		check_ajax_referer( 'pw_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [ 'message' => 'Forbidden' ] );
		$versions = TemplateManager::get_versions( (int)$_POST['template_id'] );
		wp_send_json_success( [ 'versions' => $versions ] );
	}

	public function ajax_restore_template_version() {
		check_ajax_referer( 'pw_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [ 'message' => 'Forbidden' ] );
		$result = TemplateManager::restore_version( (int)$_POST['version_id'] );
		if ( is_wp_error( $result ) ) wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		else wp_send_json_success();
	}

	public function ajax_clear_cache() {
		check_ajax_referer( 'pw_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [ 'message' => 'Forbidden' ] );

		global $wpdb;
		$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_pw_%'" );
		$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_timeout_pw_%'" );

		wp_send_json_success( [ 'message' => __( 'Cache vidé.', 'postal-warmup' ) ] );
	}

	public function ajax_export_stats() {
		check_ajax_referer( 'pw_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [ 'message' => 'Forbidden' ] );

		global $wpdb;
		$stats_table = $wpdb->prefix . 'postal_stats';
		$servers_table = $wpdb->prefix . 'postal_servers';

		$results = $wpdb->get_results( "
			SELECT s.date, s.hour, s.sent_count, s.success_count, s.error_count, s.avg_response_time, sv.domain
			FROM $stats_table s
			LEFT JOIN $servers_table sv ON s.server_id = sv.id
			ORDER BY s.date DESC, s.hour DESC
		", ARRAY_A );

		$filename = 'postal-stats-' . date('Y-m-d') . '.csv';

		header( 'Content-Type: text/csv' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		$output = fopen( 'php://output', 'w' );
		fputcsv( $output, [ 'Date', 'Hour', 'Server', 'Sent', 'Success', 'Error', 'Avg Latency (ms)' ] );

		foreach ( $results as $row ) {
			fputcsv( $output, $row );
		}

		fclose( $output );
		exit;
	}

	public function ajax_reorder_templates() {
		check_ajax_referer( 'pw_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [ 'message' => 'Forbidden' ] );
		wp_send_json_success();
	}

	public function ajax_bulk_action_templates() {
		check_ajax_referer( 'pw_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [ 'message' => 'Forbidden' ] );
		wp_send_json_success();
	}

	public function ajax_export_template() {
		check_ajax_referer( 'pw_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [ 'message' => 'Forbidden' ] );

		$name = sanitize_text_field( $_POST['name'] );
		$template = TemplateManager::get_template( $name );

		if ( ! $template ) wp_send_json_error( [ 'message' => 'Template not found' ] );

		$filename = $name . '.json';

		header( 'Content-Type: application/json' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		echo json_encode( $template, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
		exit;
	}

	public function ajax_import_templates() {
		check_ajax_referer( 'pw_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [ 'message' => 'Forbidden' ] );

		$file = $_FILES['file'] ?? ( $_FILES['import_file'] ?? null );

		if ( empty( $file ) ) wp_send_json_error( [ 'message' => 'No file uploaded' ] );

		$content = file_get_contents( $file['tmp_name'] );
		$data = json_decode( $content, true );

		if ( ! $data || ! isset( $data['subject'] ) ) wp_send_json_error( [ 'message' => 'Invalid JSON' ] );

		$name = sanitize_title( pathinfo( $file['name'], PATHINFO_FILENAME ) );
		$uncat = TemplateManager::ensure_uncategorized_folder();

		$meta = [
			'id' => 0,
			'folder_id' => $uncat,
			'status' => 'active',
			'tags' => []
		];

		global $wpdb;
		$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}postal_templates WHERE name = %s", $name ) );
		if ( $exists ) $name .= '-' . time();

		TemplateManager::save_template( $name, $data, $meta );
		wp_send_json_success( [ 'message' => 'Imported as ' . $name ] );
	}

	public function ajax_get_suppression_list() {
		check_ajax_referer( 'pw_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [ 'message' => 'Forbidden' ] );

		$server_id = (int) $_POST['server_id'];
		$result = Client::request( $server_id, 'suppressions' );

		if ( is_wp_error( $result ) ) wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		else wp_send_json_success( [ 'list' => $result ] );
	}

	public function ajax_delete_suppression() {
		check_ajax_referer( 'pw_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [ 'message' => 'Forbidden' ] );

		$server_id = (int) $_POST['server_id'];
		$email = sanitize_email( $_POST['email'] );

		$result = Client::request( $server_id, 'suppressions/delete', 'POST', [ 'email' => $email ] );

		if ( is_wp_error( $result ) ) wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		else wp_send_json_success();
	}

	public function ajax_get_server_health() {
		check_ajax_referer( 'pw_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [ 'message' => 'Forbidden' ] );

		$server_id = (int) $_POST['server_id'];
		$start = microtime( true );
		$result = Client::request( $server_id, 'messages', 'GET', [ 'count' => 1 ] ); // Light request
		$duration = round( ( microtime( true ) - $start ) * 1000, 2 );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'status' => 'error', 'latency' => $duration, 'message' => $result->get_error_message() ] );
		} else {
			wp_send_json_success( [ 'status' => 'ok', 'latency' => $duration, 'message' => 'Connected' ] );
		}
	}
}
