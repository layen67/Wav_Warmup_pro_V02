<?php
/**
 * Script de migration pour Postal Warmup Pro - Optimisation des Statistiques
 * 
 * Usage : 
 * 1. Placer ce fichier à la racine de votre installation WordPress.
 * 2. Accéder via le navigateur (si connecté admin) ou via CLI.
 * 3. Supprimer le fichier après usage.
 */

require_once 'wp-load.php';

if ( ! current_user_can( 'manage_options' ) && php_sapi_name() !== 'cli' ) {
    wp_die( 'Accès refusé.' );
}

echo "Début de la migration des statistiques...\n";

// 1. Création de la table si elle n'existe pas (au cas où le plugin n'a pas été réactivé)
global $wpdb;
$table_daily = $wpdb->prefix . 'postal_stats_daily';
$charset_collate = $wpdb->get_charset_collate();

$sql = "CREATE TABLE IF NOT EXISTS $table_daily (
    id bigint NOT NULL AUTO_INCREMENT,
    server_id int NOT NULL,
    date date NOT NULL,
    total_sent int DEFAULT 0 NOT NULL,
    total_success int DEFAULT 0 NOT NULL,
    total_error int DEFAULT 0 NOT NULL,
    avg_response_time decimal(10,3) DEFAULT NULL,
    updated_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
    PRIMARY KEY  (id),
    UNIQUE KEY unique_daily (server_id, date),
    KEY idx_date (date)
) $charset_collate;";

require_once ABSPATH . 'wp-admin/includes/upgrade.php';
dbDelta( $sql );
echo "Table $table_daily vérifiée.\n";

// 2. Lancement de l'agrégation
if ( class_exists( 'PostalWarmup\Models\Stats' ) ) {
    echo "Agrégation de l'historique en cours...\n";
    PostalWarmup\Models\Stats::aggregate_daily_stats();
    echo "Terminé !\n";
    echo "Vous pouvez maintenant supprimer ce fichier.";
} else {
    echo "Erreur : Classe PostalWarmup\\Models\\Stats introuvable. Le plugin est-il activé ?";
}
