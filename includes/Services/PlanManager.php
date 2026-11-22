<?php
/**
 * Plan Manager class for SearchJet plugin.
 *
 * @package SearchJet
 * @since 1.3.3
 */

namespace SearchJet\Services;

use SearchJet\API\SearchJetRemoteClient;

/**
 * Plan Manager - Single Source of Truth from SearchJet Backend
 *
 * All plan limits and features come from the client-info endpoint.
 * This prevents tampering and ensures consistency with the backend.
 */
class PlanManager {

	/**
	 * Get current client info from SearchJet backend.
	 *
	 * @param bool $force_refresh Force refresh from API.
	 * @return array|null Client info or null if unavailable.
	 */
	public static function get_client_info( $force_refresh = false ) {
		$info = SearchJetRemoteClient::fetchClientInfo( null, $force_refresh );
		if ( $info === null ) {
			$info = get_option( 'searchjet_client_info', null );
		}
		return $info;
	}

	/**
	 * Get current plan name.
	 *
	 * @return string Plan name (free, pro, growth, enterprise).
	 */
	public static function get_current_plan() {
		$client_info = self::get_client_info();
		if ( ! $client_info ) {
			return 'free';
		}
		return isset( $client_info['plan'] ) ? $client_info['plan'] : 'free';
	}

	/**
	 * Get plan limits from backend (cached).
	 *
	 * @return array Plan limits.
	 */
	public static function get_plan_limits() {
		$client_info = self::get_client_info();

		if ( $client_info && isset( $client_info['plan_limits'] ) ) {
			return $client_info['plan_limits'];
		}

		// Fallback: limits may be in usage_limits (current API shape)
		if ( $client_info && isset( $client_info['usage_limits'] ) ) {
			$limits = $client_info['usage_limits'];
			// Extract only the limit keys (not usage counters)
			$limit_keys = [ 'search_queries_per_month', 'max_indexed_pages', 'reindexing_per_day', 'ai_answers_per_month', 'analytics_retention_days', 'max_api_keys_per_site' ];
			$plan = [];
			foreach ( $limit_keys as $key ) {
				if ( isset( $limits[ $key ] ) ) {
					$plan[ $key ] = $limits[ $key ];
				}
			}
			// Check if this plan has semantic search
			$plan['semantic_search'] = $client_info['plan'] === 'pro' || $client_info['plan'] === 'enterprise';
			return $plan;
		}

		// Ultimate fallback to free plan defaults if backend unavailable.
		return self::get_free_plan_defaults();
	}

	/**
	 * Check if a feature is available in current plan.
	 *
	 * @param string $feature Feature key.
	 * @return bool
	 */
	public static function has_feature( $feature ) {
		$limits = self::get_plan_limits();

		switch ( $feature ) {
			case 'ai_answers':
				return isset( $limits['ai_answers_per_month'] ) && $limits['ai_answers_per_month'] > 0;

			case 'semantic_search':
				return ! empty( $limits['semantic_search'] );

			case 'priority_support':
				return ! empty( $limits['priority_support'] );

			case 'multisite':
				$multisite_limit = isset( $limits['multisite_support'] ) ? $limits['multisite_support'] : 0;
				return -1 === $multisite_limit || $multisite_limit > 0;

			default:
				return ! empty( $limits[ $feature ] );
		}
	}

	/**
	 * Get limit value for a feature.
	 *
	 * @param string $feature Feature key with _per_month or _per_day suffix.
	 * @return int Limit (-1 = unlimited, 0 = none).
	 */
	public static function get_limit( $feature ) {
		$limits = self::get_plan_limits();
		return isset( $limits[ $feature ] ) ? $limits[ $feature ] : 0;
	}

	/**
	 * Get current usage for a feature.
	 *
	 * @param string $feature Feature key (ai_answers, search_queries, etc.).
	 * @return int Current usage.
	 */
	public static function get_usage( $feature ) {
		$period = gmdate( 'Y-m' ); // Monthly period.

		switch ( $feature ) {
			case 'ai_answers':
				return (int) get_option( "searchjet_usage_{$feature}_{$period}", 0 );

			case 'search_queries':
				return (int) get_option( "searchjet_usage_{$feature}_{$period}", 0 );

			case 'indexed_pages':
				$count = SearchJetRemoteClient::fetchDocumentCount();
				if ( null !== $count ) {
					return (int) $count;
				}
				return 0;

			default:
				return 0;
		}
	}

