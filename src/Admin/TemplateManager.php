<?php

namespace PostalWarmup\Admin;

use PostalWarmup\Services\TemplateLoader;
use PostalWarmup\Models\Stats;
use WP_Error;

/**
 * Manager pour les Templates (Admin Logic)
 */
class TemplateManager {

	public static function get_all_with_meta() {
		// Simplified fetch using DB
		global $wpdb;
		$table = $wpdb->prefix . 'postal_templates';
		$results = $wpdb->get_results( "SELECT * FROM $table ORDER BY name ASC", ARRAY_A );
		
		// Fetch stats for all templates (last 30 days default)
		$all_stats = Stats::get_all_templates_summary(30);

		$templates = [];
		foreach ( $results as $row ) {
			$data = json_decode( $row['data'], true );
			if ( $data ) {
				$row['variants'] = [
					'subject' => count( $data['subject'] ?? [] ),
					'text'    => count( $data['text'] ?? [] ),
					'html'    => count( $data['html'] ?? [] )
				];

				// Map stats
				$s = $all_stats[$row['name']] ?? [];
				$sent = (int) ($s['usage_count'] ?? 0);
				$success = (int) ($s['success_count'] ?? 0);
				$avg = round( (float) ($s['avg_response_time'] ?? 0), 2 );
				$rate = $sent > 0 ? round(($success / $sent) * 100, 1) : 0;

				$row['stats'] = [
					'sent' => $sent,
					'success_rate' => $rate,
					'avg_time' => $avg
				];

				// Escape output
				$row['name'] = esc_html( $row['name'] );
				$templates[] = $row;
			}
		}
		return $templates;
	}

	public static function ensure_uncategorized_folder() {
		global $wpdb;
		$table = $wpdb->prefix . 'postal_template_folders';
		$id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE name = %s", 'Non catégorisé' ) );
		if ( ! $id ) {
			$wpdb->insert( $table, [
				'name'       => 'Non catégorisé',
				'color'      => '#646970',
				'menu_order' => -1,
				'created_at' => current_time( 'mysql' )
			]);
			return $wpdb->insert_id;
		}
		return (int) $id;
	}

	public static function get_folders_tree() {
		global $wpdb;
		$table_folders = $wpdb->prefix . 'postal_template_folders';
		$table_templates = $wpdb->prefix . 'postal_templates';
		
		// 1. Get all folders
		$folders = $wpdb->get_results( "SELECT * FROM $table_folders ORDER BY menu_order ASC", ARRAY_A );
		
		// 2. Get counts
		$counts = $wpdb->get_results( "SELECT folder_id, COUNT(*) as count FROM $table_templates GROUP BY folder_id", ARRAY_A );
		$count_map = [];
		foreach ( $counts as $row ) {
			$count_map[$row['folder_id']] = (int) $row['count'];
		}
		
		// 3. Build Tree
		$tree = [];
		$map = []; // Map ID to reference
		
		// Initialize map with empty children and counts
		foreach ( $folders as &$folder ) {
			$folder['id'] = (int) $folder['id'];
			$folder['parent_id'] = $folder['parent_id'] ? (int) $folder['parent_id'] : null;
			$folder['children'] = [];
			$folder['count'] = $count_map[$folder['id']] ?? 0;
			$map[$folder['id']] = &$folder;
		}
		
		// Link children to parents
		foreach ( $folders as &$folder ) {
			if ( $folder['parent_id'] && isset( $map[$folder['parent_id']] ) ) {
				$map[$folder['parent_id']]['children'][] = &$folder;
			} else {
				$tree[] = &$folder;
			}
		}
		
		return $tree;
	}

