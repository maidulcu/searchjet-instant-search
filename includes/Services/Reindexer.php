<?php
/**
 * Reindexer class for SearchJet plugin.
 *
 * @package SearchJet
 * @since 1.3.3
 */

namespace SearchJet\Services;

use SearchJet\Services\MeilisearchClient;

/**
 * Handles reindexing of WordPress content for SearchJet.
 */
class Reindexer {

	/**
	 * MeilisearchClient instance.
	 *
	 * @var MeilisearchClient
	 */
	protected $client;

	/**
	 * Constructs a Reindexer object with a MeilisearchClient instance.
	 */
	public function __construct() {
		$this->client = new MeilisearchClient();
	}

	/**
	 * Reindex a batch of posts.
	 *
	 * @param int  $offset       Starting offset for the query.
	 * @param int  $limit        Number of posts to process.
	 * @param bool $full_reindex Whether this is a full reindex operation.
	 * @return array|false Result array or false if no posts found.
	 */
	public function reindex_batch( $offset = 0, $limit = 100, $full_reindex = false ) {
		$exclude_sticky      = get_option( 'searchjet_exclude_sticky' );
		$include_attachments = (bool) get_option( 'searchjet_index_attachments', false );
		$post_types          = \SearchJet\Helpers\IndexScope::post_types();
		$post_statuses  = array_filter( array_map( 'trim', explode( ',', (string) get_option( 'searchjet_include_status', 'publish' ) ) ) );

		if ( empty( $post_statuses ) ) {
			$post_statuses = array( 'publish' );
		}

		$args = array(
			'post_type'      => $post_types,
			'post_status'    => apply_filters(
				'searchjet_indexable_post_statuses',
				array_values( $post_statuses )
			),
			'posts_per_page' => $limit,
			'offset'         => $offset,
			'fields'         => 'ids',
			'wpml_language'  => 'all',
			'suppress_filters' => true,
		);

		if ( $exclude_sticky ) {
			$args['post__not_in'] = get_option( 'sticky_posts' );
		}

		if ( empty( $post_types ) ) {
			return false;
		}

		$query = new \WP_Query( $args );

		$post_ids = $query->have_posts() ? $query->posts : array();

		if ( ! $query->have_posts() ) {
			wp_reset_postdata();
			return false;
		}

		wp_reset_postdata();

		$posts = array_map( 'get_post', $post_ids );

		// Password-protected posts never go to the index.
		$posts = array_filter(
			$posts,
			function( $post ) {
				return $post && ! \SearchJet\Helpers\IndexScope::is_protected( $post );
			}
		);

		$exclude_mimes = array_filter(
			array_map( 'trim', explode( ',', get_option( 'searchjet_exclude_mime_types', '' ) ) )
		);

		if ( ! empty( $exclude_mimes ) ) {
			$posts = array_filter(
				$posts,
				function( $post ) use ( $exclude_mimes ) {
					$mime_type = get_post_mime_type( $post );
					return ! $mime_type || ! in_array( $mime_type, $exclude_mimes, true );
				}
			);
		}

		if ( ! $include_attachments ) {
			$posts = array_filter(
				$posts,
				function( $post ) {
					return 'attachment' !== $post->post_type;
				}
			);
		}

		$posts = array_map(
			function( $post ) {
				$post->post_title   = $this->clean_text( $post->post_title );
				$post->post_content = $this->clean_text( $post->post_content );
				return $post;
			},
			$posts
		);

		$skipped_post_ids = array();
		$documents        = array_map(
			function( $post ) use ( &$skipped_post_ids ) {
				if ( '' === $post->post_title ) {
					$skipped_post_ids[] = $post->ID;
					return null;
				}

				$type      = $post->post_type ? $post->post_type : 'page';
				$permalink = get_permalink( $post );
				// When WPML is active, switch to the post's language to get the translated URL.
				if ( function_exists( 'wpml_get_language_information' ) ) {
					$lang_info = wpml_get_language_information( null, $post->ID );
					if ( is_array( $lang_info ) && isset( $lang_info['language_code'] ) ) {
						$old_lang = apply_filters( 'wpml_current_language', null );
						do_action( 'wpml_switch_language', $lang_info['language_code'] );
						$permalink = get_permalink( $post );
						do_action( 'wpml_switch_language', $old_lang );
					}
				}
				$author    = get_the_author_meta( 'display_name', (int) $post->post_author );
				$date_iso  = get_post_time( 'c', true, $post );

				$image     = get_the_post_thumbnail_url( $post, 'full' );
				$thumbnail = get_the_post_thumbnail_url( $post, 'thumbnail' );

				$raw_excerpt = get_the_excerpt( $post );
				$excerpt     = $raw_excerpt ? wp_strip_all_tags( $raw_excerpt ) : wp_trim_words( $post->post_content, 30 );

				$doc = array(
					'id'        => 'post-' . $post->ID,
					'type'      => $type,
					'title'     => $post->post_title,
					'content'   => $post->post_content,
					'url'       => $permalink ? $permalink : '',
					'excerpt'   => $excerpt ? $excerpt : '',
					'author'    => $author ? $author : '',
					'date'      => $date_iso ? $date_iso : '',
					'image'     => $image ? $image : '',
					'thumbnail' => $thumbnail ? $thumbnail : '',
					'language'  => function_exists( 'wpml_get_language_information' ) ? ( wpml_get_language_information( null, $post->ID )['language_code'] ?? get_locale() ) : get_locale(),
				);

				if ( 'product' === $type ) {
					$cat_tax = 'product_cat';
					$tag_tax = 'product_tag';
				} else {
					$cat_tax = 'category';
					$tag_tax = 'post_tag';
				}

				$cats = wp_get_post_terms( $post->ID, $cat_tax, array( 'fields' => 'names' ) );
				$tags = wp_get_post_terms( $post->ID, $tag_tax, array( 'fields' => 'names' ) );

				if ( ! is_wp_error( $cats ) && ! empty( $cats ) ) {
					$doc['categories'] = array_values( array_unique( array_filter( $cats ) ) );
				}
				if ( ! is_wp_error( $tags ) && ! empty( $tags ) ) {
					$doc['tags'] = array_values( array_unique( array_filter( $tags ) ) );
				}

				if ( 'product' === $type && function_exists( 'wc_get_product' ) ) {
					$product = wc_get_product( $post->ID );
					if ( $product ) {
						$sku = (string) $product->get_sku();
						if ( '' !== $sku ) {
							$doc['sku'] = $sku;
						}

						$regular = $product->get_regular_price();
						$sale    = $product->get_sale_price();
						$active  = $product->get_price();

						if ( is_numeric( $active ) ) {
							$doc['price'] = (float) $active;
						}
						if ( is_numeric( $regular ) ) {
							$doc['regular_price'] = (float) $regular;
						}
						if ( is_numeric( $sale ) ) {
							$doc['sale_price'] = (float) $sale;
						}

						if ( function_exists( 'get_woocommerce_currency' ) ) {
							$doc['currency'] = (string) get_woocommerce_currency();
						}
						if ( method_exists( $product, 'get_stock_status' ) ) {
							$doc['stock_status'] = (string) $product->get_stock_status();
						}

						if ( empty( $doc['image'] ) ) {
							$prod_img_id = $product->get_image_id();
							if ( $prod_img_id ) {
								$doc['image']     = wp_get_attachment_image_url( $prod_img_id, 'full' ) ? wp_get_attachment_image_url( $prod_img_id, 'full' ) : '';
								$doc['thumbnail'] = wp_get_attachment_image_url( $prod_img_id, 'thumbnail' ) ? wp_get_attachment_image_url( $prod_img_id, 'thumbnail' ) : $doc['thumbnail'];
							}
						}
					}
				}

				return apply_filters( 'searchjet_index_document', $doc, $post );
			},
			$posts
		);

		$documents = array_values( array_filter( $documents ) );

		$result = $this->client->bulk_index_posts( $documents, $full_reindex );

		$return = array(
			'count'   => isset( $result['count'] ) ? $result['count'] : count( $documents ),
			'message' => isset( $result['message'] ) ? $result['message'] : null,
		);

		if ( isset( $result['error'] ) ) {
			$return['error'] = $result['error'];
		}
		if ( isset( $result['quota'] ) ) {
			$return['quota'] = $result['quota'];
		}
		if ( isset( $result['upgrade_url'] ) ) {
			$return['upgrade_url'] = $result['upgrade_url'];
		}

		$return['scanned'] = count( $post_ids );

		return $return;
	}

