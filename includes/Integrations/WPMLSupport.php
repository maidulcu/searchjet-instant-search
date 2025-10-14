<?php
namespace SearchJet\Integrations;

class WPMLSupport {

    public static function init() {
        if (!defined('ICL_SITEPRESS_VERSION')) {
            return;
        }

        add_filter('searchjet_index_document', [self::class, 'addLanguageToDocument'], 10, 2);
    }

    public static function addLanguageToDocument($doc, $post) {
        $lang_info = apply_filters('wpml_post_language_details', null, $post->ID);

        if (isset($lang_info['language_code'])) {
            $doc['language'] = $lang_info['language_code'];
        }

        return $doc;
    }
}