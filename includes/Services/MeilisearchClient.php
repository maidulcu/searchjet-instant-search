<?php
/**
 * MeilisearchClient class for SearchJet plugin.
 *
 * @package SearchJet
 * @since 1.3.3
 */

namespace SearchJet\Services;

/**
 * NOTE: This class now supports user-defined Meilisearch host URLs.
 * Users can configure their own Meilisearch host and API key from the plugin settings page.
 * Later phases will add an option for users to choose between their own host or a SearchJet-hosted API.
 *
 * This client assumes the index already exists on the Meilisearch server.
 * Index creation is managed exclusively by the SearchJet SaaS backend.
 */
class MeilisearchClient {
	/**
	 * API key for authentication.
	 *
	 * @var string
	 */
	private $api_key;

	/**
	 * Host URL for API requests.
	 *
	 * @var string
	 */
	private $host_url;

	/**
	 * Initialize the SaaSClient with API credentials from WordPress options.
	 */
	public function __construct() {
		$this->api_key  = get_option( 'searchjet_hosted_api_key' );
		$this->host_url = rtrim( SEARCHJET_API_BASE_URL, '/' ) . '/api';

		// Validate API key on construction.
		if ( empty( $this->api_key ) ) {
			error_log( '[SearchJet] Warning: API key is not configured. All API operations will fail.' );
		}
	}

	/**
	 * Check if the client is properly configured.
	 *
	 * @return bool True if configured, false otherwise.
	 */
	private function is_configured() {
		return ! empty( $this->api_key );
	}

	/**
	 * Send a single post to the SearchJet indexing API.
	 *
	 * @param \WP_Post $post The WordPress post object to index.
	 * @return array Response array with success status and message.
	 */
	public function index_post( $post ) {
		if ( ! $this->is_configured() ) {
			return array(
				'success' => false,
				'message' => 'API key not configured',
			);
		}

		if ( empty( $post->post_title ) ) {
			return array(
				'success' => false,
				'message' => 'Post title is empty',
			);
		}

		$payload = array(
			'id'         => 'post-' . $post->ID,
			'title'      => $post->post_title,
			'content'    => wp_strip_all_tags( $post->post_content ),
			'url'        => get_permalink( $post ),
			'excerpt'    => wp_strip_all_tags( get_the_excerpt( $post ) ),
			'type'       => $post->post_type,
			'categories' => wp_get_post_terms( $post->ID, 'category', array( 'fields' => 'names' ) ),
			'tags'       => wp_get_post_terms( $post->ID, 'post_tag', array( 'fields' => 'names' ) ),
			'language'   => get_locale(),
		);

		// Remove empty values.
		$payload = array_filter(
			$payload,
			function( $value ) {
				return ! empty( $value ) || 0 === $value;
			}
		);

		if ( 'product' === $post->post_type && function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $post->ID );
			if ( $product ) {
				$payload['price']      = $product->get_price();
				$payload['sku']        = $product->get_sku();
				$payload['categories'] = wp_get_post_terms( $post->ID, 'product_cat', array( 'fields' => 'names' ) );
				$payload['tags']       = wp_get_post_terms( $post->ID, 'product_tag', array( 'fields' => 'names' ) );
				$payload['stock']      = $product->get_stock_status();
				$payload['image']      = get_the_post_thumbnail_url( $post->ID, 'medium' );
			}
		}

		$url = $this->host_url . '/v1/index/bulk';

		$response = wp_remote_post(
			$url,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( array( 'documents' => array( $payload ) ) ),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			$error_msg = sanitize_text_field( $response->get_error_message() );
			error_log( '[SearchJet] wp_remote_post error during indexPost: ' . $error_msg );
			return array(
				'success' => false,
				'message' => 'Request failed: ' . $error_msg,
			);
		} else {
			$http_code = wp_remote_retrieve_response_code( $response );
			if ( $http_code < 200 || $http_code >= 300 ) {
				$response_body = sanitize_text_field( wp_remote_retrieve_body( $response ) );
				error_log( '[SearchJet] Unexpected HTTP code ' . $http_code . ' during indexPost: ' . $response_body );
				return array(
					'success' => false,
					'message' => 'HTTP error ' . $http_code,
				);
			}
		}

