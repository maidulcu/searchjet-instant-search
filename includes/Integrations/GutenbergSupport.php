<?php

namespace SearchJet\Integrations;

class GutenbergSupport {

    public static function init() {
        if (!function_exists('has_blocks')) {
            return;
        }

        add_filter('searchjet_index_document', [self::class, 'injectGutenbergBlocks'], 10, 2);
    }

    public static function injectGutenbergBlocks($doc, $post) {
        if (!has_blocks($post->post_content)) {
            return $doc;
        }

        $blocks = parse_blocks($post->post_content);
        $text = self::extractTextFromBlocks($blocks);

        if (!empty($text)) {
            $doc['content'] = trim(($doc['content'] ?? '') . ' ' . $text);
        }

        return $doc;
    }

    protected static function extractTextFromBlocks(array $blocks): string {
        $output = [];

        foreach ($blocks as $block) {
            if (!empty($block['innerHTML'])) {
                $output[] = wp_strip_all_tags($block['innerHTML']);
            }

            if (!empty($block['innerBlocks'])) {
                $output[] = self::extractTextFromBlocks($block['innerBlocks']);
            }
        }

        return implode(' ', $output);
    }
}