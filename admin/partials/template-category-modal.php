<?php
/**
 * admin/partials/template-category-modal.php
 * Modal pour la création et modification des catégories
 */

if (!defined('ABSPATH')) exit;
?>

<div id="pw-category-modal" class="pw-modal" style="display:none;">
    <div class="pw-modal-content">
        <div class="pw-modal-header">
            <h2 id="pw-category-modal-title"><?php _e('Nouvelle Catégorie', 'postal-warmup'); ?></h2>
            <button class="pw-modal-close">&times;</button>
        </div>
        
        <form id="pw-category-form">
            <input type="hidden" id="pw-category-id" name="id">
            
            <div class="pw-form-group">
                <label for="pw-category-name"><?php _e('Nom de la catégorie', 'postal-warmup'); ?></label>
                <input type="text" id="pw-category-name" name="name" required placeholder="Ex: Newsletters, Transactionnel...">
            </div>
            
            <div class="pw-form-group">
                <label for="pw-category-parent"><?php _e('Catégorie parente', 'postal-warmup'); ?></label>
                <select id="pw-category-parent" name="parent_id">
                    <option value=""><?php _e('Aucune (Racine)', 'postal-warmup'); ?></option>
                    <!-- Rempli en JS -->
                </select>
            </div>
            
            <div class="pw-form-group">
                <label for="pw-category-color"><?php _e('Couleur', 'postal-warmup'); ?></label>
                <input type="color" id="pw-category-color" name="color" value="#2271b1">
            </div>
        </form>
        
        <div class="pw-modal-footer">
            <button type="button" class="pw-btn pw-btn-secondary pw-modal-cancel"><?php _e('Annuler', 'postal-warmup'); ?></button>
            <button type="button" class="pw-btn pw-btn-primary" id="pw-save-category-btn"><?php _e('Enregistrer', 'postal-warmup'); ?></button>
        </div>
    </div>
</div>
