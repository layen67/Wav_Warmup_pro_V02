<?php
/**
 * admin/partials/template-preview-modal.php
 * Template Preview Modal v3.1
 */

if (!defined('ABSPATH')) exit;
?>

<div id="pw-template-preview-modal" class="pw-modal" style="display:none;">
    <div class="pw-modal-content pw-modal-lg">
        <div class="pw-modal-header">
            <h2 id="pw-preview-title">Aperçu du Template</h2>
            <div class="pw-preview-actions">
                <select id="pw-preview-variant-select" class="pw-filter-select">
                    <!-- Variantes injectées en JS -->
                </select>
                <button class="pw-modal-close">&times;</button>
            </div>
        </div>
        
        <div class="pw-modal-body no-padding">
            <div class="pw-preview-container">
                <div class="pw-preview-sidebar">
                    <div class="pw-preview-meta">
                        <label>De :</label>
                        <div id="pw-preview-from"></div>
                    </div>
                    <div class="pw-preview-meta">
                        <label>Objet :</label>
                        <div id="pw-preview-subject"></div>
                    </div>
                    <div class="pw-preview-device-toggle">
                        <button class="pw-device-btn active" data-device="desktop"><span class="dashicons dashicons-desktop"></span></button>
                        <button class="pw-device-btn" data-device="mobile"><span class="dashicons dashicons-smartphone"></span></button>
                    </div>
                </div>
                
                <div class="pw-preview-frame-container">
                    <iframe id="pw-preview-frame" src="about:blank"></iframe>
                    <div id="pw-preview-text-fallback" style="display:none;"></div>
                </div>
            </div>
        </div>
        
        <div class="pw-modal-footer">
            <div class="pw-preview-footer-info">
                <span class="dashicons dashicons-info"></span>
                Les variables <code>{{email}}</code>, <code>{{site_url}}</code>, etc. sont remplacées par des données de test.
            </div>
            <button type="button" class="pw-btn pw-btn-secondary pw-modal-close">Fermer</button>
        </div>
    </div>
</div>
