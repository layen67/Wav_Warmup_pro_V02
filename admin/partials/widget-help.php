<!-- Widget latéral d'aide - À insérer dans templates.php -->

<style>
/* Widget latéral sticky */
.pw-help-widget {
    position: sticky;
    top: 32px;
    width: 320px;
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    max-height: calc(100vh - 64px);
    overflow-y: auto;
}

.pw-help-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 15px;
    font-weight: 600;
    font-size: 14px;
    border-radius: 4px 4px 0 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.pw-help-tabs {
    display: flex;
    border-bottom: 1px solid #c3c4c7;
    background: #f6f7f7;
}

.pw-help-tab {
    flex: 1;
    padding: 10px 5px;
    text-align: center;
    cursor: pointer;
    border: none;
    background: transparent;
    font-size: 12px;
    font-weight: 600;
    color: #646970;
    transition: all 0.2s;
}

.pw-help-tab:hover {
    background: #e9eaeb;
}

.pw-help-tab.active {
    background: #fff;
    color: #2271b1;
    border-bottom: 2px solid #2271b1;
}

.pw-help-content {
    padding: 15px;
}

.pw-help-tab-panel {
    display: none;
}

.pw-help-tab-panel.active {
    display: block;
}

/* Générateur de shortcode */
.pw-generator-field {
    margin-bottom: 15px;
}

.pw-generator-field label {
    display: block;
    font-weight: 600;
    font-size: 12px;
    margin-bottom: 5px;
    color: #1d2327;
}

.pw-generator-field input[type="text"],
.pw-generator-field textarea,
.pw-generator-field select {
    width: 100%;
    font-size: 12px;
    padding: 6px 8px;
}

.pw-generator-field textarea {
    min-height: 60px;
    font-family: monospace;
}

.pw-radio-group {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 5px;
}

.pw-radio-label {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 11px;
    cursor: pointer;
}

.pw-shortcode-output {
    background: #f6f7f7;
    border: 1px solid #c3c4c7;
    border-radius: 3px;
    padding: 10px;
    margin-top: 15px;
    position: relative;
}

.pw-shortcode-output pre {
    margin: 0;
    font-size: 11px;
    line-height: 1.6;
    white-space: pre-wrap;
    font-family: 'Courier New', monospace;
}

.pw-copy-btn {
    width: 100%;
    margin-top: 10px;
    background: #2271b1;
    color: white;
    border: none;
    padding: 8px;
    border-radius: 3px;
    cursor: pointer;
    font-weight: 600;
    font-size: 12px;
    transition: background 0.2s;
}

.pw-copy-btn:hover {
    background: #135e96;
}

.pw-copy-btn.copied {
    background: #46b450;
}

/* Exemples */
.pw-example-card {
    background: #f6f7f7;
    border: 1px solid #c3c4c7;
    border-radius: 3px;
    padding: 10px;
    margin-bottom: 10px;
}

.pw-example-title {
    font-weight: 600;
    font-size: 12px;
    margin-bottom: 8px;
    color: #1d2327;
}

.pw-example-code {
    background: #1e1e1e;
    color: #d4d4d4;
    padding: 8px;
    border-radius: 3px;
    font-size: 10px;
    line-height: 1.5;
    font-family: 'Courier New', monospace;
    margin-bottom: 8px;
    overflow-x: auto;
}

.pw-example-actions {
    display: flex;
    gap: 5px;
}

.pw-example-actions button {
    flex: 1;
    padding: 5px;
    font-size: 11px;
    cursor: pointer;
    border: 1px solid #c3c4c7;
    background: white;
    border-radius: 3px;
    transition: all 0.2s;
}

.pw-example-actions button:hover {
    background: #f6f7f7;
}

/* Variables */
.pw-var-section {
    margin-bottom: 15px;
}

.pw-var-section-title {
    font-weight: 600;
    font-size: 12px;
    margin-bottom: 8px;
    color: #2271b1;
    border-bottom: 1px solid #c3c4c7;
    padding-bottom: 5px;
}