	public static function save_category( $name, $parent_id, $color, $id = null ) {
		global $wpdb;
		$table = $wpdb->prefix . 'postal_template_folders';
		
		// Fix: Ensure parent_id 0 is treated as NULL (Root)
		$parent_id = (int) $parent_id;
		if ( $parent_id === 0 ) {
			$parent_id = null;
		}

		$data = [
			'name'      => sanitize_text_field( $name ),
			'parent_id' => $parent_id,
			'color'     => $color
		];
		if ( $id ) {
			$wpdb->update( $table, $data, [ 'id' => $id ] );
			return $id;
		}
		$data['created_at'] = current_time( 'mysql' );
		$wpdb->insert( $table, $data );
		return $wpdb->insert_id;
	}

	public static function delete_category( $id ) {
		global $wpdb;
		$uncat = self::ensure_uncategorized_folder();
		if ( $id == $uncat ) return;
		
		// Move templates
		$wpdb->update( $wpdb->prefix . 'postal_templates', [ 'folder_id' => $uncat ], [ 'folder_id' => $id ] );
		// Move children
		$wpdb->update( $wpdb->prefix . 'postal_template_folders', [ 'parent_id' => null ], [ 'parent_id' => $id ] );
		// Delete
		$wpdb->delete( $wpdb->prefix . 'postal_template_folders', [ 'id' => $id ] );
	}

	public static function save_template( $name, $data, $meta ) {
		global $wpdb;
		$table = $wpdb->prefix . 'postal_templates';
		
		// Ensure name is valid filename-safe string if we were using files, strictly alphanumeric for safety
		$name = sanitize_title( $name );

		// If updating, merge with existing data to prevent data loss
		if ( $meta['id'] ) {
			$existing = $wpdb->get_row( $wpdb->prepare( "SELECT data FROM $table WHERE id = %d", $meta['id'] ), ARRAY_A );
			if ( $existing ) {
				$existing_data = json_decode( $existing['data'], true ) ?: [];
				// Merge: new data overwrites existing keys, but keeps missing keys (like mailto_* fields not present in simple form)
				$data = array_merge( $existing_data, $data );
			}
		}
		
		$json = json_encode( $data, JSON_UNESCAPED_UNICODE );
		
		$db_data = [
			'name'       => $name,
			'data'       => $json,
			'folder_id'  => $meta['folder_id'],
			'status'     => $meta['status'],
			'tags'       => implode( ',', $meta['tags'] ),
			'updated_at' => current_time( 'mysql' )
		];

		if ( $meta['id'] ) {
			$wpdb->update( $table, $db_data, [ 'id' => $meta['id'] ] );
		} else {
			$db_data['created_at'] = current_time( 'mysql' );
			$wpdb->insert( $table, $db_data );
		}

		// Also save to file for fallback/backup if directory exists
		if ( defined( 'PW_TEMPLATES_DIR' ) && is_dir( PW_TEMPLATES_DIR ) ) {
			file_put_contents( PW_TEMPLATES_DIR . $name . '.json', json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
		}

		return true;
	}

	public static function delete_template( $name ) {
		global $wpdb;
		$wpdb->delete( $wpdb->prefix . 'postal_templates', [ 'name' => $name ] );
		if ( defined( 'PW_TEMPLATES_DIR' ) ) {
			@unlink( PW_TEMPLATES_DIR . $name . '.json' );
		}
		return true;
	}

	public static function duplicate_template( $name, $new_name ) {
		global $wpdb;
		$table = $wpdb->prefix . 'postal_templates';

		$source = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE name = %s", $name ), ARRAY_A );
		if ( ! $source ) {
			return new WP_Error( 'not_found', 'Source template not found.' );
		}

		$new_name_clean = sanitize_title( $new_name );
		if ( empty( $new_name_clean ) ) {
			return new WP_Error( 'invalid_name', 'Invalid name.' );
		}

		$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE name = %s", $new_name_clean ) );
		if ( $exists ) {
			return new WP_Error( 'exists', 'Name already exists.' );
		}

		// Prepare new data
		$new_data = [
			'name'       => $new_name_clean,
			'data'       => $source['data'], // Keep same content
			'folder_id'  => $source['folder_id'],
			'status'     => $source['status'], // Keep same status
			'tags'       => $source['tags'],
			'created_at' => current_time( 'mysql' ),
			'updated_at' => current_time( 'mysql' )
		];

		$wpdb->insert( $table, $new_data );
		$new_id = $wpdb->insert_id;

		// Handle file backup if enabled
		if ( defined( 'PW_TEMPLATES_DIR' ) && is_dir( PW_TEMPLATES_DIR ) ) {
			file_put_contents( PW_TEMPLATES_DIR . $new_name_clean . '.json', $source['data'] ); // $source['data'] is already JSON
		}

		return $new_id;
	}

	public static function get_template( $name ) {
		return TemplateLoader::load( $name );
	}

	public static function move_template( $id, $folder_id ) {
		global $wpdb;
		
		// Fix: If folder_id is 0 or empty, move to 'Uncategorized'
		$folder_id = (int) $folder_id;
		if ( $folder_id <= 0 ) {
			$folder_id = self::ensure_uncategorized_folder();
		}

		$table = $wpdb->prefix . 'postal_templates';
		return $wpdb->update( 
			$table, 
			[ 'folder_id' => $folder_id, 'updated_at' => current_time( 'mysql' ) ], 
			[ 'id' => $id ], 
			[ '%d', '%s' ], 
			[ '%d' ] 
		);
	}

	public static function toggle_favorite( $id, $is_favorite ) {
		global $wpdb;
		$table = $wpdb->prefix . 'postal_templates';
		// Check if column exists, if not we might need to alter table, but assuming schema exists
		return $wpdb->update( 
			$table, 
			[ 'is_favorite' => $is_favorite ? 1 : 0 ], 
			[ 'id' => $id ], 
			[ '%d' ], 
			[ '%d' ] 
		);
	}

	public static function update_status( $id, $status ) {
		global $wpdb;
		$table = $wpdb->prefix . 'postal_templates';
		return $wpdb->update( 
			$table, 
			[ 'status' => sanitize_key( $status ), 'updated_at' => current_time( 'mysql' ) ], 
			[ 'id' => $id ], 
			[ '%s', '%s' ], 
			[ '%d' ] 
		);
	}

	public static function get_versions( $id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'postal_template_versions';
		if ( $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) !== $table ) return [];
		
		return $wpdb->get_results( 
			$wpdb->prepare( "SELECT * FROM $table WHERE template_id = %d ORDER BY created_at DESC LIMIT 20", $id ), 
			ARRAY_A 
		);
	}

