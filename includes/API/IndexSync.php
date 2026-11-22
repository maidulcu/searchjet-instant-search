<?php
namespace SearchJet\API;

use SearchJet\Services\MeilisearchClient;
use SearchJet\Helpers\IndexScope;

class IndexSync {
    /**
     * Register WordPress hooks for syncing post data with SearchJet.
     */
    public static function init() {
        add_action('save_post', [self::class, 'handleIndex'], 10, 3);
        add_action('delete_post', [self::class, 'handleDelete']);
    }

    /**
     * Index a post when it's saved or updated.
     *
     * @param int      $post_id Post ID.
     * @param \WP_Post $post    Post object.
     * @param bool     $update  Whether this is an existing post being updated.
     */
    public static function handleIndex($post_id, $post, $update) {
        $allowed_statuses = apply_filters(
            'searchjet_indexable_post_statuses',
            array_map('trim', explode(',', get_option('searchjet_include_status', 'publish')))
        );
        if (!in_array($post->post_status, $allowed_statuses)) return;

        if (!in_array($post->post_type, IndexScope::post_types(), true)) return;

        // Exclude sticky posts
        if (get_option('searchjet_exclude_sticky') && is_sticky($post_id)) return;

        // Password-protected posts never go to the index. If this post was indexed before
        // the password was set, remove it.
        if (IndexScope::is_protected($post)) {
            try {
                $client = new MeilisearchClient();
                $client->delete_post($post_id);
                delete_transient('searchjet_document_count');
            } catch (\Exception $e) {
                // Nothing to remove or service unreachable: the next full reindex cleans up.
            }
            return;
        }

        // Exclude MIME types (attachments only)
        if ($post->post_type === 'attachment') {
            $mime = get_post_mime_type($post_id);
            if ($mime) {
                $excluded_mimes = array_map('trim', explode(',', get_option('searchjet_exclude_mime_types', '')));
                if (in_array($mime, $excluded_mimes)) return;
            }
        }

        try {
            $client = new MeilisearchClient();
            $client->index_post($post);

        } catch (\Exception $e) {
            error_log('[SearchJet] Index failed for post ' . $post_id . ': ' . $e->getMessage());
        }
    }

    /**
     * Remove a post from the index when it's deleted.
     *
     * @param int $post_id Post ID.
     */
    public static function handleDelete($post_id) {

        $post = get_post($post_id);
        if (!$post || $post->post_status === 'auto-draft') return;

        if (!in_array($post->post_type, IndexScope::post_types(), true)) return;

        // Only allow "publish" posts to remain indexed; remove all others including drafts, trash, pending, etc.
        if ($post->post_status !== 'publish') {
            try {
                $remote = new \SearchJet\API\SearchJetRemoteClient();
                $remote->deleteDocument([
                    'id' => $post_id
                ]);

            } catch (\Exception $e) {
                error_log('[SearchJet] Delete failed for post ' . $post_id . ': ' . $e->getMessage());
            }

            delete_transient('searchjet_document_count');
        }
    }
}
