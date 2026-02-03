<?php
/**
 * Vue moderne de gestion des templates (v3.0)
 */

if (!defined('ABSPATH')) {
    exit;
}

$templates = PW_Template_Loader::get_all_templates();
?>

<div class="wrap pw-modern-admin">
    <div class="pw-header">
        <h1>
            <span class="dashicons dashicons-email-alt"></span>
            <?php _e('Gestionnaire de Templates Ultra-Agile', 'postal-warmup'); ?>
            <span class="pw-badge">v3.0 PRO</span>
        </h1>
        <div class="pw-actions">
            <button class="button button-primary" id="pw-add-template-btn">
                <span class="dashicons dashicons-plus"></span>
                <?php _e('Nouveau Template', 'postal-warmup'); ?>
            </button>
            <button class="button" id="pw-import-templates-btn">
                <span class="dashicons dashicons-upload"></span>
                <?php _e('Importer', 'postal-warmup'); ?>
            </button>
        </div>
    </div>

    <div class="pw-toolbar">
        <div class="pw-search-box">
            <span class="dashicons dashicons-search"></span>
            <input type="text" id="pw-template-search" placeholder="<?php _e('Rechercher un template...', 'postal-warmup'); ?>">
        </div>
        <div class="pw-view-switch">
            <button class="button active" data-view="grid"><span class="dashicons dashicons-grid-view"></span></button>
            <button class="button" data-view="list"><span class="dashicons dashicons-list-view"></span></button>
        </div>
    </div>

    <div class="pw-template-grid" id="pw-template-container">
        <?php foreach ($templates as $name => $tpl) : ?>
            <div class="pw-template-card" data-name="<?php echo esc_attr($name); ?>">
                <div class="pw-card-header">
                    <span class="pw-drag-handle dashicons dashicons-menu"></span>
                    <h3 class="pw-tpl-name"><?php echo esc_html($name); ?></h3>
                </div>

                <div class="pw-card-body">
                    <div class="pw-tpl-meta">
                        <span><span class="dashicons dashicons-subject"></span> <?php echo $tpl['subjects_count']; ?></span>
                        <span><span class="dashicons dashicons-editor-paragraph"></span> <?php echo $tpl['texts_count']; ?></span>
                        <span><span class="dashicons dashicons-media-code"></span> <?php echo $tpl['htmls_count']; ?></span>
                    </div>

                    <div class="pw-card-shortcode" style="margin-top:10px; background:#f0f6fc; padding:8px; border-radius:4px; display:flex; justify-content:space-between; align-items:center; border:1px solid #d8e3ed;">
                        <code style="font-size:11px;">[warmup_mailto template="<?php echo esc_attr($name); ?>"]</code>
                        <button type="button" class="pw-copy-shortcode" style="background:none; border:none; color:#2271b1; cursor:pointer;" data-shortcode='[warmup_mailto template="<?php echo esc_attr($name); ?>"]'>
                            <span class="dashicons dashicons-clipboard"></span>
                        </button>
                    </div>
                </div>

                <div class="pw-card-footer">
                    <button class="button button-small pw-edit-tpl"><?php _e('Éditer', 'postal-warmup'); ?></button>
                    <button class="button button-small pw-preview-tpl"><?php _e('Aperçu', 'postal-warmup'); ?></button>
                    <div class="pw-card-more">
                        <span class="dashicons dashicons-ellipsis"></span>
                        <div class="pw-more-menu">
                            <a href="#" class="pw-duplicate-tpl"><?php _e('Dupliquer', 'postal-warmup'); ?></a>
                            <a href="#" class="pw-export-tpl"><?php _e('Exporter', 'postal-warmup'); ?></a>
                            <hr>
                            <a href="#" class="pw-delete-tpl danger"><?php _e('Supprimer', 'postal-warmup'); ?></a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Modal Éditeur -->