	public static function restore_version( $version_id ) {
		global $wpdb;
		$table_versions = $wpdb->prefix . 'postal_template_versions';
		$table_templates = $wpdb->prefix . 'postal_templates';
		
		$version = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_versions WHERE id = %d", $version_id ), ARRAY_A );
		if ( ! $version ) return new WP_Error( 'not_found', 'Version not found' );
		
		return $wpdb->update(
			$table_templates,
			[ 
				'data' => $version['data'], 
				'updated_at' => current_time( 'mysql' ) 
			],
			[ 'id' => $version['template_id'] ]
		);
	}

	public static function get_all_tags() {
		global $wpdb;
		$table = $wpdb->prefix . 'postal_template_tags';
		// Check table existence first
		if ( $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) !== $table ) return [];
		return $wpdb->get_results( "SELECT * FROM $table ORDER BY usage_count DESC", ARRAY_A ) ?: [];
	}

	public static function render_folder_options_html( $folders, $level = 0, $selected_id = null ) {
		if ( empty( $folders ) ) return;
		
		foreach ( $folders as $folder ) {
			$indent = str_repeat( '&nbsp;&nbsp;', $level );
			$selected = ( (string)$folder['id'] === (string)$selected_id ) ? 'selected' : '';
			echo '<option value="' . esc_attr( $folder['id'] ) . '" ' . $selected . '>' . $indent . esc_html( $folder['name'] ) . '</option>';
			
			if ( ! empty( $folder['children'] ) ) {
				self::render_folder_options_html( $folder['children'], $level + 1, $selected_id );
			}
		}
	}
}
