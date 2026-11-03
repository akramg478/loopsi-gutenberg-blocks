<?php
/**
 * Sanitize block attributes for queries and rendering.
 *
 * @package Loopsi_Blocks
 */

namespace Loopsi\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Normalizes and sanitizes posts-query block attributes.
 */
final class AttributeSanitizer {

	/**
	 * Maximum number of post IDs allowed in include/exclude lists.
	 */
	private const MAX_POST_ID_LIST = 20;

	/**
	 * Sanitize attributes from block editor, render, or AJAX.
	 *
	 * @param array<string, mixed> $raw Raw attributes.
	 * @return array<string, mixed>
	 */
	public static function sanitize( array $raw ): array {
		$post_type = sanitize_key( (string) ( $raw['postType'] ?? 'post' ) );

		if ( ! is_post_type_viewable( $post_type ) ) {
			$post_type = 'post';
		}

		$layout = sanitize_key( (string) ( $raw['layout'] ?? 'grid' ) );
		if ( ! in_array( $layout, array( 'grid', 'list' ), true ) ) {
			$layout = 'grid';
		}

		$pagination = self::sanitize_pagination_type( $raw['paginationType'] ?? 'none' );

		$order = strtoupper( sanitize_text_field( (string) ( $raw['order'] ?? 'DESC' ) ) );
		if ( ! in_array( $order, array( 'ASC', 'DESC' ), true ) ) {
			$order = 'DESC';
		}

		$orderby         = sanitize_key( (string) ( $raw['orderBy'] ?? 'date' ) );
		$allowed_orderby = array( 'date', 'title', 'rand', 'modified', 'menu_order', 'ID' );
		if ( ! in_array( $orderby, $allowed_orderby, true ) ) {
			$orderby = 'date';
		}

		$show_thumbnail = self::sanitize_bool( $raw, 'showThumbnail', 'showFeaturedImage', true );

		return array(
			'postsPerPage'      => max( 1, min( 50, absint( $raw['postsPerPage'] ?? 6 ) ) ),
			'postType'          => $post_type,
			'order'             => $order,
			'orderBy'           => $orderby,
			'author'            => absint( $raw['author'] ?? 0 ),
			'includedPostIds'   => self::sanitize_id_list( $raw['includedPostIds'] ?? $raw['include'] ?? array() ),
			'excludedPostIds'   => self::sanitize_id_list( $raw['excludedPostIds'] ?? $raw['exclude'] ?? array() ),
			'taxQuery'          => self::sanitize_tax_query( $raw['taxQuery'] ?? array(), $post_type ),
			'metaQuery'         => self::sanitize_meta_query( $raw['metaQuery'] ?? array() ),
			'search'            => sanitize_text_field( (string) ( $raw['search'] ?? '' ) ),
			'stickyPosts'       => self::sanitize_sticky( $raw['stickyPosts'] ?? 'inherit' ),
			'layout'            => $layout,
			'columns'           => max( 1, min( 6, absint( $raw['columns'] ?? 3 ) ) ),
			'paginationType'    => $pagination,
			'cacheResults'      => ! empty( $raw['cacheResults'] ),
			'cacheTTL'          => max( 60, min( 3600, absint( $raw['cacheTTL'] ?? 300 ) ) ),
			'showTitle'         => self::sanitize_bool( $raw, 'showTitle', null, true ),
			'showThumbnail'     => $show_thumbnail,
			'showFeaturedImage' => $show_thumbnail,
			'showExcerpt'       => self::sanitize_bool( $raw, 'showExcerpt', null, true ),
			'showAuthor'        => self::sanitize_bool( $raw, 'showAuthor', null, false ),
			'showReadMore'      => self::sanitize_bool( $raw, 'showReadMore', null, true ),
			'readMoreText'      => self::sanitize_read_more_text( $raw['readMoreText'] ?? '' ),
			'paged'             => max( 1, absint( $raw['paged'] ?? 1 ) ),
		);
	}

	/**
	 * Sanitize attributes received over AJAX.
	 *
	 * @param mixed $payload JSON string or array.
	 * @return array<string, mixed>
	 */
	public static function sanitize_from_request( $payload ): array {
		if ( is_string( $payload ) ) {
			if ( '' === $payload ) {
				return self::sanitize( array() );
			}

			$decoded = json_decode( wp_unslash( $payload ), true );
			$payload = is_array( $decoded ) ? $decoded : array();
		}

		if ( ! is_array( $payload ) ) {
			$payload = array();
		}

		return self::sanitize( $payload );
	}

