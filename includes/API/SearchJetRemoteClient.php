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

    public static function fetchClientInfo($apiKey = null, $forceRefresh = false) {
        $apiKey = $apiKey ?? self::getApiKey();
        
        if (empty($apiKey)) {
            return null;
        }
        
        // Check cache first unless force refresh
        $cache_key = 'searchjet_client_info_' . md5($apiKey);
        if (!$forceRefresh) {
            $cached = get_transient($cache_key);
            if ($cached !== false) {
                return $cached;
            }
        }

        $url = self::getApiBaseUrl() . '/v1/client-info';
        
        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
                'Cache-Control' => 'no-cache',
            ],
            'timeout' => 20,
        ]);

        if (is_wp_error($response)) {
            error_log('[SearchJet] fetchClientInfo WP_Error: ' . $response->get_error_message());
            delete_transient($cache_key);
            return null;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($code === 401 || $code === 403) {
            error_log('[SearchJet] fetchClientInfo auth failure: HTTP ' . $code);
            delete_transient($cache_key);
            return null;
        }

        if ($code !== 200) {
            error_log('[SearchJet] fetchClientInfo non-200: HTTP ' . $code . ' body=' . substr($body, 0, 200));
            delete_transient($cache_key);
            return null;
        }

        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            error_log('[SearchJet] fetchClientInfo bad JSON: error=' . json_last_error_msg() . ' body=' . substr($body, 0, 200));
            delete_transient($cache_key);
            return null;
        }

        // Cache for 1 minute
        set_transient($cache_key, $data, 60);
        error_log('[SearchJet] fetchClientInfo OK. usage_limits keys: ' . (isset($data['usage_limits']) ? implode(', ', array_keys($data['usage_limits'])) : 'NONE'));
        error_log('[SearchJet] fetchClientInfo OK. plan_limits keys: ' . (isset($data['plan_limits']) ? implode(', ', array_keys($data['plan_limits'])) : 'NONE'));
        return $data;
    }

    public static function getTaskStatus($taskUid) {
        $url = self::getApiBaseUrl() . '/v1/tasks/' . $taskUid;

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
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return $data;
    }

    public static function deleteDocument($payload = []) {
        $url = self::getApiBaseUrl() . '/v1/index/delete';

        if (empty($url) || empty(self::getApiKey())) {
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
            return null;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($code !== 200) {
            return null;
        }

        return json_decode($body, true);
    }
    public static function indexDocument($payload = []) {
        $url = self::getApiBaseUrl() . '/v1/index/bulk';

        if (empty($url) || empty(self::getApiKey())) {
            return null;
        }

        // Ensure payload matches API spec
        $formatted_payload = [
            'documents' => isset($payload['documents']) ? $payload['documents'] : [$payload],
            'full_reindex' => $payload['full_reindex'] ?? false
        ];

        $response = wp_remote_post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . self::getApiKey(),
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
            'body'    => json_encode($formatted_payload),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($code !== 200) {
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
        if ($cached !== false && $cached !== null) {
            error_log('[SearchJet] fetchDocumentCount: returning cached value=' . $cached);
            return (int) $cached;
        }

        $url = self::getApiBaseUrl() . '/v1/index/count';
        $api_key = self::getApiKey();
        error_log('[SearchJet] fetchDocumentCount: url=' . $url . ' api_key=' . (!empty($api_key) ? 'SET' : 'EMPTY'));

        if (empty($url) || empty($api_key)) {
            error_log('[SearchJet] fetchDocumentCount: returning null (empty url or key)');
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
            error_log('[SearchJet] fetchDocumentCount: bad JSON error=' . json_last_error_msg());
            return null;
        }

        $count = $data['total_indexed_documents'] ?? $data['numberOfDocuments'] ?? $data['total'] ?? $data['count'] ?? null;
        if ($count === null) {
            error_log('[SearchJet] fetchDocumentCount: no known key found. Response keys: ' . implode(', ', array_keys($data)));
            error_log('[SearchJet] fetchDocumentCount: full response: ' . substr($body, 0, 500));
            return null;
        }

        $count = (int) $count;
        set_transient('searchjet_document_count', $count, 5 * MINUTE_IN_SECONDS);
        return $count;
    }
    /**
     * Fetches search analytics data from the SearchJet API.
     *
     * @return array|null
     */
    public static function fetchSearchAnalytics() {
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
            'timeout' => 15,
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

        return $data;
    }

    public static function recordSearchAnalytics($logs) {
        $url = self::getApiBaseUrl() . '/v1/search-analytics';

        if (empty($url) || empty(self::getApiKey())) {
            return null;
        }

        $payload = is_array($logs) && isset($logs[0]) ? ['logs' => $logs] : $logs;

        $response = wp_remote_post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . self::getApiKey(),
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
            'body'    => json_encode($payload),
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        $code = wp_remote_retrieve_response_code($response);
        return $code === 200;
    }

    /**
     * Records click analytics to the SearchJet API.
     *
     * @param array $click_data Click event data
     * @return bool True on success, false on failure
     */
    public static function recordClickAnalytics($click_data) {
        $url = self::getApiBaseUrl() . '/v1/analytics/click';

        if (empty($url) || empty(self::getApiKey())) {
            return false;
        }

        $response = wp_remote_post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . self::getApiKey(),
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
            'body'    => json_encode($click_data),
            'timeout' => 10,
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        $code = wp_remote_retrieve_response_code($response);
        return $code === 200;
    }

    /**
     * Performs a comprehensive search with advanced filtering and AI options.
     *
     * @param string $query Search query
     * @param array $params Additional parameters:
     *   - limit: Number of results (default: 20, max: 100)
     *   - page: Page number (alternative to offset, default: 1)
     *   - offset: Results offset for pagination
     *   - semantic: Enable AI-powered semantic search (bool)
     *   - semanticRatio: Ratio of semantic vs keyword (0.0-1.0, default: 0.5)
     *   - ai_answer: Generate AI answer from results (bool)
     *   - filters: Meilisearch filter expressions (string)
     *   - sort: Sort criteria (array or string)
     *   - facets: Faceted search attributes (array)
     *   - attributes: Attributes to retrieve (array)
     *   - attributesToHighlight: Attributes to highlight (array)
     *   - attributesToCrop: Attributes to crop (array)
     *   - cropLength: Length of cropped snippets (int)
     *   - highlightPreTag: HTML tag for highlight start (default: <em>)
     *   - highlightPostTag: HTML tag for highlight end (default: </em>)
     *   - typoTolerance: 'strict' or 'min' (string)
     *   - showMatchesPosition: Show match positions (bool)
     *   - matchingStrategy: 'last' or 'all' (string)
     *   - debug: Include debug info (bool, dev only)
     * @return array|null Search results or null on failure
     */
    public static function search($query, $params = []) {
        $url = self::getApiBaseUrl() . '/v1/search';

        if (empty($url) || empty(self::getApiKey())) {
            return null;
        }

        // Build query parameters with all supported options
        $query_params = [
            'q' => $query,
        ];

        // Pagination
        if (isset($params['offset'])) {
            $query_params['offset'] = (int) $params['offset'];
        } elseif (isset($params['page'])) {
            $query_params['page'] = max(1, (int) $params['page']);
        }

        $query_params['limit'] = min(100, max(1, (int) ($params['limit'] ?? 20)));

        // Semantic search
        if (isset($params['semantic']) && $params['semantic']) {
            $query_params['semantic'] = 'true';

            if (isset($params['semanticRatio'])) {
                $ratio = (float) $params['semanticRatio'];
                $query_params['semanticRatio'] = max(0.0, min(1.0, $ratio));
            }
        }

        // AI answer generation
        if (isset($params['ai_answer']) && $params['ai_answer']) {
            $query_params['ai_answer'] = 'true';
        }

        // Filtering
        if (!empty($params['filters'])) {
            $query_params['filters'] = (string) $params['filters'];
        }

        // Sorting
        if (!empty($params['sort'])) {
            if (is_array($params['sort'])) {
                $query_params['sort'] = implode(',', $params['sort']);
            } else {
                $query_params['sort'] = (string) $params['sort'];
            }
        }

        // Facets
        if (!empty($params['facets']) && is_array($params['facets'])) {
            $query_params['facets'] = implode(',', array_filter($params['facets']));
        }

        // Attributes to retrieve
        if (!empty($params['attributes']) && is_array($params['attributes'])) {
            $query_params['attributes'] = implode(',', array_filter($params['attributes']));
        }

        // Highlighting
        if (!empty($params['attributesToHighlight']) && is_array($params['attributesToHighlight'])) {
            $query_params['attributesToHighlight'] = implode(',', array_filter($params['attributesToHighlight']));
        }

        if (!empty($params['attributesToCrop']) && is_array($params['attributesToCrop'])) {
            $query_params['attributesToCrop'] = implode(',', array_filter($params['attributesToCrop']));
        }

        if (isset($params['cropLength'])) {
            $query_params['cropLength'] = max(10, (int) $params['cropLength']);
        }

        if (!empty($params['highlightPreTag'])) {
            $query_params['highlightPreTag'] = (string) $params['highlightPreTag'];
        }

        if (!empty($params['highlightPostTag'])) {
            $query_params['highlightPostTag'] = (string) $params['highlightPostTag'];
        }

        // Typo tolerance
        if (!empty($params['typoTolerance'])) {
            $tolerance = (string) $params['typoTolerance'];
            if (in_array($tolerance, ['strict', 'min'], true)) {
                $query_params['typoTolerance'] = $tolerance;
            }
        }

        // Match position
        if (isset($params['showMatchesPosition']) && $params['showMatchesPosition']) {
            $query_params['showMatchesPosition'] = 'true';
        }

        // Matching strategy
        if (!empty($params['matchingStrategy'])) {
            $strategy = (string) $params['matchingStrategy'];
            if (in_array($strategy, ['last', 'all'], true)) {
                $query_params['matchingStrategy'] = $strategy;
            }
        }

        // Debug mode (development only)
        if (defined('WP_DEBUG') && WP_DEBUG && isset($params['debug']) && $params['debug']) {
            $query_params['debug'] = 'true';
        }

        $url_with_params = add_query_arg($query_params, $url);

        $response = wp_remote_get($url_with_params, [
            'headers' => [
                'Authorization' => 'Bearer ' . self::getApiKey(),
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
            'timeout' => 30, // Longer timeout for AI processing
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

        return $data;
    }

    /**
     * Get available subscription plans from the backend.
     * Used to dynamically display pricing in the plugin UI.
     *
     * @return array|null Array of plans or null on failure
     */
    public static function getPlans() {
        $url = self::getApiBaseUrl() . '/v1/plans';

        if (empty($url) || empty(self::getApiKey())) {
            return null;
        }

        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . self::getApiKey(),
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
            'timeout' => 15,
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

        return $data;
    }

    /**
     * Get subscription status for the current site.
     *
     * @return array|null Subscription status or null on failure
     */
    public static function getSubscriptionStatus() {
        $url = self::getApiBaseUrl() . '/v1/subscription/status';

        if (empty($url) || empty(self::getApiKey())) {
            return null;
        }

        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . self::getApiKey(),
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
            'timeout' => 15,
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

        return $data;
    }

    /**
     * Get system status.
     *
     * @return array|null System status or null on failure
     */
    public static function getSystemStatus() {
        $url = self::getApiBaseUrl() . '/v1/status';

        $response = wp_remote_get($url, [
            'headers' => [
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

        return $data;
    }

    /**
     * Get API key information (debug endpoint).
     *
     * @return array|null Key info or null on failure
     */
    public static function getKeyInfo() {
        $url = self::getApiBaseUrl() . '/v1/site/key-info';

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

        return $data;
    }

    /**
     * Generate AI answer or summary.
     *
     * @param string $prompt The prompt/question for AI
     * @param array $context Optional context documents
     * @return array|null Response with answer or generation ID
     */
    public static function generateAI($prompt, $context = []) {
        $url = self::getApiBaseUrl() . '/v1/ai/generate';

        if (empty($url) || empty(self::getApiKey())) {
            return null;
        }

        $payload = ['prompt' => $prompt];
        if (!empty($context)) {
            $payload['context'] = $context;
        }

        $response = wp_remote_post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . self::getApiKey(),
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
            'body'    => json_encode($payload),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($code !== 200 && $code !== 202) {
            return [
                'success' => false,
                'message' => 'AI generation failed',
                'code' => $code,
            ];
        }

        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return $data;
    }

    /**
     * Get AI generation status.
     *
     * @param string $generation_id The generation ID
     * @return array|null Status information or null on failure
     */
    public static function getAIStatus($generation_id) {
        $url = self::getApiBaseUrl() . '/v1/ai/status/' . $generation_id;

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
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return $data;
    }

    /**
     * Trigger web scraping for a URL.
     *
     * @param string $url URL to scrape
     * @param array $options Scraping options
     * @return array|null Response or null on failure
     */
    public static function triggerScrape($url, $options = []) {
        $endpoint = self::getApiBaseUrl() . '/v1/scrape/trigger';

        if (empty($endpoint) || empty(self::getApiKey())) {
            return null;
        }

        $payload = array_merge(['url' => $url], $options);

        $response = wp_remote_post($endpoint, [
            'headers' => [
                'Authorization' => 'Bearer ' . self::getApiKey(),
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
            'body'    => json_encode($payload),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($code !== 200 && $code !== 202) {
            return [
                'success' => false,
                'message' => 'Scraping trigger failed',
                'code' => $code,
            ];
        }

        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return $data;
    }

    /**
     * Get web scraping status.
     *
     * @return array|null Scraping status or null on failure
     */
    public static function getScrapeStatus() {
        $url = self::getApiBaseUrl() . '/v1/scrape/status';

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
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return $data;
    }

    /**
     * Get scraped pages.
     *
     * @param array $params Query parameters (limit, offset, etc.)
     * @return array|null Pages data or null on failure
     */
    public static function getScrapePages($params = []) {
        $url = self::getApiBaseUrl() . '/v1/scrape/pages';

        if (empty($url) || empty(self::getApiKey())) {
            return null;
        }

        if (!empty($params)) {
            $url = add_query_arg($params, $url);
        }

        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . self::getApiKey(),
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
            'timeout' => 15,
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
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return $data;
    }

    /**
     * Get index settings from the SearchJet API.
     *
     * @return array|null Index settings or null on failure
     */
    public static function getIndexSettings() {
        $url = self::getApiBaseUrl() . '/v1/index/settings';

        if (empty($url) || empty(self::getApiKey())) {
            return null;
        }

        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . self::getApiKey(),
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($code !== 200) {
            // 404 means no settings document exists yet (fresh index / shared index).
            if ($code === 404) {
                return [];
            }
            return null;
        }

        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            return null;
        }

        return $data;
    }

    /**
     * Update index settings via the SearchJet API.
     *
     * @param array $settings Settings to update
     * @return array|null Response with taskUid or null on failure
     */
    public static function updateIndexSettings($settings) {
        $url = self::getApiBaseUrl() . '/v1/index/settings';

        if (empty($url) || empty(self::getApiKey())) {
            return null;
        }

        $response = wp_remote_request($url, [
            'method'  => 'POST',
            'headers' => [
                'Authorization' => 'Bearer ' . self::getApiKey(),
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
            'body'    => json_encode($settings),
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($code !== 200 && $code !== 202) {
            return null;
        }

        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return $data;
    }

    /**
     * Get index statistics from the SearchJet API.
     *
     * @return array|null Index stats or null on failure
     */
    public static function getIndexStats() {
        $url = self::getApiBaseUrl() . '/v1/index/stats';

        if (empty($url) || empty(self::getApiKey())) {
            return null;
        }

        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . self::getApiKey(),
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
            'timeout' => 15,
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

        return $data;
    }

    /**
     * Update scraper settings.
     *
     * @param array $settings Scraper settings
     * @return array|null Response or null on failure
     */
    public static function updateScrapeSettings($settings) {
        $url = self::getApiBaseUrl() . '/v1/scrape/settings';

        if (empty($url) || empty(self::getApiKey())) {
            return null;
        }

        $response = wp_remote_post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . self::getApiKey(),
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
            'body'    => json_encode($settings),
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($code !== 200) {
            return [
                'success' => false,
                'message' => 'Settings update failed',
                'code' => $code,
            ];
        }

        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return $data;
    }
}