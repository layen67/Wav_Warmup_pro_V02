/**
 * Scripts publics Postal Warmup
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        // Auto-refresh stats toutes les 30 secondes
        $('.pw-public-stat').each(function() {
            const $stat = $(this);
            const type = $stat.data('stat-type');
            
            setInterval(function() {
                // Rafraîchir si besoin
            }, 30000);
        });
    });
    
})(jQuery);