.pw-var-list {
    list-style: none;
    margin: 0;
    padding: 0;
}

.pw-var-item {
    display: flex;
    align-items: start;
    gap: 8px;
    margin-bottom: 8px;
    font-size: 11px;
}

.pw-var-code {
    background: #f6f7f7;
    padding: 2px 6px;
    border-radius: 3px;
    font-family: 'Courier New', monospace;
    color: #d63638;
    cursor: pointer;
    flex-shrink: 0;
    transition: background 0.2s;
}

.pw-var-code:hover {
    background: #2271b1;
    color: white;
}

.pw-var-desc {
    color: #646970;
    line-height: 1.4;
}

/* Accordéon */
.pw-accordion {
    margin-top: 10px;
}

.pw-accordion-header {
    background: #f6f7f7;
    border: 1px solid #c3c4c7;
    padding: 8px 10px;
    cursor: pointer;
    font-weight: 600;
    font-size: 11px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-radius: 3px;
}

.pw-accordion-header:hover {
    background: #e9eaeb;
}

.pw-accordion-content {
    display: none;
    padding: 10px;
    border: 1px solid #c3c4c7;
    border-top: none;
    background: white;
}

.pw-accordion-content.open {
    display: block;
}

/* Presets visuels */
.pw-preset-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
    margin-top: 8px;
}

.pw-preset-item {
    padding: 6px;
    border-radius: 3px;
    text-align: center;
    font-size: 10px;
    font-weight: 600;
    cursor: pointer;
    border: 2px solid transparent;
    transition: all 0.2s;
}

.pw-preset-item:hover {
    transform: scale(1.05);
}

.pw-preset-primary {
    background: #2271b1;
    color: white;
}

.pw-preset-success {
    background: #46b450;
    color: white;
}

.pw-preset-danger {
    background: #dc3232;
    color: white;
}

.pw-preset-warning {
    background: #f0b849;
    color: white;
}

.pw-preset-minimal {
    background: white;
    color: #2271b1;
    border-color: #2271b1;
}

.pw-preset-gradient {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}
</style>

