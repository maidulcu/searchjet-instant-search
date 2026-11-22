<?php
/**
 * Batch Delete Helper
 *
 * Provides utilities for bulk deleting documents from the search index
 * based on various criteria (post type, category, date, etc).
 *
 * @package SearchJet
 */

namespace SearchJet\Helpers;

use SearchJet\API\SearchJetRemoteClient;

class BatchDeleteHelper {

    /**
     * Delete documents by post type.
     *
     * Useful when excluding a post type from search.
     *
     * @param string|array $post_types Post type(s) to delete
     * @return array|null API response or null on failure
     */
    public static function deleteByPostType($post_types) {
        if (empty($post_types)) {
            return null;
        }

        if (!is_array($post_types)) {
            $post_types = [$post_types];
        }

        $filter_parts = array_map(function($type) {
            return 'type = "' . $type . '"';
        }, $post_types);

        $filter = implode(' OR ', $filter_parts);

        return SearchJetRemoteClient::deleteBatch([], $filter);
    }

    /**
     * Delete documents by category/term.
     *
     * @param string $category Category name or ID
     * @param string $taxonomy Taxonomy name (default: category)
     * @return array|null API response or null on failure
     */
    public static function deleteByTerm($category, $taxonomy = 'category') {
        if (empty($category)) {
            return null;
        }

        // Build filter - assumes API stores categories as array
        $filter = sprintf(
            'categories = "%s"',
            str_replace('"', '\\"', $category)
        );

        return SearchJetRemoteClient::deleteBatch([], $filter);
    }

    /**
     * Delete documents by date range.
     *
     * @param string $start_date Start date (YYYY-MM-DD)
     * @param string $end_date End date (YYYY-MM-DD)
     * @return array|null API response or null on failure
     */
    public static function deleteByDateRange($start_date, $end_date) {
        if (empty($start_date) || empty($end_date)) {
            return null;
        }

        $filter = sprintf(
            'date >= "%s" AND date <= "%s"',
            $start_date,
            $end_date
        );

        return SearchJetRemoteClient::deleteBatch([], $filter);
    }

    /**
     * Delete documents by document IDs.
     *
     * @param array $document_ids Array of document IDs
     * @return array|null API response or null on failure
     */
    public static function deleteByIds($document_ids) {
        if (empty($document_ids)) {
            return null;
        }

        if (!is_array($document_ids)) {
            $document_ids = [$document_ids];
        }

        return SearchJetRemoteClient::deleteBatch($document_ids);
    }

    /**
     * Delete documents matching a custom Meilisearch filter.
     *
     * Filter syntax examples:
     * - type = "product"
     * - price > 100
     * - stock_status = "inStock"
     * - categories = "electronics"
     * - type = "post" AND date < "2024-01-01"
     *
     * @param string $filter Meilisearch filter expression
     * @return array|null API response or null on failure
     */
    public static function deleteByFilter($filter) {
        if (empty($filter)) {
            return null;
        }

        return SearchJetRemoteClient::deleteBatch([], $filter);
    }

    /**
     * Delete all WordPress posts of given types from index.
     *
     * Useful for reindexing or when post type is removed from search.
     *
     * @param string|array $post_types Post type(s) to delete
     * @return array Total deleted count and details
     */
    public static function deletePostTypeFromIndex($post_types) {
        if (empty($post_types)) {
            return ['success' => false, 'message' => 'No post types specified'];
        }

        if (!is_array($post_types)) {
            $post_types = [$post_types];
        }

        // Query all posts of the given type
        $posts = get_posts([
            'post_type' => $post_types,
            'posts_per_page' => -1,
            'fields' => 'ids',
            'post_status' => 'any',
        ]);

        if (empty($posts)) {
            return ['success' => true, 'deleted' => 0, 'message' => 'No posts found to delete'];
        }

        // Convert post IDs to document IDs
        $document_ids = array_map(function($post_id) {
            return 'post-' . $post_id;
        }, $posts);

        // Delete in batches (API may have limits)
        $batch_size = 100;
        $batches = array_chunk($document_ids, $batch_size);
        $total_deleted = 0;
        $errors = [];

        foreach ($batches as $batch) {
            $result = self::deleteByIds($batch);

            if ($result && isset($result['deletedCount'])) {
                $total_deleted += $result['deletedCount'];
            } else {
                $errors[] = 'Batch delete failed: ' . implode(', ', $batch);
            }
        }

        return [
            'success' => empty($errors),
            'deleted' => $total_deleted,
            'total_posts' => count($posts),
            'batches_processed' => count($batches),
            'errors' => $errors,
        ];
    }

    /**
     * Full index clear (delete all documents).
     *
     * WARNING: This deletes everything from the search index!
     * Use with caution.
     *
     * @return array|null API response or null on failure
     */
    public static function clearIndex() {
        // Use a filter that matches everything
        return SearchJetRemoteClient::deleteBatch([], '1 = 1');
    }

    /**
     * Batch re-index posts (delete and re-add).
     *
     * Useful for updating stale search index entries.
     *
     * @param array $post_ids Post IDs to reindex
     * @param bool $force_full Force full reindex flag
     * @return array Result with count and task info
     */
    public static function reindexPosts($post_ids, $force_full = false) {
        if (empty($post_ids)) {
            return ['success' => false, 'message' => 'No post IDs provided'];
        }

        if (!is_array($post_ids)) {
            $post_ids = [$post_ids];
        }

        // Get post objects
        $posts = array_map('get_post', $post_ids);
        $posts = array_filter($posts);

        if (empty($posts)) {
            return ['success' => false, 'message' => 'No valid posts found'];
        }

        // First delete old entries
        $doc_ids = array_map(function($post) {
            return 'post-' . $post->ID;
        }, $posts);

        $delete_result = self::deleteByIds($doc_ids);

        // Then re-index
        $meilisearch = new \SearchJet\Services\MeilisearchClient();
        $index_result = $meilisearch->bulk_index_posts($posts, $force_full);

        return [
            'success' => true,
            'deleted' => isset($delete_result['deletedCount']) ? $delete_result['deletedCount'] : count($doc_ids),
            'reindexed' => $index_result['count'] ?? 0,
            'message' => sprintf(
                __('Reindexed %d posts', 'searchjet-instant-search'),
                $index_result['count'] ?? 0
            ),
        ];
    }
}
