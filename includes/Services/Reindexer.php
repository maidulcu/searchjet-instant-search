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
        $include_attachments = (bool) get_option('searchjet_index_attachments', false);
        $public_types = get_post_types(['public' => true], 'names');
        if (!$include_attachments && isset($public_types['attachment'])) {
            unset($public_types['attachment']);
        }
        $excluded_types = array_map('trim', explode(',', get_option('searchjet_exclude_post_types', '')));

        $args = [
            'post_type'      => apply_filters(
                'searchjet_indexable_post_types',
                array_diff($public_types, $excluded_types)
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

        // Safeguard: drop attachments entirely if not indexing them
        if (!$include_attachments) {
            $posts = array_filter($posts, function($post) {
                return $post->post_type !== 'attachment';
            });
        }

        // Clean post title and content before indexing
        $posts = array_map(function($post) {
            $post->post_title = $this->cleanText($post->post_title);
            $post->post_content = $this->cleanText($post->post_content);
            return $post;
        }, $posts);

        // Build document payload (include WooCommerce product meta when available)
        $skipped_post_ids = [];
        $documents = array_map(function($post) use (&$skipped_post_ids) {
            if ($post->post_title === '') {
                $skipped_post_ids[] = $post->ID;
                return null;
            }
            $type = $post->post_type ?: 'page';
            $permalink = get_permalink($post);
            $author = get_the_author_meta('display_name', (int) $post->post_author);
            $dateIso = get_post_time('c', true, $post); // ISO 8601

            // Thumbnail helpers
            $image = get_the_post_thumbnail_url($post, 'full');
            $thumbnail = get_the_post_thumbnail_url($post, 'thumbnail');

            // Excerpt fallback
            $raw_excerpt = get_the_excerpt($post);
            $excerpt = $raw_excerpt ? wp_strip_all_tags($raw_excerpt) : wp_trim_words($post->post_content, 30);

            $doc = [
                'id'       => (int) $post->ID,
                'type'     => $type,
                'title'    => $post->post_title,
                'content'  => $post->post_content,
                'url'      => $permalink ?: '',
                'excerpt'  => $excerpt ?: '',
                'author'   => $author ?: '',
                'date'     => $dateIso ?: '',
                'image'    => $image ?: '',
                'thumbnail'=> $thumbnail ?: '',
            ];

            // Attach categories/tags depending on type
            if ($type === 'product') {
                $cat_tax = 'product_cat';
                $tag_tax = 'product_tag';
            } else {
                $cat_tax = 'category';
                $tag_tax = 'post_tag';
            }
            $cats = wp_get_post_terms($post->ID, $cat_tax, ['fields' => 'names']);
            $tags = wp_get_post_terms($post->ID, $tag_tax, ['fields' => 'names']);
            if (!is_wp_error($cats) && !empty($cats)) { $doc['categories'] = array_values(array_unique(array_filter($cats))); }
            if (!is_wp_error($tags) && !empty($tags)) { $doc['tags'] = array_values(array_unique(array_filter($tags))); }

            // WooCommerce-specific meta
            if ($type === 'product' && function_exists('wc_get_product')) {
                $product = wc_get_product($post->ID);
                if ($product) {
                    $sku = (string) $product->get_sku();
                    if ($sku !== '') { $doc['sku'] = $sku; }

                    // Prices as numbers (float)
                    $regular = $product->get_regular_price();
                    $sale    = $product->get_sale_price();
                    $active  = $product->get_price(); // active price shown to customer

                    if (is_numeric($active)) {
                        $doc['price'] = (float) $active;
                    }
                    if (is_numeric($regular)) {
                        $doc['regular_price'] = (float) $regular;
                    }
                    if (is_numeric($sale)) {
                        $doc['sale_price'] = (float) $sale;
                    }

                    if (function_exists('get_woocommerce_currency')) {
                        $doc['currency'] = (string) get_woocommerce_currency();
                    }
                    if (method_exists($product, 'get_stock_status')) {
                        $doc['stock_status'] = (string) $product->get_stock_status();
                    }
                    // Ensure image/thumbnail fallback from product if missing
                    if (empty($doc['image'])) {
                        $prod_img_id = $product->get_image_id();
                        if ($prod_img_id) {
                            $doc['image'] = wp_get_attachment_image_url($prod_img_id, 'full') ?: '';
                            $doc['thumbnail'] = wp_get_attachment_image_url($prod_img_id, 'thumbnail') ?: $doc['thumbnail'];
                        }
                    }
                }
            }

            return apply_filters('searchjet_index_document', $doc, $post);
        }, $posts);
        $documents = array_values(array_filter($documents));

        if (defined('WP_DEBUG') && WP_DEBUG && !empty($skipped_post_ids)) {
            error_log('[SearchJet Debug] Skipped posts with empty titles: ' . implode(', ', $skipped_post_ids));
        }

        $result = $this->client->bulkIndexPosts($documents, $fullReindex);

        return [
            'count' => $result['count'] ?? count($documents),
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
