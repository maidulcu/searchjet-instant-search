<?php
namespace SearchJet\Shortcodes;

class FederatedSearch {

    public static function init() {
        add_shortcode('searchjet_federated', [self::class, 'render']);
        add_action('wp_enqueue_scripts', [self::class, 'enqueueAssets']);
    }

    public static function enqueueAssets() {
        // Only enqueue if the shortcode is present or we want to force it?
        // Ideally we check if post has shortcode, but for simplicity/demo we can just register it 
        // and let wp_enqueue_scripts handle it if called, or just always enqueue if lightweight.
        // Better: enqueue in the render method if it's not too late, or just register here.
        
        // Actually, render() happens during content processing, which might be after wp_enqueue_scripts.
        // So we strictly register here, and maybe enqueue here if we are on a page that needs it, 
        // or rely on a global enqueue if the user wants it everywhere.
        // For this demo, let's enqueue it globally or check typical conditions. 
        // But since we can't easily know if shortcode is used involved without parsing content,
        // we will register it and maybe enqueue it always for this version/demo or assume the user manages it.
        // Let's just enqueue it globally for now as it's a plugin feature.
        
        $assetVersion = (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG)
            ? time()
            : (defined('SEARCHJET_VERSION') ? SEARCHJET_VERSION : '1.0.0');

        wp_register_script(
            'searchjet-federated',
            plugin_dir_url(dirname(__DIR__)) . 'assets/js/searchjet-federated.js',
            ['searchjet-connect', 'searchjet-helpers'],
            $assetVersion,
            true
        );
    }

    public static function render($atts = []) {
        wp_enqueue_script('searchjet-federated');
        // Ensure styles are enqueued
        wp_enqueue_style('searchjet-style'); 

        $atts = shortcode_atts([
            'placeholder' => 'Search "hoodie", "red", "accessories", "sale"',
            'class' => ''
        ], $atts);
        
        ob_start();
        ?>
        <div class="searchjet-federated-container <?php echo esc_attr($atts['class']); ?>">
            <div class="searchjet-federated-header">
                <h2>Federated Search Demo</h2>
                <p>Try searching: "hoodie", "red", "accessories", "sale"</p>
                <div class="searchjet-federated-input-wrapper">
                    <svg class="search-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    <input 
                        type="search" 
                        class="searchjet-federated-input" 
                        placeholder="<?php echo esc_attr($atts['placeholder']); ?>" 
                        autocomplete="off"
                    />
                </div>
            </div>

            <div id="searchjet-federated-results" class="searchjet-federated-results" style="display: none;">
                <div class="searchjet-federated-main">
                    <h3 class="searchjet-section-title">Products <span class="count" id="searchjet-product-count"></span></h3>
                    <div id="searchjet-federated-products-grid" class="searchjet-grid">
                        <!-- Products will be injected here -->
                    </div>
                </div>
                
                <div class="searchjet-federated-sidebar">
                    <div class="searchjet-sidebar-section">
                        <h3 class="searchjet-section-title">
                            <span class="icon">✨</span> Suggestions
                        </h3>
                        <div id="searchjet-federated-suggestions" class="searchjet-list">
                            <!-- Suggestions will be injected here -->
                        </div>
                    </div>

                    <div class="searchjet-sidebar-section">
                        <h3 class="searchjet-section-title">Pages & Articles</h3>
                        <div id="searchjet-federated-pages" class="searchjet-list">
                            <!-- Pages will be injected here -->
                        </div>
                    </div>
                </div>
            </div>
            
            <div id="searchjet-federated-empty" class="searchjet-federated-empty" style="display: none;">
                No results found.
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
