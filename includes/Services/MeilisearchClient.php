<?php
namespace SearchJet\Services;


/**
 * NOTE: This class now supports user-defined Meilisearch host URLs.
 * Users can configure their own Meilisearch host and API key from the plugin settings page.
 * Later phases will add an option for users to choose between their own host or a SearchJet-hosted API.
 *
 * This client assumes the index already exists on the Meilisearch server.
 * Index creation is managed exclusively by the SearchJet SaaS backend.
 */
class MeilisearchClient {
    private $apiKey;
    private $hostUrl;
    private $indexName;

    /**
     * Initialize the SaaSClient with API credentials from WordPress options.
     */
    public function __construct() {
        $this->apiKey = get_option('searchjet_hosted_api_key');
        $this->hostUrl = rtrim(SEARCHJET_API_BASE_URL, '/') . '/api';
    }

    /**
     * Send a single post to the SearchJet indexing API.
     *
     * @param \WP_Post $post The WordPress post object to index.
     */
    public function indexPost($post) {
        if (empty($post->post_title)) {
            return;
        }

        $payload = [
            'id'      => $post->ID,
            'title'   => $post->post_title,
            'content' => wp_strip_all_tags($post->post_content),
            'type'    => $post->post_type,
            'url'     => get_permalink($post),
            'slug'    => $post->post_name,
            'date'    => get_the_date('c', $post),
            'modified'=> get_the_modified_date('c', $post),
            'excerpt' => wp_strip_all_tags(get_the_excerpt($post)),
            'author'  => get_the_author_meta('display_name', $post->post_author),
            'post_status' => $post->post_status,
        ];

        if ($post->post_type === 'product' && function_exists('wc_get_product')) {
            $product = wc_get_product($post->ID);
            if ($product) {
                $payload['price']      = $product->get_price();
                $payload['sku']        = $product->get_sku();
                $payload['categories'] = wp_get_post_terms($post->ID, 'product_cat', ['fields' => 'names']);
                $payload['tags']       = wp_get_post_terms($post->ID, 'product_tag', ['fields' => 'names']);
                $payload['stock']      = $product->get_stock_status();
                $payload['image']      = get_the_post_thumbnail_url($post->ID, 'medium');
            }
        }

        $url = $this->hostUrl . '/v1/index/bulk';

        $response = wp_remote_post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode(['items' => [$payload]]),
            'timeout' => 10,
        ]);

        //error_log('[SearchJet] indexPost response: ' . print_r($response, true));
        if (is_wp_error($response)) {
            error_log('[SearchJet] wp_remote_post error during indexPost: ' . $response->get_error_message());
        } else {
            $http_code = wp_remote_retrieve_response_code($response);
            if ($http_code < 200 || $http_code >= 300) {
                error_log('[SearchJet] Unexpected HTTP code ' . $http_code . ' during indexPost: ' . wp_remote_retrieve_body($response));
            }
        }
    }

    /**
     * Request deletion of a post from the SearchJet index.
     *
     * @param int    $post_id   The ID of the post to delete.
     * @param string $post_type The post type of the item being deleted.
     */
    
    public function deletePost($post_id, $post_type) {
        $url = $this->hostUrl . '/v1/index/delete';

        if (!$this->indexName) {
            // error_log('[SearchJet] No index name set, skipping delete.');
            return;
        }

        $response = wp_remote_request($url, [
            'method'  => 'DELETE',
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode([
                'items' => [[
                    'id' => $post_id,
                    'type' => $post_type,
                ]],
            ]),
            'timeout' => 10,
        ]);

        if (is_wp_error($response)) {
            // error_log('[SearchJet] wp_remote_request error during deletePost: ' . $response->get_error_message());
        } else {
            $http_code = wp_remote_retrieve_response_code($response);
            if ($http_code < 200 || $http_code >= 300) {
                // error_log('[SearchJet] Unexpected HTTP code ' . $http_code . ' during deletePost: ' . wp_remote_retrieve_body($response));
            }
        }
    }

    /**
     * Send multiple posts in bulk to the SearchJet indexing API.
     *
     * @param \WP_Post[] $posts An array of WordPress post objects.
     */
    public function bulkIndexPosts(array $posts, bool $fullReindex = false) {
        $payload = array_map(function($post) {
            if (empty($post->post_title)) {
                return null; // Skip posts with no title
            }

            $item = [
                'id'      => $post->ID,
                'title'   => $post->post_title,
                'content' => wp_strip_all_tags($post->post_content),
                'type'    => $post->post_type,
                'url'     => get_permalink($post),
                'slug'    => $post->post_name,
                'date'    => get_the_date('c', $post),
                'modified'=> get_the_modified_date('c', $post),
                'excerpt' => wp_strip_all_tags(get_the_excerpt($post)),
                'author'  => get_the_author_meta('display_name', $post->post_author),
                'post_status' => $post->post_status,
            ];

            if ($post->post_type === 'product' && function_exists('wc_get_product')) {
                $product = wc_get_product($post->ID);
                if ($product) {
                    $item['price']      = $product->get_price();
                    $item['sku']        = $product->get_sku();
                    $item['categories'] = wp_get_post_terms($post->ID, 'product_cat', ['fields' => 'names']);
                    $item['tags']       = wp_get_post_terms($post->ID, 'product_tag', ['fields' => 'names']);
                    $item['stock']      = $product->get_stock_status();
                    $item['image']      = get_the_post_thumbnail_url($post->ID, 'medium');
                }
            }

            return $item;
        }, $posts);

        $payload = array_filter($payload);

        $url = $this->hostUrl . '/v1/index/bulk';

        if (!$this->indexName) {
            return;
        }

        $request_header = [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
            'body' => wp_json_encode([
                'documents' => array_values($payload),
                 'full_reindex' => $fullReindex ? true : null,
            ]),
            'timeout' => 60,
        ];

        $response = wp_remote_post($url, array_merge($request_header, [
            'redirection' => 0,
            'httpversion' => '1.1',
        ]));

        $response_body = wp_remote_retrieve_body($response);
        $data = json_decode($response_body, true);

        if (is_wp_error($response)) {
            return [
                'count' => 0,
                'message' => 'Indexing failed: ' . $response->get_error_message(),
            ];
        }

        $http_code = wp_remote_retrieve_response_code($response);
        if ($http_code < 200 || $http_code >= 300) {
            return [
                'count' => 0,
                'message' => 'Error: ' . ($data['message'] ?? wp_remote_retrieve_body($response)),
            ];
        }

        return [
            'count' => count($payload),
            'message' => $data['message'] ?? 'Indexed ' . count($payload) . ' documents.',
            'was_cleared' => $data['was_cleared'] ?? false,
        ];
    }

}
