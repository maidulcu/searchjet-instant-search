<?php
namespace SearchJet\Admin;

class DocumentationPage {
    
    public static function init() {
        add_action('admin_menu', [self::class, 'registerMenu']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueueAssets']);
    }

    public static function registerMenu() {
        add_submenu_page(
            'searchjet',
            __('Documentation', 'searchjet'),
            __('Documentation', 'searchjet'),
            'manage_options',
            'searchjet-docs',
            [self::class, 'render']
        );
    }

    public static function enqueueAssets($hook) {
        if ($hook !== 'searchjet_page_searchjet-docs') return;

        wp_enqueue_style(
            'searchjet-docs',
            SEARCHJET_PLUGIN_URL . 'assets/css/admin-style-enhanced.css',
            [],
            '1.0.0'
        );

        wp_enqueue_script(
            'searchjet-docs',
            SEARCHJET_PLUGIN_URL . 'assets/js/admin-enhanced.js',
            ['jquery'],
            '1.0.0',
            true
        );
    }

    public static function render() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'searchjet'));
        }

        // Read documentation files
        $plugin_dir = plugin_dir_path(__FILE__) . '../../';
        $admin_guide = file_exists($plugin_dir . 'ADMIN_PANEL_GUIDE.md') ? file_get_contents($plugin_dir . 'ADMIN_PANEL_GUIDE.md') : '';
        $api_docs = file_exists($plugin_dir . 'API_DOCUMENTATION.md') ? file_get_contents($plugin_dir . 'API_DOCUMENTATION.md') : '';
        
        ?>
        <div class="searchjet-admin-wrap">
            <div class="searchjet-header">
                <h1><?php _e('SearchJet Documentation', 'searchjet'); ?></h1>
                <div class="subtitle"><?php _e('Complete guide to using SearchJet AI-powered search', 'searchjet'); ?></div>
            </div>

            <div class="searchjet-content">
                <div class="searchjet-card">
                    <div class="searchjet-docs-nav">
                        <div class="searchjet-docs-tabs">
                            <button type="button" class="searchjet-docs-tab active" data-tab="overview"><?php _e('Overview', 'searchjet'); ?></button>
                            <button type="button" class="searchjet-docs-tab" data-tab="admin-guide"><?php _e('Admin Guide', 'searchjet'); ?></button>
                            <button type="button" class="searchjet-docs-tab" data-tab="api-reference"><?php _e('API Reference', 'searchjet'); ?></button>
                            <button type="button" class="searchjet-docs-tab" data-tab="troubleshooting"><?php _e('Troubleshooting', 'searchjet'); ?></button>
                        </div>
                    </div>

                    <div class="searchjet-docs-content">
                        <!-- Overview Tab -->
                        <div id="docs-overview" class="searchjet-docs-section active">
                            <h3><?php _e('SearchJet Overview', 'searchjet'); ?></h3>
                            <p><?php _e('SearchJet is an AI-powered instant search plugin for WordPress and WooCommerce that provides blazing-fast, typo-tolerant search functionality with real-time analytics, automation capabilities, and AI agents.', 'searchjet'); ?></p>
                            
                            <h4><?php _e('Key Features', 'searchjet'); ?></h4>
                            <div class="searchjet-feature-grid">
                                <div class="searchjet-feature-card">
                                    <div class="searchjet-feature-icon">⚡</div>
                                    <h5><?php _e('Instant Search', 'searchjet'); ?></h5>
                                    <p><?php _e('Lightning-fast search results as users type with real-time suggestions and auto-complete.', 'searchjet'); ?></p>
                                </div>
                                <div class="searchjet-feature-card">
                                    <div class="searchjet-feature-icon">🧠</div>
                                    <h5><?php _e('AI-Powered Intelligence', 'searchjet'); ?></h5>
                                    <p><?php _e('Advanced AI algorithms provide typo tolerance, semantic understanding, and intelligent result ranking.', 'searchjet'); ?></p>
                                </div>
                                <div class="searchjet-feature-card">
                                    <div class="searchjet-feature-icon">📊</div>
                                    <h5><?php _e('Advanced Analytics', 'searchjet'); ?></h5>
                                    <p><?php _e('Comprehensive search analytics with user behavior insights and performance metrics.', 'searchjet'); ?></p>
                                </div>
                                <div class="searchjet-feature-card">
                                    <div class="searchjet-feature-icon">🔧</div>
                                    <h5><?php _e('Smart Automations', 'searchjet'); ?></h5>
                                    <p><?php _e('Automated optimization and intelligent improvements based on search patterns.', 'searchjet'); ?></p>
                                </div>
                                <div class="searchjet-feature-card">
                                    <div class="searchjet-feature-icon">🛒</div>
                                    <h5><?php _e('WooCommerce Integration', 'searchjet'); ?></h5>
                                    <p><?php _e('Native support for products, categories, pricing, and inventory management.', 'searchjet'); ?></p>
                                </div>
                                <div class="searchjet-feature-card">
                                    <div class="searchjet-feature-icon">🎨</div>
                                    <h5><?php _e('Customizable UI', 'searchjet'); ?></h5>
                                    <p><?php _e('Flexible search interface that adapts to your theme and brand requirements.', 'searchjet'); ?></p>
                                </div>
                            </div>

                            <h4><?php _e('Quick Start Guide', 'searchjet'); ?></h4>
                            <ol class="searchjet-steps">
                                <li><strong><?php _e('Install & Activate:', 'searchjet'); ?></strong> <?php _e('Install the SearchJet plugin from WordPress repository', 'searchjet'); ?></li>
                                <li><strong><?php _e('Get API Key:', 'searchjet'); ?></strong> <?php _e('Sign up at SearchJet.com and get your API key', 'searchjet'); ?></li>
                                <li><strong><?php _e('Connect:', 'searchjet'); ?></strong> <?php _e('Enter your API key in the Connection tab', 'searchjet'); ?></li>
                                <li><strong><?php _e('Index Content:', 'searchjet'); ?></strong> <?php _e('Run initial indexing from the Indexing tab', 'searchjet'); ?></li>
                                <li><strong><?php _e('Configure:', 'searchjet'); ?></strong> <?php _e('Customize settings and exclusions as needed', 'searchjet'); ?></li>
                                <li><strong><?php _e('Test:', 'searchjet'); ?></strong> <?php _e('Test search functionality on your site', 'searchjet'); ?></li>
                            </ol>

                            <div class="searchjet-alert searchjet-alert-info">
                                <div>
                                    <strong><?php _e('Need Help?', 'searchjet'); ?></strong><br>
                                    <?php _e('Visit our', 'searchjet'); ?> <a href="https://searchjetengine.com/docs" target="_blank"><?php _e('online documentation', 'searchjet'); ?></a> 
                                    <?php _e('or try our', 'searchjet'); ?> <a href="https://demo.searchjetengine.com/" target="_blank"><?php _e('live demo', 'searchjet'); ?></a>.
                                </div>
                            </div>
                        </div>

                        <!-- Admin Guide Tab -->
                        <div id="docs-admin-guide" class="searchjet-docs-section">
                            <h3><?php _e('Admin Panel Guide', 'searchjet'); ?></h3>
                            <?php if ($admin_guide): ?>
                                <div class="searchjet-markdown-content">
                                    <?php echo self::parseMarkdown($admin_guide); ?>
                                </div>
                            <?php else: ?>
                                <div class="searchjet-alert searchjet-alert-warning">
                                    <div><?php _e('Admin guide documentation file not found.', 'searchjet'); ?></div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- API Reference Tab -->
                        <div id="docs-api-reference" class="searchjet-docs-section">
                            <h3><?php _e('API Reference', 'searchjet'); ?></h3>
                            <?php if ($api_docs): ?>
                                <div class="searchjet-markdown-content">
                                    <?php echo self::parseMarkdown($api_docs); ?>
                                </div>
                            <?php else: ?>
                                <div class="searchjet-alert searchjet-alert-warning">
                                    <div><?php _e('API documentation file not found.', 'searchjet'); ?></div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Troubleshooting Tab -->
                        <div id="docs-troubleshooting" class="searchjet-docs-section">
                            <h3><?php _e('Troubleshooting Guide', 'searchjet'); ?></h3>
                            
                            <div class="searchjet-troubleshoot-item">
                                <h4><?php _e('Search Not Working', 'searchjet'); ?></h4>
                                <ul>
                                    <li><?php _e('Verify your API key is correctly entered and valid', 'searchjet'); ?></li>
                                    <li><?php _e('Check that content has been indexed (see Indexing tab)', 'searchjet'); ?></li>
                                    <li><?php _e('Ensure search mode is properly configured in Advanced settings', 'searchjet'); ?></li>
                                    <li><?php _e('Clear browser cache and try again', 'searchjet'); ?></li>
                                    <li><?php _e('Check browser console for JavaScript errors', 'searchjet'); ?></li>
                                </ul>
                            </div>

                            <div class="searchjet-troubleshoot-item">
                                <h4><?php _e('Indexing Issues', 'searchjet'); ?></h4>
                                <ul>
                                    <li><?php _e('Check your plan limits for indexed pages in the dashboard', 'searchjet'); ?></li>
                                    <li><?php _e('Review exclusion settings for post types and statuses', 'searchjet'); ?></li>
                                    <li><?php _e('Verify content is published and publicly accessible', 'searchjet'); ?></li>
                                    <li><?php _e('Try manual reindexing from the Indexing tab', 'searchjet'); ?></li>
                                    <li><?php _e('Check server logs for any indexing errors', 'searchjet'); ?></li>
                                </ul>
                            </div>

                            <div class="searchjet-troubleshoot-item">
                                <h4><?php _e('Performance Issues', 'searchjet'); ?></h4>
                                <ul>
                                    <li><?php _e('Switch to JavaScript mode for faster client-side results', 'searchjet'); ?></li>
                                    <li><?php _e('Reduce the number of indexed attributes to essential ones only', 'searchjet'); ?></li>
                                    <li><?php _e('Optimize your exclusion rules to reduce index size', 'searchjet'); ?></li>
                                    <li><?php _e('Check for plugin conflicts by deactivating other plugins', 'searchjet'); ?></li>
                                    <li><?php _e('Ensure your hosting meets minimum requirements', 'searchjet'); ?></li>
                                </ul>
                            </div>

                            <div class="searchjet-troubleshoot-item">
                                <h4><?php _e('Connection Problems', 'searchjet'); ?></h4>
                                <ul>
                                    <li><?php _e('Verify your API key is active and not expired', 'searchjet'); ?></li>
                                    <li><?php _e('Check your server can make outbound HTTPS requests', 'searchjet'); ?></li>
                                    <li><?php _e('Ensure your firewall allows connections to SearchJet servers', 'searchjet'); ?></li>
                                    <li><?php _e('Try reconnecting using the "Connect to SearchJet" button', 'searchjet'); ?></li>
                                    <li><?php _e('Contact your hosting provider about API connectivity', 'searchjet'); ?></li>
                                </ul>
                            </div>

                            <h4><?php _e('Getting Additional Help', 'searchjet'); ?></h4>
                            <div class="searchjet-feature-grid">
                                <div class="searchjet-feature-card">
                                    <div class="searchjet-feature-icon">📚</div>
                                    <h5><a href="https://searchjetengine.com/docs" target="_blank"><?php _e('Official Documentation', 'searchjet'); ?></a></h5>
                                    <p><?php _e('Comprehensive guides and tutorials', 'searchjet'); ?></p>
                                </div>
                                <div class="searchjet-feature-card">
                                    <div class="searchjet-feature-icon">🎮</div>
                                    <h5><a href="https://demo.searchjetengine.com/" target="_blank"><?php _e('Live Demo', 'searchjet'); ?></a></h5>
                                    <p><?php _e('Try SearchJet features interactively', 'searchjet'); ?></p>
                                </div>
                                <div class="searchjet-feature-card">
                                    <div class="searchjet-feature-icon">💬</div>
                                    <h5><a href="https://wordpress.org/support/plugin/searchjet/" target="_blank"><?php _e('Support Forum', 'searchjet'); ?></a></h5>
                                    <p><?php _e('Community support and discussions', 'searchjet'); ?></p>
                                </div>
                                <div class="searchjet-feature-card">
                                    <div class="searchjet-feature-icon">⬆️</div>
                                    <h5><a href="https://searchjetengine.com/pricing" target="_blank"><?php _e('Upgrade Plans', 'searchjet'); ?></a></h5>
                                    <p><?php _e('More features and higher limits', 'searchjet'); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const docsTabs = document.querySelectorAll('.searchjet-docs-tab');
            const docsSections = document.querySelectorAll('.searchjet-docs-section');
            
            docsTabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    const targetTab = this.getAttribute('data-tab');
                    
                    docsTabs.forEach(t => t.classList.remove('active'));
                    docsSections.forEach(s => s.classList.remove('active'));
                    
                    this.classList.add('active');
                    document.getElementById('docs-' + targetTab).classList.add('active');
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Simple markdown parser for basic formatting
     */
    private static function parseMarkdown($content) {
        // Basic markdown parsing - you might want to use a proper markdown parser
        $content = htmlspecialchars($content);
        
        // Headers
        $content = preg_replace('/^### (.+)$/m', '<h4>$1</h4>', $content);
        $content = preg_replace('/^## (.+)$/m', '<h3>$1</h3>', $content);
        $content = preg_replace('/^# (.+)$/m', '<h2>$1</h2>', $content);
        
        // Code blocks
        $content = preg_replace('/```([^`]+)```/s', '<div class="searchjet-code-block"><code>$1</code></div>', $content);
        $content = preg_replace('/`([^`]+)`/', '<code>$1</code>', $content);
        
        // Links
        $content = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2" target="_blank">$1</a>', $content);
        
        // Bold and italic
        $content = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $content);
        $content = preg_replace('/\*([^*]+)\*/', '<em>$1</em>', $content);
        
        // Line breaks
        $content = nl2br($content);
        
        return $content;
    }
}