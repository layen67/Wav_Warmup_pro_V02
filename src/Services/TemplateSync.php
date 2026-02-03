<?php

namespace PostalWarmup\Services;

use PostalWarmup\Admin\TemplateManager;
use PostalWarmup\Services\Logger;

class TemplateSync {

	public static function sync_all() {
		self::sync_files_to_db();
		self::sync_db_to_files();
	}

	public static function sync_files_to_db() {
		global $wpdb;
		$table = $wpdb->prefix . 'postal_templates';

		$files = glob( PW_TEMPLATES_DIR . '*.json' );
		if ( empty( $files ) ) return;

		foreach ( $files as $file ) {
			$name = basename( $file, '.json' );
			$file_time = filemtime( $file );

			$db_tpl = $wpdb->get_row( $wpdb->prepare(
				"SELECT id, updated_at FROM $table WHERE name = %s",
				$name
			) );

			$should_import = false;
			$comment = '';

			if ( ! $db_tpl ) {
				$should_import = true;
				$comment = 'Initial import from file';
			} else {
				$db_time = strtotime( $db_tpl->updated_at );
				if ( $file_time > ( $db_time + 2 ) ) {
					$should_import = true;
					$comment = 'Auto-updated from newer file';
				}
			}

			if ( $should_import ) {
				$data = json_decode( file_get_contents( $file ), true );
				if ( $data && is_array( $data ) ) {
					// Use TemplateManager::save_template which wraps DB logic
					// Construct meta array for TemplateManager
					$meta = [
						'id' => $db_tpl ? $db_tpl->id : null,
						'folder_id' => null, // Default
						'status' => 'active', // Default
						'tags' => [],
						'comment' => $comment
					];
					
					TemplateManager::save_template( $name, $data, $meta );
					Logger::info( "Sync: '$name' updated from file to DB" );
				}
			}
		}
	}

	public static function sync_db_to_files() {
		global $wpdb;
		$table = $wpdb->prefix . 'postal_templates';

		$templates = $wpdb->get_results( "SELECT name, data, updated_at FROM $table" );
		if ( empty( $templates ) ) return;

		if ( ! is_dir( PW_TEMPLATES_DIR ) ) {
			wp_mkdir_p( PW_TEMPLATES_DIR );
		}

		foreach ( $templates as $tpl ) {
			$file = PW_TEMPLATES_DIR . $tpl->name . '.json';
			$should_write = false;

			if ( ! file_exists( $file ) ) {
				$should_write = true;
			} else {
				$file_time = filemtime( $file );
				$db_time = strtotime( $tpl->updated_at );
				if ( $db_time > ( $file_time + 2 ) ) {
					$should_write = true;
				}
			}

			if ( $should_write ) {
				file_put_contents( $file, $tpl->data, LOCK_EX );
				Logger::info( "Sync: file for '{$tpl->name}' updated from DB" );
			}
		}
	}
}
