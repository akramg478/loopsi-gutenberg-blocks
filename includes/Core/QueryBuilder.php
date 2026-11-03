<?php
/**
 * Builds WP_Query instances from block attributes.
 *
 * @package Loopsi_Blocks
 */

namespace Loopsi\Core;

use WP_Query;

defined( 'ABSPATH' ) || exit;

/**
 * Converts sanitized block attributes into WP_Query arguments.
 */
final class QueryBuilder {

	/**
	 * Custom query var used to scope post ID exclusions to Loopsi queries.
	 */
	public const EXCLUDED_POST_IDS_QUERY_VAR = 'loopsi_excluded_post_ids';

	/**
	 * @var array<string, mixed>
	 */
	private array $attributes = array();

	/**
	 * @var array<int, callable>
	 */
	private array $filters = array();

	/**
	 * @param array<string, mixed> $attributes Sanitized attributes.
	 */
	public function __construct( array $attributes = array() ) {
		$this->attributes = $attributes;
	}

	/**
	 * @param array<string, mixed> $attributes Sanitized attributes.
	 */
	public static function make( array $attributes = array() ): self {
		return new self( $attributes );
	}

	/**
	 * Register query filters used by Loopsi block queries.
	 */
	public static function register_hooks(): void {
		add_filter( 'posts_where', array( self::class, 'filter_posts_where_exclusions' ), 10, 2 );
	}

	/**
	 * Exclude a bounded list of post IDs using a scoped WHERE clause.
	 *
	 * @param string    $where Existing WHERE clause.
	 * @param \WP_Query $query Query instance.
	 * @return string
	 */
	public static function filter_posts_where_exclusions( string $where, WP_Query $query ): string {
		$exclude_ids = $query->get( self::EXCLUDED_POST_IDS_QUERY_VAR );

		if ( ! is_array( $exclude_ids ) || empty( $exclude_ids ) ) {
			return $where;
		}

		$exclude_ids = array_values( array_filter( array_map( 'absint', $exclude_ids ) ) );

		if ( empty( $exclude_ids ) ) {
			return $where;
		}

		global $wpdb;

		foreach ( $exclude_ids as $exclude_id ) {
			$where .= $wpdb->prepare( " AND {$wpdb->posts}.ID != %d", $exclude_id );
		}

		return $where;
	}

	/**
	 * @param callable $callback Receives ( $args, $attributes ).
	 */
	public function add_filter( callable $callback ): self {
		$this->filters[] = $callback;
		return $this;
	}

