<?php

namespace PostalWarmup\Services;

/**
 * Gestionnaire de logs
 */
class Logger {

	const LEVEL_DEBUG = 'DEBUG';
	const LEVEL_INFO = 'INFO';
	const LEVEL_WARNING = 'WARNING';
	const LEVEL_ERROR = 'ERROR';
	const LEVEL_CRITICAL = 'CRITICAL';

	/**
	 * Répertoire des logs
	 */
	private static function get_log_dir() {
		$upload_dir = wp_upload_dir();
		$log_dir = $upload_dir['basedir'] . '/postal-warmup-logs';

		if ( ! file_exists( $log_dir ) ) {
			wp_mkdir_p( $log_dir );
			
			// Protection .htaccess
			$htaccess = $log_dir . '/.htaccess';
			if ( ! file_exists( $htaccess ) ) {
				file_put_contents( $htaccess, "Deny from all\n" );
			}
			
			// Protection index.php
			$index = $log_dir . '/index.php';
			if ( ! file_exists( $index ) ) {
				file_put_contents( $index, "<?php\n// Silence is golden." );
			}
		}

		return $log_dir;
	}

	/**
	 * Fichier de log du jour
	 */
	private static function get_log_file() {
		$log_dir = self::get_log_dir();
		return $log_dir . '/postal-' . date( 'Y-m-d' ) . '.log';
	}

	/**
	 * Log un message
	 */
	public static function log( $message, $level = self::LEVEL_INFO, $context = [] ) {
		
		if ( ! get_option( 'pw_enable_logging', true ) ) {
			return;
		}

		$mode = get_option( 'pw_log_mode', 'file' );

		// Log File : file, both, error_db (always file)
		if ( in_array( $mode, [ 'file', 'both', 'error_db' ] ) ) {
			self::log_to_file( $message, $level, $context );
		}

		// Log DB : db, both
		if ( in_array( $mode, [ 'db', 'both' ] ) ) {
			self::log_to_database( $message, $level, $context );
		} elseif ( $mode === 'error_db' ) {
			// error_db : DB only if ERROR or CRITICAL
			if ( in_array( $level, [ self::LEVEL_ERROR, self::LEVEL_CRITICAL ] ) ) {
				self::log_to_database( $message, $level, $context );
			}
		}
	}

	/**
	 * Log dans un fichier
	 */
	private static function log_to_file( $message, $level, $context ) {
		try {
			$file = self::get_log_file();
			$timestamp = current_time( 'mysql' );
			
			// On évite d'écrire des infos sensibles dans le contexte si possible
			// Mais ici on suppose que $context est déjà safe (le caller doit gérer)
			$context_str = ! empty( $context ) ? ' ' . json_encode( $context, JSON_UNESCAPED_UNICODE ) : '';
			$line = "[$timestamp] [$level] $message$context_str\n";

			$dir = dirname( $file );
			if ( ! is_dir( $dir ) ) {
				wp_mkdir_p( $dir );
			}

			file_put_contents( $file, $line, FILE_APPEND | LOCK_EX );

			// Rotation simple (> 10MB)
			if ( file_exists( $file ) && filesize( $file ) > 10 * 1024 * 1024 ) {
				self::rotate_current_log();
			}
		} catch ( \Exception $e ) {
			error_log( 'PW_Logger: Impossible d\'écrire dans le fichier de log - ' . $e->getMessage() );
		}
	}

	/**
	 * Log dans la base de données
	 */
	private static function log_to_database( $message, $level, $context ) {
		global $wpdb;

		try {
			$table = $wpdb->prefix . 'postal_logs';
			
			// Quick check to avoid errors if table missing during dev
			// In prod, table should exist.
			
			$data = [
				'server_id'     => isset( $context['server_id'] ) ? (int) $context['server_id'] : 0,
				'level'         => $level,
				'message'       => $message,
				'context'       => ! empty( $context ) ? json_encode( $context, JSON_UNESCAPED_UNICODE ) : null,
				'email_to'      => isset( $context['email_to'] ) ? $context['email_to'] : null,
				'email_from'    => isset( $context['email_from'] ) ? $context['email_from'] : null,
				'template_used' => isset( $context['template'] ) ? $context['template'] : null,
				'status'        => isset( $context['status'] ) ? $context['status'] : null,
				'response_time' => isset( $context['response_time'] ) ? $context['response_time'] : null,
				'created_at'    => current_time( 'mysql' )
			];

			$wpdb->insert( $table, $data );

		} catch ( \Exception $e ) {
			// Silent fail
		}
	}

	private static function rotate_current_log() {
		$current = self::get_log_file();
		if ( ! file_exists( $current ) ) {
			return;
		}
		
		// Keep last 5 logs
		$dir = dirname( $current );
		$filename = basename( $current );
		$rotations = 5;
		
		for ( $i = $rotations; $i > 0; $i-- ) {
			$old = $current . '.' . $i;
			$new = $current . '.' . ( $i + 1 );
			if ( file_exists( $old ) ) {
				if ( $i == $rotations ) {
					@unlink( $old );
				} else {
					@rename( $old, $new );
				}
			}
		}
		
		@rename( $current, $current . '.1' );
	}

	/**
	 * Nettoyage des vieux logs (CRON)
	 */
	public static function cleanup_old_logs() {
		$days = get_option( 'pw_log_retention_days', 30 );
		
		// Fichiers
		$log_dir = self::get_log_dir();
		$files = glob( $log_dir . '/postal-*.log*' );
		if ( $files ) {
			foreach ( $files as $file ) {
				if ( filemtime( $file ) < strtotime( "-$days days" ) ) {
					@unlink( $file );
				}
			}
		}

		// BDD (Optimisation Batch)
		global $wpdb;
		$table = $wpdb->prefix . 'postal_logs';
		$date = date( 'Y-m-d H:i:s', strtotime( "-$days days" ) );
		
		$batch_size = 1000;
		$rows_affected = 0;
		
		do {
			$wpdb->query( $wpdb->prepare( "DELETE FROM $table WHERE created_at < %s LIMIT %d", $date, $batch_size ) );
			$rows_affected = $wpdb->rows_affected;
			
			// Petite pause pour ne pas surcharger la base
			if ( $rows_affected > 0 ) {
				usleep( 50000 ); // 50ms
			}
		} while ( $rows_affected >= $batch_size );
	}

	public static function debug( $message, $context = [] ) { self::log( $message, self::LEVEL_DEBUG, $context ); }
	public static function info( $message, $context = [] ) { self::log( $message, self::LEVEL_INFO, $context ); }
	public static function warning( $message, $context = [] ) { self::log( $message, self::LEVEL_WARNING, $context ); }
	public static function error( $message, $context = [] ) { self::log( $message, self::LEVEL_ERROR, $context ); }
	public static function critical( $message, $context = [] ) { self::log( $message, self::LEVEL_CRITICAL, $context ); }
	
	public static function clear_all_logs() {
		global $wpdb;
		$log_dir = self::get_log_dir();
		$files = glob( $log_dir . '/postal-*.log*' );
		if ( $files ) {
			foreach ( $files as $file ) { @unlink( $file ); }
		}
		$table = $wpdb->prefix . 'postal_logs';
		$wpdb->query( "TRUNCATE TABLE $table" );
		return true;
	}
}
