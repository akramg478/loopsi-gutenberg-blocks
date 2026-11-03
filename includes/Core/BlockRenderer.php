<?php
/**
 * Server-side block rendering.
 *
 * @package Loopsi_Blocks
 */

namespace Loopsi\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the posts-query block on the frontend and in the editor preview.
 */
final class BlockRenderer {

	/**
	 * Block render callback.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param string               $content    Inner blocks content.
	 * @param \WP_Block|null       $block      Block instance.
	 * @return string
	 */
	public static function render( array $attributes, string $content = '', ?\WP_Block $block = null ): string {
		unset( $content, $block );

		$attributes = AttributeSanitizer::sanitize( $attributes );
		$attributes = self::apply_pagination_context( $attributes );

		$cache_enabled = ! empty( $attributes['cacheResults'] );
		$cache_ttl     = (int) $attributes['cacheTTL'];
		$paged         = (int) $attributes['paged'];
		$cache_key     = 'loopsi_' . md5( wp_json_encode( $attributes ) );

		if ( $cache_enabled ) {
			$cached = get_transient( $cache_key );
			if ( is_string( $cached ) && '' !== $cached ) {
				return $cached;
			}
		}

		$query = QueryBuilder::make( $attributes )->get();

		if ( ! $query->have_posts() ) {
			$output = '<p class="loopsi-empty">' . esc_html__( 'No posts found.', 'loopsi-blocks' ) . '</p>';

			if ( $cache_enabled ) {
				CacheManager::remember_transient( $cache_key, $output, $cache_ttl );
			}

			return $output;
		}

		ob_start();

		echo '<div class="loopsi-block-wrap">';

		self::render_grid_open( $attributes, (int) $query->max_num_pages );
		self::render_posts( $query, $attributes );
		self::render_grid_close();

		wp_reset_postdata();

		self::render_pagination( $query, $attributes, $paged );

		echo '</div>';

		$output = ob_get_clean();

		if ( ! is_string( $output ) ) {
			$output = '';
		}

		if ( $cache_enabled ) {
			CacheManager::remember_transient( $cache_key, $output, $cache_ttl );
		}

		return $output;
	}

	/**
	 * Render posts for AJAX pagination (grid contents only).
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return array{html: string, has_more: bool}
	 */
	public static function render_ajax_page( array $attributes ): array {
		$attributes = AttributeSanitizer::sanitize( $attributes );
		$paged      = (int) $attributes['paged'];

		$query = QueryBuilder::make( $attributes )->get();

		if ( ! $query->have_posts() ) {
			return array(
				'html'     => '',
				'has_more' => false,
			);
		}

		ob_start();
		self::render_posts( $query, $attributes );
		wp_reset_postdata();

		$html = ob_get_clean();

		return array(
			'html'     => is_string( $html ) ? $html : '',
			'has_more' => $paged < (int) $query->max_num_pages,
		);
	}

	/**
	 * @param array<string, mixed> $attributes Block attributes.
	 */
	private static function apply_pagination_context( array $attributes ): array {
		$loopsi_page = filter_input( INPUT_GET, 'loopsi_page', FILTER_VALIDATE_INT );

		if ( is_int( $loopsi_page ) && $loopsi_page > 0 ) {
			$attributes['paged'] = $loopsi_page;
		}

		return $attributes;
	}

