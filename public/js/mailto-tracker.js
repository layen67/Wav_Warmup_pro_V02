/**
 * Tracking des clics sur les liens mailto warmup
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        
        // Tracker les clics sur les liens mailto
        $('.pw-mailto-link[data-track="true"]').on('click', function(e) {
            const $link = $(this);
            const template = $link.data('template');
            const server = $link.data('server');
            const pageUrl = window.location.href;
            
            // Envoyer la requête AJAX (non-bloquante)
            $.post(pwMailto.ajaxurl, {
                action: 'pw_track_mailto_click',
                nonce: pwMailto.nonce,
                template: template,
                server: server,
                page_url: pageUrl
            });
            
            // Le clic continue normalement (ouvre le client mail)
        });
        
    });
    
})(jQuery);