	/**
	 * Increment usage counter for a feature.
	 *
	 * @param string $feature Feature key.
	 * @param int    $amount  Amount to increment (default: 1).
	 * @return int New usage value.
	 */
	public static function increment_usage( $feature, $amount = 1 ) {
		$period = gmdate( 'Y-m' );
		$key    = "searchjet_usage_{$feature}_{$period}";

		// Use WordPress atomic increment to prevent race conditions.
		$current   = (int) get_option( $key, 0 );
		$new_value = $current + $amount;

		// Attempt atomic update with retry logic.
		$updated = update_option( $key, $new_value, false ); // Don't autoload usage counters.
		if ( ! $updated ) {
			// Retry once if update failed.
			$current   = (int) get_option( $key, 0 );
			$new_value = $current + $amount;
			update_option( $key, $new_value, false );
		}

		return $new_value;
	}

	/**
	 * Check if quota is exceeded for a feature.
	 *
	 * @param string $feature Feature key.
	 * @return bool True if quota exceeded.
	 */
	public static function quota_exceeded( $feature ) {
		// Handle special case for indexed_pages.
		if ( 'indexed_pages' === $feature ) {
			$limit = self::get_limit( 'max_indexed_pages' );
		} else {
			$feature_key = $feature . '_per_month';
			$limit       = self::get_limit( $feature_key );
		}

		// -1 means unlimited.
		if ( -1 === $limit ) {
			return false;
		}

		// 0 means feature not available.
		if ( 0 === $limit ) {
			return true;
		}

		$usage = self::get_usage( $feature );
		return $usage >= $limit;
	}

	/**
	 * Get remaining quota for a feature.
	 *
	 * @param string $feature Feature key.
	 * @return int Remaining (-1 = unlimited).
	 */
	public static function get_remaining( $feature ) {
		// Handle special case for indexed_pages.
		if ( 'indexed_pages' === $feature ) {
			$limit = self::get_limit( 'max_indexed_pages' );
		} else {
			$feature_key = $feature . '_per_month';
			$limit       = self::get_limit( $feature_key );
		}

		if ( -1 === $limit ) {
			return -1; // Unlimited.
		}

		$usage = self::get_usage( $feature );
		return max( 0, $limit - $usage );
	}

	/**
	 * Get usage percentage for a feature.
	 *
	 * @param string $feature Feature key.
	 * @return float Percentage (0-100), or -1 if unlimited.
	 */
	public static function get_usage_percentage( $feature ) {
		// Handle special case for indexed_pages.
		if ( 'indexed_pages' === $feature ) {
			$limit = self::get_limit( 'max_indexed_pages' );
		} else {
			$feature_key = $feature . '_per_month';
			$limit       = self::get_limit( $feature_key );
		}

		if ( -1 === $limit || 0 === $limit ) {
			return -1;
		}

		$usage = self::get_usage( $feature );
		return min( 100, ( $usage / $limit ) * 100 );
	}

	/**
	 * Get all features with their status.
	 *
	 * @return array Features array with usage and limits.
	 */
	public static function get_all_features() {
		$limits = self::get_plan_limits();
		$plan   = self::get_current_plan();

		$features = array();

		// Pre-calculate usage values to avoid redundant calls.
		$search_usage  = self::get_usage( 'search_queries' );
		$indexed_usage = self::get_usage( 'indexed_pages' );
		$ai_usage      = self::get_usage( 'ai_answers' );

		// Search Queries.
		if ( isset( $limits['search_queries_per_month'] ) ) {
			$features['search_queries'] = array(
				'name'       => 'Search Queries',
				'key'        => 'search_queries',
				'limit'      => $limits['search_queries_per_month'],
				'used'       => $search_usage,
				'remaining'  => self::calculate_remaining( $search_usage, $limits['search_queries_per_month'] ),
				'percentage' => self::calculate_percentage( $search_usage, $limits['search_queries_per_month'] ),
				'unlimited'  => -1 === $limits['search_queries_per_month'],
				'available'  => true,
			);
		}

		// Indexed Pages.
		if ( isset( $limits['max_indexed_pages'] ) ) {
			$features['indexed_pages'] = array(
				'name'       => 'Indexed Pages',
				'key'        => 'indexed_pages',
				'limit'      => $limits['max_indexed_pages'],
				'used'       => $indexed_usage,
				'remaining'  => self::calculate_remaining( $indexed_usage, $limits['max_indexed_pages'] ),
				'percentage' => self::calculate_percentage( $indexed_usage, $limits['max_indexed_pages'] ),
				'unlimited'  => -1 === $limits['max_indexed_pages'],
				'available'  => true,
			);
		}

		// AI Answers.
		if ( isset( $limits['ai_answers_per_month'] ) ) {
			$features['ai_answers'] = array(
				'name'           => 'AI Answers',
				'key'            => 'ai_answers',
				'limit'          => $limits['ai_answers_per_month'],
				'used'           => $ai_usage,
				'remaining'      => self::calculate_remaining( $ai_usage, $limits['ai_answers_per_month'] ),
				'percentage'     => self::calculate_percentage( $ai_usage, $limits['ai_answers_per_month'] ),
				'unlimited'      => -1 === $limits['ai_answers_per_month'],
				'available'      => true,
				'quota_exceeded' => $ai_usage >= $limits['ai_answers_per_month'] && -1 !== $limits['ai_answers_per_month'],
			);
		} else {
			// Feature not available in plan.
			$features['ai_answers'] = array(
				'name'          => 'AI Answers',
				'key'           => 'ai_answers',
				'available'     => false,
				'required_plan' => 'pro',
			);
		}

		// Semantic Search.
		$features['semantic_search'] = array(
			'name'          => 'Semantic Search',
			'key'           => 'semantic_search',
			'available'     => ! empty( $limits['semantic_search'] ),
			'required_plan' => empty( $limits['semantic_search'] ) ? 'pro' : null,
		);

		// Priority Support.
		$features['priority_support'] = array(
			'name'          => 'Priority Support',
			'key'           => 'priority_support',
			'available'     => ! empty( $limits['priority_support'] ),
			'sla_hours'     => isset( $limits['support_sla_hours'] ) ? $limits['support_sla_hours'] : 48,
			'required_plan' => empty( $limits['priority_support'] ) ? 'pro' : null,
		);

		// Analytics Retention.
		if ( isset( $limits['analytics_retention_days'] ) ) {
			$features['analytics_retention'] = array(
				'name'      => 'Analytics Retention',
				'key'       => 'analytics_retention',
				'limit'     => $limits['analytics_retention_days'],
				'display'   => self::format_days( $limits['analytics_retention_days'] ),
				'unlimited' => -1 === $limits['analytics_retention_days'],
				'available' => true,
			);
		}

		return $features;
	}

