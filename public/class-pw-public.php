<?php
/**
 * Classe pour la partie publique du site
 */

class PW_Public {

    private $version;

    public function __construct($version) {
        $this->version = $version;
    }

    /**
     * Enregistre les styles publics
     */
    public function enqueue_styles() {
        
        $version = (defined('WP_DEBUG') && WP_DEBUG) ? time() : $this->version;

        wp_enqueue_style(
            'pw-public',
            PW_PLUGIN_URL . 'public/css/public.css',
            [],
            $version
        );
    }

    /**
     * Enregistre les scripts publics
     */
    public function enqueue_scripts() {
        
        $version = (defined('WP_DEBUG') && WP_DEBUG) ? time() : $this->version;

        wp_enqueue_script(
            'pw-public',
            PW_PLUGIN_URL . 'public/js/public.js',
            ['jquery'],
            $version,
            true
        );
    }
}