	public function get(): WP_Query {
		$args = $this->build_base_query();
		$args = $this->apply_search( $args );
		$args = $this->apply_author( $args );
		$args = $this->apply_include_exclude( $args );
		$args = $this->apply_tax_query( $args );
		$args = $this->apply_meta_query( $args );
		$args = $this->apply_sticky_posts( $args );

		foreach ( $this->filters as $filter ) {
			$filtered = $filter( $args, $this->attributes );
			if ( is_array( $filtered ) ) {
				$args = $filtered;
			}
		}

		/**
		 * Filter WP_Query arguments for Loopsi blocks.
		 *
		 * @param array<string, mixed> $args       Query arguments.
		 * @param array<string, mixed> $attributes Block attributes.
		 */
		$args = apply_filters( 'loopsi/query_args', $args, $this->attributes );

		return new WP_Query( $args );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function build_base_query(): array {
		$post_type = sanitize_key( (string) ( $this->attributes['postType'] ?? 'post' ) );

		if ( ! is_post_type_viewable( $post_type ) ) {
			$post_type = 'post';
		}

		return array(
			'post_type'              => $post_type,
			'posts_per_page'         => max( 1, min( 50, absint( $this->attributes['postsPerPage'] ?? 6 ) ) ),
			'order'                  => $this->sanitize_order( $this->attributes['order'] ?? 'DESC' ),
			'orderby'                => sanitize_key( (string) ( $this->attributes['orderBy'] ?? 'date' ) ),
			'post_status'            => 'publish',
			'paged'                  => max( 1, absint( $this->attributes['paged'] ?? 1 ) ),
			'ignore_sticky_posts'    => true,
			'no_found_rows'          => false,
			'update_post_meta_cache' => true,
			'update_post_term_cache' => true,
		);
	}

	/**
	 * @param array<string, mixed> $args Query arguments.
	 * @return array<string, mixed>
	 */
	private function apply_search( array $args ): array {
		$search = sanitize_text_field( (string) ( $this->attributes['search'] ?? '' ) );

		if ( '' !== $search ) {
			$args['s'] = $search;
		}

		return $args;
	}

	/**
	 * @param array<string, mixed> $args Query arguments.
	 * @return array<string, mixed>
	 */
	private function apply_author( array $args ): array {
		$author = absint( $this->attributes['author'] ?? 0 );

		if ( $author > 0 ) {
			$args['author'] = $author;
		}

		return $args;
	}

	/**
	 * @param array<string, mixed> $args Query arguments.
	 * @return array<string, mixed>
	 */
	private function apply_include_exclude( array $args ): array {
		$include = $this->attributes['includedPostIds'] ?? array();
		$exclude = $this->attributes['excludedPostIds'] ?? array();

		if ( is_array( $include ) && ! empty( $include ) ) {
			$args['post__in'] = array_map( 'absint', $include );
		}

		if ( is_array( $exclude ) && ! empty( $exclude ) ) {
			$args[ self::EXCLUDED_POST_IDS_QUERY_VAR ] = array_map( 'absint', $exclude );
		}

		return $args;
	}

	/**
	 * @param array<string, mixed> $args Query arguments.
	 * @return array<string, mixed>
	 */
	private function apply_tax_query( array $args ): array {
		$tax_data = $this->attributes['taxQuery'] ?? array();

		if ( ! is_array( $tax_data ) || empty( $tax_data ) ) {
			return $args;
		}

		$tax_query = array( 'relation' => 'AND' );

		foreach ( $tax_data as $taxonomy => $terms ) {
			$taxonomy = sanitize_key( (string) $taxonomy );

			if ( ! taxonomy_exists( $taxonomy ) || ! is_array( $terms ) || empty( $terms ) ) {
				continue;
			}

			$term_ids = array_values( array_filter( array_map( 'absint', $terms ) ) );

			if ( empty( $term_ids ) ) {
				continue;
			}

			$tax_query[] = array(
				'taxonomy' => $taxonomy,
				'field'    => 'term_id',
				'terms'    => $term_ids,
			);
		}

		if ( count( $tax_query ) > 1 ) {
			$args['tax_query'] = $tax_query;
		}

		return $args;
	}

	/**
	 * @param array<string, mixed> $args Query arguments.
	 * @return array<string, mixed>
	 */
	private function apply_meta_query( array $args ): array {
		$meta_data = $this->attributes['metaQuery'] ?? array();

		if ( ! is_array( $meta_data ) || empty( $meta_data ) ) {
			return $args;
		}

		$meta_query = array( 'relation' => 'AND' );

		foreach ( $meta_data as $rule ) {
			if ( ! is_array( $rule ) || empty( $rule['key'] ) ) {
				continue;
			}

			$compare = sanitize_text_field( (string) ( $rule['compare'] ?? '=' ) );
			$type    = sanitize_text_field( (string) ( $rule['type'] ?? 'CHAR' ) );

			$meta_rule = array(
				'key'     => sanitize_key( (string) $rule['key'] ),
				'compare' => $compare,
				'type'    => $type,
			);

			if ( ! in_array( $compare, array( 'EXISTS', 'NOT EXISTS' ), true ) ) {
				$meta_rule['value'] = sanitize_text_field( (string) ( $rule['value'] ?? '' ) );
			}

			$meta_query[] = $meta_rule;
		}

		if ( count( $meta_query ) > 1 ) {
			$args['meta_query'] = $meta_query;
		}

		return $args;
	}

	/**
	 * @param array<string, mixed> $args Query arguments.
	 * @return array<string, mixed>
	 */
	private function apply_sticky_posts( array $args ): array {
		$mode = sanitize_key( (string) ( $this->attributes['stickyPosts'] ?? 'inherit' ) );

		if ( 'only' === $mode ) {
			$sticky = get_option( 'sticky_posts', array() );
			$sticky = is_array( $sticky ) ? array_map( 'absint', $sticky ) : array();

			if ( empty( $sticky ) ) {
				$args['post__in'] = array( 0 );
			} else {
				$args['post__in']            = $sticky;
				$args['ignore_sticky_posts'] = true;
			}
		} elseif ( 'exclude' === $mode ) {
			$args['ignore_sticky_posts'] = true;
		} else {
			$args['ignore_sticky_posts'] = false;
		}

		return $args;
	}

	/**
	 * @param mixed $value Order direction.
	 */
	private function sanitize_order( $value ): string {
		$value = strtoupper( sanitize_text_field( (string) $value ) );
		return in_array( $value, array( 'ASC', 'DESC' ), true ) ? $value : 'DESC';
	}
}
