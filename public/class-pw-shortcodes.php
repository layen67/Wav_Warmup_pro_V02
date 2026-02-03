<?php
/**
 * Classe de gestion des shortcodes
 */

class PW_Shortcodes {

    /**
     * Enregistre tous les shortcodes
     */
    public function register_shortcodes() {
        add_shortcode('email_warmup', [$this, 'email_warmup_shortcode']);
        add_shortcode('pw_stats', [$this, 'stats_shortcode']);
    }

    /**
     * Shortcode [email_warmup]
     * Génère un lien mailto avec template warmup
     * 
     * Usage: [email_warmup prefix="support" label="Nous contacter"]
     */
    public function email_warmup_shortcode($atts) {
        
        $atts = shortcode_atts([
            'prefix' => 'support',
            'label'  => __('Nous contacter', 'postal-warmup'),
            'class'  => 'warmup-btn',
            'style'  => ''
        ], $atts, 'email_warmup');
        
        // Récupérer le serveur le moins utilisé
        $server = PW_Database::get_least_used_server();
        
        if (!$server) {
            return '<strong>' . __('Aucun serveur Postal configuré.', 'postal-warmup') . '</strong>';
        }
        
        $email = $atts['prefix'] . '@' . $server['domain'];
        
        // Charger le template
        $template = PW_Template_Loader::load($atts['prefix'], $server['domain']);
        
        $subject = PW_Template_Loader::pick_random($template['subject']);
        $body = PW_Template_Loader::pick_random($template['text']);
        
        // Nettoyer le corps pour mailto
        $body = strip_tags($body);
        $body = str_replace(["\r\n", "\n", "\r"], '%0D%0A', $body);
        
        // Construire l'URL mailto
        $mailto = sprintf(
            'mailto:%s?subject=%s&body=%s',
            rawurlencode($email),
            rawurlencode($subject),
            $body
        );
        
        // Générer le HTML
        $class = esc_attr($atts['class']);
        $style = $atts['style'] ? ' style="' . esc_attr($atts['style']) . '"' : '';
        $label = esc_html($atts['label']);
        
        return sprintf(
            '<a href="%s" class="%s"%s>%s</a>',
            esc_url($mailto),
            $class,
            $style,
            $label
        );
    }

    /**
     * Shortcode [pw_stats]
     * Affiche des statistiques publiques
     * 
     * Usage: [pw_stats type="total"]
     * Types: total, today, success_rate
     */
    public function stats_shortcode($atts) {
        
        $atts = shortcode_atts([
            'type'  => 'total',
            'class' => 'pw-public-stat'
        ], $atts, 'pw_stats');
        
        // Vérifier si les stats sont publiques
        if (!get_option('pw_public_stats', false)) {
            return '';
        }
        
        $stats = PW_Stats::get_dashboard_stats();
        
        $value = '';
        
        switch ($atts['type']) {
            case 'total':
                $value = number_format_i18n($stats['total_sent']);
                break;
                
            case 'today':
                $value = number_format_i18n($stats['sent_today']);
                break;
                
            case 'success_rate':
                $value = $stats['success_rate'] . '%';
                break;
                
            case 'servers':
                $value = $stats['active_servers'] . ' / ' . $stats['total_servers'];
                break;
                
            default:
                $value = '';
        }
        
        if (empty($value)) {
            return '';
        }
        
        return sprintf(
            '<span class="%s" data-stat-type="%s">%s</span>',
            esc_attr($atts['class']),
            esc_attr($atts['type']),
            esc_html($value)
        );
    }
}