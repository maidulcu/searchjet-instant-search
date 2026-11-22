<?php
namespace SearchJet\Helpers;

/**
 * One definition of what may be sent to the SearchJet index.
 *
 * Every index path (single post sync, batch reindex, Bulk Re-Index page, Index Health count)
 * must use this class, so they cannot disagree on scope.
 *
 * @since 2.0.7
 */
class IndexScope {

    /**
     * Post types that may be indexed.
     *
     * Only types that are public AND included in the site's own front-end search. This keeps
     * admin-only content (WooCommerce coupons, forms, reusable blocks, template libraries)
     * on the site.
     *
     * @return string[] Post type names.
     */
    public static function post_types(): array {
        $types = get_post_types(['public' => true, 'exclude_from_search' => false], 'names');

        if (!get_option('searchjet_index_attachments', false)) {
            unset($types['attachment']);
        }

        $excluded = array_filter(array_map('trim', explode(',', (string) get_option('searchjet_exclude_post_types', ''))));
        $types    = array_values(array_diff($types, $excluded));

        /**
         * Filter the post types SearchJet may index.
         *
         * @param string[] $types Post type names.
         */
        return array_values(array_filter((array) apply_filters('searchjet_indexable_post_types', $types), 'is_string'));
    }

    /**
     * Whether a post is password-protected and therefore must stay out of the index.
     *
     * @param \WP_Post|null $post Post object.
     */
    public static function is_protected($post): bool {
        if (!$post || empty($post->post_password)) {
            return false;
        }

        /**
         * Allow password-protected posts into the index. Off by default: their text would
         * become searchable by anyone.
         *
         * @param bool     $allow Default false.
         * @param \WP_Post $post  Post object.
         */
        return !apply_filters('searchjet_index_protected_posts', false, $post);
    }

    /**
     * Whether this post's type and protection allow indexing. Status is checked by the caller.
     *
     * @param \WP_Post|null $post Post object.
     */
    public static function allows($post): bool {
        if (!$post || !in_array($post->post_type, self::post_types(), true)) {
            return false;
        }

        return !self::is_protected($post);
    }
}
