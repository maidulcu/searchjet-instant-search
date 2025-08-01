<?php
namespace SearchJet\API;

use SearchJet\Services\MeilisearchClient;

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

        $allowed_post_types = apply_filters(
            'searchjet_indexable_post_types',
            get_post_types(['public' => true], 'names')
        );
        if (!in_array($post->post_type, $allowed_post_types)) return;

        // Exclude sticky posts
        if (get_option('searchjet_exclude_sticky') && is_sticky($post_id)) return;

        // Exclude password-protected posts
        if (get_option('searchjet_exclude_protected') && !empty($post->post_password)) return;

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
            $client->indexPost($post);

        } catch (\Exception $e) {
            //error_log('[SearchJet] Indexing failed for post ID ' . $post_id . ': ' . $e->getMessage());
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

        $allowed_post_types = apply_filters(
            'searchjet_indexable_post_types',
            array_diff(
                get_post_types(['public' => true], 'names'),
                array_map('trim', explode(',', get_option('searchjet_exclude_post_types', '')))
            )
        );
        if (!in_array($post->post_type, $allowed_post_types)) return;

        // Only allow "publish" posts to remain indexed; remove all others including drafts, trash, pending, etc.
        if ($post->post_status !== 'publish') {
            try {
                $remote = new \SearchJet\API\SearchJetRemoteClient();
                $remote->deleteDocument([
                    'id' => $post_id
                ]);

            } catch (\Exception $e) {
                // error_log('[SearchJet] Deletion failed for post ID ' . $post_id . ': ' . $e->getMessage());
            }

            delete_transient('searchjet_document_count');
        }
    }
}
