<?php
namespace SearchJet\Admin;

class DashboardWidget {
    
    public static function init() {
        add_action('wp_dashboard_setup', [self::class, 'addDashboardWidget']);
    }

    public static function addDashboardWidget() {
        wp_add_dashboard_widget(
            'searchjet_dashboard_widget',
            __('SearchJet Quick Guide', 'searchjet'),
            [self::class, 'renderWidget']
        );
    }

    public static function renderWidget() {
        $client_info = get_option('searchjet_client_info', []);
        $api_key = get_option('searchjet_hosted_api_key', '');
        $is_connected = !empty($client_info) && !empty($api_key);
        
        ?>
        <div class="searchjet-dashboard-widget">
            <div class="searchjet-widget-status">
                <div class="searchjet-status-indicator <?php echo $is_connected ? 'connected' : 'disconnected'; ?>"></div>
                <strong><?php echo $is_connected ? __('Connected', 'searchjet') : __('Not Connected', 'searchjet'); ?></strong>
                <?php if ($is_connected): ?>
                    <span class="searchjet-plan"><?php printf(__('Plan: %s', 'searchjet'), esc_html($client_info['plan'] ?? 'Free')); ?></span>
                <?php endif; ?>
            </div>

            <?php if (!$is_connected): ?>
                <div class="searchjet-widget-section">
                    <h4><?php _e('Get Started', 'searchjet'); ?></h4>
                    <ol>
                        <li><?php _e('Get your API key from SearchJet.com', 'searchjet'); ?></li>
                        <li><?php _e('Connect your site in SearchJet settings', 'searchjet'); ?></li>
                        <li><?php _e('Index your content', 'searchjet'); ?></li>
                    </ol>
                    <p>
                        <a href="<?php echo admin_url('admin.php?page=searchjet'); ?>" class="button button-primary">
                            <?php _e('Configure SearchJet', 'searchjet'); ?>
                        </a>
                    </p>
                </div>
            <?php else: ?>
                <div class="searchjet-widget-section">
                    <h4><?php _e('Quick Actions', 'searchjet'); ?></h4>
                    <p>
                        <a href="<?php echo admin_url('admin.php?page=searchjet'); ?>" class="button">
                            <?php _e('Settings', 'searchjet'); ?>
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=searchjet-analytics'); ?>" class="button">
                            <?php _e('Analytics', 'searchjet'); ?>
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=searchjet-docs'); ?>" class="button">
                            <?php _e('Documentation', 'searchjet'); ?>
                        </a>
                    </p>
                </div>
            <?php endif; ?>

            <div class="searchjet-widget-section">
                <h4><?php _e('Resources', 'searchjet'); ?></h4>
                <ul class="searchjet-resource-list">
                    <li><a href="https://searchjetengine.com/docs" target="_blank"><?php _e('📚 Documentation', 'searchjet'); ?></a></li>
                    <li><a href="https://demo.searchjetengine.com/" target="_blank"><?php _e('🎮 Live Demo', 'searchjet'); ?></a></li>
                    <li><a href="https://wordpress.org/support/plugin/searchjet/" target="_blank"><?php _e('💬 Support Forum', 'searchjet'); ?></a></li>
                    <li><a href="https://searchjetengine.com/pricing" target="_blank"><?php _e('⬆️ Upgrade Plans', 'searchjet'); ?></a></li>
                </ul>
            </div>
        </div>

        <style>
        .searchjet-dashboard-widget {
            font-size: 13px;
        }
        
        .searchjet-widget-status {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 16px;
            padding: 12px;
            background: #f8f9fa;
            border-radius: 6px;
        }
        
        .searchjet-status-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #dc3545;
        }
        
        .searchjet-status-indicator.connected {
            background: #28a745;
        }
        
        .searchjet-plan {
            color: #6c757d;
            font-size: 12px;
            margin-left: auto;
        }
        
        .searchjet-widget-section {
            margin-bottom: 16px;
        }
        
        .searchjet-widget-section h4 {
            margin: 0 0 8px 0;
            font-size: 14px;
            color: #1d2327;
        }
        
        .searchjet-widget-section ol {
            margin: 8px 0;
            padding-left: 20px;
        }
        
        .searchjet-widget-section li {
            margin-bottom: 4px;
        }
        
        .searchjet-resource-list {
            list-style: none;
            margin: 0;
            padding: 0;
        }
        
        .searchjet-resource-list li {
            margin-bottom: 6px;
        }
        
        .searchjet-resource-list a {
            text-decoration: none;
            color: #0073aa;
        }
        
        .searchjet-resource-list a:hover {
            text-decoration: underline;
        }
        </style>
        <?php
    }
}