		return array(
			'success' => true,
			'message' => 'Post indexed successfully',
		);
	}

	/**
	 * Request deletion of a post from the SearchJet index.
	 *
	 * @param int $post_id The ID of the post to delete.
	 * @return array Response array with success status and message.
	 */
	public function delete_post( $post_id ) {
		if ( ! $this->is_configured() ) {
			return array(
				'success' => false,
				'message' => 'API key not configured',
			);
		}

		$url = $this->host_url . '/v1/index/delete';

		$response = wp_remote_request(
			$url,
			array(
				'method'  => 'DELETE',
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'id' => 'post-' . $post_id,
					)
				),
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) ) {
			$error_msg = sanitize_text_field( $response->get_error_message() );
			error_log( '[SearchJet] wp_remote_request error during deletePost: ' . $error_msg );
			return array(
				'success' => false,
				'message' => 'Request failed: ' . $error_msg,
			);
		} else {
			$http_code = wp_remote_retrieve_response_code( $response );
			if ( $http_code < 200 || $http_code >= 300 ) {
				$response_body = sanitize_text_field( wp_remote_retrieve_body( $response ) );
				error_log( '[SearchJet] Unexpected HTTP code ' . $http_code . ' during deletePost: ' . $response_body );
				return array(
					'success' => false,
					'message' => 'HTTP error ' . $http_code,
				);
			}
		}

		return array(
			'success' => true,
			'message' => 'Post deleted successfully',
		);
	}

	/**
	 * Send multiple posts in bulk to the SearchJet indexing API.
	 *
	 * @param array $posts        An array of WordPress post objects.
	 * @param bool  $full_reindex Whether this is a full reindex operation.
	 * @return array Response array with count, message and optional error info.
	 */
	public function bulk_index_posts( array $posts, $full_reindex = false ) {
		if ( ! $this->is_configured() ) {
			return array(
				'count'   => 0,
				'message' => 'API key not configured',
				'error'   => 'configuration_error',
			);
		}

		// Build payload. Supports two input shapes:
		// 1) Array of prebuilt associative arrays (from reindexBatch) → pass through.
		// 2) Array of WP_Post objects → build a minimal doc (no Woo-specific lookups here).
		$payload = array_map(
			function( $post ) {
				// Case 1: prebuilt doc (already contains Woo fields, images, etc.).
				if ( is_array( $post ) ) {
					// Ensure mandatory keys exist.
					if ( ! isset( $post['id'] ) || ! isset( $post['title'] ) ) {
						return null;
					}
					return $post;
				}

				// Case 2: WP_Post fallback (MVP minimal fields).
				if ( ! ( $post instanceof \WP_Post ) ) {
					return null;
				}

				if ( empty( $post->post_title ) ) {
					return null; // Skip posts with no title.
				}

				$item = array(
					'id'          => 'post-' . $post->ID,
					'title'       => $post->post_title,
					'content'     => wp_strip_all_tags( $post->post_content ),
					'type'        => $post->post_type,
					'url'         => get_permalink( $post ),
					'slug'        => $post->post_name,
					'date'        => get_the_date( 'c', $post ),
					'modified'    => get_the_modified_date( 'c', $post ),
					'excerpt'     => wp_strip_all_tags( get_the_excerpt( $post ) ),
					'author'      => get_the_author_meta( 'display_name', $post->post_author ),
					'post_status' => $post->post_status,
					'image'       => get_the_post_thumbnail_url( $post->ID, 'large' ) ?: '',
					'thumbnail'   => get_the_post_thumbnail_url( $post->ID, 'thumbnail' ) ?: '',
					'language'    => self::detect_post_language( $post ),
				);

				// Add WooCommerce-specific fields if this is a product and WooCommerce is available.
				if ( 'product' === $post->post_type && function_exists( 'wc_get_product' ) ) {
					$product = wc_get_product( $post->ID );
					if ( $product ) {
						$item['price']      = $product->get_price();
						$item['sku']        = $product->get_sku();
						$item['categories'] = wp_get_post_terms( $post->ID, 'product_cat', array( 'fields' => 'names' ) );
						$item['tags']       = wp_get_post_terms( $post->ID, 'product_tag', array( 'fields' => 'names' ) );
						$item['stock']      = $product->get_stock_status();
					}
				}

				// Drop empty values to keep payload lean.
				$item = array_filter(
					$item,
					function( $v ) {
						return null !== $v && '' !== $v;
					}
				);
				return $item;
			},
			$posts
		);

		$payload = array_filter( $payload );

		$url = $this->host_url . '/v1/index/bulk';

		$body = array(
			'documents' => array_values( $payload ),
		);

		if ( $full_reindex ) {
			$body['full_reindex'] = true;
		}

		$request_header = array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $this->api_key,
				'Content-Type'  => 'application/json',
				'Accept'        => 'application/json',
			),
			'body'    => wp_json_encode( $body ),
			'timeout' => 60,
		);

		$response = wp_remote_post(
			$url,
			array_merge(
				$request_header,
				array(
					'redirection'  => 5,
					'httpversion'  => '1.1',
				)
			)
		);

		$response_body = wp_remote_retrieve_body( $response );
		$data          = json_decode( $response_body, true );

		if ( is_wp_error( $response ) ) {
			return array(
				'count'   => 0,
				'message' => 'Indexing failed: ' . $response->get_error_message(),
				'error'   => 'request_error',
			);
		}

		$http_code = wp_remote_retrieve_response_code( $response );

		// Handle quota exceeded error (429).
		if ( 429 === $http_code ) {
			$error_message = isset( $data['message'] ) ? $data['message'] : 'Document limit exceeded.';
			$quota_info    = '';

			if ( isset( $data['quota'] ) ) {
				$quota      = $data['quota'];
				$quota_info = sprintf(
					' Your plan allows %d documents. Current: %d, Requested: %d, Available: %d.',
					isset( $quota['limit'] ) ? $quota['limit'] : 0,
					isset( $quota['current'] ) ? $quota['current'] : 0,
					isset( $quota['requested'] ) ? $quota['requested'] : 0,
					isset( $quota['available'] ) ? $quota['available'] : 0
				);
			}

			return array(
				'count'       => 0,
				'message'     => $error_message . $quota_info,
				'error'       => 'quota_exceeded',
				'quota'       => isset( $data['quota'] ) ? $data['quota'] : null,
				'upgrade_url' => isset( $data['upgrade_url'] ) ? $data['upgrade_url'] : null,
			);
		}

		if ( $http_code < 200 || $http_code >= 300 ) {
			$error_body = isset( $data['message'] ) ? $data['message'] : wp_remote_retrieve_body( $response );
			error_log( '[SearchJet] bulk_index_posts HTTP ' . $http_code . ' response: ' . substr( $error_body, 0, 500 ) );

			if ( 500 === $http_code ) {
				$error_body = 'The SearchJet server returned a 500 error. This is a temporary server issue. Please try again in a few minutes. If the problem persists, contact SearchJet support.';
			} elseif ( 401 === $http_code || 403 === $http_code ) {
				$error_body = 'Authentication failed. Your API key may be invalid or expired. Please check your API key in the Connection tab.';
			} elseif ( 404 === $http_code ) {
				$error_body = 'API endpoint not found. The SearchJet service may be undergoing maintenance.';
			}

			return array(
				'count'   => 0,
				'message' => $error_body,
				'error'   => 'http_error',
			);
		}

		return array(
			'count'       => count( $payload ),
			'message'     => isset( $data['message'] ) ? $data['message'] : 'Indexed ' . count( $payload ) . ' documents.',
			'was_cleared' => isset( $data['was_cleared'] ) ? $data['was_cleared'] : false,
		);
	}

	/**
	 * Get the count of documents in the index.
	 *
	 * @return array Response with document count or error message.
	 */
	public function count_documents() {
		if ( ! $this->is_configured() ) {
			return array(
				'success' => false,
				'message' => 'API key not configured',
			);
		}

		$url = $this->host_url . '/v1/index/count';

		$response = wp_remote_get(
			$url,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->api_key,
					'Content-Type'  => 'application/json',
				),
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => 'Request failed: ' . $response->get_error_message(),
			);
		}

		$http_code     = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );
		$data          = json_decode( $response_body, true );

		if ( $http_code < 200 || $http_code >= 300 ) {
			return array(
				'success' => false,
				'message' => 'Error: ' . ( isset( $data['message'] ) ? $data['message'] : $response_body ),
			);
		}

		return array(
			'success' => true,
			'count'   => isset( $data['count'] ) ? $data['count'] : 0,
			'message' => isset( $data['message'] ) ? $data['message'] : 'Document count retrieved successfully.',
		);
	}

	/**
	 * Detect the language of a post.
	 *
	 * @param \WP_Post $post Post object.
	 * @return string Language code (e.g., 'en', 'fr').
	 */
	private static function detect_post_language( $post ) {
		if ( function_exists( 'wpml_get_language_information' ) ) {
			$lang_info = wpml_get_language_information( null, $post->ID );
			if ( is_array( $lang_info ) && isset( $lang_info['language_code'] ) ) {
				return $lang_info['language_code'];
			}
		}
		return get_locale();
	}
}