	/**
	 * @param mixed $value Pagination mode from block attributes.
	 */
	private static function sanitize_pagination_type( $value ): string {
		$value = sanitize_text_field( (string) $value );

		$map = array(
			'none'           => 'none',
			'pagination'     => 'pagination',
			'loadMore'       => 'loadMore',
			'loadmore'       => 'loadMore',
			'infiniteScroll' => 'infiniteScroll',
			'infinitescroll' => 'infiniteScroll',
		);

		return $map[ $value ] ?? $map[ strtolower( $value ) ] ?? 'none';
	}

	/**
	 * @param mixed $value ID list.
	 * @return int[]
	 */
	private static function sanitize_id_list( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$ids = array_values( array_filter( array_map( 'absint', $value ) ) );

		if ( count( $ids ) > self::MAX_POST_ID_LIST ) {
			$ids = array_slice( $ids, 0, self::MAX_POST_ID_LIST );
		}

		return $ids;
	}

	/**
	 * @param mixed  $value     Taxonomy => term IDs map.
	 * @param string $post_type Selected post type.
	 * @return array<string, int[]>
	 */
	private static function sanitize_tax_query( $value, string $post_type ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$object_taxonomies = get_object_taxonomies( $post_type, 'names' );
		$sanitized         = array();

		foreach ( $value as $taxonomy => $terms ) {
			$taxonomy = sanitize_key( (string) $taxonomy );

			if (
				! taxonomy_exists( $taxonomy )
				|| ! is_array( $terms )
				|| ! in_array( $taxonomy, $object_taxonomies, true )
			) {
				continue;
			}

			$valid_term_ids = array();

			foreach ( array_map( 'absint', $terms ) as $term_id ) {
				if ( $term_id <= 0 ) {
					continue;
				}

				$term = get_term( $term_id, $taxonomy );

				if ( $term instanceof \WP_Term && ! is_wp_error( $term ) ) {
					$valid_term_ids[] = $term_id;
				}
			}

			$valid_term_ids = array_values( array_unique( $valid_term_ids ) );

			if ( ! empty( $valid_term_ids ) ) {
				$sanitized[ $taxonomy ] = $valid_term_ids;
			}
		}

		return $sanitized;
	}

	/**
	 * @param array<string, mixed> $raw           Raw attributes.
	 * @param string               $key           Primary boolean key.
	 * @param string|null          $legacy_key    Optional legacy key.
	 * @param bool                 $default_value Default when unset.
	 */
	private static function sanitize_bool( array $raw, string $key, ?string $legacy_key, bool $default_value ): bool {
		if ( array_key_exists( $key, $raw ) ) {
			return ! empty( $raw[ $key ] );
		}

		if ( null !== $legacy_key && array_key_exists( $legacy_key, $raw ) ) {
			return ! empty( $raw[ $legacy_key ] );
		}

		return $default_value;
	}

	/**
	 * @param mixed $value Read-more label.
	 */
	private static function sanitize_read_more_text( $value ): string {
		$text = sanitize_text_field( (string) $value );

		if ( '' === $text ) {
			return __( 'Read more', 'loopsi-blocks' );
		}

		return $text;
	}

	/**
	 * @param mixed $value Meta query rules.
	 * @return array<int, array<string, string>>
	 */
	private static function sanitize_meta_query( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$allowed_compare = array( '=', '!=', '>', '>=', '<', '<=', 'LIKE', 'NOT LIKE', 'EXISTS', 'NOT EXISTS' );
		$allowed_types   = array( 'CHAR', 'NUMERIC', 'DATE', 'DATETIME', 'DECIMAL', 'SIGNED', 'UNSIGNED' );

		$rules = array();

		foreach ( $value as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}

			$key = sanitize_key( (string) ( $rule['key'] ?? '' ) );

			if ( '' === $key ) {
				continue;
			}

			$compare = sanitize_text_field( (string) ( $rule['compare'] ?? '=' ) );
			if ( ! in_array( $compare, $allowed_compare, true ) ) {
				$compare = '=';
			}

			$type = sanitize_text_field( (string) ( $rule['type'] ?? 'CHAR' ) );
			if ( ! in_array( $type, $allowed_types, true ) ) {
				$type = 'CHAR';
			}

			$sanitized_rule = array(
				'key'     => $key,
				'compare' => $compare,
				'type'    => $type,
			);

			if ( ! in_array( $compare, array( 'EXISTS', 'NOT EXISTS' ), true ) ) {
				$sanitized_rule['value'] = sanitize_text_field( (string) ( $rule['value'] ?? '' ) );
			}

			$rules[] = $sanitized_rule;
		}

		return $rules;
	}

	/**
	 * @param mixed $value Sticky posts mode.
	 */
	private static function sanitize_sticky( $value ): string {
		$value = sanitize_key( (string) $value );

		if ( in_array( $value, array( 'inherit', 'only', 'exclude' ), true ) ) {
			return $value;
		}

		return 'inherit';
	}
}