<div id="pw-editor-modal" class="pw-modal">
    <div class="pw-modal-content">
        <div class="pw-modal-header">
            <h2 id="pw-editor-title"><?php _e('Édition Template', 'postal-warmup'); ?></h2>
            <span class="pw-modal-close">&times;</span>
        </div>
        <div class="pw-modal-body">
            <form id="pw-template-form">
                <div style="display:flex; gap:20px; margin-bottom:20px;">
                    <div class="pw-form-group" style="flex:2;">
                        <label><strong><?php _e('Nom du template', 'postal-warmup'); ?></strong></label>
                        <input type="text" name="tpl_name" id="pw-tpl-name-input" required style="width:100%">
                    </div>
                    <div class="pw-form-group" style="flex:1;">
                        <label><strong><?php _e('Dossier / Catégorie', 'postal-warmup'); ?></strong></label>
                        <input type="text" id="pw-tpl-folder-input" placeholder="Ex: Campagne A" style="width:100%">
                    </div>
                    <div class="pw-form-group" style="flex:1;">
                        <label><strong><?php _e('Statut', 'postal-warmup'); ?></strong></label>
                        <select id="pw-tpl-status-input" style="width:100%">
                            <option value="active"><?php _e('Actif', 'postal-warmup'); ?></option>
                            <option value="draft"><?php _e('Brouillon', 'postal-warmup'); ?></option>
                            <option value="archived"><?php _e('Archivé', 'postal-warmup'); ?></option>
                        </select>
                    </div>
                </div>

                <div class="pw-editor-tabs">
                    <button type="button" class="pw-tab-btn active" data-tab="subjects"><?php _e('Sujets (Postal)', 'postal-warmup'); ?></button>
                    <button type="button" class="pw-tab-btn" data-tab="from_names"><?php _e('Expéditeurs (Postal)', 'postal-warmup'); ?></button>
                    <button type="button" class="pw-tab-btn" data-tab="text"><?php _e('Texte', 'postal-warmup'); ?></button>
                    <button type="button" class="pw-tab-btn" data-tab="html"><?php _e('HTML', 'postal-warmup'); ?></button>
                    <button type="button" class="pw-tab-btn" data-tab="mailto"><?php _e('Mailto (Shortcode)', 'postal-warmup'); ?></button>
                </div>

                <!-- Tab Sujets Postal -->
                <div class="pw-tab-content active" id="pw-tab-subjects">
                    <div class="pw-tab-actions">
                        <button type="button" class="button pw-add-variation-btn" data-type="subject">+ <?php _e('Ajouter un sujet', 'postal-warmup'); ?></button>
                        <button type="button" class="button pw-bulk-add-btn" data-type="subject"><?php _e('Ajout en masse', 'postal-warmup'); ?></button>
                    </div>
                    <div id="pw-subjects-list" class="pw-variations-list"></div>
                </div>

                <!-- Tab Expéditeurs Postal -->
                <div class="pw-tab-content" id="pw-tab-from_names">
                    <div class="pw-tab-actions">
                        <button type="button" class="button pw-add-variation-btn" data-type="from_name">+ <?php _e('Ajouter un expéditeur', 'postal-warmup'); ?></button>
                        <button type="button" class="button pw-bulk-add-btn" data-type="from_name"><?php _e('Ajout en masse', 'postal-warmup'); ?></button>
                    </div>
                    <div id="pw-from_names-list" class="pw-variations-list"></div>
                </div>

                <div class="pw-tab-content" id="pw-tab-text">
                    <div class="pw-tab-actions">
                        <button type="button" class="button pw-add-variation-btn" data-type="text">+ <?php _e('Ajouter un texte', 'postal-warmup'); ?></button>
                        <button type="button" class="button pw-bulk-add-btn" data-type="text"><?php _e('Ajout en masse', 'postal-warmup'); ?></button>
                    </div>
                    <div id="pw-texts-list" class="pw-variations-list"></div>
                </div>
                <div class="pw-tab-content" id="pw-tab-html">
                    <div class="pw-tab-actions">
                        <button type="button" class="button pw-add-variation-btn" data-type="html">+ <?php _e('Ajouter un HTML', 'postal-warmup'); ?></button>
                        <button type="button" class="button pw-bulk-add-btn" data-type="html"><?php _e('Ajout en masse', 'postal-warmup'); ?></button>
                    </div>
                    <div id="pw-htmls-list" class="pw-variations-list"></div>
                </div>

                <div class="pw-tab-content" id="pw-tab-mailto">
                    <div class="pw-mailto-info" style="background:#e7f5e9; padding:15px; border-radius:6px; margin-bottom:20px; border-left:4px solid #46b450;">
                        <h4 style="margin:0 0 10px 0; color:#1e4620;">🔗 <?php _e('Pré-remplissage Shortcode [warmup_mailto]', 'postal-warmup'); ?></h4>
                        <p style="margin:0; font-size:13px;"><?php _e('Ces champs pré-remplissent le client email du visiteur quand il clique sur votre lien warmup.', 'postal-warmup'); ?></p>
                    </div>

                    <h3 style="font-size:14px; margin-top:20px;"><?php _e('📬 Sujets pré-remplis (Mailto)', 'postal-warmup'); ?></h3>
                    <div class="pw-tab-actions">
                        <button type="button" class="button pw-add-variation-btn" data-type="mailto_subject">+ <?php _e('Ajouter un sujet', 'postal-warmup'); ?></button>
                        <button type="button" class="button pw-bulk-add-btn" data-type="mailto_subject"><?php _e('Ajout en masse', 'postal-warmup'); ?></button>
                    </div>
                    <div id="pw-mailto_subjects-list" class="pw-variations-list"></div>

                    <h3 style="font-size:14px; margin-top:30px;"><?php _e('📝 Corps pré-remplis (Mailto)', 'postal-warmup'); ?></h3>
                    <div class="pw-tab-actions">
                        <button type="button" class="button pw-add-variation-btn" data-type="mailto_body">+ <?php _e('Ajouter un corps', 'postal-warmup'); ?></button>
                        <button type="button" class="button pw-bulk-add-btn" data-type="mailto_body"><?php _e('Ajout en masse', 'postal-warmup'); ?></button>
                    </div>
                    <div id="pw-mailto_bodys-list" class="pw-variations-list"></div>

                    <h3 style="font-size:14px; margin-top:30px;"><?php _e('👤 Noms d\'expéditeur (Mailto)', 'postal-warmup'); ?></h3>
                    <div class="pw-tab-actions">
                        <button type="button" class="button pw-add-variation-btn" data-type="mailto_from_name">+ <?php _e('Ajouter un nom', 'postal-warmup'); ?></button>
                        <button type="button" class="button pw-bulk-add-btn" data-type="mailto_from_name"><?php _e('Ajout en masse', 'postal-warmup'); ?></button>
                    </div>
                    <div id="pw-mailto_from_names-list" class="pw-variations-list"></div>
                </div>
            </form>
        </div>
        <div class="pw-modal-footer">
            <button type="button" class="button" id="pw-editor-cancel"><?php _e('Annuler', 'postal-warmup'); ?></button>
            <button type="button" class="button button-primary" id="pw-editor-save"><?php _e('Enregistrer', 'postal-warmup'); ?></button>
        </div>
    </div>
