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

        // --- DASHBOARD REALTIME ---
        let activityChart = null;

        if ($('.pw-dashboard').length) {
            initDashboard();
        }

        function initDashboard() {
            $('#pw-refresh-stats-btn').on('click', function() {
                refreshDashboard();
            });

            // Initial load
            refreshDashboard();

            // Auto-refresh every 30s
            setInterval(refreshDashboard, 30000);
        }

        function refreshDashboard() {
            const $btn = $('#pw-refresh-stats-btn');
            $btn.addClass('is-loading').prop('disabled', true);

            // Fetch generic activity
            $.post(pwAdmin.ajaxurl, {
                action: 'pw_get_latest_activity',
                nonce: pwAdmin.nonce
            }).done(function(res) {
                if (res.success) {
                    updateActivityList(res.data.logs);
                    updateStatsWidgets(res.data.stats);
                    updateActivityChart(res.data.chart);
                }
            }).always(function() {
                $btn.removeClass('is-loading').prop('disabled', false);
            });

            // Fetch Health (v3.2)
            if ($('#pw-server-health-widget').length) {
                $.post(pwAdmin.ajaxurl, {
                    action: 'pw_get_server_health',
                    nonce: pwAdmin.nonce
                }).done(function(res) {
                    if (res.success && res.data.servers.length) {
                        // Aggregate generic
                        let queue = 0;
                        let throughput = 0;
                        res.data.servers.forEach(s => {
                            queue += s.queue || 0;
                            throughput += s.throughput || 0;
                        });
                        $('#pw-health-queue').text(queue);
                        $('#pw-health-throughput').text(throughput);
                    }
                });
            }
        }

        function updateActivityChart(chartData) {
            const ctx = document.getElementById('pw-activity-chart');
            if (!ctx) return;

            if (!chartData || !chartData.labels || chartData.labels.length === 0) {
                const parent = ctx.parentElement;
                if (parent) {
                    parent.innerHTML = '<div class="pw-no-data" style="padding:40px; text-align:center; color:#646970;">' + 
                                       '<span class="dashicons dashicons-chart-area" style="font-size:40px; width:40px; height:40px; margin-bottom:10px; opacity:0.3;"></span>' +
                                       '<p>En attente de données pour le graphique...</p></div>';
                }
                return;
            }

            if (activityChart) {
                activityChart.data.labels = chartData.labels;
                activityChart.data.datasets[0].data = chartData.data;
                activityChart.update();
                return;
            }

            if (typeof Chart === 'undefined') return;

            activityChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: chartData.labels,
                    datasets: [{
                        label: 'Emails envoyés',
                        data: chartData.data,
                        borderColor: '#2271b1',
                        backgroundColor: 'rgba(34, 113, 177, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 2,
                        pointHoverRadius: 5
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { color: '#f0f0f1' }
                        },
                        x: {
                            grid: { display: false }
                        }
                    }
                }
            });
        }

        function updateActivityList(logs) {
            const $list = $('#pw-realtime-activity');
            $list.empty();

            logs.forEach(log => {
                const level = String(log.level).toLowerCase();
                const time = log.created_at.split(' ')[1];
                const server = log.server_domain ? `[${log.server_domain.replace('www.', '')}]` : '';
                const ctx = log.context ? JSON.parse(log.context) : {};
                
                let html = `
                    <li class="pw-activity-item ${level}">
                        <div class="pw-activity-header">
                            <span class="pw-activity-time">${time}</span>
                            <span class="pw-activity-server" title="${log.server_domain || ''}">${server}</span>
                        </div>
                        <div class="pw-activity-content">
                            <span class="pw-activity-msg">${escapeHtml(log.message)}</span>`;
                
                if (ctx.subject) {
                    html += `<span class="pw-activity-subject">"${escapeHtml(ctx.subject)}"</span>`;
                }

                if (log.email_from) {
                    const prefix = log.email_from.split('@')[0];
                    html += `
                        <div class="pw-activity-meta">
                            <span class="pw-activity-from">
                                <span class="dashicons dashicons-arrow-right-alt"></span>
                                <strong>${escapeHtml(prefix)}</strong>
                                ${log.server_domain ? '@ ' + escapeHtml(log.server_domain) : ''}
                            </span>`;
                    
                    if (log.email_to) {
                        html += `<span class="pw-activity-to"> -> ${escapeHtml(log.email_to)}</span>`;
                    }

                    if (log.status) {
                        html += `<span class="pw-activity-status badge-${log.status}">${log.status}</span>`;
                    }

                    if (ctx.bounce_type) {
                        html += `<span class="pw-activity-badge warning">${escapeHtml(ctx.bounce_type)}</span>`;
                    }

                    if (ctx.message_id) {
                        html += `<span class="pw-activity-id" title="Postal Message ID">#${ctx.message_id.substring(0, 8)}...</span>`;
                    }

                    html += `</div>`;
                }

                if (ctx.details) {
                    html += `<div class="pw-activity-details">${escapeHtml(ctx.details)}</div>`;
                }

                html += `</div></li>`;
                $list.append(html);
            });
        }

        function updateStatsWidgets(stats) {
            $('#pw-total-sent').text(stats.total_sent.toLocaleString());
            $('#pw-delivered-count').text((stats.delivered || 0).toLocaleString());
            $('#pw-opened-count').text((stats.opened || 0).toLocaleString());
            $('#pw-bounce-count').text((stats.bounces || 0).toLocaleString());
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
