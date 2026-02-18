<?php

namespace PostalWarmup\Admin;

class Settings {
	public function register_settings() {
		// === Section General ===
		add_settings_section( 'pw_general_section', __( 'Options Générales', 'postal-warmup' ), array( $this, 'general_section_callback' ), 'postal-warmup-settings' );
		register_setting( 'postal-warmup-settings', 'pw_global_tag', array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => 'warmup' ) );
		add_settings_field( 'pw_global_tag', __( 'Tag Postal Global', 'postal-warmup' ), array( $this, 'global_tag_field' ), 'postal-warmup-settings', 'pw_general_section' );

		register_setting( 'postal-warmup-settings', 'pw_disable_ip_logging', array( 'type' => 'boolean', 'default' => false ) );
		add_settings_field( 'pw_disable_ip_logging', __( 'Conformité RGPD', 'postal-warmup' ), array( $this, 'disable_ip_logging_field' ), 'postal-warmup-settings', 'pw_general_section' );

		// === Section Logs ===
		add_settings_section( 'pw_logs_section', __( 'Gestion des Logs', 'postal-warmup' ), array( $this, 'logs_section_callback' ), 'postal-warmup-settings' );
		register_setting( 'postal-warmup-settings', 'pw_enable_logging', array( 'type' => 'boolean', 'default' => true ) );
		register_setting( 'postal-warmup-settings', 'pw_log_mode', array( 'type' => 'string', 'sanitize_callback' => 'sanitize_key', 'default' => 'file' ) );
		add_settings_field( 'pw_enable_logging', __( 'Configuration des logs', 'postal-warmup' ), array( $this, 'enable_logging_field' ), 'postal-warmup-settings', 'pw_logs_section' );

		register_setting( 'postal-warmup-settings', 'pw_log_retention_days', array( 'type' => 'integer', 'sanitize_callback' => 'absint', 'default' => 30 ) );
		add_settings_field( 'pw_log_retention_days', __( 'Rétention des logs (jours)', 'postal-warmup' ), array( $this, 'log_retention_field' ), 'postal-warmup-settings', 'pw_logs_section' );
		
		// === Section Statistiques ===
		add_settings_section( 'pw_stats_section', __( 'Statistiques', 'postal-warmup' ), array( $this, 'stats_section_callback' ), 'postal-warmup-settings' );
		register_setting( 'postal-warmup-settings', 'pw_stats_enabled', array( 'type' => 'boolean', 'default' => true ) );
		add_settings_field( 'pw_stats_enabled', __( 'Activer les statistiques', 'postal-warmup' ), array( $this, 'stats_enabled_field' ), 'postal-warmup-settings', 'pw_stats_section' );
		
		// === Section Limites (RESTORED) ===
		add_settings_section( 'pw_limits_section', __( 'Limites d\'envoi', 'postal-warmup' ), array( $this, 'limits_section_callback' ), 'postal-warmup-settings' );
		register_setting( 'postal-warmup-settings', 'pw_daily_limit', array( 'type' => 'integer', 'sanitize_callback' => 'absint', 'default' => 0 ) );
		add_settings_field( 'pw_daily_limit', __( 'Limite quotidienne', 'postal-warmup' ), array( $this, 'daily_limit_field' ), 'postal-warmup-settings', 'pw_limits_section' );
		register_setting( 'postal-warmup-settings', 'pw_rate_limit_per_hour', array( 'type' => 'integer', 'sanitize_callback' => 'absint', 'default' => 0 ) );
		add_settings_field( 'pw_rate_limit_per_hour', __( 'Limite horaire par serveur', 'postal-warmup' ), array( $this, 'rate_limit_field' ), 'postal-warmup-settings', 'pw_limits_section' );
		register_setting( 'postal-warmup-settings', 'pw_max_retries', array( 'type' => 'integer', 'sanitize_callback' => 'absint', 'default' => 3 ) );
		add_settings_field( 'pw_max_retries', __( 'Nombre de tentatives', 'postal-warmup' ), array( $this, 'max_retries_field' ), 'postal-warmup-settings', 'pw_limits_section' );

