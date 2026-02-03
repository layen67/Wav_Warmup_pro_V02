<?php

namespace PostalWarmup\Services;

use PostalWarmup\Models\Stats;

/**
 * Service de notifications par email
 */
class EmailNotifications {

	/**
	 * Envoie une notification d'erreur
	 */
	public static function send_error_notification( $message, $context = [] ) {
		
		if ( ! get_option( 'pw_notify_on_error', true ) ) {
			return;
		}
		
		$to = get_option( 'pw_notification_email', get_option( 'admin_email' ) );
		
		if ( empty( $to ) ) {
			return;
		}
		
		$subject = sprintf(
			__( '[%s] Erreur Postal Warmup', 'postal-warmup' ),
			get_bloginfo( 'name' )
		);
		
		$body = self::build_error_email( $message, $context );
		
		$headers = [
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . get_bloginfo( 'name' ) . ' <' . get_option( 'admin_email' ) . '>'
		];
		
		wp_mail( $to, $subject, $body, $headers );
	}

	/**
	 * Construit le contenu de l'email d'erreur
	 */
	private static function build_error_email( $message, $context ) {
		
		$site_name = get_bloginfo( 'name' );
		$site_url = get_bloginfo( 'url' );
		$admin_url = admin_url( 'admin.php?page=postal-warmup-logs' );
		
		ob_start();
		?>
		<!DOCTYPE html>
		<html>
		<head>
			<meta charset="UTF-8">
			<style>
				body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
				.container { max-width: 600px; margin: 0 auto; padding: 20px; }
				.header { background: #dc3232; color: white; padding: 20px; border-radius: 5px 5px 0 0; }
				.content { background: #f9f9f9; padding: 20px; border: 1px solid #ddd; border-top: none; }
				.footer { background: #f1f1f1; padding: 15px; text-align: center; font-size: 12px; color: #666; }
				.error-box { background: white; border-left: 4px solid #dc3232; padding: 15px; margin: 20px 0; }
				.button { display: inline-block; padding: 10px 20px; background: #2271b1; color: white; text-decoration: none; border-radius: 3px; }
				.meta { font-size: 13px; color: #666; }
			</style>
		</head>
		<body>
			<div class="container">
				<div class="header">
					<h2 style="margin: 0;">⚠️ Erreur Postal Warmup</h2>
				</div>
				<div class="content">
					<p><strong>Site :</strong> <?php echo esc_html( $site_name ); ?></p>
					<p><strong>Date :</strong> <?php echo current_time( 'mysql' ); ?></p>
					
					<div class="error-box">
						<strong>Message d'erreur :</strong><br>
						<?php echo esc_html( $message ); ?>
					</div>
					
					<?php if ( ! empty( $context ) ) : ?>
						<p class="meta"><strong>Contexte :</strong></p>
						<pre style="background: #f5f5f5; padding: 10px; border-radius: 3px; overflow-x: auto;"><?php echo esc_html( print_r( $context, true ) ); ?></pre>
					<?php endif; ?>
					
					<p style="margin-top: 30px;">
						<a href="<?php echo esc_url( $admin_url ); ?>" class="button">Voir les logs</a>
					</p>
				</div>
				<div class="footer">
					<p>
						Cet email a été envoyé automatiquement par Postal Warmup.<br>
						<a href="<?php echo esc_url( $site_url ); ?>"><?php echo esc_html( $site_name ); ?></a>
					</p>
				</div>
			</div>
		</body>
		</html>
		<?php
		return ob_get_clean();
	}

	/**
	 * Envoie le rapport quotidien (tâche CRON)
	 */
	public static function send_daily_report() {
		
		if ( ! get_option( 'pw_daily_report', false ) ) {
			return;
		}
		
		$to = get_option( 'pw_notification_email', get_option( 'admin_email' ) );
		
		if ( empty( $to ) ) {
			return;
		}
		
		// Récupérer les stats d'hier
		$yesterday = date( 'Y-m-d', strtotime( '-1 day' ) );
		
		// Note: Stats::get_global_stats missing in current model, need to add it or adapt logic
		// Adapting logic using Stats model methods or Database directly
		global $wpdb;
		$stats_table = $wpdb->prefix . 'postal_stats';
		
		$day_stats = $wpdb->get_row( $wpdb->prepare(
			"SELECT 
				SUM(sent_count) as total_sent,
				SUM(success_count) as total_success,
				SUM(error_count) as total_errors,
				AVG(avg_response_time) as avg_time
			FROM $stats_table
			WHERE date = %s",
			$yesterday
		), ARRAY_A );
		
		if ( ! $day_stats || $day_stats['total_sent'] == 0 ) {
			return; // Pas de données
		}
		
		$day_stats['date'] = $yesterday;
		
		$subject = sprintf(
			__( '[%s] Rapport quotidien Postal Warmup - %s', 'postal-warmup' ),
			get_bloginfo( 'name' ),
			date_i18n( 'd/m/Y', strtotime( $yesterday ) )
		);
		
		$body = self::build_daily_report_email( $day_stats );
		
		$headers = [
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . get_bloginfo( 'name' ) . ' <' . get_option( 'admin_email' ) . '>'
		];
		
		wp_mail( $to, $subject, $body, $headers );
		
		Logger::info( 'Rapport quotidien envoyé' );
	}

	/**
	 * Construit le contenu du rapport quotidien
	 */
	private static function build_daily_report_email( $stats ) {
		
		$site_name = get_bloginfo( 'name' );
		$site_url = get_bloginfo( 'url' );
		$admin_url = admin_url( 'admin.php?page=postal-warmup' );
		
		$total_sent = (int) $stats['total_sent'];
		$total_success = (int) $stats['total_success'];
		$total_errors = (int) $stats['total_errors'];
		
		$success_rate = $total_sent > 0 
			? round( ( $total_success / $total_sent ) * 100, 2 )
			: 0;
		
		$avg_time = round( (float)$stats['avg_time'], 3 );
		
		ob_start();
		?>
		<!DOCTYPE html>
		<html>
		<head>
			<meta charset="UTF-8">
			<style>
				body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
				.container { max-width: 600px; margin: 0 auto; padding: 20px; }
				.header { background: #2271b1; color: white; padding: 20px; border-radius: 5px 5px 0 0; }
				.content { background: #f9f9f9; padding: 20px; border: 1px solid #ddd; border-top: none; }
				.footer { background: #f1f1f1; padding: 15px; text-align: center; font-size: 12px; color: #666; }
				.stats-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin: 20px 0; }
				.stat-card { background: white; padding: 15px; border-radius: 5px; text-align: center; border: 1px solid #ddd; }
				.stat-value { font-size: 32px; font-weight: bold; color: #2271b1; }
				.stat-label { font-size: 13px; color: #666; margin-top: 5px; }
				.success { color: #46b450; }
				.error { color: #dc3232; }
				.button { display: inline-block; padding: 10px 20px; background: #2271b1; color: white; text-decoration: none; border-radius: 3px; }
			</style>
		</head>
		<body>
			<div class="container">
				<div class="header">
					<h2 style="margin: 0;">📊 Rapport Quotidien Postal Warmup</h2>
					<p style="margin: 5px 0 0 0; opacity: 0.9;">
						<?php echo date_i18n( 'l j F Y', strtotime( $stats['date'] ) ); ?>
					</p>
				</div>
				<div class="content">
					
					<div class="stats-grid">
						<div class="stat-card">
							<div class="stat-value"><?php echo number_format_i18n( $total_sent ); ?></div>
							<div class="stat-label">Emails envoyés</div>
						</div>
						
						<div class="stat-card">
							<div class="stat-value success"><?php echo $success_rate; ?>%</div>
							<div class="stat-label">Taux de succès</div>
						</div>
						
						<div class="stat-card">
							<div class="stat-value success"><?php echo number_format_i18n( $total_success ); ?></div>
							<div class="stat-label">Succès</div>
						</div>
						
						<div class="stat-card">
							<div class="stat-value error"><?php echo number_format_i18n( $total_errors ); ?></div>
							<div class="stat-label">Erreurs</div>
						</div>
					</div>
					
					<p style="text-align: center; margin-top: 10px; color: #666; font-size: 13px;">
						Temps de réponse moyen : <strong><?php echo $avg_time; ?>s</strong>
					</p>
					
					<?php if ( $total_errors > 0 ) : ?>
						<div style="background: #fff3cd; border-left: 4px solid #f0b849; padding: 15px; margin: 20px 0;">
							<strong>⚠️ Attention :</strong> 
							<?php echo $total_errors; ?> erreur(s) détectée(s) hier.
							Consultez les logs pour plus de détails.
						</div>
					<?php endif; ?>
					
					<p style="margin-top: 30px; text-align: center;">
						<a href="<?php echo esc_url( $admin_url ); ?>" class="button">Voir le dashboard</a>
					</p>
				</div>
				<div class="footer">
					<p>
						Rapport automatique de Postal Warmup<br>
						<a href="<?php echo esc_url( $site_url ); ?>"><?php echo esc_html( $site_name ); ?></a>
					</p>
				</div>
			</div>
		</body>
		</html>
		<?php
		return ob_get_clean();
	}
}
