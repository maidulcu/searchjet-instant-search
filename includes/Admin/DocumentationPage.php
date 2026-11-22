<?php
namespace SearchJet\Admin;

class DocumentationPage {
    
    public static function init() {
        add_action('admin_enqueue_scripts', [self::class, 'enqueueAssets']);
    }

    public static function enqueueAssets($hook) {
        if ($hook !== 'searchjet_page_searchjet-docs') return;
        wp_enqueue_style(
            'searchjet-admin-tailwind',
            SEARCHJET_PLUGIN_URL . 'assets/css/admin-tailwind.css',
            [],
            SEARCHJET_VERSION
        );

        wp_enqueue_style(
            'searchjet-docs',
            SEARCHJET_PLUGIN_URL . 'assets/css/admin-style-enhanced.css',
            [],
            SEARCHJET_VERSION
        );

        wp_enqueue_script(
            'searchjet-docs-js',
            SEARCHJET_PLUGIN_URL . 'assets/js/admin-enhanced.js',
            ['jquery'],
            SEARCHJET_VERSION,
            true
        );
    }

    public static function render() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'searchjet-instant-search'));
        }

        $plugin_version = defined('SEARCHJET_VERSION') ? SEARCHJET_VERSION : '1.7.1';
        ?>
        <div class="searchjet-admin-wrap min-h-screen bg-gradient-to-br from-slate-50 to-slate-100">
            <div class="bg-gradient-to-r from-[#152A53] to-[#2563eb] text-white px-8 py-6 shadow-lg">
                <div class="max-w-6xl mx-auto flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl text-white mb-1"><?php esc_html_e('Documentation', 'searchjet-instant-search'); ?></h1>
                        <p class="text-blue-100 text-xl"><?php esc_html_e('Everything you need to set up, configure, and optimize SearchJet for your WordPress site.', 'searchjet-instant-search'); ?></p>
                    </div>
                    <div class="flex items-center gap-2 bg-white px-4 py-2 rounded-lg shadow-lg">
                        <span class="w-2 h-2 bg-green-500 rounded-full"></span>
                        <strong class="text-sm text-[#152A53]"><?php esc_html_e('Help', 'searchjet-instant-search'); ?></strong>
                    </div>
                </div>
            </div>

            <div class="searchjet-content max-w-6xl mx-auto">

                <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:24px;">
                    <a href="https://searchjetengine.com/docs/" target="_blank" rel="noopener noreferrer" class="searchjet-btn searchjet-btn-secondary" style="justify-content:center;">
                        <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/docs-book.svg'); ?>" alt="" width="14" height="14" style="display:block;">
                        <?php esc_html_e('Online Docs', 'searchjet-instant-search'); ?>
                    </a>
                    <a href="https://searchjetengine.com/contact" target="_blank" rel="noopener noreferrer" class="searchjet-btn searchjet-btn-secondary" style="justify-content:center;">
                        <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/docs-phone.svg'); ?>" alt="" width="14" height="14" style="display:block;">
                        <?php esc_html_e('Contact Support', 'searchjet-instant-search'); ?>
                    </a>
                    <a href="https://searchjetengine.com/pricing" target="_blank" rel="noopener noreferrer" class="searchjet-btn searchjet-btn-secondary" style="justify-content:center;">
                        <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/docs-mic.svg'); ?>" alt="" width="14" height="14" style="display:block;">
                        <?php esc_html_e('Pricing', 'searchjet-instant-search'); ?>
                    </a>
                </div>

                <div class="searchjet-docs-hub">
                <!-- Hero -->
                <div class="searchjet-docs-hero">
                    <h1><?php esc_html_e('SearchJet Documentation', 'searchjet-instant-search'); ?></h1>
                    <p><?php esc_html_e('Everything you need to set up, configure, and optimize SearchJet for your WordPress site.', 'searchjet-instant-search'); ?></p>
                    <div class="searchjet-docs-hero-links">
                        <a href="https://searchjetengine.com/docs/guides/get-started/quick-start-guide/" target="_blank" rel="noopener noreferrer" class="searchjet-docs-hero-btn primary">
                            <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/docs-file.svg'); ?>" alt="" width="16" height="16" style="display:block;">
                            <?php esc_html_e('Quick Start Guide', 'searchjet-instant-search'); ?>
                        </a>
                        <a href="https://searchjetengine.com/docs" target="_blank" rel="noopener noreferrer" class="searchjet-docs-hero-btn secondary">
                            <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/docs-globe.svg'); ?>" alt="" width="16" height="16" style="display:block;">
                            <?php esc_html_e('Online Documentation', 'searchjet-instant-search'); ?>
                        </a>
                    </div>
                </div>

                <!-- Quick Links Cards -->
                <div class="searchjet-docs-cards">
                    <a href="https://searchjetengine.com/docs/guides/get-started/quick-start-guide/" target="_blank" rel="noopener noreferrer" class="searchjet-docs-card">
                        <div class="searchjet-docs-card-icon">
                            <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/docs-rocket.svg'); ?>" alt="" width="24" height="24" style="display:block;">
                        </div>
                        <h3><?php esc_html_e('Quick Start', 'searchjet-instant-search'); ?></h3>
                        <p><?php esc_html_e('Get up and running in minutes. Install, connect, and start searching.', 'searchjet-instant-search'); ?></p>
                    </a>
                </div>

                <!-- Sentient AI Cross-Promotion -->
                <div class="searchjet-docs-promo">
                    <div class="searchjet-docs-promo-inner">
                        <div class="searchjet-docs-promo-content">
                            <span class="searchjet-docs-promo-badge"><?php esc_html_e('Recommended', 'searchjet-instant-search'); ?></span>
                            <h3><?php esc_html_e('Add AI Chat to Your Site', 'searchjet-instant-search'); ?></h3>
                            <p>
                                <?php esc_html_e('Looking for an AI chatbot?', 'searchjet-instant-search'); ?> <strong><?php esc_html_e('Sentient AI', 'searchjet-instant-search'); ?></strong> <?php esc_html_e('combines AI-powered conversations with semantic search, WooCommerce support, and WordPress Autopilot — all from your WordPress dashboard.', 'searchjet-instant-search'); ?>
                            </p>
                            <ul>
                                <li><?php esc_html_e('Multi-provider AI (OpenAI, Claude, Gemini)', 'searchjet-instant-search'); ?></li>
                                <li><?php esc_html_e('Semantic vector search with Local Brain', 'searchjet-instant-search'); ?></li>
                                <li><?php esc_html_e('Browser-based FAQ matching (zero API cost)', 'searchjet-instant-search'); ?></li>
                                <li><?php esc_html_e('WooCommerce product lookup & order status', 'searchjet-instant-search'); ?></li>
                            </ul>
                            <a href="https://wordpress.org/plugins/sentient-ai/" target="_blank" rel="noopener noreferrer" class="searchjet-btn searchjet-btn-primary" style="background:#16a34a;border-color:#16a34a;">
                                <?php esc_html_e('Get Sentient AI Free', 'searchjet-instant-search'); ?>
                                <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/arrow-right.svg'); ?>" alt="" width="14" height="14" style="display:block;">
                            </a>
                        </div>
                        <div class="searchjet-docs-promo-icon">
                            <div class="searchjet-docs-promo-logo">
                                <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/docs-brain.svg'); ?>" alt="" width="40" height="40" style="display:block;">
                            </div>
                            <div class="searchjet-docs-promo-caption"><?php esc_html_e('Sentient AI', 'searchjet-instant-search'); ?></div>
                        </div>
                    </div>
                </div>

                <!-- Version Info -->
                <div class="searchjet-docs-version">
                    <div class="searchjet-docs-version-head">
                        <div class="searchjet-docs-version-title">
                            <div class="searchjet-docs-version-icon">
                                <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/plugin-info-icon.svg'); ?>" alt="<?php esc_attr_e('Plugin Information', 'searchjet-instant-search'); ?>" width="24" height="24" style="display:block;">
                            </div>
                            <div>
                                <h3><?php esc_html_e('Plugin Information', 'searchjet-instant-search'); ?></h3>
                                <div class="searchjet-docs-version-badge">v<?php echo esc_html($plugin_version); ?></div>
                            </div>
                        </div>
                        <a href="https://searchjetengine.com/pricing" target="_blank" rel="noopener noreferrer" class="searchjet-btn searchjet-btn-primary">
                            <?php esc_html_e('View Plans & Pricing', 'searchjet-instant-search'); ?>
                            <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/arrow-right.svg'); ?>" alt="" width="14" height="14" style="display:block;">
                        </a>
                    </div>
                    <div class="searchjet-docs-version-changelog">
                        <strong><?php esc_html_e("What's New:", 'searchjet-instant-search'); ?></strong>
                        <ul>
                            <li>
                                <span class="searchjet-docs-version-check">
                                    <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/docs-check.svg'); ?>" alt="" width="12" height="12" style="display:block;">
                                </span>
                                <?php esc_html_e('AI-powered typo correction and semantic search', 'searchjet-instant-search'); ?>
                            </li>
                            <li>
                                <span class="searchjet-docs-version-check">
                                    <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/docs-check.svg'); ?>" alt="" width="12" height="12" style="display:block;">
                                </span>
                                <?php esc_html_e('Real-time indexing with instant updates', 'searchjet-instant-search'); ?>
                            </li>
                            <li>
                                <span class="searchjet-docs-version-check">
                                    <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/docs-check.svg'); ?>" alt="" width="12" height="12" style="display:block;">
                                </span>
                                <?php esc_html_e('WooCommerce product search with rich results', 'searchjet-instant-search'); ?>
                            </li>
                            <li>
                                <span class="searchjet-docs-version-check">
                                    <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/docs-check.svg'); ?>" alt="" width="12" height="12" style="display:block;">
                                </span>
                                <?php esc_html_e('Customizable search UI with theme integration', 'searchjet-instant-search'); ?>
                            </li>
                            <li>
                                <span class="searchjet-docs-version-check">
                                    <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/docs-check.svg'); ?>" alt="" width="12" height="12" style="display:block;">
                                </span>
                                <?php esc_html_e('Advanced analytics dashboard', 'searchjet-instant-search'); ?>
                            </li>
                        </ul>
                    </div>
                </div>
                </div>
            </div>
        </div>
        <?php
    }
}