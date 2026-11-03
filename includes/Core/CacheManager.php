<?php
/**
 * Transient cache helpers for block render output.
 *
 * @package Loopsi_Blocks
 */

namespace Loopsi\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Clears Loopsi render transients.
 */
final class CacheManager {

	/**
	 * Transient key prefix used by BlockRenderer.
	 */
	private const TRANSIENT_PREFIX = 'loopsi_';

	/**
	 * Option name that tracks render cache transient keys.
	 */
	private const CACHE_KEYS_OPTION = 'loopsi_render_cache_keys';

	/**
	 * Maximum number of cache keys to track.
	 */
	private const MAX_REGISTERED_KEYS = 200;

	/**
	 * Store rendered output in a transient and register its key for later cleanup.
	 *
	 * @param string $cache_key Transient key.
	 * @param string $value     Cached HTML.
	 * @param int    $ttl       Time to live in seconds.
	 */
	public static function remember_transient( string $cache_key, string $value, int $ttl ): void {
		set_transient( $cache_key, $value, $ttl );
		self::register_cache_key( $cache_key );
	}

	/**
	 * Delete all plugin render cache transients.
	 */
	public static function clear_render_cache(): void {
		$keys = get_option( self::CACHE_KEYS_OPTION, array() );

		if ( is_array( $keys ) ) {
			foreach ( $keys as $cache_key ) {
				if ( is_string( $cache_key ) && 0 === strpos( $cache_key, self::TRANSIENT_PREFIX ) ) {
					delete_transient( $cache_key );
				}
			}
		}

		delete_option( self::CACHE_KEYS_OPTION );
	}

	/**
	 * @param string $cache_key Transient key.
	 */
	private static function register_cache_key( string $cache_key ): void {
		if ( 0 !== strpos( $cache_key, self::TRANSIENT_PREFIX ) ) {
			return;
		}

		$keys = get_option( self::CACHE_KEYS_OPTION, array() );

		if ( ! is_array( $keys ) ) {
			$keys = array();
		}

		if ( in_array( $cache_key, $keys, true ) ) {
			return;
		}

		$keys[] = $cache_key;

		if ( count( $keys ) > self::MAX_REGISTERED_KEYS ) {
			$keys = array_slice( $keys, -self::MAX_REGISTERED_KEYS );
		}

		update_option( self::CACHE_KEYS_OPTION, $keys, false );
	}
}
