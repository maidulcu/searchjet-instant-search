<?php
namespace SearchJet\Admin;

use SearchJet\API\SearchJetRemoteClient;
use SearchJet\Services\TaskStatusTracker;

class DashboardWidget {

    const WIDGET_ID = 'searchjet_dashboard_widget';
    const NONCE_KEY = 'searchjet_widget_nonce';

    public static function init() {
        add_action('wp_dashboard_setup', [self::class, 'addDashboardWidget']);
        add_action('wp_ajax_searchjet_dashboard_widget_stats', [self::class, 'ajaxGetStats']);
    }

    public static function addDashboardWidget() {
        wp_add_dashboard_widget(
            'searchjet_dashboard_widget',
            __('SearchJet Quick Guide', 'searchjet-instant-search'),
            [self::class, 'renderWidget']
        );
    }

    public static function renderWidget() {
        $api_key = get_option('searchjet_hosted_api_key', '');
        $is_connected = !empty($api_key);
        $nonce = wp_create_nonce(self::NONCE_KEY);
        ?>
        <div class="searchjet-dashboard-widget-wrapper">
            <div id="searchjet-widget-content">
                <?php if (!$is_connected): ?>
                    <div class="sj-widget-alert sj-alert-info">
                        <div class="sj-alert-icon">
                            <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/widget-info-blue.svg'); ?>" alt="" width="20" height="20" style="display:block;">
                        </div>
                        <div class="sj-alert-content">
                            <div class="sj-alert-title"><?php esc_html_e('Not Connected', 'searchjet-instant-search'); ?></div>
                            <div class="sj-alert-description"><?php esc_html_e('Connect your SearchJet API key to enable powerful AI search on your site.', 'searchjet-instant-search'); ?></div>
                        </div>
                    </div>
                    <div class="sj-widget-actions">
                        <a href="<?php echo esc_url(admin_url('admin.php?page=searchjet')); ?>" class="sj-btn-primary full-width">
                            <?php esc_html_e('Get Started with SearchJet', 'searchjet-instant-search'); ?>
                        </a>
                    </div>
                <?php else: ?>
                    <div class="sj-loading-state">
                        <div class="sj-loader"></div>
                        <span><?php esc_html_e('Fetching index status...', 'searchjet-instant-search'); ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($is_connected): ?>
        <script type="text/javascript">
        (function($) {
            'use strict';

            $(document).ready(function() {
                var widget = $('#searchjet-widget-content');
                var nonce = '<?php echo esc_js($nonce); ?>';
                var searchjetWidgetImgUrl = '<?php echo esc_js(SEARCHJET_PLUGIN_URL . 'assets/img/'); ?>';

                function fetchStats() {
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'searchjet_dashboard_widget_stats',
                            nonce: nonce
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success && response.data) {
                                renderStats(response.data);
                            } else {
                                showError('<?php esc_html_e('Failed to load status', 'searchjet-instant-search'); ?>');
                            }
                        },
                        error: function() {
                            showError('<?php esc_html_e('API Connection Error', 'searchjet-instant-search'); ?>');
                        }
                    });
                }

                function renderStats(stats) {
                    var html = '<div class="sj-widget-container">';

                    // Header
                    var statusState = stats.connection ? 'online' : 'offline';
                    var statusLabel = stats.connection ? '<?php esc_html_e('Operational', 'searchjet-instant-search'); ?>' : '<?php esc_html_e('Offline', 'searchjet-instant-search'); ?>';
                    
                    html += '<div class="sj-widget-status-bar">';
                    html += '<div class="sj-status-chip ' + statusState + '"><span class="sj-dot-pulse"></span>' + statusLabel + '</div>';
                    if (stats.pending_tasks > 0) {
                        html += '<div class="text-[10px] font-bold text-blue-500 animate-pulse uppercase tracking-wider">' + stats.pending_tasks + ' <?php esc_html_e('Syncing', 'searchjet-instant-search'); ?></div>';
                    } else {
                        html += '<div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider"><?php esc_html_e('Steady State', 'searchjet-instant-search'); ?></div>';
                    }
                    html += '</div>';

                    // Grid
                    html += '<div class="sj-widget-stats">';
                    html += '<div class="sj-widget-stat-card"><div class="sj-w-stat-label">Indexed Documents</div><div class="sj-w-stat-value">' + stats.documents.toLocaleString() + '</div></div>';
                    html += '<div class="sj-widget-stat-card"><div class="sj-w-stat-label">Neural Health</div><div class="sj-w-stat-value">100%</div></div>';
                    html += '</div>';

                    // Nav
                    html += '<div class="sj-widget-nav">';
                    html += '<a href="<?php echo esc_url(admin_url('admin.php?page=searchjet-search-ui')); ?>" class="sj-w-nav-link"><img src="' + searchjetWidgetImgUrl + 'widget-ui-customizer.svg" alt="" width="14" height="14" style="display:block;"><?php esc_html_e('UI Customizer', 'searchjet-instant-search'); ?></a>';
                    html += '<a href="<?php echo esc_url(admin_url('admin.php?page=searchjet-index-settings')); ?>" class="sj-w-nav-link"><img src="' + searchjetWidgetImgUrl + 'widget-index-rules.svg" alt="" width="14" height="14" style="display:block;"><?php esc_html_e('Index Rules', 'searchjet-instant-search'); ?></a>';
                    html += '<a href="<?php echo esc_url(admin_url('admin.php?page=searchjet-bulk-reindex')); ?>" class="sj-w-nav-link"><img src="' + searchjetWidgetImgUrl + 'widget-neural-sync.svg" alt="" width="14" height="14" style="display:block;"><?php esc_html_e('Neural Sync', 'searchjet-instant-search'); ?></a>';
                    html += '<a href="<?php echo esc_url(admin_url('admin.php?page=searchjet-docs')); ?>" class="sj-w-nav-link"><img src="' + searchjetWidgetImgUrl + 'widget-help-center.svg" alt="" width="14" height="14" style="display:block;"><?php esc_html_e('Help Center', 'searchjet-instant-search'); ?></a>';
                    html += '</div>';

                    html += '</div>';
                    widget.html(html);
                }

                function showError(message) {
                    var html = '<div class="sj-widget-container">';
                    html += '<div class="sj-widget-info-box" style="background: #fef2f2; border-color: #fecaca; color: #991b1b;">';
                    html += '<strong><?php esc_html_e('Sync Interrupted', 'searchjet-instant-search'); ?></strong><br>' + escapeHtml(message);
                    html += '</div>';
                    html += '<a href="#" id="sj-retry-btn" class="sj-btn-widget"><?php esc_html_e('Attempt Reconnection', 'searchjet-instant-search'); ?></a>';
                    html += '</div>';
                    widget.html(html);
                    
                    $('#sj-retry-btn').on('click', function(e) {
                        e.preventDefault();
                        widget.html('<div class="sj-w-loader-wrap"><div class="sj-w-spinner"></div><div class="text-[11px] text-slate-400 uppercase font-bold"><?php esc_html_e('Establishing link...', 'searchjet-instant-search'); ?></div></div>');
                        fetchStats();
                    });
                }

                function escapeHtml(text) {
                    var div = document.createElement('div');
                    div.textContent = text;
                    return div.innerHTML;
                }

                // Initial fetch
                fetchStats();
            });
        })(jQuery);
        </script>
        <?php endif; ?>

        <style>
        .searchjet-dashboard-widget-wrapper { margin: -12px; font-family: 'Inter', system-ui, sans-serif; }
        
        .sj-widget-container { padding: 16px; background: #fff; border-radius: 12px; }
        
        /* Premium Header */
        .sj-widget-status-bar { display: flex; align-items: center; justify-content: space-between; padding-bottom: 16px; border-bottom: 1px solid #f1f5f9; margin-bottom: 16px; }
        .sj-status-chip { display: flex; align-items: center; gap: 8px; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; }
        .sj-status-chip.online { background: #ecfdf5; color: #059669; }
        .sj-status-chip.offline { background: #fef2f2; color: #dc2626; }
        .sj-dot-pulse { width: 8px; height: 8px; border-radius: 50%; background: currentColor; position: relative; }
        .sj-dot-pulse::after { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 100%; border-radius: 50%; background: currentColor; animation: sj-widget-pulse 2s infinite; opacity: 0.5; }
        @keyframes sj-widget-pulse { 0% { transform: scale(1); opacity: 0.5; } 100% { transform: scale(3); opacity: 0; } }

        /* Stats Grid */
        .sj-widget-stats { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px; }
        .sj-widget-stat-card { background: #f8fafc; padding: 12px; border-radius: 10px; border: 1px solid #f1f5f9; transition: all 0.2s; }
        .sj-widget-stat-card:hover { border-color: #cbd5e1; transform: translateY(-1px); }
        .sj-w-stat-label { font-size: 10px; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 4px; }
        .sj-w-stat-value { font-size: 18px; font-weight: 800; color: #1e293b; }
        
        /* Action Links */
        .sj-widget-nav { display: grid; grid-template-columns: repeat(2, 1fr); gap: 6px; }
        .sj-w-nav-link { 
            display: flex; align-items: center; gap: 8px; padding: 8px 12px; 
            background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; 
            text-decoration: none; color: #475569; font-size: 12px; font-weight: 600; 
            transition: all 0.2s;
        }
        .sj-w-nav-link:hover { background: #f1f5f9; border-color: #94a3b8; color: #1e293b; }
        .sj-w-nav-link svg { width: 14px; height: 14px; color: #64748b; }

        /* Alerts */
        .sj-widget-info-box { background: #eff6ff; border: 1px solid #dbeafe; padding: 12px; border-radius: 10px; color: #1e40af; font-size: 12px; line-height: 1.5; }
        .sj-btn-widget { 
            display: block; width: 100%; text-align: center; margin-top: 12px; 
            padding: 10px; background: #2563eb; color: white; border-radius: 8px; 
            text-decoration: none; font-weight: 700; font-size: 13px; 
            box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.2); transition: all 0.2s;
        }
        .sj-btn-widget:hover { background: #1d4ed8; transform: translateY(-1px); }

        /* Loading */
        .sj-w-loader-wrap { text-align: center; padding: 20px 0; }
        .sj-w-spinner { width: 24px; height: 24px; border: 2px solid #e2e8f0; border-top-color: #2563eb; border-radius: 50%; animation: sj-spin 0.8s linear infinite; margin: 0 auto 10px; }
        @keyframes sj-spin { to { transform: rotate(360deg); } }
        </style>

        <?php
    }

    public static function ajaxGetStats() {
        check_ajax_referer(self::NONCE_KEY, 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'searchjet-instant-search'));
        }

        // Fetch document count (known working endpoint)
        $indexed_docs = SearchJetRemoteClient::fetchDocumentCount();
        if ($indexed_docs === null) {
            wp_send_json_error(__('Failed to load index stats', 'searchjet-instant-search'));
        }

        // Get task statistics
        $task_stats = TaskStatusTracker::getStatistics();

        // Get last task
        $history = TaskStatusTracker::getTaskHistory(1);
        $last_task = !empty($history) ? $history[0] : null;

        wp_send_json_success([
            'documents' => $indexed_docs,
            'pending_tasks' => $task_stats['total_active'] ?? 0,
            'connection' => true,
            'last_task' => $last_task ? [
                'status' => $last_task['status'],
                'type' => $last_task['type'],
                'updated_at' => $last_task['updated_at'],
            ] : null,
        ]);
    }
}