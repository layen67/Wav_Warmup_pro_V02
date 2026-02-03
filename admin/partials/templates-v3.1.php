<?php 
/** 
 * admin/partials/templates-v3.1.php 
 * Interface Ultra-Moderne avec toutes les features 
 */ 
 
if (!defined('ABSPATH')) exit; 
 
// Récupérer tous les templates avec métadonnées 
$templates = PW_Template_Manager::get_all_with_meta(); 

// Séparer le template système
$system_template = array_filter($templates, fn($t) => $t['name'] === 'null');
$user_templates = array_filter($templates, fn($t) => $t['name'] !== 'null');

$folders = PW_Template_Manager::get_folders_tree(); 
$uncategorized_id = PW_Template_Manager::ensure_uncategorized_folder();
$tags = PW_Template_Manager::get_all_tags(); 
$stats_global = PW_Stats::get_templates_global_stats(); 

// === DEBUG MODE ===
$debug_mode = defined('WP_DEBUG') && WP_DEBUG; 

// Traiter la réparation de la DB
if (isset($_POST['pw_action']) && $_POST['pw_action'] === 'fix_db') {
    check_admin_referer('pw_fix_db');
    require_once PW_INCLUDES_DIR . 'class-pw-activator.php';
    PW_Activator::activate();
    echo '<div class="notice notice-success"><p>✅ Base de données initialisée avec succès !</p></div>';
}

// Traiter la création du template de test
if (isset($_POST['pw_action']) && $_POST['pw_action'] === 'create_test_template') {
    check_admin_referer('pw_create_test_template');
    
    $test_data = [
        'subject' => ['Bienvenue !', 'Merci pour votre message'],
        'text' => ["Bonjour,\n\nMerci de nous avoir contactés."],
        'html' => ['<p>Bonjour,</p><p>Merci de nous avoir contactés.</p>'],
        'from_name' => ['Support', 'Service Client'],
        'mailto_subject' => [],
        'mailto_body' => [],
        'mailto_from_name' => []
    ];
    
    $result = PW_Template_Loader::save_template('test-auto', $test_data);
    
    if ($result === true) {
        echo '<div class="notice notice-success"><p>✅ Template de test créé avec succès ! Rechargez la page.</p></div>';
        echo '<script>setTimeout(function(){ location.reload(); }, 2000);</script>';
        // Refresh template list
        $templates = PW_Template_Manager::get_all_with_meta();
    } else {
        echo '<div class="notice notice-error"><p>❌ Erreur: ' . $result->get_error_message() . '</p></div>';
    }
}
?> 

<?php 
// Vérification automatique de la DB pour tout le monde (pas seulement debug)
global $wpdb;
$table_templates = $wpdb->prefix . 'postal_templates';
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_templates'") === $table_templates;

if (!$table_exists): ?>
    <div class="notice notice-error" style="margin: 20px 0; padding: 20px;">
        <h3 style="margin-top:0;">🚨 Base de données incomplète</h3>
        <p>Certaines tables nécessaires au fonctionnement des templates v3.1 sont manquantes.</p>
        <form method="post" style="margin-top:15px;">
            <?php wp_nonce_field('pw_fix_db'); ?>
            <input type="hidden" name="pw_action" value="fix_db">
            <button type="submit" class="button button-primary">
                🔧 Initialiser les tables de la base de données
            </button>
        </form>
    </div>
<?php endif; ?>

<?php if ($debug_mode): ?>
    <div class="notice notice-info" style="margin: 20px 0; padding: 15px; background: #e7f0ff; border-left: 4px solid #2271b1;">
        <h3 style="margin-top:0;">🔍 MODE DEBUG ACTIVÉ</h3>
        <p><strong>Templates trouvés:</strong> <?php echo count($templates); ?></p>
        
        <?php
        // Vérifier DB
        $table = $wpdb->prefix . 'postal_templates';
        $db_count = $table_exists ? $wpdb->get_var("SELECT COUNT(*) FROM $table") : 0;
        echo '<p><strong>Templates en DB:</strong> ' . (int)$db_count . '</p>';
        
        // Vérifier fichiers
        $files = glob(PW_TEMPLATES_DIR . '*.json');
        echo '<p><strong>Fichiers JSON:</strong> ' . count($files ?: []) . '</p>';
        
        // Vérifier feature flags
        $flags = get_option('pw_feature_flags', []);
        echo '<p><strong>Feature flags:</strong></p><pre style="background:#f6f7f7;padding:10px;">';
        print_r($flags);
        echo '</pre>';
        
        // Afficher templates
        if (!empty($templates)) {
            echo '<p><strong>Liste des templates:</strong></p><ul>';
            foreach ($templates as $name => $info) {
                echo '<li>' . esc_html($name) . ' (source: ' . esc_html($info['source'] ?? 'unknown') . ')</li>';
            }
            echo '</ul>';
        }
        ?>
    </div>
