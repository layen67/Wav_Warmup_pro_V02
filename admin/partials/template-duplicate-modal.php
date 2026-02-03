<?php
/**
 * admin/partials/template-duplicate-modal.php
 */
if (!defined('ABSPATH')) exit;
?>
<div id="pw-template-duplicate-modal" class="pw-modal" style="display:none;">
    <div class="pw-modal-content pw-modal-sm">
        <div class="pw-modal-header">
            <h2>Dupliquer le Template</h2>
            <button class="pw-modal-close">&times;</button>
        </div>
        <div class="pw-modal-body">
            <div class="pw-form-group">
                <label for="pw-duplicate-new-name">Nouveau nom du template</label>
                <input type="text" id="pw-duplicate-new-name" name="new_name" class="large-text" placeholder="Entrez le nom..." required>
                <p class="description">Le nouveau nom doit être unique et sans espaces (ex: support_pro).</p>
            </div>
            <input type="hidden" id="pw-duplicate-source-name" name="source_name">
        </div>
        <div class="pw-modal-footer">
            <button type="button" class="pw-btn pw-btn-secondary pw-modal-close">Annuler</button>
            <button type="button" class="pw-btn pw-btn-primary" id="pw-confirm-duplicate-btn">🚀 Dupliquer</button>
        </div>
    </div>
</div>