	/**
	 * Calculate remaining quota (helper method).
	 *
	 * @param int $usage Current usage.
	 * @param int $limit Limit value.
	 * @return int Remaining quota.
	 */
	private static function calculate_remaining( $usage, $limit ) {
		if ( -1 === $limit ) {
			return -1; // Unlimited.
		}
		return max( 0, $limit - $usage );
	}

	/**
	 * Calculate usage percentage (helper method).
	 *
	 * @param int $usage Current usage.
	 * @param int $limit Limit value.
	 * @return float Percentage or -1 if unlimited.
	 */
	private static function calculate_percentage( $usage, $limit ) {
		if ( -1 === $limit || 0 === $limit ) {
			return -1;
		}
		return min( 100, ( $usage / $limit ) * 100 );
	}

	/**
	 * Get minimum required plan for a feature.
	 *
	 * @param string $feature Feature key.
	 * @return string|null Plan name or null if available in current plan.
	 */
	public static function get_required_plan( $feature ) {
		if ( self::has_feature( $feature ) ) {
			return null;
		}

		// Derive required plan from backend limits instead of hardcoded mapping.
		$limits = self::get_plan_limits();

		// Check if feature exists in any higher tier by examining limits.
		switch ( $feature ) {
			case 'semantic_search':
				return empty( $limits['semantic_search'] ) ? 'pro' : null;
			case 'priority_support':
				return empty( $limits['priority_support'] ) ? 'pro' : null;
			case 'ai_answers':
				return ( ! isset( $limits['ai_answers_per_month'] ) || 0 === $limits['ai_answers_per_month'] ) ? 'free' : null;
			default:
				return 'pro'; // Default fallback.
		}
	}

	/**
	 * Get upgrade URL for plan comparison.
	 *
	 * @return string URL to pricing page.
	 */
	public static function get_upgrade_url() {
		$client_info = self::get_client_info();
		$site_id     = isset( $client_info['site_id'] ) ? $client_info['site_id'] : '';

		// Link to SearchJet dashboard pricing with site context.
		return 'https://app.searchjetengine.com/pricing?site=' . urlencode( $site_id );
	}

	/**
	 * Format days (-1 = unlimited).
	 *
	 * @param int $days Number of days.
	 * @return string Formatted string.
	 */
	private static function format_days( $days ) {
		if ( -1 === $days ) {
			return 'Unlimited';
		}
		return $days . ' ' . _n( 'day', 'days', $days, 'searchjet-instant-search' );
	}

