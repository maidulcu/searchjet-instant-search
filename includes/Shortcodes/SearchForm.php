<?php
namespace SearchJet\Shortcodes;

if (!defined('ABSPATH')) {
    exit;
}

class SearchForm {
    private static function getHostUrl() {
        $hostUrl = get_option('searchjet_host_url');
        return untrailingslashit($hostUrl);
    }

    public static function init() {
        add_shortcode('searchjet_form', [self::class, 'render']);
    
    }


    public static function render($atts = []) {
        $atts = shortcode_atts([
            'placeholder' => 'Search...',
            'show_button' => true,
            'class' => ''
        ], $atts);
        
        ob_start();
        ?>
        <div class="searchjet-form-wrapper <?php echo esc_attr($atts['class']); ?>">
            <form method="get" action="<?php echo esc_url(home_url('/')); ?>" class="searchjet-search-form">
                <div class="searchjet-input-wrapper">
                    <input 
                        type="search" 
                        class="search-field" 
                        name="s" 
                        placeholder="<?php echo esc_attr($atts['placeholder']); ?>" 
                        value="<?php echo get_search_query(); ?>" 
                        aria-label="Search"
                        autocomplete="off"
                    />
                    <?php if ($atts['show_button']): ?>
                    <button type="submit" class="search-submit" aria-label="Submit search">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/>
                        </svg>
                    </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
}

SearchForm::init();