/**
 * Scripts de l'administration Postal Warmup Pro
 * VERSION ROBUSTE (v3.0.7) - Final UI & Deletion Fix
 */

(function($) {
    'use strict';

    $(document).ready(function() {

        // --- CLIPBOARD ---
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

        function debounce(func, wait) {
            let timeout;
            return function(...args) {
                const context = this;
                clearTimeout(timeout);
                timeout = setTimeout(() => func.apply(context, args), wait);
            };
        }

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
                        // Reload page to update the displayed URL properly
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

        // --- GESTION DES TEMPLATES (Legacy/Inactifs - handled by templates-manager.js) ---

        // --- DASHBOARD REALTIME (Optimized v3.2) ---
        let activityChart = null;

        if ($('.pw-dashboard').length) {
            initDashboard();
        }

        function initDashboard() {
            // Refresh on period change
            $('#pw-chart-period').on('change', function() {
                refreshDashboard();
            });

            // Initial load
            refreshDashboard();

            // Auto-refresh every 60s
            setInterval(refreshDashboard, 60000);
        }

        function refreshDashboard() {
            const days = $('#pw-chart-period').val() || 7;

            $.post(pwAdmin.ajaxurl, {
                action: 'pw_get_dashboard_data',
                nonce: pwAdmin.nonce,
                days: days
            }).done(function(res) {
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

             $('#pw-d-sent-today').html(
                stats.sent_today +
                ' <small style="font-size: 14px; color: ' + (parseFloat(stats.evolution) >= 0 ? '#46b450' : '#dc3232') + '">' +
                '(' + (parseFloat(stats.evolution) >= 0 ? '+' : '') + stats.evolution + '%)</small>'
            );
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

            activityChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Envoyés',
                            data: sent,
                            borderColor: '#2271b1',
                            backgroundColor: 'rgba(34, 113, 177, 0.1)',
                            tension: 0.4,
                            fill: true
                        },
                        {
                            label: 'Succès',
                            data: success,
                            borderColor: '#46b450',
                            backgroundColor: 'rgba(70, 180, 80, 0.1)',
                            tension: 0.4,
                            fill: true
                        },
                        {
                            label: 'Erreurs',
                            data: errors,
                            borderColor: '#dc3232',
                            backgroundColor: 'rgba(220, 50, 50, 0.1)',
                            tension: 0.4,
                            fill: true
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'top' }
                    },
                    scales: {
                        y: { beginAtZero: true }
                    }
                }
            });
        }

        function updateErrorsList(errors) {
            const $container = $('#pw-errors-widget-content');
            if ( ! errors || errors.length === 0 ) {
                 $container.html('<p class="pw-no-data"><span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span> Aucune erreur récente</p>');
                 return;
            }

            let html = '<ul class="pw-errors-list">';
            errors.forEach(err => {
                html += `
                    <li class="pw-error-item">
                        <div class="pw-error-level">
                            <span class="pw-badge error">${escapeHtml(err.level)}</span>
                        </div>
                        <div class="pw-error-details">
                            <div class="pw-error-message">${escapeHtml(err.message)}</div>
                            <div class="pw-error-meta">
                                ${err.server_domain ? '<span>' + escapeHtml(err.server_domain) + '</span> • ' : ''}
                                <span>${escapeHtml(err.created_at)}</span>
                            </div>
                        </div>
                    </li>`;
            });
            html += '</ul>';
            $container.html(html);
        }
    });

    // --- SUPPRESSION LIST MANAGER (v3.2) ---
    $(document).ready(function() {
        if ($('.pw-suppression-wrap').length) {
            loadSuppressionList();
            $('#pw-suppression-server').on('change', function() { loadSuppressionList(); });
            $('#pw-refresh-suppression').on('click', function(e) { e.preventDefault(); loadSuppressionList(); });
            $(document).on('click', '.pw-delete-suppression', function(e) {
                e.preventDefault();
                if (confirm('Voulez-vous vraiment retirer ' + $(this).data('address') + ' de la liste de suppression ?')) deleteSuppression($(this).data('address'));
            });
        }
        function loadSuppressionList() {
            const serverId = $('#pw-suppression-server').val();
            const $tbody = $('#pw-suppression-list-body');
            $tbody.html('<tr><td colspan="5" style="text-align: center; padding: 20px;"><span class="spinner is-active" style="float:none; margin:0;"></span> Chargement...</td></tr>');
            $.post(pwAdmin.ajaxurl, { action: 'pw_get_suppression_list', nonce: pwAdmin.nonce, server_id: serverId }).done(function(res) {
                if (res.success) renderSuppressionList(res.data.list); else $tbody.html('<tr><td colspan="5" style="color: #d63638; text-align:center;">' + (res.data.message || 'Erreur') + '</td></tr>');
            });
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

    // --- ADVANCED STATS MODULE (v4.0) ---
    $(document).ready(function() {
        if (!$('.pw-stats-page').length) return;

        let charts = {};

        // 1. Dark Mode
        const darkModeKey = 'pw_dark_mode';
        if (localStorage.getItem(darkModeKey) === 'true') $('body').addClass('pw-dark-mode');
        $('#pw-dark-mode-toggle').on('click', function() {
            $('body').toggleClass('pw-dark-mode');
            localStorage.setItem(darkModeKey, $('body').hasClass('pw-dark-mode'));
        });

        // 2. Tabs
        $('.nav-tab-wrapper a').on('click', function(e) {
            e.preventDefault();
            $('.nav-tab-wrapper a').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');
            $('.pw-tab-content').hide();
            $($(this).attr('href')).show();
        });

        // 3. Filters
        $('#filter-days, #filter-server').on('change', function() { refreshStats(); });

        // 4. Load Data
        fetchAdvancedStats();

        function refreshStats() {
            const data = { action: 'pw_get_stats_table', nonce: pwAdmin.nonce, days: $('#filter-days').val(), server: $('#filter-server').val() };
            $('.pw-stats-page').css('opacity', '0.5');
            $.when(
                $.post(pwAdmin.ajaxurl, data),
                $.post(pwAdmin.ajaxurl, { ...data, action: 'pw_get_advanced_stats' })
            ).done(function(resTable, resCharts) {
                if(resTable[0].success) renderTable(resTable[0].data.stats);
                if(resCharts[0].success) { renderCharts(resCharts[0].data.charts); renderHeatmap(resCharts[0].data.heatmap); }
                $('.pw-stats-page').css('opacity', '1');
            });
        }

        function fetchAdvancedStats() {
            $.post(pwAdmin.ajaxurl, { action: 'pw_get_advanced_stats', nonce: pwAdmin.nonce, days: $('#filter-days').val() }).done(function(res) {
                if(res.success) { renderCharts(res.data.charts); renderHeatmap(res.data.heatmap); }
            });
        }

        function renderTable(stats) {
            const $tbody = $('#pw-detailed-stats-body');
            $tbody.empty();
            if (!stats || stats.length === 0) { $tbody.html('<tr><td colspan="9">Aucune donnée.</td></tr>'); return; }
            
            let currentServer = '';
            stats.forEach(s => {
                const prefix = s.email_from;
                const domain = s.server_domain;
                if (currentServer !== domain) {
                    currentServer = domain;
                    $tbody.append(`<tr class="pw-server-header-row" data-server="${domain}"><td colspan="9"><strong><span class="dashicons dashicons-networking"></span> ${domain}</strong></td></tr>`);
                }
                const sent = parseInt(s.total_sent);
                const success = parseInt(s.success_count);
                const delRate = sent > 0 ? ((success / sent) * 100).toFixed(1) : 0;
                const openRate = success > 0 ? ((parseInt(s.opened_count||0) / success) * 100).toFixed(1) : 0;
                const clickRate = success > 0 ? ((parseInt(s.clicked_count||0) / success) * 100).toFixed(1) : 0;
                const prefixDisplay = prefix === 'null' ? '<em style="color: #888;">&lt;sans template&gt;</em>' : `<code>${prefix}</code>`;

                $tbody.append(`
                    <tr class="pw-stat-row" data-domain="${domain}-${prefix}" data-sent="${sent}" data-delivered="${delRate}" data-opened="${openRate}" data-clicked="${clickRate}" data-bounced="${s.error_count}" data-latency="${s.avg_response_time}">
                        <td style="padding-left: 25px;">${prefixDisplay}</td>
                        <td>${sent.toLocaleString()}</td>
                        <td><div class="pw-progress-bar"><div class="pw-progress-fill ${delRate > 90 ? 'success' : 'warning'}" style="width: ${delRate}%"></div><span>${success} (${delRate}%)</span></div></td>
                        <td>${s.opened_count||0} <small class="pw-rate">(${openRate}%)</small></td>
                        <td>${s.clicked_count||0} <small class="pw-rate">(${clickRate}%)</small></td>
                        <td><span class="pw-count bounced">${s.error_count}</span></td>
                        <td><span class="pw-count delayed">${s.delayed_count}</span></td>
                        <td><span class="pw-count held">${s.held_count}</span></td>
                        <td>${parseFloat(s.avg_response_time).toFixed(3)}s</td>
                    </tr>
                `);
            });
        }

        $('.pw-sortable').on('click', function() {
            const sortKey = $(this).data('sort');
            const $tbody = $('#pw-detailed-stats-body');
            const rows = $tbody.find('.pw-stat-row').get();
            let dir = $(this).hasClass('is-sorted-desc') ? 'asc' : 'desc';
            $('.pw-sortable').removeClass('is-sorted is-sorted-desc').find('.dashicons').removeClass('dashicons-arrow-up-alt2 dashicons-arrow-down-alt2').addClass('dashicons-sort');
            $(this).addClass(dir === 'asc' ? 'is-sorted' : 'is-sorted-desc');
            $(this).find('.dashicons').removeClass('dashicons-sort').addClass(dir === 'asc' ? 'dashicons-arrow-up-alt2' : 'dashicons-arrow-down-alt2');

            rows.sort((a, b) => {
                let vA = parseFloat($(a).data(sortKey)) || 0;
                let vB = parseFloat($(b).data(sortKey)) || 0;
                return dir === 'asc' ? vA - vB : vB - vA;
            });
            $('.pw-server-header-row').hide();
            if (sortKey === 'domain') $('.pw-server-header-row').show(); // Simplistic restore
            $.each(rows, (i, r) => $tbody.append(r));
        });

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
            let csv = [];
            document.querySelectorAll("#pw-detailed-stats-table tr").forEach(tr => {
                if(tr.style.display !== 'none') {
                    let row = [];
                    tr.querySelectorAll("td, th").forEach(td => row.push('"' + td.innerText.replace(/"/g, '""').trim() + '"'));
                    csv.push(row.join(","));
                }
            });
            const link = document.createElement("a");
            link.download = 'postal-stats.csv';
            link.href = window.URL.createObjectURL(new Blob([csv.join("\n")], {type: "text/csv"}));
            link.click();
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
