<?php
/**
 * Plugin bootstrap class.
 *
 * @package Loopsi_Blocks
 */

namespace Loopsi;

use Loopsi\Core\AttributeSanitizer;
use Loopsi\Core\BlockRenderer;
use Loopsi\Core\CacheManager;
use Loopsi\Core\QueryBuilder;

defined( 'ABSPATH' ) || exit;

/**
 * Main plugin controller.
 */
final class Plugin {

	/**
	 * @var self|null
	 */
	private static ?self $instance = null;

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		$this->init_hooks();
	}

	private function init_hooks(): void {
		add_action( 'init', array( $this, 'register_blocks' ) );
		add_action( 'wp_ajax_loopsi_load_more', array( $this, 'load_more' ) );
		add_action( 'wp_ajax_nopriv_loopsi_load_more', array( $this, 'load_more' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
		add_filter( 'render_block', array( $this, 'enqueue_script_for_block' ), 10, 2 );
		add_action( 'save_post', array( $this, 'maybe_clear_render_cache' ), 10, 2 );
	}

	public function register_blocks(): void {
		QueryBuilder::register_hooks();

		$block_path = LOOPSI_BLOCKS_PLUGIN_DIR . 'build/blocks/posts-query';

		if ( ! file_exists( $block_path . '/block.json' ) ) {
			return;
		}

		wp_register_script(
			'loopsi-load-more',
			LOOPSI_BLOCKS_PLUGIN_URL . 'assets/js/load-more.js',
			array(),
			LOOPSI_BLOCKS_VERSION,
			true
		);

		wp_localize_script(
			'loopsi-load-more',
			'loopsiAjax',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'loopsi_load_more' ),
				'i18n'    => array(
					'loadMore' => __( 'Load More', 'loopsi-blocks' ),
					'loading'  => __( 'Loading…', 'loopsi-blocks' ),
				),
			)
		);

		register_block_type(
			$block_path,
			array(
				'render_callback'     => array( BlockRenderer::class, 'render' ),
				'view_script_handles' => array( 'loopsi-load-more' ),
			)
		);
	}

	public function enqueue_editor_assets(): void {
		wp_enqueue_script( 'loopsi-load-more' );
	}

	/**
	 * Ensure frontend pagination JS loads whenever the block is rendered.
	 *
	 * @param string               $block_content Block HTML.
	 * @param array<string, mixed> $block         Block data.
	 * @return string
	 */
	public function enqueue_script_for_block( string $block_content, array $block ): string {
		if ( 'loopsi/posts-query' === ( $block['blockName'] ?? '' ) ) {
			wp_enqueue_script( 'loopsi-load-more' );
		}

		return $block_content;
	}

	/**
	 * Clear render cache when content changes.
	 *
	 * @param int           $post_id Post ID.
	 * @param \WP_Post|null $post    Post object.
	 */
	public function maybe_clear_render_cache( int $post_id, $post = null ): void {
		unset( $post );

		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		CacheManager::clear_render_cache();
	}

	public function load_more(): void {
		check_ajax_referer( 'loopsi_load_more', 'nonce' );

		$result = BlockRenderer::render_ajax_page( $this->get_ajax_request_attributes() );

		wp_send_json_success(
			array(
				'html'     => $result['html'],
				'has_more' => $result['has_more'],
			)
		);
	}

	/**
	 * Read and sanitize AJAX request attributes.
	 *
	 * @return array<string, mixed>
	 */
	private function get_ajax_request_attributes(): array {
		$query_input   = filter_input( INPUT_POST, 'query', FILTER_UNSAFE_RAW );
		$query_payload = '';

		if ( is_string( $query_input ) ) {
			$query_payload = wp_unslash( $query_input );
		}

		$attributes = AttributeSanitizer::sanitize_from_request( $query_payload );

		$paged_input = filter_input( INPUT_POST, 'paged', FILTER_VALIDATE_INT );

		if ( false !== $paged_input && null !== $paged_input ) {
			$attributes['paged'] = max( 1, (int) $paged_input );
		}

		return $attributes;
	}
}
