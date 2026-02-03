<?php
/**
 * admin/partials/template-versions-modal.php
 * Template Versions Modal v3.1
 */

if (!defined('ABSPATH')) exit;
?>

<div id="pw-template-versions-modal" class="pw-modal" style="display:none;">
    <div class="pw-modal-content">
        <div class="pw-modal-header">
            <h2>Historique des Versions</h2>
            <button class="pw-modal-close">&times;</button>
        </div>
        
        <div class="pw-modal-body">
            <div id="pw-versions-list" class="pw-versions-timeline">
                <!-- Versions injectées en JS -->
                <div class="pw-loading">Chargement de l'historique...</div>
            </div>
        </div>
        
        <div class="pw-modal-footer">
            <button type="button" class="pw-btn pw-btn-secondary pw-modal-close">Fermer</button>
        </div>
    </div>
</div>

<script type="text/template" id="pw-version-item-template">
    <div class="pw-version-item" data-version-id="<%- id %>">
        <div class="pw-version-marker"></div>
        <div class="pw-version-info">
            <div class="pw-version-header">
                <span class="pw-version-number">v<%- version_number %></span>
                <span class="pw-version-date"><%- created_at %></span>
                <span class="pw-version-author">par <%- author_name %></span>
            </div>
            <% if (comment) { %>
                <div class="pw-version-comment"><%- comment %></div>
            <% } %>
            <% if (diff_summary) { %>
                <div class="pw-version-diff"><%- diff_summary %></div>
            <% } %>
            <div class="pw-version-actions">
                <button class="pw-btn pw-btn-sm pw-restore-version-btn" data-id="<%- id %>">Restaurer</button>
                <button class="pw-btn pw-btn-sm pw-view-version-btn" data-id="<%- id %>">Voir</button>
            </div>
        </div>
    </div>
</script>
