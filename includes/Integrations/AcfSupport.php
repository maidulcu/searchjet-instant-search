<?php

namespace SearchJet\Integrations;

class AcfSupport {

    public static function init() {
        if (!function_exists('get_fields')) {
            return;
        }

        add_filter('searchjet_index_document', [self::class, 'injectAcfFields'], 10, 2);
    }

    public static function injectAcfFields($doc, $post) {
        $acf_fields = get_fields($post->ID);

        if (!is_array($acf_fields)) {
            return $doc;
        }

        $acf_content = [];

        foreach ($acf_fields as $key => $value) {
            if (is_string($value) && !empty($value)) {
                $acf_content[] = $value;
                $doc[$key] = $value;
            }
        }

        if (!empty($acf_content)) {
            $doc['content'] = trim(($doc['content'] ?? '') . ' ' . implode(' ', $acf_content));
        }

        return $doc;
    }
}