/**
 * Scripts de l'administration Postal Warmup Pro
 * VERSION ROBUSTE (v3.1.0) - Accordion Lazy Load
 */

(function($) {
    'use strict';

    // --- HELPERS (Shared Scope) ---
    function copyToClipboard(text) {
        if (!text) return;
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).catch(() => fallbackCopy(text));
        } else {
            fallbackCopy(text);
        }
    }

    function fallbackCopy(text) {
        const $temp = $('<textarea>');
        $temp.css({ position: 'fixed', left: '-9999px', top: '0' });
        $('body').append($temp);
        $temp.val(text).select();
        try { document.execCommand('copy'); } catch (err) { console.error('Erreur copie:', err); }
        $temp.remove();
    }

    function escapeHtml(text) {
        if (!text) return "";
        return String(text).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    }

    $(document).ready(function() {

        // --- ACTIONS GLOBALES ---
        $(document).on('click', '.pw-copy-btn, #pw-copy-secret, .pw-copy-shortcode', function(e) {
            e.preventDefault();
            const $btn = $(this);
            const text = $btn.data('shortcode') || $btn.data('clipboard') || $('#pw_webhook_secret').val();
            copyToClipboard(text);
            const oldHtml = $btn.html();
            $btn.html('<span class="dashicons dashicons-yes" style="color:#46b450"></span>');
            setTimeout(() => $btn.html(oldHtml), 2000);
        });

        $(document).on('click', '.pw-test-server-btn', function(e) {
            e.preventDefault();
            const $btn = $(this);
            $btn.prop('disabled', true).text('...');
            $.post(pwAdmin.ajaxurl, { action: 'pw_test_server', nonce: pwAdmin.nonce, server_id: $btn.data('server-id') })
                .done(res => alert(res.data.message || res.data))
                .always(() => $btn.prop('disabled', false).text('Tester'));
        });

        $(document).on('click', '#pw-clear-logs-btn', function(e) {
            e.preventDefault();
            if (!confirm('Supprimer tous les logs ?')) return;
            const $btn = $(this);
            const oldText = $btn.text();
            $btn.prop('disabled', true).text('...');
            $.post(pwAdmin.ajaxurl, { action: 'pw_clear_logs', nonce: pwAdmin.nonce })
                .done(res => {
                    alert(res.data.message || 'Logs supprimés');
                    if (res.success) location.reload();
                })
                .always(() => $btn.prop('disabled', false).text(oldText));
        });

        $(document).on('click', '#pw-clear-cache-btn', function(e) {
            e.preventDefault();
            if (!confirm('Vider tout le cache ?')) return;
            const $btn = $(this);
            const oldText = $btn.text();
            $btn.prop('disabled', true).text('...');
            $.post(pwAdmin.ajaxurl, { action: 'pw_clear_cache', nonce: pwAdmin.nonce })
                .done(res => alert(res.data.message || 'Cache vidé'))
                .always(() => $btn.prop('disabled', false).text(oldText));
        });

        // --- REGENERATE TOKEN ---
        $(document).on('click', '#pw-regenerate-token-btn', function(e) {
            e.preventDefault();
            if (!confirm('Attention : Êtes-vous sûr de vouloir régénérer le token ? L\'ancienne URL ne fonctionnera plus.')) return;
            
            const $btn = $(this);
            const oldText = $btn.text();
            $btn.prop('disabled', true).text('Régénération...');
            
            $.post(pwAdmin.ajaxurl, { action: 'pw_regenerate_secret', nonce: pwAdmin.nonce })
                .done(function(res) {
                    if (res.success) {
                        alert(res.data.message);
                        location.reload();
                    } else {
                        alert(res.data.message || 'Erreur');
                    }
                })
                .fail(function() {
                    alert('Erreur réseau');
                })
                .always(() => $btn.prop('disabled', false).text(oldText));
        });

        // --- DASHBOARD REALTIME ---
        let activityChart = null;

        if ($('.pw-dashboard').length) {
            initDashboard();
        }

        function initDashboard() {
            $('#pw-chart-period').on('change', function() { refreshDashboard(); });
            refreshDashboard();
            setInterval(refreshDashboard, 60000);
        }

        function refreshDashboard() {
            const days = $('#pw-chart-period').val() || 7;
            $.post(pwAdmin.ajaxurl, { action: 'pw_get_dashboard_data', nonce: pwAdmin.nonce, days: days }).done(function(res) {
                if (res.success) {
                    if (res.data.summary) updateStatsWidgets(res.data.summary);
                    if (res.data.chart) updateActivityChart(res.data.chart);
                    if (res.data.errors) updateErrorsList(res.data.errors);
                }
            });
        }

        function updateStatsWidgets(stats) {
            $('#pw-d-total-sent').text( parseInt(stats.total_sent).toLocaleString() );
            $('#pw-d-success-rate').text( stats.success_rate + '%' );
            $('#pw-d-active-servers').text( stats.active_servers + ' / ' + stats.total_servers );
             $('#pw-d-sent-today').html( stats.sent_today + ' <small style="font-size: 14px; color: ' + (parseFloat(stats.evolution) >= 0 ? '#46b450' : '#dc3232') + '">' + '(' + (parseFloat(stats.evolution) >= 0 ? '+' : '') + stats.evolution + '%)</small>' );
        }

        function updateActivityChart(chartData) {
            const ctx = document.getElementById('pw-sends-chart');
            if (!ctx) return;
            const labels = chartData.map(d => d.date);
            const sent = chartData.map(d => parseInt(d.total_sent));
            const success = chartData.map(d => parseInt(d.total_success));
            const errors = chartData.map(d => parseInt(d.total_errors));

            if (activityChart) {
                activityChart.data.labels = labels;
                activityChart.data.datasets[0].data = sent;
                activityChart.data.datasets[1].data = success;
                activityChart.data.datasets[2].data = errors;
                activityChart.update();
                return;
            }
             if (typeof Chart === 'undefined') return;
            activityChart = new Chart(ctx, { type: 'line', data: { labels: labels, datasets: [ { label: 'Envoyés', data: sent, borderColor: '#2271b1', backgroundColor: 'rgba(34, 113, 177, 0.1)', tension: 0.4, fill: true }, { label: 'Succès', data: success, borderColor: '#46b450', backgroundColor: 'rgba(70, 180, 80, 0.1)', tension: 0.4, fill: true }, { label: 'Erreurs', data: errors, borderColor: '#dc3232', backgroundColor: 'rgba(220, 50, 50, 0.1)', tension: 0.4, fill: true } ] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'top' } }, scales: { y: { beginAtZero: true } } } });
        }

        function updateErrorsList(errors) {
            const $container = $('#pw-errors-widget-content');
            if ( ! errors || errors.length === 0 ) { $container.html('<p class="pw-no-data"><span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span> Aucune erreur récente</p>'); return; }
            let html = '<ul class="pw-errors-list">';
            errors.forEach(err => { html += `<li class="pw-error-item"><div class="pw-error-level"><span class="pw-badge error">${escapeHtml(err.level)}</span></div><div class="pw-error-details"><div class="pw-error-message">${escapeHtml(err.message)}</div><div class="pw-error-meta">${err.server_domain ? '<span>' + escapeHtml(err.server_domain) + '</span> • ' : ''}<span>${escapeHtml(err.created_at)}</span></div></div></li>`; });
            html += '</ul>';
            $container.html(html);
        }
    });

    // --- SUPPRESSION LIST MANAGER ---
    $(document).ready(function() {
        if ($('.pw-suppression-wrap').length) {
            loadSuppressionList();
            $('#pw-suppression-server').on('change', function() { loadSuppressionList(); });
            $('#pw-refresh-suppression').on('click', function(e) { e.preventDefault(); loadSuppressionList(); });
            $(document).on('click', '.pw-delete-suppression', function(e) { e.preventDefault(); if (confirm('Voulez-vous vraiment retirer ' + $(this).data('address') + ' de la liste de suppression ?')) deleteSuppression($(this).data('address')); });
        }
        function loadSuppressionList() {
            const serverId = $('#pw-suppression-server').val();
            const $tbody = $('#pw-suppression-list-body');
            $tbody.html('<tr><td colspan="5" style="text-align: center; padding: 20px;"><span class="spinner is-active" style="float:none; margin:0;"></span> Chargement...</td></tr>');
            $.post(pwAdmin.ajaxurl, { action: 'pw_get_suppression_list', nonce: pwAdmin.nonce, server_id: serverId }).done(function(res) { if (res.success) renderSuppressionList(res.data.list); else $tbody.html('<tr><td colspan="5" style="color: #d63638; text-align:center;">' + (res.data.message || 'Erreur') + '</td></tr>'); });
        }
        function renderSuppressionList(list) {
            const $tbody = $('#pw-suppression-list-body');
            if (!list || !Array.isArray(list) || list.length === 0) { $tbody.html('<tr><td colspan="5" style="text-align:center;">Liste vide</td></tr>'); return; }
            $tbody.empty();
            const tpl = $('#pw-suppression-row-tpl').html();
            list.forEach(item => { $tbody.append(tpl.replace(/<%- address %>/g, item.address).replace(/<%- type %>/g, item.type || 'Bounced').replace(/<%- source %>/g, item.source || 'SMTP').replace(/<%- timestamp %>/g, new Date(item.timestamp * 1000).toLocaleString())); });
        }
        function deleteSuppression(address) {
            $.post(pwAdmin.ajaxurl, { action: 'pw_delete_suppression', nonce: pwAdmin.nonce, server_id: $('#pw-suppression-server').val(), address: address }).done(function(res) { if (res.success) loadSuppressionList(); else alert(res.data.message || 'Erreur'); });
        }
    });

    // --- ADVANCED STATS MODULE (Accordion & Charts) ---
    $(document).ready(function() {
        if (!$('.pw-stats-page').length) return;

        let charts = {};

        // Dark Mode
        const darkModeKey = 'pw_dark_mode';
        if (localStorage.getItem(darkModeKey) === 'true') $('body').addClass('pw-dark-mode');
        $('#pw-dark-mode-toggle').on('click', function() { $('body').toggleClass('pw-dark-mode'); localStorage.setItem(darkModeKey, $('body').hasClass('pw-dark-mode')); });

        // Tabs
        $('.nav-tab-wrapper a').on('click', function(e) { e.preventDefault(); $('.nav-tab-wrapper a').removeClass('nav-tab-active'); $(this).addClass('nav-tab-active'); $('.pw-tab-content').hide(); $($(this).attr('href')).show(); });

        // Filters
        $('#filter-days').on('change', function() { location.reload(); }); // Refresh page to reload headers correctly or implement partial reload.
        // Optimization: Full reload is safer for headers summary update + accordion state reset.
        // For smoother UX, we could just reload charts via AJAX, but Accordion Headers need server-side regeneration or complex JS.
        // Given "Pro" requirement, let's keep it simple: Filter changes = Reload. Charts inside tabs update on load.

        // 4. Load Charts
        fetchAdvancedStats();

        // 5. ACCORDION LOGIC (Lazy Load)
        $(document).on('click', '.pw-accordion-header', function() {
            const $item = $(this).closest('.pw-accordion-item');
            const $body = $item.find('.pw-accordion-body');
            const serverId = $item.data('server-id');
            const days = $('#filter-days').val();

            if ($(this).hasClass('active')) {
                // Close
                $(this).removeClass('active');
                $body.slideUp(200);
            } else {
                // Open
                $(this).addClass('active');
                $body.slideDown(200);

                // Lazy Load if empty
                if (!$body.data('loaded')) {
                    loadServerDetail(serverId, days, $body);
                }
            }
        });

        function loadServerDetail(serverId, days, $container) {
            $.post(pwAdmin.ajaxurl, {
                action: 'pw_get_server_detail',
                nonce: pwAdmin.nonce,
                server_id: serverId,
                days: days
            }).done(function(res) {
                if (res && res.success) {
                    renderDetailTable(res.data.stats, $container, serverId);
                    $container.data('loaded', true);
                } else {
                    const msg = (res && res.data && res.data.message) ? res.data.message : 'Erreur inconnue';
                    $container.html('<div class="pw-error" style="color:red; padding:10px;">' + escapeHtml(msg) + '</div>');
                }
            }).fail(function(xhr, status, error) {
                console.error("AJAX Error:", status, error, xhr.responseText);
                $container.html('<div class="pw-error" style="color:red; padding:10px;">Erreur réseau ou serveur: ' + escapeHtml(error || status) + '</div>');
            });
        }

        function renderDetailTable(stats, $container, serverId) {
            if (!stats || stats.length === 0) {
                $container.html('<p class="pw-no-data" style="padding:15px; text-align:center;">Aucune donnée détaillée.</p>');
                return;
            }

            let html = `
            <table class="wp-list-table widefat fixed striped pw-sortable-table" id="pw-table-${serverId}">
                <thead>
                    <tr>
                        <th class="pw-sortable" data-sort="template">Template / Préfixe <span class="dashicons dashicons-sort"></span></th>
                        <th class="pw-sortable is-sorted-desc" data-sort="sent">Sent <span class="dashicons dashicons-arrow-down-alt2"></span></th>
                        <th class="pw-sortable" data-sort="delivered">Delivered <span class="dashicons dashicons-sort"></span></th>
                        <th class="pw-sortable" data-sort="opened" title="Inclut les ouvertures automatiques (anti-spam, proxy images).">Opened <span class="dashicons dashicons-info" style="font-size:14px; color:#999;"></span> <span class="dashicons dashicons-sort"></span></th>
                        <th class="pw-sortable" data-sort="clicked">Clicked <span class="dashicons dashicons-sort"></span></th>
                        <th class="pw-sortable" data-sort="bounced">Bounced <span class="dashicons dashicons-sort"></span></th>
                        <th>Delayed</th>
                        <th>Held</th>
                    </tr>
                </thead>
                <tbody>`;

            stats.forEach(s => {
                const sent = parseInt(s.total_sent);
                const success = parseInt(s.success_count);
                const delRate = sent > 0 ? ((success / sent) * 100).toFixed(1) : 0;
                const openRate = success > 0 ? ((parseInt(s.opened_count||0) / success) * 100).toFixed(1) : 0;
                const clickRate = success > 0 ? ((parseInt(s.clicked_count||0) / success) * 100).toFixed(1) : 0;
                const name = s.template_name === 'null' ? '<em style="color:#888">&lt;Sans template&gt;</em>' : `<code>${escapeHtml(s.template_name)}</code>`;

                html += `
                    <tr class="pw-stat-row"
                        data-template="${escapeHtml(s.template_name)}"
                        data-sent="${sent}"
                        data-delivered="${delRate}"
                        data-opened="${openRate}"
                        data-clicked="${clickRate}"
                        data-bounced="${s.error_count}">
                        <td style="padding-left: 15px;">${name}</td>
                        <td>${sent.toLocaleString()}</td>
                        <td>
                            <div class="pw-progress-bar">
                                <div class="pw-progress-fill ${delRate > 90 ? 'success' : 'warning'}" style="width: ${delRate}%"></div>
                                <span>${success} (${delRate}%)</span>
                            </div>
                        </td>
                        <td>${s.opened_count||0} <small class="pw-rate">(${openRate}%)</small></td>
                        <td>${s.clicked_count||0} <small class="pw-rate">(${clickRate}%)</small></td>
                        <td><span class="pw-count bounced">${s.error_count}</span></td>
                        <td>${s.delayed_count}</td>
                        <td>${s.held_count}</td>
                    </tr>`;
            });

            html += '</tbody></table>';
            $container.html(html);
        }

        // Sorting Logic for dynamically created tables
        $(document).on('click', '.pw-sortable', function() {
            const $table = $(this).closest('table');
            const $tbody = $table.find('tbody');
            const sortKey = $(this).data('sort');
            const rows = $tbody.find('.pw-stat-row').get();

            let dir = $(this).hasClass('is-sorted-desc') ? 'asc' : 'desc';

            $table.find('.pw-sortable').removeClass('is-sorted is-sorted-desc').find('.dashicons').removeClass('dashicons-arrow-up-alt2 dashicons-arrow-down-alt2').addClass('dashicons-sort');
            $(this).addClass(dir === 'asc' ? 'is-sorted' : 'is-sorted-desc');
            $(this).find('.dashicons').removeClass('dashicons-sort').addClass(dir === 'asc' ? 'dashicons-arrow-up-alt2' : 'dashicons-arrow-down-alt2');

            rows.sort((a, b) => {
                let vA = $(a).data(sortKey);
                let vB = $(b).data(sortKey);

                // Numeric sort for metrics
                if (typeof vA === 'number') return dir === 'asc' ? vA - vB : vB - vA;
                // String sort for template name
                return dir === 'asc' ? String(vA).localeCompare(String(vB)) : String(vB).localeCompare(String(vA));
            });

            $.each(rows, (i, r) => $tbody.append(r));
        });

        function fetchAdvancedStats() {
            $.post(pwAdmin.ajaxurl, { action: 'pw_get_advanced_stats', nonce: pwAdmin.nonce, days: $('#filter-days').val() }).done(function(res) {
                if(res.success) { renderCharts(res.data.charts); renderHeatmap(res.data.heatmap); }
            });
        }

        function renderCharts(data) {
            ['volume', 'deliverability', 'openrate', 'errors'].forEach(k => { if (charts[k]) charts[k].destroy(); });
            if(!data || !data.dates) return;
            const create = (id, label, d, color, type='line') => {
                const ctx = document.getElementById('pw-chart-' + id);
                if(!ctx) return null;
                return new Chart(ctx, { type: type, data: { labels: data.dates, datasets: [{ label: label, data: d, borderColor: color, backgroundColor: color.replace(')', ', 0.2)').replace('rgb', 'rgba'), borderWidth: 2, tension: 0.3, fill: true }] }, options: { responsive: true, maintainAspectRatio: false } });
            };
            charts.volume = create('volume', 'Volume', data.sent, 'rgb(34, 113, 177)', 'bar');
            charts.deliverability = create('deliverability', 'Délivrabilité (%)', data.deliverability, 'rgb(70, 180, 80)');
            charts.openrate = create('openrate', 'Ouverture (%)', data.open_rate, 'rgb(240, 173, 78)');
            charts.errors = create('errors', 'Erreurs', data.errors, 'rgb(220, 50, 50)', 'bar');
        }

        function renderHeatmap(data) {
             const $c = $('#pw-heatmap-container');
             let h = '<table class="pw-heatmap-table"><thead><tr><th class="tpl-name">Template</th>';
             for(let i=0; i<24; i++) h += `<th>${i}h</th>`;
             h += '</tr></thead><tbody>';
             let max = 0; Object.values(data).forEach(arr => arr.forEach(v => max = Math.max(max, v)));
             Object.keys(data).forEach(tpl => {
                 h += `<tr><td class="tpl-name"><code>${escapeHtml(tpl)}</code></td>`;
                 data[tpl].forEach(val => {
                     const bg = max > 0 ? `rgba(34, 113, 177, ${Math.max(0.1, val/max)})` : 'transparent';
                     h += `<td><span class="pw-heatmap-cell" style="background:${val > 0 ? bg : ''}" title="${val}"></span></td>`;
                 });
                 h += '</tr>';
             });
             $c.html(h + '</tbody></table>');
        }

        $('#pw-export-csv-btn').on('click', function() {
            // Simplified CSV Export for visible data (Accordion detail + Global)
            // Or better: hit the dedicated backend export endpoint for raw data
            // But user asked for "Convertir le tableau complet en CSV".
            // Since we use Accordion (hidden data), client-side export is tricky unless all are open.
            // Best approach: Use the AJAX export endpoint which we already have!
            location.href = pwAdmin.ajaxurl + '?action=pw_export_stats&nonce=' + pwAdmin.nonce;
        });

        $('#pw-export-pdf-btn').on('click', function() {
            if (confirm('Pour une meilleure qualité, utilisez la fonction "Enregistrer au format PDF" de votre navigateur.\n\nVoulez-vous ouvrir la boîte de dialogue d\'impression ?')) {
                window.print();
            } else {
                 if (window.jspdf) {
                     const { jsPDF } = window.jspdf;
                     const doc = new jsPDF({ orientation: 'landscape' });
                     html2canvas(document.querySelector("#pw-stats-export-area")).then(canvas => {
                         const img = canvas.toDataURL('image/png');
                         const w = doc.internal.pageSize.getWidth();
                         doc.addImage(img, 'PNG', 0, 0, w, (canvas.height * w) / canvas.width);
                         doc.save('postal-stats.pdf');
                     });
                 }
            }
        });
    });

})(jQuery);