	/**
	 * Fallback free plan defaults (used when backend unavailable).
	 *
	 * @return array Default free plan limits.
	 */
	private static function get_free_plan_defaults() {
		return array(
			'search_queries_per_month'   => 10000,
			'max_indexed_pages'          => 1000,
			'max_api_keys_per_site'      => 1, // One API key per site.
			'analytics_retention_days'   => 7,
			'reindexing_per_day'         => 1,
			'api_access'                 => 'shared',
			'support'                    => 'community',
			'support_sla_hours'          => 48,
			'multisite_support'          => 1,
			'priority_support'           => false,
			'semantic_search'            => false,
			'ai_answers_per_month'       => 50,
		);
	}

	/**
	 * Clear cached plan info.
	 */
	public static function clear_cache() {
		$api_key = get_option( 'searchjet_hosted_api_key', '' );
		if ( $api_key ) {
			$cache_key = 'searchjet_client_info_' . hash( 'sha256', $api_key );
			delete_transient( $cache_key );
		}
		delete_option( 'searchjet_client_info' );
	}

	/**
	 * Get usage limits from transient, falling back to client_info option.
	 *
	 * @return array
	 */
	public static function get_usage_limits() {
		$limits = get_transient( 'searchjet_usage_limits' ) ?: [];
		$client_info = get_option( 'searchjet_client_info', [] );
		if ( empty( $limits ) && ! empty( $client_info['usage_limits'] ) ) {
			$limits = $client_info['usage_limits'];
		}
		return $limits;
	}

	/**
	 * Get effective number of reindexes used today.
	 *
	 * The backend's `reindexes_today` counter is used as the source of truth,
	 * but some backends fail to reset it at midnight (the counter keeps a stale
	 * value for days), which wrongly exhausts the daily limit. To stay correct,
	 * the backend count only counts toward today's limit when the last full
	 * reindex (`last_reindexed`) actually happened today. A local per-day
	 * counter is kept as a reliable fallback that always resets at UTC midnight.
	 *
	 * @return int
	 */
	public static function get_reindexes_today() {
		$limits = self::get_usage_limits();

		$api_count = (int) ( $limits['reindexes_today'] ?? 0 );
		$last_reindexed = (string) ( $limits['last_reindexed'] ?? '' );
		$today = gmdate( 'Y-m-d' );

		// Only trust the backend counter when the last reindex happened today.
		if ( $api_count > 0 && substr( $last_reindexed, 0, 10 ) !== $today ) {
			$api_count = 0;
		}

		// Local per-day counter, always reset at UTC midnight.
		$local = get_option( 'searchjet_reindex_daily', array( 'date' => '', 'count' => 0 ) );
		$local_count = ( (string) ( $local['date'] ?? '' ) === $today ) ? (int) ( $local['count'] ?? 0 ) : 0;

		return max( 0, $api_count, $local_count );
	}

	/**
	 * Record that a full reindex was performed today (local per-day counter).
	 */
	public static function record_reindex_today() {
		$today = gmdate( 'Y-m-d' );
		$local = get_option( 'searchjet_reindex_daily', array( 'date' => '', 'count' => 0 ) );
		$local_count = ( (string) ( $local['date'] ?? '' ) === $today ) ? (int) ( $local['count'] ?? 0 ) : 0;
		update_option( 'searchjet_reindex_daily', array( 'date' => $today, 'count' => $local_count + 1 ), false );
	}

	/**
	 * Get plan display name.
	 *
	 * @param string|null $plan Plan key (null = current plan).
	 * @return string Display name.
	 */
	public static function get_plan_display_name( $plan = null ) {
		$plan = $plan ? $plan : self::get_current_plan();

		$names = array(
			'free'       => __( 'Free', 'searchjet-instant-search' ),
			'pro'        => __( 'Pro', 'searchjet-instant-search' ),
			'growth'     => __( 'Growth', 'searchjet-instant-search' ),
			'enterprise' => __( 'Enterprise', 'searchjet-instant-search' ),
		);

		return isset( $names[ $plan ] ) ? $names[ $plan ] : ucfirst( $plan );
	}

	/**
	 * Check if current plan can upgrade to target plan.
	 *
	 * @param string $target_plan Target plan.
	 * @return bool
	 */
	public static function can_upgrade_to( $target_plan ) {
		$current = self::get_current_plan();

		$hierarchy = array(
			'free'       => 0,
			'pro'        => 1,
			'growth'     => 2,
			'enterprise' => 3,
		);

		$current_level = isset( $hierarchy[ $current ] ) ? $hierarchy[ $current ] : 0;
		$target_level  = isset( $hierarchy[ $target_plan ] ) ? $hierarchy[ $target_plan ] : 0;

		// Validate that target plan exists.
		if ( ! isset( $hierarchy[ $target_plan ] ) ) {
			return false; // Unknown target plan.
		}

		return $target_level > $current_level;
	}
}
