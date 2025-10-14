<?php
namespace SearchJet\Integrations;

class AstraSupport {

    public static function init() {
        if (!defined('ASTRA_THEME_SETTINGS')) {
            return;
        }

        add_filter('searchjet_index_document', [self::class, 'injectAstraMeta'], 10, 2);
    }

    public static function injectAstraMeta($doc, $post) {
        $custom_title = get_post_meta($post->ID, 'astra-custom-title', true);
        $custom_intro = get_post_meta($post->ID, 'astra-intro-text', true);

        if (!empty($custom_title)) {
            $doc['astra_custom_title'] = $custom_title;
            $doc['content'] .= ' ' . $custom_title;
        }

        if (!empty($custom_intro)) {
            $doc['astra_intro'] = $custom_intro;
            $doc['content'] .= ' ' . $custom_intro;
        }

        return $doc;
    }
}