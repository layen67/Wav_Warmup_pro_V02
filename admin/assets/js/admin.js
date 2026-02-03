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

            $('#pw-suppression-server').on('change', function() {
                loadSuppressionList();
            });

            $('#pw-refresh-suppression').on('click', function(e) {
                e.preventDefault();
                loadSuppressionList();
            });

            $(document).on('click', '.pw-delete-suppression', function(e) {
                e.preventDefault();
                const address = $(this).data('address');
                if (confirm('Voulez-vous vraiment retirer ' + address + ' de la liste de suppression ?')) {
                    deleteSuppression(address);
                }
            });
        }

        function loadSuppressionList() {
            const serverId = $('#pw-suppression-server').val();
            const $tbody = $('#pw-suppression-list-body');
            
            $tbody.html('<tr><td colspan="5" style="text-align: center; padding: 20px;"><span class="spinner is-active" style="float:none; margin:0;"></span> Chargement...</td></tr>');

            $.post(pwAdmin.ajaxurl, {
                action: 'pw_get_suppression_list',
                nonce: pwAdmin.nonce,
                server_id: serverId
            }).done(function(res) {
                if (res.success) {
                    try {
                        renderSuppressionList(res.data.list);
                    } catch (e) {
                        console.error('Render error:', e);
                        $tbody.html('<tr><td colspan="5" style="color: #d63638; text-align:center;">Erreur d\'affichage: ' + e.message + '</td></tr>');
                    }
                } else {
                    $tbody.html('<tr><td colspan="5" style="color: #d63638; text-align:center;">' + (res.data.message || 'Erreur inconnue') + '</td></tr>');
                }
            }).fail(function() {
                $tbody.html('<tr><td colspan="5" style="color: #d63638; text-align:center;">Erreur réseau</td></tr>');
            });
        }

        function renderSuppressionList(list) {
            const $tbody = $('#pw-suppression-list-body');
            const tpl = $('#pw-suppression-row-tpl').html();
            
            // Safety check: ensure list is an array
            if (!list || !Array.isArray(list) || list.length === 0) {
                $tbody.html('<tr><td colspan="5" style="text-align:center; color:#646970;">La liste est vide. Tout va bien !</td></tr>');
                return;
            }

            $tbody.empty();
            list.forEach(item => {
                let html = tpl
                    .replace(/<%- address %>/g, item.address)
                    .replace(/<%- type %>/g, item.type || 'Bounced')
                    .replace(/<%- source %>/g, item.source || 'SMTP')
                    .replace(/<%- timestamp %>/g, new Date(item.timestamp * 1000).toLocaleString());
                $tbody.append(html);
            });
        }

        function deleteSuppression(address) {
            const serverId = $('#pw-suppression-server').val();
            
            $.post(pwAdmin.ajaxurl, {
                action: 'pw_delete_suppression',
                nonce: pwAdmin.nonce,
                server_id: serverId,
                address: address
            }).done(function(res) {
                if (res.success) {
                    loadSuppressionList();
                } else {
                    alert(res.data.message || 'Erreur');
                }
            });
        }
    });

})(jQuery);