<?php endif; ?>

<?php if (empty($templates)): ?>
    <div class="notice notice-warning" style="margin: 20px 0; padding: 20px;">
        <h3>⚠️ Aucun template trouvé</h3>
        <p>Créez votre premier template en cliquant sur "Nouveau Template" ci-dessus.</p>
        <p><strong>Ou bien, créez un template de test automatiquement:</strong></p>
        <form method="post" style="margin-top:15px;">
            <?php wp_nonce_field('pw_create_test_template'); ?>
            <input type="hidden" name="pw_action" value="create_test_template">
            <button type="submit" class="button button-primary button-large">
                ✨ Créer un template de test automatique
            </button>
        </form>
    </div>
<?php endif; ?>
 
<div class="pw-templates-v31"> 
     
    <!-- Header avec actions --> 
    <div class="pw-header"> 
        <div class="pw-header-left"> 
            <h1> 
                <span class="dashicons dashicons-email-alt"></span> 
                Templates Postal Warmup 
                <span class="pw-version-badge">v3.1 PRO</span> 
            </h1> 
            <p class="pw-subtitle"> 
                <?php echo count($user_templates); ?> templates ·  
                <?php echo number_format($stats_global['total_sent']); ?> envois ·  
                <?php echo $stats_global['avg_success_rate']; ?>% succès 
            </p> 
        </div> 
         
        <div class="pw-header-actions"> 
            <form method="post" style="display:inline-block;">
                <input type="hidden" name="pw_action" value="fix_db">
                <?php wp_nonce_field('pw_fix_db'); ?>
                <button type="submit" class="pw-btn pw-btn-secondary" title="Réparer/Initialiser les tables si les templates ne s'affichent pas">
                    <span class="dashicons dashicons-admin-tools"></span>
                    Initialiser les tables
                </button>
            </form>
            <button class="pw-btn pw-btn-secondary" id="pw-import-btn"> 
                <span class="dashicons dashicons-upload"></span> 
                Importer 
            </button> 
            <button class="pw-btn pw-btn-primary" id="pw-new-template-btn"> 
                <span class="dashicons dashicons-plus-alt"></span> 
                Nouveau Template 
            </button> 
        </div> 
    </div> 
     
    <!-- Barre de recherche et filtres --> 
    <div class="pw-toolbar"> 
        <div class="pw-search-container"> 
            <span class="dashicons dashicons-search"></span> 
            <input  
                type="text"  
                id="pw-search-input"  
                placeholder="Rechercher par nom, tag, contenu..." 
                autocomplete="off" 
            > 
            <button class="pw-search-clear" style="display:none;">×</button> 
        </div> 
         
        <div class="pw-filters"> 
            <select id="pw-filter-status" class="pw-filter-select"> 
                <option value="">Tous les statuts</option> 
                <option value="active">🟢 Actifs</option> 
                <option value="draft">🟡 Brouillons</option> 
                <option value="archived">🔴 Archivés</option> 
                <option value="test">🔵 Tests</option> 
            </select> 
             
            <select id="pw-filter-folder" class="pw-filter-select"> 
                <option value="">Tous les dossiers</option> 
                <?php PW_Template_Manager::render_folder_options_html($folders); ?>
            </select> 
             
            <button class="pw-btn pw-btn-icon" id="pw-toggle-view" title="Changer la vue"> 
                <span class="dashicons dashicons-grid-view"></span> 
            </button> 
        </div> 
    </div> 
     
    <!-- Layout 3 colonnes --> 
    <div class="pw-main-layout"> 
         
        <!-- Sidebar gauche (Dossiers + Tags) --> 
        <aside class="pw-sidebar"> 
             
            <!-- Section Dossiers --> 
            <div class="pw-sidebar-section"> 
                <div class="pw-section-header"> 
                    <h3> 
                        <span class="dashicons dashicons-category"></span> 
                        Dossiers 
                    </h3> 
                    <button class="pw-btn-icon-sm" id="pw-add-folder-btn"> 
                        <span class="dashicons dashicons-plus"></span> 
                    </button> 
                </div> 
                 
                <ul class="pw-folder-list" id="pw-folders-tree"> 
                    <li class="pw-folder-item active" data-folder-id=""> 
                        <span class="pw-folder-icon dashicons dashicons-portfolio"></span> 
                        <span class="pw-folder-name">Tous les templates</span> 
                        <span class="pw-folder-count"><?php echo count($user_templates); ?></span> 
                    </li> 
                     
                    <?php 
                    if (!empty($folders)) {
                        PW_Template_Manager::render_folder_tree_html($folders, $uncategorized_id);
                    } 
                    ?> 
                </ul> 
            </div> 
             
            <!-- Section Favoris --> 
            <div class="pw-sidebar-section"> 
                <div class="pw-section-header"> 
                    <h3> 
                        <span class="dashicons dashicons-star-filled"></span> 
                        Favoris 
                    </h3> 
                </div> 
                <ul class="pw-quick-links"> 
                    <li> 
                        <a href="#" class="pw-quick-link" data-filter="favorite"> 
                            Mes favoris 
                            <span class="pw-count" id="pw-favorites-count"> 
                                <?php echo count(array_filter($user_templates, fn($t) => $t['is_favorite'])); ?> 
                            </span> 
                        </a> 
                    </li> 
                </ul> 
            </div> 

            <!-- Section Système -->
            <div class="pw-sidebar-section">
                <div class="pw-section-header">
                    <h3>
                        <span class="dashicons dashicons-admin-settings"></span>
                        Système
                    </h3>
                </div>
                <ul class="pw-folder-list">
                    <li class="pw-system-item" data-filter="system">
                        <span class="pw-folder-icon dashicons dashicons-admin-generic"></span>
                        <span class="pw-folder-name">Template Système (null)</span>
                        <span class="pw-folder-count">1</span>
                    </li>
                </ul>
            </div>
             
            <!-- Section Tags --> 
            <div class="pw-sidebar-section"> 
                <div class="pw-section-header"> 
                    <h3> 
                        <span class="dashicons dashicons-tag"></span> 
                        Tags 
                    </h3> 
                </div> 
                 
                <div class="pw-tags-cloud"> 
                    <?php if (!empty($tags)): foreach ($tags as $tag): ?> 
                        <button  
                            class="pw-tag-pill"  
                            data-tag="<?php echo esc_attr($tag['name']); ?>" 
                            style="background: <?php echo $tag['color']; ?>20; color: <?php echo $tag['color']; ?>;" 
                        > 
                            #<?php echo esc_html($tag['name']); ?> 
                            <span class="pw-tag-count"><?php echo $tag['usage_count']; ?></span> 
                        </button> 
                    <?php endforeach; endif; ?> 
                </div> 
            </div> 
             
            <!-- Stats rapides --> 
            <?php include __DIR__ . '/template-stats-widget.php'; ?> 
             
        </aside> 
         
        <!-- Zone principale (Grille de templates) --> 
        <main class="pw-content"> 
             
            <div class="pw-templates-grid" id="pw-templates-container"> 
                 
                <?php foreach ($user_templates as $tpl): ?> 
                     
                    <div  
                        class="pw-template-card"  
                        data-template-id="<?php echo $tpl['id']; ?>" 
                        data-template-name="<?php echo esc_attr($tpl['name']); ?>" 
                        data-status="<?php echo $tpl['status']; ?>" 
                        data-folder="<?php echo $tpl['folder_id']; ?>" 
                        draggable="true" 
                    > 
                        <!-- Header --> 
                        <div class="pw-card-header"> 
                            <div class="pw-card-drag-handle"> 
                                <span class="dashicons dashicons-menu"></span> 
                            </div> 
                             
                            <div class="pw-card-title-group"> 
                                <h4 class="pw-card-title"><?php echo esc_html($tpl['name']); ?></h4> 
                                 
                                <div class="pw-card-badges"> 
                                    <?php if ($tpl['name'] === 'null'): ?>
                                        <span class="pw-system-badge" title="Template Système (Fallback)">⚙️ SYSTEM</span>
                                    <?php else: ?>
                                        <?php  
                                        $status_icons = [ 
                                            'active' => ['🟢', 'Actif'], 
                                            'draft' => ['🟡', 'Brouillon'], 
                                            'archived' => ['🔴', 'Archivé'], 
                                            'test' => ['🔵', 'Test'] 
                                        ]; 
                                        $icon_data = $status_icons[$tpl['status']] ?? ['⚪', 'Inconnu']; 
                                        ?> 
                                        <span class="pw-status-badge pw-status-<?php echo $tpl['status']; ?>" title="<?php echo $icon_data[1]; ?>"> 
                                            <?php echo $icon_data[0]; ?> 
                                        </span> 
                                    <?php endif; ?>
                                     
                                    <?php if ($tpl['is_favorite']): ?> 
                                        <span class="pw-favorite-badge" title="Favori">⭐</span> 
                                    <?php endif; ?> 
                                </div> 
                            </div> 
                             
                            <button class="pw-card-favorite-btn <?php echo $tpl['is_favorite'] ? 'active' : ''; ?>"> 
                                <span class="dashicons dashicons-star-<?php echo $tpl['is_favorite'] ? 'filled' : 'empty'; ?>"></span> 
                            </button> 
                        </div> 
                         
                        <!-- Body --> 
                        <div class="pw-card-body"> 
                             
                            <!-- Métadonnées variantes --> 
                            <div class="pw-card-meta"> 
                                <div class="pw-meta-item"> 
                                    <span class="dashicons dashicons-text"></span> 
                                    <span><?php echo $tpl['variants']['subject']; ?> sujets</span> 
                                </div> 
                                <div class="pw-meta-item"> 
                                    <span class="dashicons dashicons-editor-paragraph"></span> 
                                    <span><?php echo $tpl['variants']['text']; ?> textes</span> 
                                </div> 
                                <div class="pw-meta-item"> 
                                    <span class="dashicons dashicons-media-code"></span> 
                                    <span><?php echo $tpl['variants']['html']; ?> HTML</span> 
                                </div> 
                            </div> 
                             
                            <!-- Tags --> 
                            <?php if (!empty($tpl['tags'])): ?> 
                                <div class="pw-card-tags"> 
                                    <?php foreach (array_slice($tpl['tags'], 0, 3) as $tag): ?> 
                                        <span class="pw-tag-mini">#<?php echo esc_html($tag['name']); ?></span> 
                                    <?php endforeach; ?> 
                                     
                                    <?php if (count($tpl['tags']) > 3): ?> 
                                        <span class="pw-tag-more">+<?php echo count($tpl['tags']) - 3; ?></span> 
                                    <?php endif; ?> 
                                </div> 
                            <?php endif; ?> 
                             
                            <!-- Stats rapides --> 
                            <div class="pw-card-stats"> 
                                <div class="pw-stat-mini"> 
                                    <span class="pw-stat-label">Envoyés</span> 
                                    <span class="pw-stat-value"><?php echo number_format($tpl['stats']['sent'] ?? 0); ?></span> 
                                </div> 
                                <div class="pw-stat-mini"> 
                                    <span class="pw-stat-label">Succès</span> 
                                    <span class="pw-stat-value pw-success"><?php echo $tpl['stats']['success_rate'] ?? 0; ?>%</span> 
                                </div> 
                                <div class="pw-stat-mini"> 
                                    <span class="pw-stat-label">Temps moy.</span> 
                                    <span class="pw-stat-value"><?php echo $tpl['stats']['avg_time'] ?? 0; ?>s</span> 
                                </div> 
                            </div> 
                             
                            <!-- Shortcode Integration --> 
                            <div class="pw-card-integration">
                                <div class="pw-integration-label">
                                    <label>Libellé par défaut :</label>
                                    <input 
                                        type="text" 
                                        class="pw-default-label-input" 
                                        value="<?php echo esc_attr($tpl['default_label'] ?? 'Nous contacter'); ?>" 
                                        placeholder="Nous contacter"
                                    >
                                </div>
                                <div class="pw-card-shortcode"> 
                                    <input  
                                        type="text"  
                                        value='[warmup_mailto template="<?php echo esc_attr($tpl['name']); ?>" class="btn"][/warmup_mailto]'  
                                        readonly  
                                        onclick="this.select()" 
                                    > 
                                    <button class="pw-copy-shortcode-btn" title="Copier le shortcode"> 
                                        <span class="dashicons dashicons-clipboard"></span> 
                                    </button> 
                                </div>
                            </div>
                             
                        </div> 
                         
                        <!-- Footer (Actions) --> 
                        <div class="pw-card-footer"> 
                            <button class="pw-btn pw-btn-sm pw-btn-primary pw-edit-template-btn"> 
                                <span class="dashicons dashicons-edit"></span> 
                                Éditer 
                            </button> 
                             
                            <button class="pw-btn pw-btn-sm pw-preview-template-btn"> 
                                <span class="dashicons dashicons-visibility"></span> 
                                Aperçu 
                            </button> 
                             
                            <div class="pw-card-more"> 
                                <button class="pw-btn pw-btn-sm pw-btn-icon"> 
                                    <span class="dashicons dashicons-ellipsis"></span> 
                                </button> 
                                 
                                <div class="pw-dropdown-menu"> 
                                    <a href="#" class="pw-dropdown-item pw-duplicate-btn"> 
                                        <span class="dashicons dashicons-admin-page"></span> 
                                        Dupliquer 
                                    </a> 

                                    <a href="#" class="pw-dropdown-item pw-export-btn"> 
                                        <span class="dashicons dashicons-download"></span> 
                                        Exporter 
                                    </a> 

                                    <a href="#" class="pw-dropdown-item pw-move-btn"> 
                                        <span class="dashicons dashicons-category"></span> 
                                        Déplacer vers... 
                                    </a> 

                                    <a href="#" class="pw-dropdown-item pw-versions-btn"> 
                                        <span class="dashicons dashicons-backup"></span> 
                                        Versions (<?php echo $tpl['versions_count']; ?>) 
                                    </a> 

                                    <a href="#" class="pw-dropdown-item pw-archive-btn"> 
                                        <span class="dashicons dashicons-archive"></span> 
                                        Archiver 
                                    </a> 

                                    <?php if ($tpl['name'] !== 'null'): ?>
                                        <hr> 
                                        <a href="#" class="pw-dropdown-item pw-delete-btn danger"> 
                                            <span class="dashicons dashicons-trash"></span> 
                                            Supprimer 
                                        </a> 
                                    <?php endif; ?>
                                </div> 
                            </div> 
                        </div> 
                         
                    </div> 
                     
                <?php endforeach; ?> 
                 
            </div> 
             
            <!-- État vide --> 
            <div class="pw-empty-state" <?php echo !empty($templates) ? 'style="display:none;"' : ''; ?>> 
                <span class="dashicons dashicons-email-alt"></span> 
                <h3>Aucun template trouvé</h3> 
                <p>Commencez par créer votre premier template ou importez-en depuis un fichier JSON.</p> 
                <button class="pw-btn pw-btn-primary" id="pw-new-template-empty-btn"> 
                    <span class="dashicons dashicons-plus-alt"></span> 
                    Créer un template 
                </button> 
            </div> 
             
        </main> 
         
    </div> 
     
</div> 
 
<!-- Modals --> 
<?php  
include __DIR__ . '/template-category-modal.php';
include __DIR__ . '/template-editor-modal.php'; 
include __DIR__ . '/template-preview-modal.php'; 
include __DIR__ . '/template-versions-modal.php'; 
include __DIR__ . '/template-move-modal.php'; 
include __DIR__ . '/template-duplicate-modal.php'; 
?>
