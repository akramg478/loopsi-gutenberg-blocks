<?php
/**
 * Plugin Name: Loopsi - Blocks
 * Description: Advanced Gutenberg query blocks for displaying WordPress content with flexible layouts and filtering.
 * Version: 1.0.0
 * Author:            Akram Ul Haq
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: loopsi-blocks
 * Domain Path: /languages
 * Requires at least: 5.3
 * Requires PHP: 8.0
 *
 * @package Loopsi_Blocks
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

defined( 'LOOPSI_BLOCKS_VERSION' ) || define( 'LOOPSI_BLOCKS_VERSION', '1.0.0' );
defined( 'LOOPSI_BLOCKS_PLUGIN_DIR' ) || define( 'LOOPSI_BLOCKS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
defined( 'LOOPSI_BLOCKS_PLUGIN_URL' ) || define( 'LOOPSI_BLOCKS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

$loopsi_autoload = __DIR__ . '/vendor/autoload.php';

if ( ! file_exists( $loopsi_autoload ) ) {
	add_action(
		'admin_notices',
		function () {
			echo '<div class="notice notice-error"><p>';
			echo esc_html__( 'Loopsi - Blocks requires Composer dependencies. Please run "composer install" in the plugin directory.', 'loopsi-blocks' );
			echo '</p></div>';
		}
	);
	return;
}

require_once $loopsi_autoload;

add_action(
	'plugins_loaded',
	function () {
		if ( ! class_exists( 'Loopsi\Plugin' ) ) {
			add_action(
				'admin_notices',
				function () {
					echo '<div class="notice notice-error"><p>';
					echo esc_html__( 'Loopsi - Blocks failed to load core plugin class.', 'loopsi-blocks' );
					echo '</p></div>';
				}
			);
			return;
		}

		try {
			Loopsi\Plugin::get_instance();
		} catch ( \Throwable $loopsi_exception ) {
			unset( $loopsi_exception );

			add_action(
				'admin_notices',
				function () {
					echo '<div class="notice notice-error"><p>';
					echo esc_html__( 'Loopsi - Blocks encountered a critical error during initialization.', 'loopsi-blocks' );
					echo '</p></div>';
				}
			);
		}
	}
);

register_activation_hook(
	__FILE__,
	function () {
		if ( version_compare( PHP_VERSION, '8.0', '<' ) ) {
			deactivate_plugins( plugin_basename( __FILE__ ) );
			wp_die( esc_html__( 'Loopsi - Blocks requires PHP 8.0 or higher.', 'loopsi-blocks' ) );
		}

		flush_rewrite_rules();
	}
);

register_deactivation_hook(
	__FILE__,
	function () {
		// Reserved for future cleanup if needed.
	}
);
