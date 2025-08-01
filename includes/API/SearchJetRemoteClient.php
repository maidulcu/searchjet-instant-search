<?php
namespace SearchJet\API;

use const SEARCHJET_API_BASE_URL;

class SearchJetRemoteClient {

    protected static function getApiBaseUrl() {
       return rtrim(SEARCHJET_API_BASE_URL, '/') . '/api';
    }

    /**
     * Returns the API key used to authenticate requests to the SearchJet API.
     *
     * If the `searchjet_hosted_api_key` option is not set, an empty string is returned.
     *
     * @return string The API key.
     */

    protected static function getApiKey() {
        // Use the API key from the settings page.
        return get_option('searchjet_hosted_api_key', '');
    }

    /**
     * Fetches client information from the SearchJet API.
     *
     * @return array|null The client information or null if the request fails.
     */

    public static function fetchClientInfo($apiKey = null) {

        $apiKey = $apiKey ?? self::getApiKey();

        $url = self::getApiBaseUrl() . '/v1/client-info';

        if (empty($url) || empty($apiKey)) {
            return null;
        }

        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
            'timeout' => 20,
        ]);

        //var_dump($response);die();

        if (is_wp_error($response)) {
            return null;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($code !== 200) {
            return null;
        }

        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        
 
        return $data;
    }

    public static function deleteDocument($payload = []) {
        $url = self::getApiBaseUrl() . '/v1/index/delete';

        if (empty($url) || empty(self::getApiKey())) {
            //error_log('[SearchJet] Missing API base URL or API key for document deletion.');
            return null;
        }

        $response = wp_remote_request($url, [
            'method'  => 'DELETE',
            'headers' => [
                'Authorization' => 'Bearer ' . self::getApiKey(),
                'Content-Type'  => 'application/json',
                 'Accept'        => 'application/json',
            ],
            'body'    => json_encode($payload),
            'timeout' => 10,
        ]);

        if (is_wp_error($response)) {
            //error_log('[SearchJet] WP Error during document delete: ' . $response->get_error_message());
            return null;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($code !== 200) {
           // error_log("[SearchJet] Delete failed with status: $code, body: $body");
            return null;
        }

        return json_decode($body, true);
    }
    public static function indexDocument($payload = []) {
        $url = self::getApiBaseUrl() . '/v1/index/bulk';

        if (empty($url) || empty(self::getApiKey())) {
            //error_log('[SearchJet] Missing API base URL or API key for indexing.');
            return null;
        }

        $response = wp_remote_post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . self::getApiKey(),
                'Content-Type'  => 'application/json',
                 'Accept'        => 'application/json',
            ],
            'body'    => json_encode($payload),
            'timeout' => 10,
        ]);

        if (is_wp_error($response)) {
            //error_log('[SearchJet] WP Error during document index: ' . $response->get_error_message());
            return null;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($code !== 200) {
            //error_log("[SearchJet] Index failed with status: $code, body: $body");
            return null;
        }

        return json_decode($body, true);
    }
    /**
     * Fetches the count of indexed documents from the SearchJet API.
     *
     * @return int|null Number of documents or null on failure.
     */
    public static function fetchDocumentCount() {
        $cached = get_transient('searchjet_document_count');
        if ($cached !== false) {
            return $cached;
        }

        $url = self::getApiBaseUrl() . '/v1/index/count';

        if (empty($url) || empty(self::getApiKey())) {
            //error_log('[SearchJet] Missing API base URL or API key for document count.');
            return null;
        }

        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . self::getApiKey(),
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
            'timeout' => 10,
        ]);

        if (is_wp_error($response)) {
            //error_log('[SearchJet] WP Error during document count fetch: ' . $response->get_error_message());
            return null;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($code !== 200) {
            //error_log("[SearchJet] Document count fetch failed with status: $code, body: $body");
            return null;
        }

        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE || !isset($data['total_indexed_documents'])) {
            //error_log('[SearchJet] Invalid JSON or missing count in document count response.');
            return null;
        }

        set_transient('searchjet_document_count', (int) $data['total_indexed_documents'], 24 * HOUR_IN_SECONDS);
        return (int) $data['total_indexed_documents'];
    }
    /**
     * Fetches search analytics data from the SearchJet API.
     *
     * @return array|null
     */
    public static function fetchSearchAnalytics() {
        // $cached = get_transient('searchjet_search_analytics');
        // if ($cached !== false) {
        //     return $cached;
        // }

        $url = self::getApiBaseUrl() . '/v1/search-analytics';

        if (empty($url) || empty(self::getApiKey())) {
            return null;
        }

        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . self::getApiKey(),
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
            'timeout' => 10,
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($code !== 200) {
            return null;
        }

        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            return null;
        }

        //set_transient('searchjet_search_analytics', $data, 12 * HOUR_IN_SECONDS);
        return $data;
    }
}