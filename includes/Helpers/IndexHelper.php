<?php
namespace SearchJet\Helpers;

class IndexHelper {

    /**
     * Normalize a string for indexing.
     *
     * @param string $text
     * @return string
     */
    public static function normalizeText(string $text): string {
        return strtolower(trim(preg_replace('/\s+/', ' ', $text)));
    }

    /**
     * Generate a slug from a string.
     *
     * @param string $text
     * @return string
     */
    public static function slugify(string $text): string {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9-]/', '-', $text);
        $text = preg_replace('/-+/', '-', $text);
        return trim($text, '-');
    }

    /**
     * Truncate text to a specified length, appending ellipsis if needed.
     *
     * @param string $text
     * @param int $length
     * @return string
     */
    public static function truncate(string $text, int $length = 150): string {
        return strlen($text) > $length ? substr($text, 0, $length) . '...' : $text;
    }

    /**
     * Convert a string to title case.
     *
     * @param string $text
     * @return string
     */
    public static function titleCase(string $text): string {
        return ucwords(strtolower($text));
    }

    /**
     * Remove HTML tags and extra whitespace from text.
     *
     * @param string $text
     * @return string
     */
    public static function cleanText(string $text): string {
        return trim(preg_replace('/\s+/', ' ', strip_tags($text)));
    }

    /**
     * Generate a random alphanumeric string.
     *
     * @param int $length
     * @return string
     */
    public static function randomString(int $length = 10): string {
        return substr(str_shuffle(str_repeat('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil($length / 62))), 0, $length);
    }
}
