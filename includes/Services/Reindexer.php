<?php
namespace SearchJet\Services;

// Manually include the MeilisearchClient class
require_once plugin_dir_path(__FILE__) . '/MeilisearchClient.php';
use SearchJet\Services\MeilisearchClient;

class Reindexer {

    protected $client;

    /**
     * Constructs a Reindexer object with a MeilisearchClient instance.
     */
    public function __construct() {
        $this->client = new MeilisearchClient();
    }

    public function reindexBatch($offset = 0, $limit = 100, $fullReindex = false) {
        $exclude_sticky = get_option('searchjet_exclude_sticky');
        $args = [
            'post_type'      => apply_filters(
                'searchjet_indexable_post_types',
                array_diff(
                    get_post_types(['public' => true], 'names'),
                    array_map('trim', explode(',', get_option('searchjet_exclude_post_types', '')))
                )
            ),
            'post_status'    => apply_filters(
                'searchjet_indexable_post_statuses',
                array_map('trim', explode(',', get_option('searchjet_include_status', 'publish')))
            ),
            'posts_per_page' => $limit,
            'offset'         => $offset,
            'fields'         => 'ids',
        ];

        if ($exclude_sticky) {
            $args['post__not_in'] = get_option('sticky_posts');
        }

        $query = new \WP_Query($args);

        if (!$query->have_posts()) {
            return false;
        }

        $post_ids = $query->posts;
        $posts = array_map('get_post', $post_ids);

        $exclude_protected = get_option('searchjet_exclude_protected');
        if ($exclude_protected) {
            $posts = array_filter($posts, function($post) {
                return empty($post->post_password);
            });
        }

        $exclude_mimes = array_filter(array_map('trim', explode(',', get_option('searchjet_exclude_mime_types', ''))));
        if (!empty($exclude_mimes)) {
            $posts = array_filter($posts, function($post) use ($exclude_mimes) {
                $mime_type = get_post_mime_type($post);
                return !$mime_type || !in_array($mime_type, $exclude_mimes);
            });
        }

        // Clean post title and content before indexing
        $posts = array_map(function($post) {
            $post->post_title = $this->cleanText($post->post_title);
            $post->post_content = $this->cleanText($post->post_content);
            return $post;
        }, $posts);

        // Pass the $fullReindex flag to bulkIndexPosts.
        $result = $this->client->bulkIndexPosts($posts, $fullReindex);

        return [
            'count' => $result['count'] ?? count($post_ids),
            'message' => $result['message'] ?? null,
        ];
    }

    public function reindexAll() {
        $offset = 0;
        $limit = 100;
        $total = 0;

        do {
            $fullReindex = ($offset === 0);
            $result = $this->reindexBatch($offset, $limit, $fullReindex);
            $count = is_array($result) ? ($result['count'] ?? 0) : 0;
            $offset += $limit;
            $total += $count;
        } while ($count > 0);

        return [
            'total_indexed' => $total,
            'message' => 'All posts reindexed successfully.',
        ];
    }

    protected function cleanText($text) {
        return trim(strip_tags($text));
    }

}
