/**
 * admin/js/templates-manager.js 
 * Gestionnaire principal des templates
 */

(function($) { 
    'use strict'; 
     
    /**
     * Template Editor Logic
     */
    const TemplateEditor = {
        currentBulkType: null,

        open(templateName = null) {
            const $modal = $('#pw-template-editor-modal');
            const $form = $('#pw-template-editor-form');
            if (!$modal.length || !$form.length) return;

            this.resetForm();
            
            if (templateName) {
                $('#pw-editor-title').text('Modifier le Template : ' + templateName);
                this.loadTemplate(templateName);
            } else {
                $('#pw-editor-title').text('Nouveau Template');
                // Add one default variant for each
                this.addVariant('subject');
                this.addVariant('from_name');
                this.addVariant('text');
                this.addVariant('html');
            }
            
            $modal.css('display', 'flex');
        },

        resetForm() {
            const $form = $('#pw-template-editor-form');
            if ($form.length) $form[0].reset();
            $('#pw-editor-template-id').val('');
            $('#pw-editor-name').prop('readonly', false);
            $('#pw-editor-folder').prop('disabled', false).removeClass('disabled-field');
            $('#pw-system-template-info').hide();
            $('.pw-variants-container').empty();
            $('.pw-tab-btn[data-tab="general"]').click();
        },

        async loadTemplate(name) {
            if (typeof pwAdmin === 'undefined') return;
            try {
                const response = await $.post(pwAdmin.ajaxurl, {
                    action: 'pw_get_template',
                    nonce: pwAdmin.nonce,
                    name: name
                });

                if (response.success) {
                    const tpl = response.data;
                    const tplName = tpl.name || name;
                    
                    $('#pw-editor-template-id').val(tpl.id);
                    $('#pw-editor-name').val(tplName);
                    
                    if (tplName === 'null') {
                        $('#pw-editor-name').prop('readonly', true).addClass('readonly-field');
                        $('#pw-system-template-info').show();
                        $('#pw-editor-folder').val('').prop('disabled', true).addClass('disabled-field');
                    } else {
                        $('#pw-editor-name').prop('readonly', false);
                        $('#pw-editor-name').removeClass('readonly-field');
                        $('#pw-system-template-info').hide();
                        $('#pw-editor-folder').prop('disabled', false).removeClass('disabled-field');
                    }

                    if (tplName !== 'null') {
                        $('#pw-editor-folder').val(tpl.folder_id || '');
                    }
                    $('#pw-editor-status').val(tpl.status || 'active');
                    
                    if (tpl.tags && Array.isArray(tpl.tags)) {
                        const tagNames = tpl.tags.map(t => typeof t === 'object' ? t.name : t).join(', ');
                        $('#pw-editor-tags').val(tagNames);
                    }

                    // Load default label (if present in data or variants)
                    // Note: API returns data merged into tpl object usually. If save_template stores it in data blob, it comes back here.
                    $('#pw-editor-default-label').val(tpl.default_label || '');

                    // Load variants
                    const variantTypes = ['subject', 'text', 'html', 'from_name', 'reply_to', 'mailto_subject', 'mailto_body', 'mailto_from_name'];
                    variantTypes.forEach(type => {
                        if (tpl[type] && Array.isArray(tpl[type])) {
                            tpl[type].forEach(val => this.addVariant(type, val));
                        }
                    });
                } else {
                    alert('Erreur: ' + (response.data ? response.data.message : 'inconnue'));
                }
            } catch (error) {
                console.error('Error loading template:', error);
            }
        },

        addVariant(type, value = '') {
            const $container = $('#pw-variants-' + type);
            if (!$container.length) return;

            const templateHtml = $('#pw-variant-item-template').html();
            if (!templateHtml) return;

            // Use placeholder and then set value safely
            const html = templateHtml
                .replace(/<%- type %>/g, type)
                .replace(/<%- value %>/g, ''); 
            
            const $item = $(html);
            $item.find('textarea').val(value);
            
            // Remove toolbar and preview for unsupported types
            if (!['subject', 'from_name', 'text', 'html'].includes(type)) {
                $item.find('.pw-variant-toolbar').remove();
                $item.find('.pw-variant-preview').remove();
            }

            $container.append($item);
            
            $item.find('.pw-remove-variant').on('click', function() {
                const required = ['subject', 'text', 'html', 'from_name'];
                if ($container.find('.pw-variant-item').length > 1 || !required.includes(type)) {
                    $item.remove();
                } else {
                    alert('Au moins une variante est requise pour ce champ.');
                }
            });

            // Initialize Code/Preview toggles & Base64 logic for supported types
            if (['subject', 'from_name', 'text', 'html'].includes(type)) {

                // Toggle Logic
                $item.find('.pw-toggle-btn').on('click', function() {
                    const mode = $(this).data('mode');
                    const $parent = $(this).closest('.pw-variant-item');
                    const $textarea = $parent.find('textarea');
                    const $preview = $parent.find('.pw-variant-preview');

                    $parent.find('.pw-toggle-btn').removeClass('active');
                    $(this).addClass('active');

                    if (mode === 'preview') {
                        let content = $textarea.val();
                        // Try decode Base64 if needed
                        if (content.match(/^[A-Za-z0-9+/=]+\s*$/) && content.length > 20) {
                            try {
                                content = decodeURIComponent(escape(window.atob(content)));
                            } catch (e) {
                                // Not valid base64 or other error, keep original
                            }
                        }

                        // Render based on type
                        if (type === 'html') {
                            $preview.html(content);
                        } else {
                            $preview.text(content);
                        }

                        $textarea.hide();
                        $preview.show();
                    } else {
                        $preview.hide();
                        $textarea.show();
                    }
                });

                // Base64 Logic
                $item.find('.pw-base64-btn').on('click', function() {
                    const $parent = $(this).closest('.pw-variant-item');
                    const $textarea = $parent.find('textarea');
                    const raw = $textarea.val();

                    if (!raw) return;

                    try {
                        // UTF-8 safe encoding
                        const encoded = window.btoa(unescape(encodeURIComponent(raw)));
                        $textarea.val(encoded);

                        // Switch to code view to see result
                        $parent.find('.pw-toggle-btn[data-mode="code"]').click();
                    } catch (e) {
                        console.error('Encoding error:', e);
                        alert('Erreur lors de l\'encodage Base64');
                    }
                });
            }
        },

        openBulkAdd(type) {
            this.currentBulkType = type;
            const $modal = $('#pw-bulk-add-modal');
            if (!$modal.length) return;
            $('#pw-bulk-add-textarea').val('');
            
            const isLongText = ['html', 'text', 'mailto_body'].includes(type);
            $('.pw-bulk-info-text').toggle(isLongText);
            
            $modal.css('display', 'flex');
        },

        confirmBulkAdd() {
            const type = this.currentBulkType;
            const raw = $('#pw-bulk-add-textarea').val().trim();
            if (!raw) return $('#pw-bulk-add-modal').css('display', 'none');

            const isLongText = ['html', 'text', 'mailto_body'].includes(type);
            const items = isLongText ? raw.split(/\n---\n/) : raw.split('\n');

            items.forEach(val => {
                if (val.trim()) this.addVariant(type, val.trim());
            });

            $('#pw-bulk-add-modal').css('display', 'none');
        },

        async save() {
            if (typeof pwAdmin === 'undefined') return;
            const name = $('#pw-editor-name').val();
            
            // Prevent renaming or creating a template named "null"
            if (name.toLowerCase() === 'null' && !$('#pw-system-template-info').is(':visible')) {
                alert('Le nom "null" est réservé au template système.');
                return;
            }

            const formData = $('#pw-template-editor-form').serialize();
            const $btn = $('#pw-save-template-btn');
            $btn.prop('disabled', true).text('Sauvegarde...');

            try {
                const response = await $.post(pwAdmin.ajaxurl, formData + '&action=pw_save_template&nonce=' + pwAdmin.nonce);
                if (response.success) {
                    $('#pw-template-editor-modal').hide();
                    location.reload();
                } else {
                    alert(response.data.message || 'Erreur lors de la sauvegarde');
                    $btn.prop('disabled', false).text('💾 Sauvegarder');
                }
            } catch (error) {
                console.error('Error saving template:', error);
                $btn.prop('disabled', false).text('💾 Sauvegarder');
            }
        }
    };

    /**
     * Template Preview Logic
     */
    const TemplatePreview = {
        open(templateName) {
            const $modal = $('#pw-template-preview-modal');
            if (!$modal.length) return;
            $('#pw-preview-title').text('Aperçu : ' + templateName);
            this.loadPreview(templateName);
            $modal.css('display', 'flex');
        },

        async loadPreview(name) {
            if (typeof pwAdmin === 'undefined') return;
            try {
                const response = await $.post(pwAdmin.ajaxurl, {
                    action: 'pw_get_template',
                    nonce: pwAdmin.nonce,
                    name: name
                });
                if (response.success) {
                    const tpl = response.data;
                    $('#pw-preview-from').text(tpl.from_name[0] || '');
                    $('#pw-preview-subject').text(tpl.subject[0] || '');
                    
                    const $iframe = $('#pw-preview-frame');
                    const html = tpl.html[0] || tpl.text[0] || '';
                    $iframe.contents().find('body').html(html);
                }
            } catch (error) {
                console.error('Preview error:', error);
            }
        }
    };
     
    /**
     * Template Duplicate Logic
     */
    const TemplateDuplicate = {
        open(sourceName) {
            const $modal = $('#pw-template-duplicate-modal');
            if (!$modal.length) return;
            $modal.find('#pw-duplicate-source-name').val(sourceName);
            $modal.find('#pw-duplicate-new-name').val(sourceName).select().focus();
            $modal.css('display', 'flex');
        },

        async confirm() {
            if (typeof pwAdmin === 'undefined') return;
            const $modal = $('#pw-template-duplicate-modal');
            const sourceName = $modal.find('#pw-duplicate-source-name').val();
            const newName = $modal.find('#pw-duplicate-new-name').val().trim();
            const $btn = $('#pw-confirm-duplicate-btn');

            if (!newName) {
                alert('Veuillez entrer un nom pour le nouveau template.');
                $modal.find('#pw-duplicate-new-name').focus();
                return;
            }

            if (newName === sourceName) {
                alert('Le nouveau nom doit être différent de l\'original.');
                $modal.find('#pw-duplicate-new-name').focus();
                return;
            }

            $btn.prop('disabled', true).text('Duplication...');

            try {
                const ajaxData = {
                    action: 'pw_duplicate_template',
                    nonce: pwAdmin.nonce,
                    name: sourceName,
                    new_name: newName
                };

                const response = await $.ajax({
                    url: pwAdmin.ajaxurl,
                    method: 'POST',
                    data: ajaxData,
                    dataType: 'json'
                });

                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data ? response.data.message : 'Erreur lors de la duplication');
                    $btn.prop('disabled', false).text('🚀 Dupliquer');
                }
            } catch (error) {
                console.error('Error duplicating template:', error);
                alert('Une erreur réseau est survenue lors de la duplication.');
                $btn.prop('disabled', false).text('🚀 Dupliquer');
            }
        }
    };

    /**
     * Category Manager Logic
     */
    const CategoryManager = {
        open(data = null) {
            const $modal = $('#pw-category-modal');
            const $form = $('#pw-category-form');
            if (!$modal.length || !$form.length) return;

            $form[0].reset();
            
            // Populate parent select
            this.updateParentSelect(data ? data.id : null);

            if (data) {
                $('#pw-category-modal-title').text('Modifier la catégorie');
                $('#pw-category-id').val(data.id);
                $('#pw-category-name').val(data.name);
                $('#pw-category-parent').val(data.parent_id || '');
                $('#pw-category-color').val(data.color || '#2271b1');
            } else {
                $('#pw-category-modal-title').text('Nouvelle catégorie');
                $('#pw-category-id').val('');
                $('#pw-category-color').val('#2271b1');
            }

            $modal.css('display', 'flex');
        },

        updateParentSelect(excludeId = null) {
            this.populateSelect($('#pw-category-parent'), excludeId);
        },

        populateSelect($select, excludeId = null) {
            if (typeof pwAdmin === 'undefined') return;
            if (!$select.length) return;

            // Save currently selected value
            const currentVal = $select.val();

            // Keep only the first option (usually default/empty)
            const $firstOption = $select.find('option:first');
            $select.empty().append($firstOption);
            
            const addOptions = (folders, level = 0) => {
                if (!Array.isArray(folders)) return;
                folders.forEach(f => {
                    if (excludeId && f.id == excludeId) return;
                    const indent = '—'.repeat ? '—'.repeat(level) : (new Array(level + 1).join('—'));
                    const space = level > 0 ? ' ' : '';
                    $select.append(`<option value="${f.id}">${indent}${space}${f.name}</option>`);
                    if (f.children && f.children.length > 0) {
                        addOptions(f.children, level + 1);
                    }
                });
            };

            $.post(pwAdmin.ajaxurl, {
                action: 'pw_get_categories',
                nonce: pwAdmin.nonce
            }, (response) => {
                if (response && response.success && response.data && response.data.tree) {
                    addOptions(response.data.tree);
                    // Restore selection if possible
                    if (currentVal) $select.val(currentVal);
                }
            });
        },

        async refreshTree() {
            if (typeof pwAdmin === 'undefined') return;
            try {
                const response = await $.post(pwAdmin.ajaxurl, {
                    action: 'pw_get_categories',
                    nonce: pwAdmin.nonce
                });

                if (response && response.success && response.data && response.data.tree) {
                    this.renderTree(response.data.tree);
                }
            } catch (error) {
                console.error('Error refreshing tree:', error);
            }
        },

        renderTree(tree) {
            const $container = $('#pw-folders-tree');
            if (!$container.length) return;

            // Keep "All templates" item
            const $allTemplates = $container.find('.pw-tree-item[data-folder-id=""]').detach();
            $container.empty().append($allTemplates);

            const renderRecursive = (folders, $parent) => {
                if (!Array.isArray(folders)) return;
                folders.forEach(f => {
                    const isProtected = f.name && f.name.toLowerCase().trim().includes('non catégorisé');
                    const hasChildren = f.children && f.children.length > 0;
                    const toggleId = 'folder-toggle-' + f.id;
                    
                    let html = `
                        <li class="pw-tree-item" 
                            data-folder-id="${f.id}"
                            data-name="${f.name}"
                            data-parent="${f.parent_id || ''}"
                            data-color="${f.color}"
                            droppable="true">`;
                    
                    if (hasChildren) {
                        html += `<input type="checkbox" id="${toggleId}" checked>`;
                    }

                    html += `
                            <div class="pw-tree-content">
                                <div class="pw-tree-label-group">
                                    ${hasChildren ? 
                                        `<label for="${toggleId}" class="pw-tree-toggle dashicons dashicons-arrow-right-alt2"></label>` : 
                                        `<span class="pw-tree-toggle"></span>`}
                                    
                                    <span class="pw-tree-icon dashicons dashicons-category" style="color: ${f.color}"></span> 
                                    <span class="pw-tree-name">${f.name}</span> 
                                </div>

                                <span class="pw-tree-count">${f.count}</span>
                                
                                ${!isProtected ? `
                                <div class="pw-tree-actions">
                                    <button class="pw-edit-folder-btn pw-btn-icon-sm" title="Modifier">
                                        <span class="dashicons dashicons-edit"></span>
                                    </button>
                                    <button class="pw-delete-folder-btn pw-btn-icon-sm" title="Supprimer">
                                        <span class="dashicons dashicons-trash"></span>
                                    </button>
                                </div>` : ''}
                            </div>
                            ${hasChildren ? '<ul></ul>' : ''}
                        </li>`;
                    
                    const $item = $(html);
                    $parent.append($item);
                    
                    if (hasChildren) {
                        renderRecursive(f.children, $item.find('ul'));
                    }
                });
            };

            renderRecursive(tree, $container);
            
            // Re-apply active state if needed
            if (TemplatesManager.state.selectedFolder !== null) {
                const $target = $container.find(`.pw-tree-item[data-folder-id="${TemplatesManager.state.selectedFolder}"] .pw-tree-content`);
                $('.pw-tree-content').removeClass('active');
                $target.addClass('active');
            } else {
                $allTemplates.find('.pw-tree-content').addClass('active');
            }
        },

        async save() {
            if (typeof pwAdmin === 'undefined') return;
            const $form = $('#pw-category-form');
            const data = $form.serialize();
            const $btn = $('#pw-save-category-btn');
            
            $btn.prop('disabled', true).text('Enregistrement...');

            try {
                const response = await $.post(pwAdmin.ajaxurl, data + '&action=pw_save_category&nonce=' + pwAdmin.nonce);
                if (response.success) {
                    $('#pw-category-modal').hide();
                    this.refreshTree();
                } else {
                    alert(response.data.message || 'Erreur lors de la sauvegarde');
                    $btn.prop('disabled', false).text('Enregistrer');
                }
            } catch (error) {
                console.error('Error saving category:', error);
                $btn.prop('disabled', false).text('Enregistrer');
            }
        },

        async delete(id, name) {
            if (typeof pwAdmin === 'undefined') return;
            if (!confirm(`Êtes-vous sûr de vouloir supprimer la catégorie "${name}" ?\nLes templates seront déplacés vers "Non catégorisé".`)) return;

            try {
                const response = await $.post(pwAdmin.ajaxurl, {
                    action: 'pw_delete_category',
                    nonce: pwAdmin.nonce,
                    id: id
                });

                if (response.success) {
                    this.refreshTree();
                    // If deleted folder was selected, reset filter
                    if (TemplatesManager.state.selectedFolder == id) {
                        TemplatesManager.filterByFolder('');
                    }
                } else {
                    alert(response.data.message || 'Erreur lors de la suppression');
                }
            } catch (error) {
                console.error('Error deleting category:', error);
            }
        }
    };
     
    const TemplatesManager = { 
         
        state: { 
            templates: [], 
            filteredTemplates: [], 
            currentView: 'grid', 
            selectedFolder: null, 
            selectedTags: [], 
            favoriteOnly: false,
            searchQuery: '', 
            sortBy: 'name', 
            sortOrder: 'asc' 
        }, 
         
        init() { 
            this.bindEvents(); 
            this.loadTemplates(); 
            this.initSearch(); 
            this.initDragDrop();
        }, 

        initShortcodeState() {
            // Restore shortcode preferences per template
            $('.pw-template-card').each(function() {
                const templateName = $(this).data('template-name');
                
                const storedLabel = localStorage.getItem('pw_shortcode_label_' + templateName);
                const storedType = localStorage.getItem('pw_shortcode_type_' + templateName);

                if (storedLabel) {
                    $(this).find('.pw-shortcode-label-input').val(storedLabel);
                }
                if (storedType) {
                    $(this).find('.pw-shortcode-select').val(storedType);
                }
            });
        },
         
        bindEvents() { 
            // Shortcode sync (per template now)
            $(document).on('input', '.pw-shortcode-label-input', (e) => {
                const $card = $(e.currentTarget).closest('.pw-template-card');
                const templateName = $card.data('template-name');
                const val = $(e.target).val();
                
                localStorage.setItem('pw_shortcode_label_' + templateName, val);
            });

            $(document).on('change', '.pw-shortcode-select', (e) => {
                const $card = $(e.currentTarget).closest('.pw-template-card');
                const templateName = $card.data('template-name');
                const val = $(e.target).val();
                
                localStorage.setItem('pw_shortcode_type_' + templateName, val);
            });

            // New folder/category
            $(document).on('click', '#pw-add-folder-btn', (e) => {
                e.preventDefault();
                CategoryManager.open();
            });

            // Edit folder
            $(document).on('click', '.pw-edit-folder-btn', (e) => {
                e.preventDefault();
                e.stopPropagation();
                const $item = $(e.currentTarget).closest('.pw-tree-item');
                CategoryManager.open({
                    id: $item.data('folder-id'),
                    name: $item.data('name'),
                    parent_id: $item.data('parent'),
                    color: $item.data('color')
                });
            });

            // Delete folder
            $(document).on('click', '.pw-delete-folder-btn', (e) => {
                e.preventDefault();
                e.stopPropagation();
                const $item = $(e.currentTarget).closest('.pw-tree-item');
                CategoryManager.delete($item.data('folder-id'), $item.data('name'));
            });

            // New template 
            $(document).on('click', '#pw-new-template-btn, #pw-new-template-empty-btn', (e) => { 
                e.preventDefault();
                this.openEditor(); 
            }); 
             
            // Edit 
            $(document).on('click', '.pw-edit-template-btn', (e) => { 
                e.preventDefault();
                const name = $(e.currentTarget).data('template-name') || $(e.currentTarget).closest('.pw-template-card').data('template-name'); 
                this.openEditor(name); 
            }); 
             
            // Preview 
            $(document).on('click', '.pw-preview-template-btn', (e) => { 
                e.preventDefault();
                const $card = $(e.currentTarget).closest('.pw-template-card'); 
                const name = $card.data('template-name'); 
                this.openPreview(name); 
            }); 
             
            // Favorite 
            $(document).on('click', '.pw-card-favorite-btn', (e) => { 
                e.preventDefault();
                e.stopPropagation(); 
                const $card = $(e.currentTarget).closest('.pw-template-card'); 
                this.toggleFavorite($card); 
            }); 
             
            // Delete 
            $(document).on('click', '.pw-delete-btn', (e) => { 
                e.preventDefault(); 
                const $card = $(e.currentTarget).closest('.pw-template-card'); 
                this.deleteTemplate($card); 
            }); 

            // Duplicate
            $(document).on('click', '.pw-duplicate-btn', (e) => {
                e.preventDefault();
                const $card = $(e.currentTarget).closest('.pw-template-card');
                this.duplicateTemplate($card);
            });

            // Move
            $(document).on('click', '.pw-move-btn', (e) => {
                e.preventDefault();
                const $card = $(e.currentTarget).closest('.pw-template-card');
                this.openMoveModal($card);
            });

            // Archive
            $(document).on('click', '.pw-archive-btn', (e) => {
                e.preventDefault();
                const $card = $(e.currentTarget).closest('.pw-template-card');
                this.archiveTemplate($card);
            });

            // Versions
            $(document).on('click', '.pw-versions-btn', (e) => {
                e.preventDefault();
                const $card = $(e.currentTarget).closest('.pw-template-card');
                this.openVersionsModal($card);
            });
             
            // Folders 
            $(document).on('click', '.pw-tree-content', (e) => { 
                e.stopPropagation();
                if ($(e.target).closest('.pw-tree-actions').length) return;
                
                const $item = $(e.currentTarget).closest('.pw-tree-item');
                const folderId = $item.data('folder-id'); 
                this.filterByFolder(folderId); 
            }); 

            // System filter
            $(document).on('click', '.pw-system-item', (e) => {
                e.preventDefault();
                this.openEditor('null');
            });

            // Favorite filter
            $(document).on('click', '.pw-quick-link[data-filter="favorite"]', (e) => {
                e.preventDefault();
                this.filterFavorite();
            });
             
            // Tags 
            $(document).on('click', '.pw-tag-pill', (e) => { 
                const tag = $(e.currentTarget).data('tag'); 
                this.toggleTagFilter(tag, $(e.currentTarget)); 
            }); 
             
            // Copy shortcode 
            $(document).on('click', '.pw-copy-shortcode-btn', (e) => { 
                const $card = $(e.currentTarget).closest('.pw-template-card');
                const templateName = $card.data('template-name');
                const shortcode = `[warmup_mailto template="${templateName}" class="btn"][/warmup_mailto]`;
                this.copyToClipboard(shortcode, $(e.currentTarget)); 
            });

            // Save default label on change directly from card: Fetch, Update, Save
            $(document).on('change', '.pw-default-label-input', async (e) => {
                const $input = $(e.currentTarget);
                const $card = $input.closest('.pw-template-card');
                const templateName = $card.data('template-name');
                const newVal = $input.val().trim();
                
                $input.prop('disabled', true);
                
                try {
                    // 1. Load
                    const loadRes = await $.post(pwAdmin.ajaxurl, {
                        action: 'pw_get_template',
                        nonce: pwAdmin.nonce,
                        name: templateName
                    });
                    
                    if (loadRes.success) {
                        const tpl = loadRes.data;
                        
                        // 2. Prepare data for save
                        // We need to map the flat structure back to variants structure expected by ajax_save_template
                        // ajax_save_template expects $_POST['variants']['subject'] etc.
                        const postData = {
                            action: 'pw_save_template',
                            nonce: pwAdmin.nonce,
                            id: tpl.id,
                            name: tpl.name,
                            folder_id: tpl.folder_id,
                            status: tpl.status,
                            tags: Array.isArray(tpl.tags) ? tpl.tags.join(',') : tpl.tags,
                            default_label: newVal,
                            variants: {
                                subject: tpl.subject || [],
                                text: tpl.text || [],
                                html: tpl.html || [],
                                from_name: tpl.from_name || [],
                                mailto_subject: tpl.mailto_subject || [],
                                mailto_body: tpl.mailto_body || [],
                                mailto_from_name: tpl.mailto_from_name || []
                            }
                        };
                        
                        // 3. Save
                        const saveRes = await $.post(pwAdmin.ajaxurl, postData);
                        if (saveRes.success) {
                            $input.addClass('saved').prop('disabled', false);
                            setTimeout(() => $input.removeClass('saved'), 1000);
                        } else {
                            alert('Erreur sauvegarde: ' + saveRes.data.message);
                            $input.prop('disabled', false);
                        }
                    }
                } catch (error) {
                    console.error('Error auto-saving label:', error);
                    $input.prop('disabled', false);
                }
            });
             
            // Filters 
            $('#pw-filter-status, #pw-filter-folder').on('change', () => { 
                this.applyFilters(); 
            }); 
             
            // Toggle view 
            $('#pw-toggle-view').on('click', () => { 
                this.toggleView(); 
            }); 

            // Modal closes
            $(document).on('click', '.pw-modal-close, .pw-modal-cancel', (e) => {
                $(e.currentTarget).closest('.pw-modal').hide();
            });

            // Bulk actions
            $(document).on('click', '.pw-bulk-add-btn', (e) => {
                e.preventDefault(); e.stopPropagation();
                const type = $(e.currentTarget).data('type');
                TemplateEditor.openBulkAdd(type);
            });

            $(document).on('click', '.pw-add-variant', (e) => {
                e.preventDefault(); e.stopPropagation();
                const type = $(e.currentTarget).data('type');
                TemplateEditor.addVariant(type);
            });

            $(document).on('click', '#pw-bulk-add-confirm', (e) => {
                e.preventDefault();
                TemplateEditor.confirmBulkAdd();
            });

            // Tabs
            $(document).on('click', '.pw-tab-btn', (e) => {
                const tab = $(e.currentTarget).data('tab');
                const $modal = $(e.currentTarget).closest('.pw-modal');
                $modal.find('.pw-tab-btn').removeClass('active');
                $(e.currentTarget).addClass('active');
                $modal.find('.pw-tab-content').removeClass('active');
                $modal.find('#pw-tab-' + tab).addClass('active');

                if (tab === 'stats') {
                    const tplName = $('#pw-editor-name').val();
                    if (tplName) {
                        TemplateEditor.loadStats(tplName);
                    }
                }
            });
        }, 
         
        async loadTemplates() { 
            if (typeof pwAdmin === 'undefined' || !$('#pw-templates-container').length) return;

            try { 
                const response = await $.post(pwAdmin.ajaxurl, { 
                    action: 'pw_get_all_templates', 
                    nonce: pwAdmin.nonce 
                }); 
                 
                if (response.success) { 
                    this.state.templates = response.data.templates; 
                    this.state.filteredTemplates = [...this.state.templates]; 
                    this.renderTemplates(); 
                    this.initShortcodeState();
                }
            } catch (error) { 
                console.error('Erreur chargement templates:', error); 
            } 
        }, 
         
        openEditor(templateName = null) { 
            TemplateEditor.open(templateName);
        }, 

        openPreview(templateName) {
            TemplatePreview.open(templateName);
        },
         
        async toggleFavorite($card) { 
            if (typeof pwAdmin === 'undefined') return;
            const templateId = $card.data('template-id'); 
            const $btn = $card.find('.pw-card-favorite-btn'); 
            const isFavorite = $btn.hasClass('active'); 
             
            try { 
                const response = await $.post(pwAdmin.ajaxurl, { 
                    action: 'pw_toggle_favorite', 
                    nonce: pwAdmin.nonce, 
                    template_id: templateId, 
                    favorite: !isFavorite 
                }); 
                 
                if (response.success) { 
                    $btn.toggleClass('active'); 
                    $btn.find('.dashicons').toggleClass('dashicons-star-filled').toggleClass('dashicons-star-empty'); 
                    const tpl = this.state.templates.find(t => t.id == templateId);
                    if (tpl) tpl.is_favorite = !isFavorite;
                    $('#pw-favorites-count').text(this.state.templates.filter(t => t.is_favorite).length);
                } 
            } catch (error) { 
                console.error('Erreur toggle favori:', error); 
            } 
        }, 

        async deleteTemplate($card) {
            if (typeof pwAdmin === 'undefined') return;
            const name = $card.data('template-name');
            if (!confirm(`⚠️ ATTENTION : La suppression du template "${name}" est DÉFINITIVE.\n\nIl sera effacé de la base de données ainsi que son historique.\nConfirmez-vous ?`)) return;

            try {
                const response = await $.post(pwAdmin.ajaxurl, {
                    action: 'pw_delete_template',
                    nonce: pwAdmin.nonce,
                    name: name
                });

                if (response.success) {
                    $card.fadeOut(() => {
                        this.state.templates = this.state.templates.filter(t => t.name !== name);
                        this.applyFilters();
                    });
                } else {
                    alert(response.data.message || 'Erreur lors de la suppression');
                }
            } catch (error) {
                console.error('Erreur suppression:', error);
            }
        },

        async duplicateTemplate($card) {
            const name = $card.data('template-name');
            TemplateDuplicate.open(name);
        },

        openMoveModal($card) {
            const $modal = $('#pw-template-move-modal');
            if (!$modal.length) return;
            const templateId = $card.data('template-id');
            const currentFolder = $card.data('folder');
            
            // Refresh the select options to ensure subcategories are present
            CategoryManager.populateSelect($modal.find('#pw-move-folder-select'));

            // Set value after short delay to allow populate to finish (or handle inside populate callback if refactored, but populate handles currentVal restore for existing elements, however this modal is hidden usually so val might be unset.
            // Better: just set it. populateSelect keeps val if set.
            $modal.find('#pw-move-folder-select').val(currentFolder);

            $('#pw-confirm-move-btn').off('click').on('click', () => {
                this.moveTemplate(templateId, $modal.find('#pw-move-folder-select').val());
            });
            
            $modal.css('display', 'flex');
        },

        async moveTemplate(id, folderId) {
            if (typeof pwAdmin === 'undefined') return;
            try {
                const response = await $.post(pwAdmin.ajaxurl, {
                    action: 'pw_move_template',
                    nonce: pwAdmin.nonce,
                    template_id: id,
                    folder_id: folderId
                });
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message || 'Erreur lors du déplacement');
                }
            } catch (error) {
                console.error('Error moving:', error);
            }
        },

        async archiveTemplate($card) {
            if (typeof pwAdmin === 'undefined') return;
            const id = $card.data('template-id');
            const currentStatus = $card.data('status');
            const newStatus = (currentStatus === 'archived') ? 'active' : 'archived';
            
            try {
                const response = await $.post(pwAdmin.ajaxurl, {
                    action: 'pw_update_template_status',
                    nonce: pwAdmin.nonce,
                    template_id: id,
                    status: newStatus
                });
                if (response.success) location.reload();
            } catch (error) {
                console.error('Error archiving:', error);
            }
        },

        async openVersionsModal($card) {
            if (typeof pwAdmin === 'undefined') return;
            const id = $card.data('template-id');
            const $modal = $('#pw-template-versions-modal');
            const $list = $('#pw-versions-list');
            if (!$modal.length || !$list.length) return;
            
            $list.html('<div class="pw-loading">Chargement...</div>');
            $modal.css('display', 'flex');

            try {
                const response = await $.post(pwAdmin.ajaxurl, {
                    action: 'pw_get_template_versions',
                    nonce: pwAdmin.nonce,
                    template_id: id
                });

                if (response.success) {
                    const versions = response.data.versions;
                    if (versions.length === 0) {
                        $list.html('<p>Aucun historique disponible.</p>');
                    } else {
                        $list.empty();
                        const templateHtml = $('#pw-version-item-template').html();
                        versions.forEach(v => {
                            let itemHtml = templateHtml
                                .replace(/<%- id %>/g, v.id)
                                .replace(/<%- version_number %>/g, v.version_number)
                                .replace(/<%- created_at %>/g, v.created_at)
                                .replace(/<%- author_name %>/g, v.author_name || 'Inconnu')
                                .replace(/<%- comment %>/g, v.comment || '')
                                .replace(/<%- diff_summary %>/g, v.diff_summary || '');
                            $list.append(itemHtml);
                        });
                        $('.pw-restore-version-btn').on('click', (e) => this.restoreVersion($(e.currentTarget).data('id')));
                    }
                }
            } catch (error) {
                console.error('Error loading versions:', error);
            }
        },

        async restoreVersion(versionId) {
            if (typeof pwAdmin === 'undefined') return;
            if (!confirm('Êtes-vous sûr de vouloir restaurer cette version ?')) return;
            try {
                const response = await $.post(pwAdmin.ajaxurl, {
                    action: 'pw_restore_template_version',
                    nonce: pwAdmin.nonce,
                    version_id: versionId
                });
                if (response.success) location.reload();
                else alert(response.data.message || 'Erreur lors de la restauration');
            } catch (error) {
                console.error('Error restoring:', error);
            }
        },
         
        filterByFolder(folderId) { 
            this.state.selectedFolder = folderId; 
            this.state.favoriteOnly = false;
            $('.pw-tree-content').removeClass('active'); 
            $('.pw-system-item, .pw-quick-link').removeClass('active');
            $(`.pw-tree-item[data-folder-id="${folderId}"] .pw-tree-content`).addClass('active'); 
            this.applyFilters(); 
        }, 

        filterFavorite() {
            this.state.selectedFolder = null;
            this.state.favoriteOnly = true;
            $('.pw-tree-content, .pw-system-item').removeClass('active');
            $('.pw-quick-link[data-filter="favorite"]').addClass('active');
            this.applyFilters();
        },

        toggleTagFilter(tag, $el) {
            const index = this.state.selectedTags.indexOf(tag);
            if (index > -1) { this.state.selectedTags.splice(index, 1); $el.removeClass('active'); }
            else { this.state.selectedTags.push(tag); $el.addClass('active'); }
            this.applyFilters();
        },
         
        applyFilters() { 
            let filtered = [...this.state.templates]; 
            filtered = filtered.filter(t => t.name !== 'null');
            if (this.state.favoriteOnly) filtered = filtered.filter(t => t.is_favorite);
            if (this.state.selectedFolder !== null && this.state.selectedFolder !== '') filtered = filtered.filter(t => t.folder_id == this.state.selectedFolder); 
            const status = $('#pw-filter-status').val(); 
            if (status) filtered = filtered.filter(t => t.status === status); 
            if (this.state.selectedTags.length > 0) filtered = filtered.filter(t => this.state.selectedTags.every(tag => t.tags && t.tags.some(ttag => ttag.name === tag))); 
            if (this.state.searchQuery) { 
                const query = this.state.searchQuery.toLowerCase(); 
                filtered = filtered.filter(t => t.name.toLowerCase().includes(query) || (t.tags && t.tags.some(tag => tag.name.toLowerCase().includes(query)))); 
            } 
            this.state.filteredTemplates = filtered; 
            this.renderTemplates(); 
        }, 
         
        initSearch() { 
            let timeout; 
            $('#pw-search-input').on('input', (e) => { 
                clearTimeout(timeout); 
                const query = $(e.target).val(); 
                timeout = setTimeout(() => { this.state.searchQuery = query; this.applyFilters(); $('.pw-search-clear').toggle(query.length > 0); }, 300); 
            }); 
            $('.pw-search-clear').on('click', () => { $('#pw-search-input').val('').trigger('input'); }); 
        }, 
         
        initDragDrop() { 
            const self = this;
            if (typeof pwAdmin === 'undefined') return;

            $(document).on('dragstart', '.pw-template-card', function(e) {
                const templateId = $(this).data('template-id');
                e.originalEvent.dataTransfer.setData('text/plain', templateId);
                $(this).addClass('is-dragging');
            });

            $(document).on('dragend', '.pw-template-card', function() { $(this).removeClass('is-dragging'); });

            $(document).on('dragover', '.pw-tree-item', function(e) { 
                e.preventDefault(); 
                e.stopPropagation();
                $(this).children('.pw-tree-content').addClass('drag-over'); 
            });

            $(document).on('dragleave', '.pw-tree-item', function(e) { 
                e.stopPropagation();
                $(this).children('.pw-tree-content').removeClass('drag-over'); 
            });

            $(document).on('drop', '.pw-tree-item', async function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).children('.pw-tree-content').removeClass('drag-over');
                const templateId = e.originalEvent.dataTransfer.getData('text/plain');
                const folderId = $(this).data('folder-id');
                if (templateId) {
                    try {
                        const response = await $.post(pwAdmin.ajaxurl, { action: 'pw_move_template', nonce: pwAdmin.nonce, template_id: templateId, folder_id: folderId });
                        if (response.success) {
                            const tpl = self.state.templates.find(t => t.id == templateId);
                            if (tpl) tpl.folder_id = folderId;
                            self.applyFilters();
                            CategoryManager.refreshTree();
                        } else alert(response.data.message || 'Erreur lors du déplacement');
                    } catch (error) { console.error('Drop error:', error); }
                }
            });
        }, 
         
        renderTemplates() { 
            const $container = $('#pw-templates-container'); 
            if (!$container.length) return;
            const templates = this.state.filteredTemplates; 
            if (templates.length === 0) { $container.hide(); $('.pw-empty-state').show(); return; } 
            $container.show(); $('.pw-empty-state').hide(); 
            $('.pw-template-card').hide();
            templates.forEach(t => { if (t.id) $(`.pw-template-card[data-template-id="${t.id}"]`).show(); else $(`.pw-template-card[data-template-name="${t.name}"]`).show(); });
        }, 
         
        copyToClipboard(text, $btn) { 
            if (navigator.clipboard && window.isSecureContext) { navigator.clipboard.writeText(text).then(() => this.showCopyFeedback($btn)); } 
            else { const $temp = $('<textarea>').val(text).appendTo('body').select(); document.execCommand('copy'); $temp.remove(); this.showCopyFeedback($btn); } 
        }, 
         
        showCopyFeedback($btn) { 
            const originalHtml = $btn.html(); 
            $btn.addClass('copied').html('<span class="dashicons dashicons-yes"></span>'); 
            setTimeout(() => { $btn.removeClass('copied').html(originalHtml); }, 2000); 
        }, 
         
        toggleView() { 
            this.state.currentView = this.state.currentView === 'grid' ? 'list' : 'grid'; 
            $('#pw-templates-container').toggleClass('pw-templates-grid').toggleClass('pw-templates-list'); 
            $('#pw-toggle-view .dashicons').toggleClass('dashicons-grid-view').toggleClass('dashicons-list-view'); 
        },

        showFatalError(message) {
            $('#pw-templates-container').html(`<div class="notice notice-error" style="margin: 20px 0; padding: 20px; background: #fff5f5; border-left: 4px solid #dc3232;"><h3 style="color: #dc3232;">🚨 Erreur</h3><p>${message}</p></div>`);
        }
    }; 
     
    $(document).ready(() => { 
        if ($('.pw-templates-v31, .pw-dashboard').length) { 
            // Expose for modal usage
            window.PWTemplateEditor = TemplateEditor;
            window.PWTemplatePreview = TemplatePreview;
            window.PWTemplateDuplicate = TemplateDuplicate;
            window.PWCategoryManager = CategoryManager;

            TemplatesManager.init(); 
            
            $('#pw-confirm-duplicate-btn').on('click', (e) => { e.preventDefault(); TemplateDuplicate.confirm(); });
            $('#pw-duplicate-new-name').on('keypress', (e) => { if (e.which === 13) { e.preventDefault(); TemplateDuplicate.confirm(); } });
            $('#pw-save-template-btn').on('click', () => TemplateEditor.save());
            $('#pw-save-category-btn').on('click', () => CategoryManager.save());
        } 
    }); 
     
    window.PWTemplatesManager = TemplatesManager; 
     
})(jQuery); 
