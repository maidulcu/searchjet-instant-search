<?php

namespace SearchJet\Integrations;

class ElementorSupport {

    public static function init() {
        if (!defined('ELEMENTOR_VERSION')) {
            return;
        }

        add_filter('searchjet_index_document', [self::class, 'injectElementorContent'], 10, 2);
    }

    public static function injectElementorContent($doc, $post) {
        if (!function_exists('has_blocks') || has_blocks($post)) {
            return $doc; // Let Gutenberg handle block-based content
        }

        $document = \Elementor\Plugin::$instance->documents->get($post->ID);
        if (! $document || !method_exists($document, 'get_elements_raw_data')) {
            return $doc;
        }
        $content = $document->get_elements_raw_data();

        if (!is_array($content)) {
            return $doc;
        }

        $flattened = self::extractTextFromElementor($content);

        if (!empty($flattened)) {
            $doc['content'] = trim(($doc['content'] ?? '') . ' ' . $flattened);
        }

        return $doc;
    }

    protected static function extractTextFromElementor(array $elements): string {
        $output = [];

        foreach ($elements as $element) {
            if (isset($element['widgetType'], $element['settings']['editor'])) {
                $output[] = wp_strip_all_tags($element['settings']['editor']);
            }

            if (!empty($element['elements'])) {
                $output[] = self::extractTextFromElementor($element['elements']);
            }
        }

        return implode(' ', $output);
    }
}
