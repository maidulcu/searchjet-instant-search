<?php
namespace SearchJet\Admin;

class SearchUIPage {

    const SETTINGS_GROUP = 'searchjet_search_ui';

    public static function init(): void {
        add_action('admin_enqueue_scripts', [self::class, 'enqueueAssets']);
        add_action('admin_init', [self::class, 'registerSettings']);
    }

    /**
     * Register the Search UI options under a dedicated option group.
     *
     * Each form must use its own settings group containing exactly the options
     * it posts. On modern WordPress, options.php calls update_option() with a
     * null value for every whitelisted option missing from the POST, so sharing
     * the core `searchjet_settings` group here would wipe unrelated settings
     * (including the API key).
     */
    public static function registerSettings(): void {
        $settings = [
            'searchjet_theme'              => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            'searchjet_primary_color'      => ['type' => 'string', 'sanitize_callback' => 'sanitize_hex_color'],
            'searchjet_max_results'        => ['type' => 'integer', 'sanitize_callback' => 'absint'],
            'searchjet_show_thumbnails'    => ['type' => 'boolean', 'sanitize_callback' => 'absint'],
            'searchjet_show_prices'        => ['type' => 'boolean', 'sanitize_callback' => 'absint'],
            'searchjet_show_categories'    => ['type' => 'boolean', 'sanitize_callback' => 'absint'],
            'searchjet_show_excerpts'      => ['type' => 'boolean', 'sanitize_callback' => 'absint'],
            'searchjet_show_rating'        => ['type' => 'boolean', 'sanitize_callback' => 'absint'],
            'searchjet_show_stock'         => ['type' => 'boolean', 'sanitize_callback' => 'absint'],
        ];

        foreach ($settings as $option => $args) {
            register_setting(self::SETTINGS_GROUP, $option, $args);
        }
    }

    public static function enqueueAssets($hook): void {
        if ($hook !== 'searchjet_page_searchjet-search-ui') return;

        wp_enqueue_style(
            'searchjet-admin-tailwind',
            SEARCHJET_PLUGIN_URL . 'assets/css/admin-tailwind.css',
            [],
            SEARCHJET_VERSION
        );

        wp_enqueue_style(
            'searchjet-admin-enhanced',
            SEARCHJET_PLUGIN_URL . 'assets/css/admin-style-enhanced.css',
            [],
            SEARCHJET_VERSION
        );
    }