</div>

<!-- Les autres modals restent identiques... -->
<!-- Modal Aperçu -->
<div id="pw-preview-modal" class="pw-modal">
    <div class="pw-modal-content">
        <div class="pw-modal-header">
            <h2 id="pw-preview-title"><?php _e('Aperçu du Template', 'postal-warmup'); ?></h2>
            <span class="pw-modal-close">&times;</span>
        </div>
        <div class="pw-modal-body">
            <div class="pw-preview-toolbar">
                <select id="pw-preview-variation">
                    <option value="0"><?php _e('Variation 1', 'postal-warmup'); ?></option>
                </select>
                <div class="pw-preview-device-switch">
                    <button class="button active" data-device="desktop"><span class="dashicons dashicons-desktop"></span></button>
                    <button class="button" data-device="mobile"><span class="dashicons dashicons-smartphone"></span></button>
                </div>
            </div>
            <div id="pw-preview-render-container">
                <div id="pw-preview-subject-line"><strong><?php _e('Sujet:', 'postal-warmup'); ?></strong> <span id="pw-preview-subject"></span></div>
                <iframe id="pw-preview-iframe" style="width: 100%; height: 500px; border: 1px solid #c3c4c7; background: #fff;"></iframe>
            </div>
        </div>
        <div class="pw-modal-footer">
            <button type="button" class="button" id="pw-preview-close"><?php _e('Fermer', 'postal-warmup'); ?></button>
        </div>
    </div>
</div>

<!-- Modal Import -->
<div id="pw-import-modal" class="pw-modal">
    <div class="pw-modal-content">
        <div class="pw-modal-header">
            <h2><?php _e('Importer des Templates', 'postal-warmup'); ?></h2>
            <span class="pw-modal-close">&times;</span>
        </div>
        <div class="pw-modal-body">
            <p><?php _e('Sélectionnez un fichier JSON contenant un ou plusieurs templates.', 'postal-warmup'); ?></p>
            <input type="file" id="pw-import-file" accept=".json">
        </div>
        <div class="pw-modal-footer">
            <button type="button" class="button" id="pw-import-cancel"><?php _e('Annuler', 'postal-warmup'); ?></button>
            <button type="button" class="button button-primary" id="pw-import-confirm"><?php _e('Importer', 'postal-warmup'); ?></button>
        </div>
    </div>
</div>

