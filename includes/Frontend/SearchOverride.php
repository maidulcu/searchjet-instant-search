<?php
namespace SearchJet\Frontend;

class SearchOverride {
    public static function init() {
        $disabled = get_option('searchjet_disable_search_mode', 0);
        if ($disabled) return;
        
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueueSearchAssets']);
        add_action('wp_head', [__CLASS__, 'hideDefaultSearch']);

    }

    public static function enqueueSearchAssets() {
        $api_key = get_option('searchjet_hosted_api_key', '');
        if (empty($api_key)) return;

        // Enqueue page search script for search results pages
        if (is_search()) {
            wp_enqueue_script(
                'searchjet-fullsearch',
                SEARCHJET_PLUGIN_URL . 'assets/js/searchjet-fullsearch.js',
                ['searchjet-connect','searchjet-helpers'], // Depend on the script that has SearchJetSettings
                SEARCHJET_VERSION,
                true
            );
        }
        
        // Settings are handled by Plugin.php to avoid conflicts
    }

    public static function hideDefaultSearch() {
        if (!is_search()) return;

        // Not connected: leave the theme's own search results alone.
        if (empty(get_option('searchjet_hosted_api_key', ''))) return;

        // The theme's results are hidden only while SearchJet is in charge. If the script cannot
        // show results it adds the class "searchjet-native" to <body> and the theme's results return.
        ?>
        <style>
        /* WordPress Core & Generic */
        body.search:not(.searchjet-native) .site-main > *:not(#searchjet-page-results):not(#searchjet-wrapper):not(#searchjet-search-results),
        body.search:not(.searchjet-native) .content-area .site-main > *:not(#searchjet-page-results):not(#searchjet-wrapper):not(#searchjet-search-results),
        body.search:not(.searchjet-native) main > *:not(#searchjet-page-results):not(#searchjet-wrapper):not(#searchjet-search-results),
        body.search:not(.searchjet-native) .wp-block-query:not(#searchjet-page-results):not(#searchjet-wrapper):not(#searchjet-search-results),
        body.search:not(.searchjet-native) .wp-block-post-template:not(#searchjet-page-results):not(#searchjet-wrapper):not(#searchjet-search-results),
        body.search:not(.searchjet-native) .search-results:not(#searchjet-page-results):not(#searchjet-wrapper):not(#searchjet-search-results),
        body.search:not(.searchjet-native) .search-no-results:not(#searchjet-page-results):not(#searchjet-wrapper):not(#searchjet-search-results),
        
        /* Elementor */
        body.search:not(.searchjet-native) .elementor-widget-posts:not(#searchjet-page-results):not(#searchjet-wrapper):not(#searchjet-search-results),
        body.search:not(.searchjet-native) .elementor-posts:not(#searchjet-page-results):not(#searchjet-wrapper):not(#searchjet-search-results),
        body.search:not(.searchjet-native) .elementor-loop-container:not(#searchjet-page-results):not(#searchjet-wrapper):not(#searchjet-search-results),
        
        /* Astra */
        body.search:not(.searchjet-native) .ast-search-result:not(#searchjet-page-results):not(#searchjet-wrapper):not(#searchjet-search-results),
        body.search:not(.searchjet-native) .ast-archive-description:not(#searchjet-page-results):not(#searchjet-wrapper):not(#searchjet-search-results),
        body.search:not(.searchjet-native) .ast-row:not(#searchjet-page-results):not(#searchjet-wrapper):not(#searchjet-search-results),
        
        /* OceanWP */
        body.search:not(.searchjet-native) .oceanwp-row:not(#searchjet-page-results):not(#searchjet-wrapper):not(#searchjet-search-results),
        body.search:not(.searchjet-native) .blog-entry:not(#searchjet-page-results):not(#searchjet-wrapper):not(#searchjet-search-results),
        body.search:not(.searchjet-native) .search-entry:not(#searchjet-page-results):not(#searchjet-wrapper):not(#searchjet-search-results),
        body.search:not(.searchjet-native) .ast-pagination,
        
        /* GeneratePress */
        body.search:not(.searchjet-native) .inside-article:not(#searchjet-page-results):not(#searchjet-wrapper):not(#searchjet-search-results),
        body.search:not(.searchjet-native) .generate-columns-container:not(#searchjet-page-results):not(#searchjet-wrapper):not(#searchjet-search-results),
        
        /* Kadence */
        body.search:not(.searchjet-native) .content-bg:not(#searchjet-page-results):not(#searchjet-wrapper):not(#searchjet-search-results),
        body.search:not(.searchjet-native) .loop-entry:not(#searchjet-page-results):not(#searchjet-wrapper):not(#searchjet-search-results),
        
        /* Neve */
        body.search:not(.searchjet-native) .nv-index-posts:not(#searchjet-page-results):not(#searchjet-wrapper):not(#searchjet-search-results),
        body.search:not(.searchjet-native) .posts-wrapper:not(#searchjet-page-results):not(#searchjet-wrapper):not(#searchjet-search-results),
        
        /* Storefront */
        body.search:not(.searchjet-native) .storefront-sorting:not(#searchjet-page-results):not(#searchjet-wrapper):not(#searchjet-search-results),
        body.search:not(.searchjet-native) .site-content article:not(#searchjet-page-results):not(#searchjet-wrapper):not(#searchjet-search-results),
        
        /* Divi */
        body.search:not(.searchjet-native) .et_pb_posts:not(#searchjet-page-results):not(#searchjet-wrapper):not(#searchjet-search-results),
        body.search:not(.searchjet-native) .et_pb_blog_grid:not(#searchjet-page-results):not(#searchjet-wrapper):not(#searchjet-search-results),
        body.search:not(.searchjet-native) #main-content .container:not(#searchjet-page-results):not(#searchjet-wrapper):not(#searchjet-search-results),
        
        /* Avada */
        body.search:not(.searchjet-native) .fusion-blog-layout:not(#searchjet-page-results):not(#searchjet-wrapper):not(#searchjet-search-results),
        body.search:not(.searchjet-native) .fusion-posts-container:not(#searchjet-page-results):not(#searchjet-wrapper):not(#searchjet-search-results),
        
        /* Twenty Twenty-Three & Block Themes */
        body.search:not(.searchjet-native) .wp-block-query-loop:not(#searchjet-page-results):not(#searchjet-wrapper):not(#searchjet-search-results),
        body.search:not(.searchjet-native) .wp-block-post-excerpt:not(#searchjet-page-results):not(#searchjet-wrapper):not(#searchjet-search-results),
        
        /* WooCommerce */
        body.search:not(.searchjet-native) .woocommerce-result-count:not(#searchjet-page-results):not(#searchjet-wrapper):not(#searchjet-search-results),
        body.search:not(.searchjet-native) .woocommerce-ordering:not(#searchjet-page-results):not(#searchjet-wrapper):not(#searchjet-search-results),
        body.search:not(.searchjet-native) .products:not(#searchjet-page-results):not(#searchjet-wrapper):not(#searchjet-search-results) {
            display: none !important;
        }
        
        /* Ensure SearchJet containers are visible */
        #searchjet-page-results,
        #searchjet-wrapper,
        #searchjet-search-results {
            display: block !important;
        }
        </style>
        <?php
        // Safety net: if the SearchJet script never runs (blocked, deferred away, 404), show the
        // theme's results after a short wait.
        wp_print_inline_script_tag(
            "window.addEventListener('load',function(){setTimeout(function(){if(!window.SearchJetFullSearchLoaded&&document.body){document.body.classList.add('searchjet-native');}},2500);});"
        );
    }
}