    public static function render(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'searchjet-instant-search'));
        }

        $client_info = get_option('searchjet_client_info', []);
        if (!is_array($client_info)) $client_info = [];
        $plan = strtolower($client_info['plan'] ?? 'free');
        $is_free_plan = $plan === 'free';

        ?>
        <div class="searchjet-admin-wrap min-h-screen bg-gradient-to-br from-slate-50 to-slate-100">
            <div class="bg-gradient-to-r from-[#152A53] to-[#2563eb] text-white px-8 py-6 shadow-lg">
                <div class="max-w-6xl mx-auto">
                    <h1 class="text-3xl text-white mb-1"><?php esc_html_e('Search UI', 'searchjet-instant-search'); ?></h1>
                    <p class="text-blue-100 text-xl"><?php esc_html_e('Customize how search looks and behaves for your visitors', 'searchjet-instant-search'); ?></p>
                </div>
            </div>

            <div class="searchjet-content max-w-6xl mx-auto">
                <div class="searchjet-card">
                    <h2><?php esc_html_e('Try SearchJet Demo', 'searchjet-instant-search'); ?></h2>
                    <p><?php esc_html_e('Experience how SearchJet works with this interactive demo:', 'searchjet-instant-search'); ?></p>
                    <div id="searchjet-demo-container" class="searchjet-demo-container">
                        <div class="searchjet-demo-search">
                            <input type="text" class="searchjet-demo-input" placeholder="<?php esc_attr_e('Try typing "sample" or "product"...', 'searchjet-instant-search'); ?>">
                        </div>
                        <div class="searchjet-demo-results" style="display: none;"></div>
                    </div>
                </div>

                <form method="post" action="options.php" autocomplete="off">
                    <?php settings_fields(self::SETTINGS_GROUP); ?>

                    <div class="searchjet-flex-row">
                        <div class="searchjet-card">
                            <h2><?php esc_html_e('Search UI Customization', 'searchjet-instant-search'); ?></h2>

                            <div class="searchjet-form-group">
                                <label for="searchjet_theme"><?php esc_html_e('Theme Style', 'searchjet-instant-search'); ?></label>
                                <select name="searchjet_theme" id="searchjet_theme" class="searchjet-select">
                                    <option value="default" <?php selected(get_option('searchjet_theme', 'default'), 'default'); ?>><?php esc_html_e('Default', 'searchjet-instant-search'); ?></option>
                                    <option value="dark" <?php selected(get_option('searchjet_theme', 'default'), 'dark'); ?>><?php esc_html_e('Dark Mode', 'searchjet-instant-search'); ?></option>
                                    <option value="minimal" <?php selected(get_option('searchjet_theme', 'default'), 'minimal'); ?>><?php esc_html_e('Minimal', 'searchjet-instant-search'); ?></option>
                                    <option value="rounded" <?php selected(get_option('searchjet_theme', 'default'), 'rounded'); ?>><?php esc_html_e('Rounded', 'searchjet-instant-search'); ?></option>
                                </select>
                                <div class="description"><?php esc_html_e('Choose a pre-built theme style for the search interface.', 'searchjet-instant-search'); ?></div>
                            </div>

                            <div class="searchjet-form-group">
                                <label for="searchjet_primary_color"><?php esc_html_e('Text Color', 'searchjet-instant-search'); ?></label>
                                <input type="color"
                                       id="searchjet_primary_color"
                                       name="searchjet_primary_color"
                                       value="<?php echo esc_attr(get_option('searchjet_primary_color', '#000000')); ?>"
                                       class="searchjet-color-input">
                                <div class="description"><?php esc_html_e('Text color for search results.', 'searchjet-instant-search'); ?></div>
                            </div>

                            <div class="searchjet-form-group">
                                <label for="searchjet_max_results"><?php esc_html_e('Max Results', 'searchjet-instant-search'); ?></label>
                                <input type="number"
                                       id="searchjet_max_results"
                                       name="searchjet_max_results"
                                       value="<?php echo esc_attr(get_option('searchjet_max_results', 10)); ?>"
                                       min="3"
                                       max="20"
                                       class="searchjet-input">
                                <div class="description"><?php esc_html_e('Maximum number of results to show in dropdown.', 'searchjet-instant-search'); ?></div>
                            </div>

                            <div class="searchjet-form-group">
                                <label><?php esc_html_e('Display Options', 'searchjet-instant-search'); ?></label>
                                <div class="searchjet-checkbox-group">
                                    <label class="searchjet-checkbox-option">
                                        <input type="checkbox" name="searchjet_show_thumbnails" value="1" <?php checked(get_option('searchjet_show_thumbnails', 1)); ?>>
                                        <span><?php esc_html_e('Show Product Thumbnails', 'searchjet-instant-search'); ?></span>
                                    </label>
                                    <label class="searchjet-checkbox-option">
                                        <input type="checkbox" name="searchjet_show_prices" value="1" <?php checked(get_option('searchjet_show_prices', 1)); ?>>
                                        <span><?php esc_html_e('Show Product Prices', 'searchjet-instant-search'); ?></span>
                                    </label>
                                    <label class="searchjet-checkbox-option">
                                        <input type="checkbox" name="searchjet_show_categories" value="1" <?php checked(get_option('searchjet_show_categories', 0)); ?> <?php disabled($is_free_plan, true); ?>>
                                        <span><?php esc_html_e('Show Product Categories', 'searchjet-instant-search'); ?><?php echo $is_free_plan ? ' <a href="https://searchjetengine.com/pricing/" target="_blank" rel="noopener noreferrer" style="color:#dc2626; font-weight:700; text-decoration:underline;">(Pro)</a>' : ''; ?></span>
                                    </label>
                                    <label class="searchjet-checkbox-option">
                                        <input type="checkbox" name="searchjet_show_excerpts" value="1" <?php checked(get_option('searchjet_show_excerpts', 0)); ?> <?php disabled($is_free_plan, true); ?>>
                                        <span><?php esc_html_e('Show Content Excerpts', 'searchjet-instant-search'); ?><?php echo $is_free_plan ? ' <a href="https://searchjetengine.com/pricing/" target="_blank" rel="noopener noreferrer" style="color:#dc2626; font-weight:700; text-decoration:underline;">(Pro)</a>' : ''; ?></span>
                                    </label>
                                    <label class="searchjet-checkbox-option">
                                        <input type="checkbox" name="searchjet_show_rating" value="1" <?php checked(get_option('searchjet_show_rating', 0)); ?> <?php disabled($is_free_plan, true); ?>>
                                        <span><?php esc_html_e('Show Product Ratings', 'searchjet-instant-search'); ?><?php echo $is_free_plan ? ' <a href="https://searchjetengine.com/pricing/" target="_blank" rel="noopener noreferrer" style="color:#dc2626; font-weight:700; text-decoration:underline;">(Pro)</a>' : ''; ?></span>
                                    </label>
                                    <label class="searchjet-checkbox-option">
                                        <input type="checkbox" name="searchjet_show_stock" value="1" <?php checked(get_option('searchjet_show_stock', 0)); ?> <?php disabled($is_free_plan, true); ?>>
                                        <span><?php esc_html_e('Show Stock Status', 'searchjet-instant-search'); ?><?php echo $is_free_plan ? ' <a href="https://searchjetengine.com/pricing/" target="_blank" rel="noopener noreferrer" style="color:#dc2626; font-weight:700; text-decoration:underline;">(Pro)</a>' : ''; ?></span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="searchjet-card">
                            <h2><?php esc_html_e('Live Preview', 'searchjet-instant-search'); ?></h2>
                            <div class="searchjet-preview-container">
                                <div class="searchjet-preview-search">
                                    <input type="text" placeholder="<?php esc_attr_e('Search products...', 'searchjet-instant-search'); ?>" class="searchjet-preview-input" readonly>
                                    <span class="searchjet-preview-search-icon">
                                        <img src="<?php echo esc_url(SEARCHJET_PLUGIN_URL . 'assets/img/searchui-search-gray.svg'); ?>" alt="" width="18" height="18" style="display:block;">
                                    </span>
                                </div>
                                <div id="searchjet-preview-results" class="searchjet-preview-results" style="--searchjet-primary-color: <?php echo esc_attr(get_option('searchjet_primary_color', '#000000')); ?>">
                                    <h3 class="searchjet-group-title"><?php esc_html_e('Products', 'searchjet-instant-search'); ?> (3)</h3>
                                    <a href="#" class="searchjet-hit product-hit" onclick="return false;">
                                        <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='48' height='48' fill='%23ddd'%3E%3Crect width='48' height='48'/%3E%3C/svg%3E" alt="Product" class="product-image" style="display: <?php echo get_option('searchjet_show_thumbnails', 1) ? 'block' : 'none'; ?>">
                                        <div class="hit-details">
                                            <div class="hit-title"><?php esc_html_e('Wireless Headphones', 'searchjet-instant-search'); ?></div>
                                            <div class="hit-description" style="display: <?php echo get_option('searchjet_show_excerpts', 0) ? 'block' : 'none'; ?>"><?php esc_html_e('Premium noise-cancelling wireless headphones', 'searchjet-instant-search'); ?></div>
                                            <div class="hit-meta" style="display: <?php echo get_option('searchjet_show_categories', 0) ? 'block' : 'none'; ?>"><?php esc_html_e('Electronics', 'searchjet-instant-search'); ?></div>
                                            <div class="hit-rating" style="display: <?php echo get_option('searchjet_show_rating', 0) ? 'block' : 'none'; ?>">&#9733;&#9733;&#9733;&#9733;&#9734; (4.2)</div>
                                            <div class="hit-stock" style="display: <?php echo get_option('searchjet_show_stock', 0) ? 'block' : 'none'; ?>"><?php esc_html_e('In Stock', 'searchjet-instant-search'); ?></div>
                                        </div>
                                        <div class="hit-price" style="display: <?php echo get_option('searchjet_show_prices', 1) ? 'block' : 'none'; ?>">$79.99</div>
                                    </a>
                                    <a href="#" class="searchjet-hit product-hit" onclick="return false;">
                                        <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='48' height='48' fill='%23ddd'%3E%3Crect width='48' height='48'/%3E%3C/svg%3E" alt="Product" class="product-image" style="display: <?php echo get_option('searchjet_show_thumbnails', 1) ? 'block' : 'none'; ?>">
                                        <div class="hit-details">
                                            <div class="hit-title"><?php esc_html_e('Running Shoes', 'searchjet-instant-search'); ?></div>
                                            <div class="hit-description" style="display: <?php echo get_option('searchjet_show_excerpts', 1) ? 'block' : 'none'; ?>"><?php esc_html_e('Lightweight running shoes with cushioned sole', 'searchjet-instant-search'); ?></div>
                                            <div class="hit-meta" style="display: <?php echo get_option('searchjet_show_categories', 1) ? 'block' : 'none'; ?>"><?php esc_html_e('Sports', 'searchjet-instant-search'); ?></div>
                                            <div class="hit-rating" style="display: <?php echo get_option('searchjet_show_rating', 1) ? 'block' : 'none'; ?>">&#9733;&#9733;&#9733;&#9733;&#9733; (4.8)</div>
                                            <div class="hit-stock" style="display: <?php echo get_option('searchjet_show_stock', 1) ? 'block' : 'none'; ?>"><?php esc_html_e('In Stock', 'searchjet-instant-search'); ?></div>
                                        </div>
                                        <div class="hit-price" style="display: <?php echo get_option('searchjet_show_prices', 1) ? 'block' : 'none'; ?>">$129.99</div>
                                    </a>
                                    <a href="#" class="searchjet-hit product-hit" onclick="return false;">
                                        <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='48' height='48' fill='%23ddd'%3E%3Crect width='48' height='48'/%3E%3C/svg%3E" alt="Product" class="product-image" style="display: <?php echo get_option('searchjet_show_thumbnails', 1) ? 'block' : 'none'; ?>">
                                        <div class="hit-details">
                                            <div class="hit-title"><?php esc_html_e('Coffee Maker', 'searchjet-instant-search'); ?></div>
                                            <div class="hit-description" style="display: <?php echo get_option('searchjet_show_excerpts', 1) ? 'block' : 'none'; ?>"><?php esc_html_e('Programmable 12-cup coffee maker', 'searchjet-instant-search'); ?></div>
                                            <div class="hit-meta" style="display: <?php echo get_option('searchjet_show_categories', 1) ? 'block' : 'none'; ?>"><?php esc_html_e('Home & Kitchen', 'searchjet-instant-search'); ?></div>
                                            <div class="hit-rating" style="display: <?php echo get_option('searchjet_show_rating', 1) ? 'block' : 'none'; ?>">&#9733;&#9733;&#9733;&#9734;&#9734; (3.5)</div>
                                            <div class="hit-stock" style="display: <?php echo get_option('searchjet_show_stock', 1) ? 'block' : 'none'; ?>"><?php esc_html_e('In Stock', 'searchjet-instant-search'); ?></div>
                                        </div>
                                        <div class="hit-price" style="display: <?php echo get_option('searchjet_show_prices', 1) ? 'block' : 'none'; ?>">$49.99</div>
                                    </a>
                                    <h3 class="searchjet-group-title"><?php esc_html_e('Pages', 'searchjet-instant-search'); ?> (2)</h3>
                                    <a href="#" class="searchjet-hit page-hit" onclick="return false;">
                                        <div class="page-icon">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6z"/></svg>
                                        </div>
                                        <div class="hit-details">
                                            <div class="hit-title"><?php esc_html_e('About Us', 'searchjet-instant-search'); ?></div>
                                            <div class="hit-description" style="display: <?php echo get_option('searchjet_show_excerpts', 1) ? 'block' : 'none'; ?>"><?php esc_html_e('Learn more about our company history and team', 'searchjet-instant-search'); ?></div>
                                        </div>
                                    </a>
                                    <a href="#" class="searchjet-hit page-hit" onclick="return false;">
                                        <div class="page-icon">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6z"/></svg>
                                        </div>
                                        <div class="hit-details">
                                            <div class="hit-title"><?php esc_html_e('Contact Us', 'searchjet-instant-search'); ?></div>
                                            <div class="hit-description" style="display: <?php echo get_option('searchjet_show_excerpts', 1) ? 'block' : 'none'; ?>"><?php esc_html_e('Get in touch with our support team', 'searchjet-instant-search'); ?></div>
                                        </div>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div style="margin-top: 32px;">
                        <button type="submit" class="searchjet-btn searchjet-btn-primary searchjet-btn-lg">
                            <?php esc_html_e('Save Settings', 'searchjet-instant-search'); ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <script>
        (function() {
            var demoInput = document.querySelector('.searchjet-demo-input');
            var demoResults = document.querySelector('.searchjet-demo-results');
            if (!demoInput || !demoResults) return;

            var imgUrl = '<?php echo esc_js(SEARCHJET_PLUGIN_URL . 'assets/img/'); ?>';
            var demoItems = [
                { icon: 'dash-product-headphones', title: '<?php echo esc_js(__('Wireless Headphones', 'searchjet-instant-search')); ?>', category: 'Electronics', price: '$79.99' },
                { icon: 'dash-product-music', title: '<?php echo esc_js(__('Wireless Earbuds Mini', 'searchjet-instant-search')); ?>', category: 'Electronics', price: '$49.99' },
                { icon: 'dash-product-shoe', title: '<?php echo esc_js(__('Running Shoes Ultra', 'searchjet-instant-search')); ?>', category: 'Sports', price: '$129.99' },
                { icon: 'dash-product-runner', title: '<?php echo esc_js(__('Marathon Pro Trainer', 'searchjet-instant-search')); ?>', category: 'Sports', price: '$99.99' },
                { icon: 'dash-product-coffee', title: '<?php echo esc_js(__('Ceramic Coffee Mug Set', 'searchjet-instant-search')); ?>', category: 'Home & Kitchen', price: '$24.99' },
                { icon: 'dash-product-teapot', title: '<?php echo esc_js(__('Coffee Maker', 'searchjet-instant-search')); ?>', category: 'Home & Kitchen', price: '$49.99' }
            ];

            function showNoResults() {
                demoResults.innerHTML = '<div class="searchjet-demo-no-results"><?php echo esc_js(__('No results found', 'searchjet-instant-search')); ?></div>';
            }

            function showAll() {
                demoResults.innerHTML = demoItems.slice(0, 3).map(function(it) {
                    return '<div class="searchjet-demo-result">' +
                        '<div class="searchjet-demo-icon"><img src="' + imgUrl + it.icon + '.svg" alt="" width="24" height="24" style="display:block;"></div>' +
                        '<div class="searchjet-demo-details">' +
                        '<div class="searchjet-demo-title">' + it.title + '</div>' +
                        '<div class="searchjet-demo-excerpt">' + it.category + '</div>' +
                        '</div>' +
                        '<div class="searchjet-demo-price">' + it.price + '</div>' +
                        '</div>';
                }).join('');
            }

            demoInput.addEventListener('input', function() {
                var val = this.value.toLowerCase().trim();
                if (val.length < 2) {
                    demoResults.style.display = val === '' ? 'none' : 'block';
                    demoResults.innerHTML = '';
                    return;
                }
                demoResults.style.display = 'block';
                var matches = demoItems.filter(function(it) {
                    return it.title.toLowerCase().indexOf(val) > -1 || it.category.toLowerCase().indexOf(val) > -1;
                });
                if (matches.length === 0) { showNoResults(); return; }
                demoResults.innerHTML = matches.slice(0, 3).map(function(it) {
                    return '<div class="searchjet-demo-result">' +
                        '<div class="searchjet-demo-icon"><img src="' + imgUrl + it.icon + '.svg" alt="" width="24" height="24" style="display:block;"></div>' +
                        '<div class="searchjet-demo-details">' +
                        '<div class="searchjet-demo-title">' + it.title + '</div>' +
                        '<div class="searchjet-demo-excerpt">' + it.category + '</div>' +
                        '</div>' +
                        '<div class="searchjet-demo-price">' + it.price + '</div>' +
                        '</div>';
                }).join('');
            });

            showAll();
            demoResults.style.display = 'block';
        })();
        </script>
        <?php
    }
}
