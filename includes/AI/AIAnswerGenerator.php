<?php
namespace SearchJet\AI;

/**
 * AI Answer Generator
 * Generates AI-powered search answers using SearchJet backend
 */
class AIAnswerGenerator {

    /**
     * Generate an AI answer for a search query
     *
     * @param string $query Search query
     * @param array $results Search results to use as context
     * @param array $params Additional parameters
     * @return string|null AI generated answer or null
     */
    public static function generateAnswer($query, $results = [], $params = []) {
        // Check if AI answers are enabled
        if (!get_option('searchjet_ai_answers_enabled', false)) {
            return null;
        }

        // Check usage limits (free plan: 50/month, pro: unlimited)
        if (!self::checkUsageLimit()) {
            return null;
        }

        // Try to get from cache first
        $cache_key = 'searchjet_ai_answer_' . md5($query);
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        // Call SearchJet API for AI answer
        $answer = self::requestAIAnswer($query, $results, $params);

        if ($answer) {
            // Increment usage counter
            self::incrementUsage();

            // Cache for 1 hour
            set_transient($cache_key, $answer, HOUR_IN_SECONDS);
        }

        return $answer;
    }

    /**
     * Request AI answer from SearchJet backend via search API
     * The AI answer comes within the search response when ai_answer=true
     *
     * @param string $query
     * @param array $results Not used - backend generates context internally
     * @param array $params
     * @return string|null
     */
    protected static function requestAIAnswer($query, $results, $params) {
        // Use the existing search method with ai_answer parameter
        $search_response = \SearchJet\API\SearchJetRemoteClient::search($query, [
            'ai_answer' => true,
            'limit' => 5, // Only need a few results for context
        ]);

        if (!$search_response || !is_array($search_response)) {
            return null;
        }

        // The answer field is included in the search response
        if (isset($search_response['answer']) && !empty($search_response['answer'])) {
            return sanitize_text_field($search_response['answer']);
        }

        // If no answer in response, could be due to quota or feature not available
        return null;
    }

    /**
     * Check if user has remaining AI answer quota
     *
     * @return bool
     */
    protected static function checkUsageLimit() {
        return !\SearchJet\Services\PlanManager::quota_exceeded('ai_answers');
    }

    /**
     * Increment AI answer usage counter
     */
    protected static function incrementUsage() {
        \SearchJet\Services\PlanManager::increment_usage('ai_answers');
    }

    /**
     * Get current usage stats
     *
     * @return array
     */
    public static function getUsageStats() {
        $plan = \SearchJet\Services\PlanManager::get_current_plan();
        $limit = \SearchJet\Services\PlanManager::get_limit('ai_answers_per_month');
        $used = \SearchJet\Services\PlanManager::get_usage('ai_answers');
        $remaining = \SearchJet\Services\PlanManager::get_remaining('ai_answers');

        return [
            'used' => $used,
            'limit' => $limit,
            'remaining' => $remaining,
            'plan' => $plan,
            'month' => date('Y-m'),
            'percentage' => \SearchJet\Services\PlanManager::get_usage_percentage('ai_answers'),
        ];
    }
}
