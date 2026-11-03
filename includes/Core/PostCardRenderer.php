<?php
/**
 * Post card markup for query grids.
 *
 * @package Loopsi_Blocks
 */

namespace Loopsi\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Renders a single post card in the query grid.
 */
final class PostCardRenderer {

	/**
	 * Render one post card for the current post in the loop.
	 *
	 * @param array<string, mixed> $display Display settings.
	 */
	public static function render( array $display = array() ): void {
		$show_title     = ! empty( $display['showTitle'] );
		$show_thumbnail = ! empty( $display['showThumbnail'] ) || ! empty( $display['showFeaturedImage'] );
		$show_excerpt   = ! empty( $display['showExcerpt'] );
		$show_author    = ! empty( $display['showAuthor'] );
		$show_read_more = ! empty( $display['showReadMore'] );
		$read_more_text = isset( $display['readMoreText'] ) && '' !== $display['readMoreText']
			? (string) $display['readMoreText']
			: __( 'Read more', 'loopsi-blocks' );

		?>
		<article <?php post_class( 'loopsi-post-card' ); ?>>
			<?php self::render_thumbnail( $show_thumbnail ); ?>

			<div class="loopsi-content">
				<?php if ( $show_title ) : ?>
					<h3 class="loopsi-title">
						<a href="<?php the_permalink(); ?>">
							<?php the_title(); ?>
						</a>
					</h3>
				<?php endif; ?>

				<?php if ( $show_author ) : ?>
					<p class="loopsi-author">
						<?php
						printf(
							/* translators: %s: post author display name */
							esc_html__( 'By %s', 'loopsi-blocks' ),
							esc_html( get_the_author() )
						);
						?>
					</p>
				<?php endif; ?>

				<?php if ( $show_excerpt ) : ?>
					<div class="loopsi-excerpt">
						<?php echo wp_kses_post( wp_trim_words( get_the_excerpt(), 18 ) ); ?>
					</div>
				<?php endif; ?>

				<?php if ( $show_read_more ) : ?>
					<p class="loopsi-read-more-wrap">
						<a class="loopsi-read-more" href="<?php the_permalink(); ?>">
							<?php echo esc_html( $read_more_text ); ?>
						</a>
					</p>
				<?php endif; ?>
			</div>
		</article>
		<?php
	}

	/**
	 * Render the featured image or a placeholder when thumbnails are enabled.
	 *
	 * @param bool $show_thumbnail Whether thumbnails are enabled.
	 */
	private static function render_thumbnail( bool $show_thumbnail ): void {
		if ( ! $show_thumbnail ) {
			return;
		}

		$has_thumbnail = has_post_thumbnail();
		$thumb_classes = 'loopsi-thumb';

		if ( ! $has_thumbnail ) {
			$thumb_classes .= ' loopsi-thumb--placeholder';
		}

		?>
		<div class="<?php echo esc_attr( $thumb_classes ); ?>">
			<a href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
				<?php if ( $has_thumbnail ) : ?>
					<?php the_post_thumbnail( 'medium_large' ); ?>
				<?php else : ?>
					<img
						class="loopsi-thumb-placeholder-img"
						src="<?php echo esc_url( self::get_placeholder_image_url() ); ?>"
						alt=""
						loading="lazy"
						decoding="async"
					/>
				<?php endif; ?>
			</a>
		</div>
		<?php
	}

	/**
	 * @return string Placeholder image URL for posts without a featured image.
	 */
	private static function get_placeholder_image_url(): string {
		return LOOPSI_BLOCKS_PLUGIN_URL . 'assets/images/placeholder.svg';
	}
}
