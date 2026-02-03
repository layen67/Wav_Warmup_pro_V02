<?php
/**
 * admin/partials/template-move-modal.php
 * Template Move Modal v3.1
 */

if (!defined('ABSPATH')) exit;
?>

<div id="pw-template-move-modal" class="pw-modal" style="display:none;">
    <div class="pw-modal-content pw-modal-sm">
        <div class="pw-modal-header">
            <h2>Déplacer le Template</h2>
            <button class="pw-modal-close">&times;</button>
        </div>
        
        <div class="pw-modal-body">
            <div class="pw-form-group">
                <label>Sélectionnez un dossier de destination :</label>
                <select id="pw-move-folder-select" class="widefat">
                    <option value="">Aucun dossier</option>
                    <?php foreach ($folders as $f): ?>
                        <option value="<?php echo $f['id']; ?>"><?php echo esc_html($f['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <div class="pw-modal-footer">
            <button type="button" class="pw-btn pw-btn-secondary pw-modal-close">Annuler</button>
            <button type="button" class="pw-btn pw-btn-primary" id="pw-confirm-move-btn">Déplacer</button>
        </div>
    </div>
</div>
