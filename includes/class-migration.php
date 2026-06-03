<?php
/**
 * One-time migration from legacy unprefixed / short-prefixed identifiers.
 *
 * @package IOROOT_STRIPE_BOOKINGS
 */

namespace IOROOT_STRIPE_BOOKINGS;

defined( 'ABSPATH' ) || exit;

abstract class Migration {

	private const OPTION_VERSION = 'clasbowi_db_version';
	private const DB_VERSION     = 2;

	public static function maybe_run(): void {
		$current = (int) get_option( self::OPTION_VERSION, 0 );
		if ( $current >= self::DB_VERSION ) {
			return;
		}

		self::migrate_post_types();
		self::migrate_post_meta_keys();
		self::migrate_options();
		self::migrate_cron();
		self::migrate_page_meta();

		update_option( self::OPTION_VERSION, self::DB_VERSION, false );
	}

	private static function migrate_post_types(): void {
		global $wpdb;

		$map = [
			'clasbowi_class'   => Constants::CPT_CLASS,
			'clasbowi_booking' => Constants::CPT_BOOKING,
		];

		foreach ( $map as $from => $to ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$wpdb->posts,
				[ 'post_type' => $to ],
				[ 'post_type' => $from ],
				[ '%s' ],
				[ '%s' ]
			);
		}
	}

	private static function migrate_post_meta_keys(): void {
		global $wpdb;

		$from = '_clasbowi_';
		$to   = Constants::META_PREFIX;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->postmeta} SET meta_key = REPLACE(meta_key, %s, %s) WHERE meta_key LIKE %s AND meta_key NOT LIKE %s",
				$from,
				$to,
				$wpdb->esc_like( $from ) . '%',
				$wpdb->esc_like( $to ) . '%'
			)
		);
	}

	private static function migrate_options(): void {
		global $wpdb;

		$legacy = 'clasbowi_options';
		$new    = Constants::OPTIONS_POST_ID;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->options} SET option_name = REPLACE(option_name, %s, %s) WHERE option_name LIKE %s",
				$legacy,
				$new,
				$wpdb->esc_like( $legacy ) . '%'
			)
		);
	}

	private static function migrate_cron(): void {
		$legacy_hook = 'clasbowi_expire_holds';
		$legacy_sched = 'clasbowi_five_minutes';

		while ( $ts = wp_next_scheduled( $legacy_hook ) ) {
			wp_unschedule_event( $ts, $legacy_hook );
		}

		if ( ! wp_next_scheduled( Constants::CRON_HOOK ) ) {
			wp_schedule_event( time() + 60, Constants::CRON_SCHEDULE, Constants::CRON_HOOK );
		}

		$schedules = wp_get_schedules();
		if ( isset( $schedules[ $legacy_sched ] ) && ! isset( $schedules[ Constants::CRON_SCHEDULE ] ) ) {
			// Schedules are filters; next init will register clasbowi_five_minutes.
		}
	}

	private static function migrate_page_meta(): void {
		global $wpdb;

		$map = [
			'_clasbowi_result_page_success' => Constants::META_PREFIX . 'result_page_success',
			'_clasbowi_result_page_cancel'  => Constants::META_PREFIX . 'result_page_cancel',
			'_clasbowi_result_page_error'   => Constants::META_PREFIX . 'result_page_error',
		];

		foreach ( $map as $from => $to ) {
			if ( $from === $to ) {
				continue;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->postmeta} SET meta_key = %s WHERE meta_key = %s",
					$to,
					$from
				)
			);
		}
	}
}