	/**
	 * Reindex all posts in batches.
	 *
	 * @return array Result array with total indexed count and message.
	 */
	public function reindex_all() {
		$max_execution_time = @ini_get( 'max_execution_time' );

		$offset = 0;
		$limit  = 100;
		$total  = 0;
		$start_time = time();

		do {
			if ( $max_execution_time > 0 && ( time() - $start_time ) > ( $max_execution_time * 0.8 ) ) {
				return array(
					'total_indexed' => $total,
					'message'       => sprintf( 'Partial reindex: %d documents indexed so far. Some content may not have been processed due to time limits.', $total ),
					'partial'       => true,
				);
			}

			$full_reindex = ( 0 === $offset );
			$result       = $this->reindex_batch( $offset, $limit, $full_reindex );
			$count        = is_array( $result ) ? ( isset( $result['count'] ) ? $result['count'] : 0 ) : 0;
			$scanned      = is_array( $result ) ? ( isset( $result['scanned'] ) ? $result['scanned'] : 0 ) : 0;

			if ( is_array( $result ) && isset( $result['error'] ) ) {
				return array_merge(
					$result,
					array(
						'total_indexed' => $total,
					)
				);
			}

			$offset      += $limit;
			$total       += $count;
		} while ( $scanned > 0 );

		if ( 0 === $total ) {
			return array(
				'total_indexed' => 0,
				'message'       => 'No indexable content was found. Check the Exclusions tab post type and status settings.',
				'error'         => 'no_indexable_content',
			);
		}

		return array(
			'total_indexed' => $total,
			'message'       => sprintf( 'Reindexing completed. %d documents were sent to SearchJet.', $total ),
		);
	}

	/**
	 * Clean text by removing HTML tags and trimming whitespace.
	 *
	 * @param string $text Text to clean.
	 * @return string Cleaned text.
	 */
	protected function clean_text( $text ) {
		return trim( strip_tags( $text ) );
	}
}
