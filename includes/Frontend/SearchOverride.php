<?php
namespace SearchJet\Frontend;

class SearchOverride {
    public static function init() {
        $mode = self::getSearchMode();
        if ($mode === 'template') {
            add_action('pre_get_posts', [__CLASS__, 'interceptMainSearch']);
            add_filter('template_include', [__CLASS__, 'forceSearchTemplate']);
        }

        if ($mode === 'js') {
            if (is_search()) {
                    add_action('wp_footer', [__CLASS__, 'renderResultsContainer']);
            }
        }
        add_filter('body_class', [__CLASS__, 'addBodyClass']);
    }

    protected static function getSearchMode() {
        static $cachedMode = null;

        if ($cachedMode === null) {
            $cachedMode = get_option('searchjet_search_mode', 'js') ?: 'js';
        }

        return $cachedMode;
    }

    protected static function getApiBaseUrl() {
       return rtrim(SEARCHJET_API_BASE_URL, '/') . '/api';
    }

    public static function interceptMainSearch($query) {
        if ($query->is_main_query() && !is_admin() && $query->is_search()) {
            add_filter('the_posts', [__CLASS__, 'overrideSearchResults']);
        }
        $query->is_search = true;
        $query->is_home = false;
        $query->is_archive = true;
        $query->is_singular = false;
    }


    public static function overrideSearchResults($posts) {
        $term = get_search_query();
        $results = self::querySearchJetAPI($term);

        $fake_posts = [];
        foreach ($results as $item) {
            $post = new \stdClass();
            $post->ID = $item['id'] ?? 0;
            $post->post_title = $item['title'] ?? 'No title';
            $post->post_content = $item['excerpt'] ?? '';
            $post->post_type = $item['post_type'] ?? 'post';
            $post->post_date = $item['date'] ?? current_time('mysql');
            $post->post_author = $item['author_id'] ?? 1;
            $post->guid = $item['url'] ?? '';
            $post->post_name = sanitize_title($item['title'] ?? '');
            $post->filter = 'raw';
            $fake_posts[] = new \WP_Post((object) $post);
        }

        global $wp_query;

        $wp_query->posts = $fake_posts;
        $wp_query->post_count = count($fake_posts);
        $wp_query->found_posts = count($fake_posts);
        $wp_query->max_num_pages = 1;

        return $fake_posts;
    }

    public static function querySearchJetAPI($term) {

        $clientInfo = get_option('searchjet_client_info', []);

        $url = self::getApiBaseUrl() . '/v1/search';


        $limit = get_option('posts_per_page', 10);
        $response = wp_remote_get($url . '?q=' . urlencode($term) . '&limit=' . $limit, [
            'headers' => [
                'Authorization' => 'Bearer ' . ($clientInfo['public_key'] ?? ''),
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/json',
            ]
        ]);


        if (is_wp_error($response)) return [];

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        return $data['results'] ?? [];
    }

    public static function forceSearchTemplate($template) {
        $mode = self::getSearchMode();
        if (is_search()) {
            if ($mode === 'js') {
                $page_template = locate_template('page.php');
                return $page_template ?: $template;
            }

        }
        return $template;
    }

    public static function renderResultsContainer() {
        ob_start(function ($content) {
            if (is_search() && self::getSearchMode() === 'js') {
                return preg_replace(
                    '#<div class="wp-block-query[^>]*>.*?</div>#is',
                    '<div id="searchjet-page-results"></div>',
                    $content
                );
            }
            return $content;
        });
    }

    public static function addBodyClass($classes) {
        if (is_search() && self::getSearchMode() === 'js') {
            $classes[] = 'searchjet-js-mode';
        }
        return $classes;
    }

}
