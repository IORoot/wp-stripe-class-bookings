<?php
/**
 * Ensure ACF is available.
 *
 * If ACF (Free or Pro) is active, we use it.
 * Otherwise, we bootstrap the bundled ACF Free copy.
 *
 * @package IORoot_Yoga_Bookings
 */

namespace IORoot_Yoga_Bookings;

defined( 'ABSPATH' ) || exit;

final class ACF_Dependency {
	private const BUNDLED_ACF_BOOTSTRAP = 'vendor/acf/acf.php';

	public static function init(): void {
		// Load bundled ACF as early as possible (but still after WP has loaded plugins).
		add_action( 'plugins_loaded', [ self::class, 'maybe_bootstrap_bundled_acf' ], 0 );
	}

	public static function maybe_bootstrap_bundled_acf(): void {
		// If ACF is already active (Free/Pro), always prefer that.
		if ( self::is_acf_loaded() ) {
			return;
		}

		$bootstrap = defined( 'IOROOT_YB_DIR' ) ? IOROOT_YB_DIR . self::BUNDLED_ACF_BOOTSTRAP : null;
		if ( ! $bootstrap || ! file_exists( $bootstrap ) ) {
			return;
		}

		// This file is the official ACF Free plugin bootstrap.
		include_once $bootstrap;
	}

	private static function is_acf_loaded(): bool {
		return class_exists( 'ACF' ) || function_exists( 'acf' );
	}
}

