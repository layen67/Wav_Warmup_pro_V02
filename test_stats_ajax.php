<?php
define('DOING_AJAX', true);
require_once 'wp-load.php';

// Mock request
$_POST['server_id'] = 1; // Assuming 1 is a valid server ID
$_POST['days'] = 30;
$_POST['nonce'] = wp_create_nonce('pw_admin_nonce');

// Instantiate Handler
$handler = new \PostalWarmup\Admin\AjaxHandler();

// Capture output
ob_start();
try {
    // We can't call ajax_get_server_detail directly because it checks nonce and capability which might fail in CLI
    // So we will simulate the logic inside it.

    if ( ! current_user_can( 'manage_options' ) ) {
        echo "Error: capability check would fail in CLI without user context. Skipping check for test.\n";
    }

    $server_id = (int) $_POST['server_id'];
    $days = (int) $_POST['days'];

    echo "Testing Stats::get_server_detail_breakdown for Server $server_id, Days $days...\n";

    $stats = \PostalWarmup\Models\Stats::get_server_detail_breakdown( $server_id, $days );

    echo "Result:\n";
    print_r($stats);

} catch (\Throwable $e) {
    echo "Exception: " . $e->getMessage();
}
$output = ob_get_clean();
echo $output;