	/**
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param int                  $max_pages  Total query pages.
	 */
	private static function render_grid_open( array $attributes, int $max_pages = 1 ): void {
		$layout           = sanitize_key( (string) $attributes['layout'] );
		$pagination_type  = (string) ( $attributes['paginationType'] ?? 'none' );
		$columns          = max( 1, min( 6, (int) ( $attributes['columns'] ?? 3 ) ) );
		$query_for_script = $attributes;
		unset( $query_for_script['paged'] );

		$classes = array(
			'loopsi-post-grid',
			'loopsi-layout-' . $layout,
		);

		if ( 'grid' === $layout ) {
			$classes[] = 'loopsi-columns-' . $columns;
		}

		?>
		<div
			class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
			<?php if ( 'grid' === $layout ) : ?>
				style="<?php echo esc_attr( sprintf( 'grid-template-columns: repeat(%d, minmax(0, 1fr));', $columns ) ); ?>"
			<?php endif; ?>
			data-page="<?php echo esc_attr( (string) (int) $attributes['paged'] ); ?>"
			data-max-pages="<?php echo esc_attr( (string) max( 1, $max_pages ) ); ?>"
			data-pagination-type="<?php echo esc_attr( $pagination_type ); ?>"
			data-loopsi-query="<?php echo esc_attr( wp_json_encode( $query_for_script ) ); ?>"
		>
		<?php
	}

	private static function render_grid_close(): void {
		echo '</div>';
	}

	/**
	 * @param \WP_Query            $query      Query instance.
	 * @param array<string, mixed> $attributes Block attributes.
	 */
	private static function render_posts( \WP_Query $query, array $attributes ): void {
		$display = self::get_display_settings( $attributes );

		while ( $query->have_posts() ) {
			$query->the_post();
			PostCardRenderer::render( $display );
		}
	}

	/**
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return array<string, mixed>
	 */
	private static function get_display_settings( array $attributes ): array {
		return array(
			'showTitle'         => ! empty( $attributes['showTitle'] ),
			'showThumbnail'     => ! empty( $attributes['showThumbnail'] ) || ! empty( $attributes['showFeaturedImage'] ),
			'showFeaturedImage' => ! empty( $attributes['showThumbnail'] ) || ! empty( $attributes['showFeaturedImage'] ),
			'showExcerpt'       => ! empty( $attributes['showExcerpt'] ),
			'showAuthor'        => ! empty( $attributes['showAuthor'] ),
			'showReadMore'      => ! empty( $attributes['showReadMore'] ),
			'readMoreText'      => (string) ( $attributes['readMoreText'] ?? __( 'Read more', 'loopsi-blocks' ) ),
		);
	}

	/**
	 * @param \WP_Query            $query      Query instance.
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param int                  $paged      Current page.
	 */
	private static function render_pagination( \WP_Query $query, array $attributes, int $paged ): void {
		$pagination_type = (string) ( $attributes['paginationType'] ?? 'none' );
		$total_pages     = (int) $query->max_num_pages;

		if ( 'none' === $pagination_type || $total_pages <= 1 ) {
			return;
		}

		?>
		<div class="loopsi-pagination loopsi-pagination-<?php echo esc_attr( $pagination_type ); ?>">
		<?php

		if ( 'pagination' === $pagination_type ) {
			for ( $i = 1; $i <= $total_pages; $i++ ) {
				$classes   = 'loopsi-page';
				$is_active = ( $i === $paged );

				if ( $is_active ) {
					$classes .= ' is-active';
				}
				?>
				<button
					type="button"
					class="<?php echo esc_attr( $classes ); ?>"
					data-page="<?php echo esc_attr( (string) $i ); ?>"
					<?php echo $is_active ? ' aria-current="page" disabled' : ''; ?>
				>
					<?php echo esc_html( (string) $i ); ?>
				</button>
				<?php
			}
		} elseif ( 'loadMore' === $pagination_type ) {
			$next = $paged + 1;
			if ( $next <= $total_pages ) {
				?>
				<button type="button" class="loopsi-load-more" data-next-page="<?php echo esc_attr( (string) $next ); ?>">
					<?php esc_html_e( 'Load More', 'loopsi-blocks' ); ?>
				</button>
				<?php
			}
		} elseif ( 'infiniteScroll' === $pagination_type ) {
			?>
			<div class="loopsi-infinite-sentinel" aria-hidden="true"></div>
			<?php
		}

		?>
		</div>
		<?php
	}
}
