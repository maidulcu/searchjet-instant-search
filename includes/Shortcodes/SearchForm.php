<?php
namespace SearchJet\Shortcodes;

class SearchForm {
    private static function getHostUrl() {
        $hostUrl = get_option('searchjet_host_url');
        return untrailingslashit($hostUrl);
    }

    public static function init() {
        add_shortcode('searchjet_form', [self::class, 'render']);
    
    }


    public static function render() {
        ob_start();
        ?>
        <form id="searchjet-form" method="get" action="<?php echo esc_url(home_url('/')); ?>">
            <label for="searchjet-query" class="screen-reader-text">Search for:</label>
            <input 
                type="search" 
                id="searchjet-query" 
                name="s" 
                placeholder="Search..." 
                value="<?php echo get_search_query(); ?>" 
                aria-label="Search"
            />
            <button type="submit">Search</button>
            <div id="searchjet-results"></div>
        </form>
        <?php
        return ob_get_clean();
    }
}

SearchForm::init();