<!-- Modal Bulk Add -->
<div id="pw-bulk-add-modal" class="pw-modal">
    <div class="pw-modal-content">
        <div class="pw-modal-header">
            <h2><?php _e('Ajout de Variantes en Masse', 'postal-warmup'); ?></h2>
            <span class="pw-modal-close">&times;</span>
        </div>
        <div class="pw-modal-body">
            <p class="pw-bulk-info-line"><?php _e('Collez vos variantes ci-dessous (une par ligne).', 'postal-warmup'); ?></p>
            <p class="pw-bulk-info-text" style="display:none;"><?php _e('Pour le texte/HTML, séparez les variantes par une ligne contenant "---".', 'postal-warmup'); ?></p>
            <textarea id="pw-bulk-add-textarea" rows="15" style="width:100%; font-family:monospace;"></textarea>
        </div>
        <div class="pw-modal-footer">
            <button type="button" class="button" id="pw-bulk-add-cancel"><?php _e('Annuler', 'postal-warmup'); ?></button>
            <button type="button" class="button button-primary" id="pw-bulk-add-confirm"><?php _e('Ajouter les Variantes', 'postal-warmup'); ?></button>
        </div>
    </div>
</div>

<style>
.pw-modern-admin {
    margin-top: 20px;
}
.pw-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
}
.pw-badge {
    background: #2271b1;
    color: #fff;
    font-size: 12px;
    padding: 2px 8px;
    border-radius: 12px;
}
.pw-toolbar {
    background: #fff;
    padding: 15px;
    border-radius: 8px;
    display: flex;
    gap: 20px;
    align-items: center;
    margin-bottom: 25px;
    border: 1px solid #c3c4c7;
}
.pw-search-box {
    position: relative;
    flex: 1;
}
.pw-search-box .dashicons {
    position: absolute;
    left: 10px;
    top: 50%;
    transform: translateY(-50%);
}
.pw-search-box input {
    width: 100%;
    padding-left: 35px;
    height: 36px;
    border-radius: 4px;
}
.pw-template-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
}
.pw-template-card {
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 8px;
    overflow: hidden;
}
.pw-card-header {
    padding: 15px;
    border-bottom: 1px solid #f0f0f1;
    display: flex;
    align-items: center;
    gap: 10px;
}
.pw-drag-handle { cursor: grab; color: #c3c4c7; }
.pw-tpl-name { margin: 0; font-size: 16px; flex: 1; }
.pw-card-body { padding: 15px; }
.pw-tpl-meta { display: flex; gap: 15px; color: #646970; font-size: 13px; }
.pw-card-footer {
    padding: 10px 15px;
    background: #f6f7f7;
    display: flex;
    gap: 10px;
    align-items: center;
}
.pw-card-more { margin-left: auto; cursor: pointer; position: relative; }
.pw-more-menu {
    display: none;
    position: absolute;
    right: 0;
    bottom: 100%;
    background: #fff;
    border: 1px solid #c3c4c7;
    min-width: 120px;
    z-index: 10;
}
.pw-card-more:hover .pw-more-menu { display: block; }
.pw-more-menu a { display: block; padding: 8px 12px; text-decoration: none; color: #1d2327; }
.pw-more-menu a:hover { background: #f0f0f1; }
.pw-more-menu a.danger { color: #d63638; }

.pw-modal {
    display: none;
    position: fixed;
    z-index: 100000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.7);
}
.pw-modal-content {
    background: #fff;
    margin: 2% auto;
    width: 90%;
    max-width: 1000px;
    max-height: 90vh;
    border-radius: 8px;
    display: flex;
    flex-direction: column;
}
.pw-modal-header { padding: 15px 20px; border-bottom: 1px solid #f0f0f1; display: flex; justify-content: space-between; align-items: center; }
.pw-modal-close { font-size: 24px; cursor: pointer; }
.pw-modal-body { padding: 20px; overflow-y: auto; flex: 1; }
.pw-modal-footer { padding: 15px 20px; border-top: 1px solid #f0f0f1; display: flex; justify-content: flex-end; gap: 10px; }

.pw-editor-tabs { display: flex; border-bottom: 1px solid #c3c4c7; margin-bottom: 20px; }
.pw-tab-btn { padding: 10px 15px; border: none; background: none; cursor: pointer; border-bottom: 2px solid transparent; font-weight: 600; color: #646970; }
.pw-tab-btn.active { color: #2271b1; border-bottom-color: #2271b1; }
.pw-tab-content { display: none; }
.pw-tab-content.active { display: block; }
.pw-tab-actions { margin-bottom: 15px; display: flex; gap: 10px; }
.pw-variation-row { background: #f6f7f7; padding: 10px; margin-bottom: 10px; border-radius: 4px; position: relative; }
.pw-remove-var { position: absolute; right: 5px; top: 5px; color: #d63638; cursor: pointer; }
</style>
