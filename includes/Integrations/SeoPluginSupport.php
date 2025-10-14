<?php
namespace SearchJet\Integrations;

class SeoPluginSupport {

    public static function init() {
        add_filter('searchjet_index_document', [self::class, 'injectSeoMeta'], 10, 2);
    }

    public static function injectSeoMeta($doc, $post) {
        $seo_fields = [
            '_yoast_wpseo_title',
            '_yoast_wpseo_metadesc',
            '_rank_math_title',
            '_rank_math_description',
            '_seopress_titles_title',
            '_seopress_titles_desc',
        ];

        $seo_text = [];

        foreach ($seo_fields as $meta_key) {
            $value = get_post_meta($post->ID, $meta_key, true);
            if (!empty($value) && is_string($value)) {
                $doc[$meta_key] = $value;
                $seo_text[] = $value;
            }
        }

        if (!empty($seo_text)) {
            $doc['content'] = trim(($doc['content'] ?? '') . ' ' . implode(' ', $seo_text));
        }

        return $doc;
    }
}