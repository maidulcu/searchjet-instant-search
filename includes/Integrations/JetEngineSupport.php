<?php

namespace SearchJet\Integrations;

class JetEngineSupport {

    public static function init() {
        if (!class_exists('\Jet_Engine')) {
            return;
        }

        add_filter('searchjet_index_document', [self::class, 'injectMetaFields'], 10, 2);

        add_action('admin_notices', function () {
            echo '<div class="notice notice-info is-dismissible"><p><strong>SearchJet:</strong> JetEngine meta fields are now indexed for improved search relevance.</p></div>';
        });
    }

    public static function injectMetaFields($doc, $post) {
        if (!class_exists('\Jet_Engine')) {
            return $doc;
        }

        $meta_content = [];

        if (
            !class_exists('\Jet_Engine\Meta_Boxes\Module') ||
            !property_exists(\Jet_Engine\Meta_Boxes\Module::instance(), 'meta_boxes') ||
            !method_exists(\Jet_Engine\Meta_Boxes\Module::instance()->meta_boxes, 'get_registered_meta_boxes')
        ) {
            return $doc;
        }

        $meta_boxes = \Jet_Engine\Meta_Boxes\Module::instance()->meta_boxes->get_registered_meta_boxes();
        $post_type = get_post_type($post->ID);

        foreach ($meta_boxes as $box) {
            if (!empty($box['args']['post_type']) && in_array($post_type, (array) $box['args']['post_type'], true)) {
                foreach ($box['fields'] as $field_key => $field_config) {
                    $value = get_post_meta($post->ID, $field_key, true);
                    if (is_string($value) && !empty($value)) {
                        $meta_content[] = $value;
                        $doc[$field_key] = $value;
                    }
                }
            }
        }

        if (!empty($meta_content)) {
            $doc['content'] = trim(($doc['content'] ?? '') . ' ' . implode(' ', $meta_content));
        }

        return $doc;
    }
}