		// === Section Notifications (RESTORED) ===
		add_settings_section( 'pw_notifications_section', __( 'Notifications', 'postal-warmup' ), array( $this, 'notifications_section_callback' ), 'postal-warmup-settings' );
		register_setting( 'postal-warmup-settings', 'pw_notification_email', array( 'type' => 'string', 'sanitize_callback' => 'sanitize_email', 'default' => get_option( 'admin_email' ) ) );
		add_settings_field( 'pw_notification_email', __( 'Email de notification', 'postal-warmup' ), array( $this, 'notification_email_field' ), 'postal-warmup-settings', 'pw_notifications_section' );
	}

	public function general_section_callback() { echo '<p>' . __( 'Configuration générale des envois.', 'postal-warmup' ) . '</p>'; }
	
	public function global_tag_field() {
		$value = get_option( 'pw_global_tag', 'warmup' );
		echo '<input type="text" name="pw_global_tag" value="' . esc_attr( $value ) . '" class="regular-text">';
		echo '<p class="description">' . __( 'Ce tag sera ajouté à tous les emails envoyés par le plugin pour faciliter le filtrage dans Postal.', 'postal-warmup' ) . '</p>';
	}

	public function disable_ip_logging_field() {
		$value = get_option( 'pw_disable_ip_logging', false );
		echo '<label><input type="checkbox" name="pw_disable_ip_logging" value="1" ' . checked( $value, true, false ) . '> ' . __( 'Désactiver le tracking des adresses IP (Mailto)', 'postal-warmup' ) . '</label>';
		echo '<p class="description">' . __( 'Si désactivé, les IPs ne seront pas stockées lors des clics sur les liens mailto. Sinon, elles seront anonymisées (dernier octet masqué).', 'postal-warmup' ) . '</p>';
	}

	public function logs_section_callback() { echo '<p>' . __( 'Gérez la conservation des logs.', 'postal-warmup' ) . '</p>'; }
	public function stats_section_callback() { echo '<p>' . __( 'Configuration des statistiques.', 'postal-warmup' ) . '</p>'; }
	public function limits_section_callback() { echo '<p>' . __( 'Définissez des limites pour éviter l\'abus.', 'postal-warmup' ) . '</p>'; }
	public function notifications_section_callback() { echo '<p>' . __( 'Configuration des alertes email.', 'postal-warmup' ) . '</p>'; }

	public function enable_logging_field() {
		$enabled = get_option( 'pw_enable_logging', true );
		$mode = get_option( 'pw_log_mode', 'file' );
		
		echo '<label><input type="checkbox" name="pw_enable_logging" value="1" ' . checked( $enabled, true, false ) . '> ' . __( 'Activer les logs', 'postal-warmup' ) . '</label><br><br>';
		
		echo '<label for="pw_log_mode">' . __( 'Mode de stockage :', 'postal-warmup' ) . '</label><br>';
		echo '<select name="pw_log_mode" id="pw_log_mode">';
		echo '<option value="file" ' . selected( $mode, 'file', false ) . '>' . __( 'Fichier uniquement (Recommandé)', 'postal-warmup' ) . '</option>';
		echo '<option value="db" ' . selected( $mode, 'db', false ) . '>' . __( 'Base de données uniquement', 'postal-warmup' ) . '</option>';
		echo '<option value="both" ' . selected( $mode, 'both', false ) . '>' . __( 'Les deux (Debug)', 'postal-warmup' ) . '</option>';
		echo '<option value="error_db" ' . selected( $mode, 'error_db', false ) . '>' . __( 'Fichier + BDD (Erreurs seulement)', 'postal-warmup' ) . '</option>';
		echo '</select>';
		echo '<p class="description">' . __( 'Pour des performances optimales, utilisez "Fichier uniquement" ou "Fichier + BDD (Erreurs seulement)".', 'postal-warmup' ) . '</p>';
	}

	public function log_retention_field() {
		$value = get_option( 'pw_log_retention_days', 30 );
		echo '<input type="number" name="pw_log_retention_days" value="' . esc_attr( $value ) . '" class="small-text"> ' . __( 'jours', 'postal-warmup' );
	}
	public function stats_enabled_field() {
		$value = get_option( 'pw_stats_enabled', true );
		echo '<input type="checkbox" name="pw_stats_enabled" value="1" ' . checked( $value, true, false ) . '> ' . __( 'Activer', 'postal-warmup' );
	}
	public function daily_limit_field() {
		$value = get_option( 'pw_daily_limit', 0 );
		echo '<input type="number" name="pw_daily_limit" value="' . esc_attr( $value ) . '" class="small-text"> ' . __( '0 = illimité', 'postal-warmup' );
	}
	public function rate_limit_field() {
		$value = get_option( 'pw_rate_limit_per_hour', 0 );
		echo '<input type="number" name="pw_rate_limit_per_hour" value="' . esc_attr( $value ) . '" class="small-text"> ' . __( '0 = illimité', 'postal-warmup' );
	}
	public function max_retries_field() {
		$value = get_option( 'pw_max_retries', 3 );
		echo '<input type="number" name="pw_max_retries" value="' . esc_attr( $value ) . '" class="small-text">';
	}
	public function notification_email_field() {
		$value = get_option( 'pw_notification_email', get_option( 'admin_email' ) );
		echo '<input type="email" name="pw_notification_email" value="' . esc_attr( $value ) . '" class="regular-text">';
	}
}
