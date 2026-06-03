<?php
/**
 * Ensure ACF is available.
 *
 * If ACF (Free or Pro) is active, we use it.
 * Otherwise, we bootstrap the bundled ACF Free copy and hide its admin UI.
 *
 * @package IOROOT_STRIPE_BOOKINGS
 */

namespace IOROOT_STRIPE_BOOKINGS;

defined( 'ABSPATH' ) || exit;

final class ACF_Dependency {
	private const BUNDLED_ACF_BOOTSTRAP = 'vendor/acf/acf.php';

	/** @var bool Whether this request is using the bundled ACF copy (not a separate install). */
	private static bool $using_bundled_acf = false;

	public static function init(): void {
		// Load bundled ACF as early as possible (but still after WP has loaded plugins).
		add_action( 'plugins_loaded', [ self::class, 'maybe_bootstrap_bundled_acf' ], 0 );
	}

	public static function is_using_bundled_acf(): bool {
		return self::$using_bundled_acf;
	}

	public static function maybe_bootstrap_bundled_acf(): void {
		// If ACF is already active (Free/Pro), always prefer that.
		if ( self::is_acf_loaded() ) {
			return;
		}

		$bootstrap = defined( 'CLASBOWI_DIR' ) ? CLASBOWI_DIR . self::BUNDLED_ACF_BOOTSTRAP : null;
		if ( ! $bootstrap || ! file_exists( $bootstrap ) ) {
			return;
		}

		self::$using_bundled_acf = true;
		self::register_bundled_admin_restrictions();

		// ACF's supported flag for hiding the admin UI while keeping field APIs.
		if ( ! defined( 'ACF_LITE' ) ) {
			define( 'ACF_LITE', true );
		}

		// This file is the official ACF Free plugin bootstrap.
		include_once $bootstrap;
	}

	/**
	 * Hide bundled ACF from wp-admin. Does not run when a separate ACF install is active.
	 */
	private static function register_bundled_admin_restrictions(): void {
		add_filter( 'acf/settings/show_admin', '__return_false' );
		add_filter( 'acf/settings/show_updates', '__return_false' );
		add_filter( 'acf/settings/enable_post_types', '__return_false' );
		add_filter( 'acf/settings/enable_options_pages_ui', '__return_false' );
		add_filter( 'register_post_type_args', [ self::class, 'hide_bundled_acf_post_type_ui' ], 10, 2 );
		add_action( 'admin_menu', [ self::class, 'remove_bundled_acf_menus' ], 999 );
		add_action( 'admin_init', [ self::class, 'block_bundled_acf_admin_screens' ], 1 );
	}

	/**
	 * @param array<string, mixed> $args      Post type registration args.
	 * @param string               $post_type Post type slug.
	 * @return array<string, mixed>
	 */
	public static function hide_bundled_acf_post_type_ui( array $args, string $post_type ): array {
		if ( ! self::$using_bundled_acf ) {
			return $args;
		}

		if ( in_array( $post_type, self::internal_post_types(), true ) ) {
			$args['show_ui'] = false;
		}

		return $args;
	}

	public static function remove_bundled_acf_menus(): void {
		if ( ! self::$using_bundled_acf ) {
			return;
		}

		$parent = 'edit.php?post_type=acf-field-group';

		remove_menu_page( $parent );
		remove_menu_page( 'edit.php?post_type=acf-post-type' );
		remove_menu_page( 'edit.php?post_type=acf-taxonomy' );
		remove_menu_page( 'edit.php?post_type=acf-ui-options-page' );

		remove_submenu_page( $parent, $parent );
		remove_submenu_page( $parent, 'edit.php?post_type=acf-post-type' );
		remove_submenu_page( $parent, 'edit.php?post_type=acf-taxonomy' );
		remove_submenu_page( $parent, 'acf-tools' );
	}

	public static function block_bundled_acf_admin_screens(): void {
		if ( ! self::$using_bundled_acf || ! is_admin() ) {
			return;
		}

		global $pagenow;

		$internal = self::internal_post_types();

		if ( 'edit.php' === $pagenow || 'post-new.php' === $pagenow ) {
			$post_type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( (string) $_GET['post_type'] ) ) : '';
			if ( $post_type && in_array( $post_type, $internal, true ) ) {
				wp_safe_redirect( admin_url() );
				exit;
			}
		}

		if ( 'post.php' === $pagenow && isset( $_GET['post'] ) ) {
			$post_id = (int) $_GET['post'];
			if ( $post_id && in_array( get_post_type( $post_id ), $internal, true ) ) {
				wp_safe_redirect( admin_url() );
				exit;
			}
		}

		if ( isset( $_GET['page'] ) ) {
			$page = sanitize_key( wp_unslash( (string) $_GET['page'] ) );
			if ( in_array( $page, [ 'acf-tools', 'acf-settings-updates', 'acf-options-preview' ], true ) ) {
				wp_safe_redirect( admin_url() );
				exit;
			}
		}
	}

	/**
	 * @return list<string>
	 */
	private static function internal_post_types(): array {
		if ( function_exists( 'acf_get_internal_post_types' ) ) {
			return acf_get_internal_post_types();
		}

		return [ 'acf-field-group', 'acf-post-type', 'acf-taxonomy', 'acf-ui-options-page' ];
	}

	private static function is_acf_loaded(): bool {
		return class_exists( 'ACF' ) || function_exists( 'acf' );
	}
}
