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
            <div id="searchjet-results">
                <div class="searchjet-products">
                    <div class="searchjet-product">
                        <img src="" alt="">
                        <div class="searchjet-product-detail">
                            <div class="searchjet-product-header">
                                <h2 class="searchjet-product-title"></h2>
                                <p class="searchjet-product-price"></p>
                            </div>
                            <p class="searchjet-product-excerpt"></p>
                        </div>
                    </div>
                </div>
                <div class="searchjet-page">
                    <svg fill="#000000" height="800px" width="800px" version="1.1" id="Layer_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" 
                        viewBox="0 0 50 50" enable-background="new 0 0 50 50" xml:space="preserve">
                        <path d="M36,40.301c0.552,0,1-0.447,1-1v-24.6c0-0.022-0.011-0.041-0.013-0.063c-0.006-0.09-0.023-0.177-0.053-0.262
                            c-0.012-0.034-0.021-0.066-0.036-0.098c-0.045-0.096-0.103-0.186-0.179-0.265c-0.006-0.006-0.009-0.015-0.016-0.021
                            c-0.001-0.001-0.002-0.003-0.003-0.005l-5.5-5.4c-0.083-0.081-0.18-0.14-0.282-0.188c-0.031-0.014-0.063-0.022-0.096-0.033
                            c-0.085-0.029-0.172-0.047-0.262-0.053C30.54,8.312,30.522,8.301,30.5,8.301H14c-0.552,0-1,0.447-1,1v30c0,0.553,0.448,1,1,1H36z
                            M31.5,11.685l2.054,2.017H31.5V11.685z M15,10.301h14.5v4.4c0,0.553,0.448,1,1,1H35v22.6H15V10.301z"/>
                        <path d="M32.181,24.471H18.322c-0.552,0-1,0.447-1,1s0.448,1,1,1h13.858c0.552,0,1-0.447,1-1S32.733,24.471,32.181,24.471z"/>
                        <path d="M32.181,20.271H18.322c-0.552,0-1,0.447-1,1s0.448,1,1,1h13.858c0.552,0,1-0.447,1-1S32.733,20.271,32.181,20.271z"/>
                        <path d="M32.181,28.67H18.322c-0.552,0-1,0.447-1,1s0.448,1,1,1h13.858c0.552,0,1-0.447,1-1S32.733,28.67,32.181,28.67z"/>
                        <path d="M32.181,32.87H18.322c-0.552,0-1,0.447-1,1s0.448,1,1,1h13.858c0.552,0,1-0.447,1-1S32.733,32.87,32.181,32.87z"/>
                    </svg>
                    <div class="searchjet-page-description">
                        <h2 class="searchjet-page-title"></h2>
                        <p class="searchjet-page-description"></p>
                    </div>
                </div>
            </div>
        </form>
        <?php
        return ob_get_clean();
    }
}

SearchForm::init();