<div class="pw-help-widget">
    <!-- Header -->
    <div class="pw-help-header">
        <span class="dashicons dashicons-info" style="font-size: 18px;"></span>
        💡 Aide rapide
    </div>
    
    <!-- Tabs -->
    <div class="pw-help-tabs">
        <button class="pw-help-tab active" data-tab="generator">
            🔧 Générateur
        </button>
        <button class="pw-help-tab" data-tab="examples">
            📋 Exemples
        </button>
        <button class="pw-help-tab" data-tab="variables">
            📍 Variables
        </button>
    </div>
    
    <!-- Content -->
    <div class="pw-help-content">
        
        <!-- TAB 1: GÉNÉRATEUR -->
        <div class="pw-help-tab-panel active" data-panel="generator">
            <div class="pw-generator-field">
                <label>Template</label>
                <select id="pw-gen-template">
                    <?php
                    $templates = PW_Template_Loader::get_all_templates();
                    foreach ($templates as $name => $info) {
                        echo '<option value="' . esc_attr($name) . '">' . esc_html($name) . '</option>';
                    }
                    ?>
                </select>
            </div>
            
            <div class="pw-generator-field">
                <label>Label (texte bouton)</label>
                <input type="text" id="pw-gen-label" placeholder="Contact" value="Nous contacter">
            </div>
            
            <div class="pw-generator-field">
                <label>Display</label>
                <div class="pw-radio-group">
                    <label class="pw-radio-label">
                        <input type="radio" name="pw-gen-display" value="button" checked>
                        Bouton
                    </label>
                    <label class="pw-radio-label">
                        <input type="radio" name="pw-gen-display" value="link">
                        Lien
                    </label>
                    <label class="pw-radio-label">
                        <input type="radio" name="pw-gen-display" value="badge">
                        Badge
                    </label>
                </div>
            </div>
            
            <div class="pw-generator-field">
                <label>Preset (style)</label>
                <div class="pw-preset-grid">
                    <div class="pw-preset-item pw-preset-primary" data-preset="primary">Primary</div>
                    <div class="pw-preset-item pw-preset-success" data-preset="success">Success</div>
                    <div class="pw-preset-item pw-preset-danger" data-preset="danger">Danger</div>
                    <div class="pw-preset-item pw-preset-warning" data-preset="warning">Warning</div>
                    <div class="pw-preset-item pw-preset-minimal" data-preset="minimal">Minimal</div>
                    <div class="pw-preset-item pw-preset-gradient" data-preset="gradient">Gradient</div>
                </div>
                <input type="hidden" id="pw-gen-preset" value="primary">
            </div>
            
            <!-- Accordéon options avancées -->
            <div class="pw-accordion">
                <div class="pw-accordion-header" data-accordion="advanced">
                    ⚙️ Options avancées
                    <span class="dashicons dashicons-arrow-down-alt2"></span>
                </div>
                <div class="pw-accordion-content" data-content="advanced">
                    <div class="pw-generator-field">
                        <label>Override sujet</label>
                        <input type="text" id="pw-gen-subject" placeholder="Question urgente">
                    </div>
                    
                    <div class="pw-generator-field">
                        <label>Override corps</label>
                        <textarea id="pw-gen-body" placeholder="Bonjour,\n\nJe vous contacte..."></textarea>
                    </div>
                    
                    <div class="pw-generator-field">
                        <label>Style CSS personnalisé</label>
                        <input type="text" id="pw-gen-style" placeholder="color: red; font-size: 16px;">
                    </div>
                    
                    <div class="pw-generator-field">
                        <label>Classe CSS</label>
                        <input type="text" id="pw-gen-class" placeholder="ma-classe">
                    </div>
                    
                    <div class="pw-generator-field">
                        <label>Forcer serveur</label>
                        <input type="text" id="pw-gen-force-server" placeholder="check.example.com">
                    </div>
                    
                    <div class="pw-generator-field">
                        <label class="pw-radio-label">
                            <input type="checkbox" id="pw-gen-no-track">
                            Désactiver tracking
                        </label>
                    </div>
                </div>
            </div>
            
            <!-- Résultat -->
            <div class="pw-shortcode-output">
                <strong style="font-size: 11px; display: block; margin-bottom: 8px;">📋 Shortcode généré:</strong>
                <pre id="pw-generated-shortcode">[warmup_mailto template="support" label="Nous contacter"]</pre>
                <button class="pw-copy-btn" id="pw-copy-generated">
                    📋 Copier le shortcode
                </button>
            </div>
        </div>
        
        <!-- TAB 2: EXEMPLES -->
        <div class="pw-help-tab-panel" data-panel="examples">
            
            <div class="pw-example-card">
                <div class="pw-example-title">📘 Basique</div>
                <div class="pw-example-code">[warmup_mailto template="support" label="Contact"]</div>
                <div class="pw-example-actions">
                    <button class="pw-copy-example" data-code='[warmup_mailto template="support" label="Contact"]'>
                        📋 Copier
                    </button>
                    <button class="pw-edit-example" 
                            data-template="support" 
                            data-label="Contact" 
                            data-display="button" 
                            data-preset="primary">
                        ✏️ Éditer
                    </button>
                </div>
            </div>
            
            <div class="pw-example-card">
                <div class="pw-example-title">✅ Bouton vert succès</div>
                <div class="pw-example-code">[warmup_mailto template="commercial" label="Demander un devis" preset="success"]</div>
                <div class="pw-example-actions">
                    <button class="pw-copy-example" data-code='[warmup_mailto template="commercial" label="Demander un devis" preset="success"]'>
                        📋 Copier
                    </button>
                    <button class="pw-edit-example" 
                            data-template="commercial" 
                            data-label="Demander un devis" 
                            data-preset="success">
                        ✏️ Éditer
                    </button>
                </div>
            </div>
            
            <div class="pw-example-card">
                <div class="pw-example-title">⚠️ Bouton rouge urgent</div>
                <div class="pw-example-code">[warmup_mailto template="support" label="Problème urgent" preset="danger"]</div>
                <div class="pw-example-actions">
                    <button class="pw-copy-example" data-code='[warmup_mailto template="support" label="Problème urgent" preset="danger"]'>
                        📋 Copier
                    </button>
                    <button class="pw-edit-example" 
                            data-template="support" 
                            data-label="Problème urgent" 
                            data-preset="danger">
                        ✏️ Éditer
                    </button>
                </div>
            </div>
            
            <div class="pw-example-card">
                <div class="pw-example-title">📧 Lien texte simple</div>
                <div class="pw-example-code">[warmup_mailto template="info" label="Nous contacter" display="link"]</div>
                <div class="pw-example-actions">
                    <button class="pw-copy-example" data-code='[warmup_mailto template="info" label="Nous contacter" display="link"]'>
                        📋 Copier
                    </button>
                    <button class="pw-edit-example" 
                            data-template="info" 
                            data-label="Nous contacter" 
                            data-display="link">
                        ✏️ Éditer
                    </button>
                </div>
            </div>
            
            <div class="pw-example-card">
                <div class="pw-example-title">🏷️ Badge discret</div>
                <div class="pw-example-code">[warmup_mailto template="newsletter" label="S'abonner" display="badge"]</div>
                <div class="pw-example-actions">
                    <button class="pw-copy-example" data-code='[warmup_mailto template="newsletter" label="S\'abonner" display="badge"]'>
                        📋 Copier
                    </button>
                    <button class="pw-edit-example" 
                            data-template="newsletter" 
                            data-label="S'abonner" 
                            data-display="badge">
                        ✏️ Éditer
                    </button>
                </div>
            </div>
            
            <div class="pw-example-card">
                <div class="pw-example-title">🎨 Avec variables dynamiques</div>
                <div class="pw-example-code">[warmup_mailto template="info" label="Question" subject="Question sur {{page_title}}" body="Bonjour,\n\nJe vous contacte depuis {{site_name}}..."]</div>
                <div class="pw-example-actions">
                    <button class="pw-copy-example" data-code='[warmup_mailto template="info" label="Question" subject="Question sur {{page_title}}" body="Bonjour,\n\nJe vous contacte depuis {{site_name}}..."]'>
                        📋 Copier
                    </button>
                    <button class="pw-edit-example" 
                            data-template="info" 
                            data-label="Question" 
                            data-subject="Question sur {{page_title}}" 
                            data-body="Bonjour,\n\nJe vous contacte depuis {{site_name}}...">
                        ✏️ Éditer
                    </button>
                </div>
            </div>
            
        </div>
        
        <!-- TAB 3: VARIABLES -->
        <div class="pw-help-tab-panel" data-panel="variables">
            
            <div class="pw-var-section">
                <div class="pw-var-section-title">📧 Email & Serveur</div>
                <ul class="pw-var-list">
                    <li class="pw-var-item">
                        <code class="pw-var-code">{{email}}</code>
                        <span class="pw-var-desc">Email destinataire</span>
                    </li>
                    <li class="pw-var-item">
                        <code class="pw-var-code">{{domain}}</code>
                        <span class="pw-var-desc">Domaine serveur Postal</span>
                    </li>
                    <li class="pw-var-item">
                        <code class="pw-var-code">{{local}}</code>
                        <span class="pw-var-desc">Prefix (avant @)</span>
                    </li>
                </ul>
            </div>
            
            <div class="pw-var-section">
                <div class="pw-var-section-title">📅 Dates & Temps</div>
                <ul class="pw-var-list">
                    <li class="pw-var-item">
                        <code class="pw-var-code">{{date}}</code>
                        <span class="pw-var-desc">Date (<?php echo current_time('d/m/Y'); ?>)</span>
                    </li>
                    <li class="pw-var-item">
                        <code class="pw-var-code">{{time}}</code>
                        <span class="pw-var-desc">Heure (<?php echo current_time('H:i'); ?>)</span>
                    </li>
                    <li class="pw-var-item">
                        <code class="pw-var-code">{{year}}</code>
                        <span class="pw-var-desc">Année (<?php echo current_time('Y'); ?>)</span>
                    </li>
                </ul>
            </div>
            
            <div class="pw-var-section">
                <div class="pw-var-section-title">🌐 Site WordPress</div>
                <ul class="pw-var-list">
                    <li class="pw-var-item">
                        <code class="pw-var-code">{{site_name}}</code>
                        <span class="pw-var-desc"><?php echo esc_html(get_bloginfo('name')); ?></span>
                    </li>
                    <li class="pw-var-item">
                        <code class="pw-var-code">{{site_url}}</code>
                        <span class="pw-var-desc"><?php echo esc_html(get_bloginfo('url')); ?></span>
                    </li>
                    <li class="pw-var-item">
                        <code class="pw-var-code">{{page_title}}</code>
                        <span class="pw-var-desc">Titre page actuelle</span>
                    </li>
                    <li class="pw-var-item">
                        <code class="pw-var-code">{{page_url}}</code>
                        <span class="pw-var-desc">URL page actuelle</span>
                    </li>
                </ul>
            </div>
            
            <div class="pw-var-section">
                <div class="pw-var-section-title">👤 Utilisateur connecté</div>
                <ul class="pw-var-list">
                    <li class="pw-var-item">
                        <code class="pw-var-code">{{user_name}}</code>
                        <span class="pw-var-desc">Nom complet</span>
                    </li>
                    <li class="pw-var-item">
                        <code class="pw-var-code">{{user_email}}</code>
                        <span class="pw-var-desc">Email</span>
                    </li>
                    <li class="pw-var-item">
                        <code class="pw-var-code">{{user_firstname}}</code>
                        <span class="pw-var-desc">Prénom</span>
                    </li>
                    <li class="pw-var-item">
                        <code class="pw-var-code">{{user_lastname}}</code>
                        <span class="pw-var-desc">Nom de famille</span>
                    </li>
                </ul>
            </div>
            
            <div style="background: #f0f6fc; border-left: 3px solid #2271b1; padding: 10px; margin-top: 15px; font-size: 11px;">
                <strong>💡 Astuce :</strong> Cliquez sur une variable pour la copier dans le presse-papier !
            </div>
            
        </div>
        
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    
    // === NAVIGATION TABS ===
    $('.pw-help-tab').on('click', function() {
        const tab = $(this).data('tab');
        
        $('.pw-help-tab').removeClass('active');
        $(this).addClass('active');
        
        $('.pw-help-tab-panel').removeClass('active');
        $(`.pw-help-tab-panel[data-panel="${tab}"]`).addClass('active');
    });
    
    // === ACCORDÉON ===
    $('.pw-accordion-header').on('click', function() {
        const accordion = $(this).data('accordion');
        const $content = $(`.pw-accordion-content[data-content="${accordion}"]`);
        const $icon = $(this).find('.dashicons');
        
        $content.toggleClass('open');
        
        if ($content.hasClass('open')) {
            $icon.removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-up-alt2');
        } else {
            $icon.removeClass('dashicons-arrow-up-alt2').addClass('dashicons-arrow-down-alt2');
        }
    });
    
    // === GÉNÉRATEUR ===
    
    // Sélection preset visuelle
    $('.pw-preset-item').on('click', function() {
        const preset = $(this).data('preset');
        $('#pw-gen-preset').val(preset);
        $('.pw-preset-item').css('border-width', '2px');
        $(this).css('border-width', '3px');
        updateGeneratedShortcode();
    });
    
    // Mise à jour en temps réel
    $('#pw-gen-template, #pw-gen-label, #pw-gen-subject, #pw-gen-body, #pw-gen-style, #pw-gen-class, #pw-gen-force-server').on('input', updateGeneratedShortcode);
    $('input[name="pw-gen-display"]').on('change', updateGeneratedShortcode);
    $('#pw-gen-no-track').on('change', updateGeneratedShortcode);
    
    function updateGeneratedShortcode() {
        const template = $('#pw-gen-template').val();
        const label = $('#pw-gen-label').val();
        const display = $('input[name="pw-gen-display"]:checked').val();
        const preset = $('#pw-gen-preset').val();
        const subject = $('#pw-gen-subject').val();
        const body = $('#pw-gen-body').val();
        const style = $('#pw-gen-style').val();
        const cssClass = $('#pw-gen-class').val();
        const forceServer = $('#pw-gen-force-server').val();
        const noTrack = $('#pw-gen-no-track').is(':checked');
        
        let shortcode = '[warmup_mailto';
        
        if (template) shortcode += `\n  template="${template}"`;
        if (label) shortcode += `\n  label="${label}"`;
        if (display && display !== 'button') shortcode += `\n  display="${display}"`;
        if (preset && preset !== 'primary') shortcode += `\n  preset="${preset}"`;
        if (subject) shortcode += `\n  subject="${subject}"`;
        if (body) shortcode += `\n  body="${body.replace(/\n/g, '\\n')}"`;
        if (style) shortcode += `\n  style="${style}"`;
        if (cssClass) shortcode += `\n  class="${cssClass}"`;
        if (forceServer) shortcode += `\n  force_server="${forceServer}"`;
        if (noTrack) shortcode += `\n  track="false"`;
        
        shortcode += ']';
        
        $('#pw-generated-shortcode').text(shortcode);
    }
    
    // Copier shortcode généré
    $('#pw-copy-generated').on('click', function() {
        const code = $('#pw-generated-shortcode').text();
        copyToClipboard(code, $(this));
    });
    
    // === EXEMPLES ===
    
    // Copier exemple
    $('.pw-copy-example').on('click', function() {
        const code = $(this).data('code');
        copyToClipboard(code, $(this));
    });
    
    // Éditer exemple (charge dans le générateur)
    $('.pw-edit-example').on('click', function() {
        const data = $(this).data();
        
        // Switch to generator tab
        $('.pw-help-tab[data-tab="generator"]').click();
        
        // Remplir les champs
        if (data.template) $('#pw-gen-template').val(data.template);
        if (data.label) $('#pw-gen-label').val(data.label);
        if (data.display) $(`input[name="pw-gen-display"][value="${data.display}"]`).prop('checked', true);
        if (data.preset) {
            $('#pw-gen-preset').val(data.preset);
            $('.pw-preset-item').css('border-width', '2px');
            $(`.pw-preset-item[data-preset="${data.preset}"]`).css('border-width', '3px');
        }
        if (data.subject) $('#pw-gen-subject').val(data.subject);
        if (data.body) $('#pw-gen-body').val(data.body);
        if (data.style) $('#pw-gen-style').val(data.style);
        if (data.class) $('#pw-gen-class').val(data.class);
        
        updateGeneratedShortcode();
        
        // Scroll to top
        $('.pw-help-widget').scrollTop(0);
    });
    
    // === VARIABLES ===
    
    // Copier variable au clic
    $('.pw-var-code').on('click', function() {
        const variable = $(this).text();
        copyToClipboard(variable, $(this));
    });
    
    // === FONCTION UTILITAIRE ===
    
    function copyToClipboard(text, $button) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function() {
                showCopyFeedback($button);
            });
        } else {
            // Fallback
            const $temp = $('<textarea>');
            $('body').append($temp);
            $temp.val(text).select();
            document.execCommand('copy');
            $temp.remove();
            showCopyFeedback($button);
        }
    }
    
    function showCopyFeedback($button) {
        const originalText = $button.text();
        $button.text('✓ Copié !').addClass('copied');
        
        setTimeout(function() {
            $button.text(originalText).removeClass('copied');
        }, 2000);
    }
    
    // Init
    updateGeneratedShortcode();
    
});
</script>