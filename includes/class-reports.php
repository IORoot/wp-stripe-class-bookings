<?php
/**
 * Admin reports for Stripe Bookings.
 *
 * @package IORoot_Yoga_Bookings
 */

namespace IORoot_Yoga_Bookings;

defined( 'ABSPATH' ) || exit;

abstract class Reports {

	/**
	 * @var string
	 */
	private static string $page_hook = '';

	/**
	 * Cached yearly chart payload per year (same request: enqueue + render).
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private static array $yearly_chart_cache = [];

	public static function init(): void {
		add_action( 'admin_menu', [ self::class, 'register_menu' ] );
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_assets' ] );
		add_filter( 'admin_body_class', [ self::class, 'filter_body_class' ] );
	}

	/**
	 * @param string $classes Space-prefixed body classes.
	 */
	public static function filter_body_class( string $classes ): string {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && $screen->id === CPT::BOOKING_PT . '_page_stripe-bookings-reports' ) {
			return $classes . ' ioroot-yb-reports';
		}
		return $classes;
	}

	public static function register_menu(): void {
		self::$page_hook = (string) add_submenu_page(
			'edit.php?post_type=' . CPT::BOOKING_PT,
			__( 'Reports', 'ioroot-yoga-bookings' ),
			__( 'Reports', 'ioroot-yoga-bookings' ),
			'manage_options',
			'stripe-bookings-reports',
			[ self::class, 'render_page' ]
		);
	}

	public static function enqueue_assets( string $hook ): void {
		if ( '' === self::$page_hook || $hook !== self::$page_hook ) {
			return;
		}
		wp_enqueue_style(
			'ioroot-yb-reports',
			IOROOT_YB_URL . 'assets/yoga-booking-reports-admin.css',
			[],
			IOROOT_YB_VERSION
		);

		$chart_payload = self::yearly_bookings_chart_data( self::reports_year() );

		wp_enqueue_script(
			'chart-js',
			'https://cdn.jsdelivr.net/npm/chart.js@4.4.6/dist/chart.umd.min.js',
			[],
			'4.4.6',
			true
		);
		wp_enqueue_script(
			'chart-js-adapter-date-fns',
			'https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3.0.0/dist/chartjs-adapter-date-fns.bundle.min.js',
			[ 'chart-js' ],
			'3.0.0',
			true
		);
		wp_enqueue_script(
			'hammerjs',
			'https://cdn.jsdelivr.net/npm/hammerjs@2.0.8/hammer.min.js',
			[],
			'2.0.8',
			true
		);
		wp_enqueue_script(
			'chartjs-zoom',
			'https://cdn.jsdelivr.net/npm/chartjs-plugin-zoom@2.1.0/dist/chartjs-plugin-zoom.min.js',
			[ 'chart-js', 'hammerjs' ],
			'2.1.0',
			true
		);
		wp_enqueue_script(
			'ioroot-yb-reports-chart',
			IOROOT_YB_URL . 'assets/yoga-booking-reports-chart.js',
			[ 'chart-js', 'chart-js-adapter-date-fns', 'chartjs-zoom' ],
			IOROOT_YB_VERSION,
			true
		);
		wp_localize_script(
			'ioroot-yb-reports-chart',
			'IOROOT_YB_REPORTS_CHART',
			$chart_payload
		);
	}

	public static function render_page(): void {
		$year              = self::reports_year();
		$chart = self::yearly_bookings_chart_data( $year );
		$upcoming_sessions  = self::next_sessions( 12 );
		$by_class           = self::next_sessions_by_class( 3 );
		$current_year       = (int) wp_date( 'Y' );
		$year_min           = max( 2018, $current_year - 12 );
		$year_max           = $current_year + 1;
		?>
		<div class="wrap ioroot-yb-reports-wrap">
			<header class="ioroot-yb-reports-hero">
				<div class="ioroot-yb-reports-hero__bg" aria-hidden="true">
					<span class="ioroot-yb-reports-hero__blob ioroot-yb-reports-hero__blob--1"></span>
					<span class="ioroot-yb-reports-hero__blob ioroot-yb-reports-hero__blob--2"></span>
					<span class="ioroot-yb-reports-hero__blob ioroot-yb-reports-hero__blob--3"></span>
					<span class="ioroot-yb-reports-hero__dots"></span>
				</div>
				<div class="ioroot-yb-reports-hero__inner">
					<div class="ioroot-yb-reports-hero__title-row">
						<div class="ioroot-yb-reports-hero__logo-wrap">
							<img
								class="ioroot-yb-reports-hero__logo"
								src="<?php echo esc_url( IOROOT_YB_URL . 'assets/logo_plugin.svg' ); ?>"
								width="80"
								height="67"
								alt=""
								decoding="async"
								loading="lazy"
							/>
						</div>
						<div class="ioroot-yb-reports-hero__text">
							<h1 class="ioroot-yb-reports-hero__title"><?php esc_html_e( 'Stripe Bookings Reports', 'ioroot-yoga-bookings' ); ?></h1>
							<p class="ioroot-yb-reports-hero__lead">
								<?php esc_html_e( 'Historic trends, upcoming occupancy, and per-class guest lists in one place.', 'ioroot-yoga-bookings' ); ?>
							</p>
						</div>
					</div>
				</div>
			</header>

			<section class="ioroot-yb-reports-panel" aria-labelledby="ioroot-yb-reports-historic-heading">
				<div class="ioroot-yb-reports-panel__head ioroot-yb-reports-panel__head--split">
					<div class="ioroot-yb-reports-panel__head-main">
						<h2 id="ioroot-yb-reports-historic-heading" class="ioroot-yb-reports-panel__title"><?php esc_html_e( 'Bookings by class (year view)', 'ioroot-yoga-bookings' ); ?></h2>
						<p class="ioroot-yb-reports-panel__desc">
							<?php
							printf(
								/* translators: %s: calendar year (e.g. 2026) */
								esc_html__( 'One line per Stripe Class with paid bookings. X-axis is every day from 1 Jan to 31 Dec %s (students booked that day). Scroll the mouse wheel to zoom; click-drag to pan. Double-click the chart to reset zoom.', 'ioroot-yoga-bookings' ),
								esc_html( (string) $year )
							);
							?>
						</p>
					</div>
					<form class="ioroot-yb-reports-year-form" method="get" action="<?php echo esc_url( admin_url( 'edit.php' ) ); ?>">
						<input type="hidden" name="post_type" value="<?php echo esc_attr( CPT::BOOKING_PT ); ?>" />
						<input type="hidden" name="page" value="stripe-bookings-reports" />
						<label class="ioroot-yb-reports-year-form__label" for="ioroot-yb-reports-year"><?php esc_html_e( 'Year', 'ioroot-yoga-bookings' ); ?></label>
						<select class="ioroot-yb-reports-year-form__select" id="ioroot-yb-reports-year" name="yb_year">
							<?php for ( $y = $year_min; $y <= $year_max; $y++ ) : ?>
								<option value="<?php echo esc_attr( (string) $y ); ?>"<?php selected( $year, $y ); ?>><?php echo esc_html( (string) $y ); ?></option>
							<?php endfor; ?>
						</select>
					</form>
				</div>

				<?php if ( empty( $chart['hasData'] ) ) : ?>
					<div class="ioroot-yb-reports-empty">
						<p>
							<?php
							printf(
								/* translators: %d: calendar year */
								esc_html__( 'No paid bookings with class dates in %d yet. Choose another year or complete a checkout to see data here.', 'ioroot-yoga-bookings' ),
								(int) $year
							);
							?>
						</p>
					</div>
				<?php else : ?>
					<div class="ioroot-yb-reports-chartjs" role="img" aria-label="<?php echo esc_attr( sprintf( /* translators: %d: year */ __( 'Students booked per day in %d, by class', 'ioroot-yoga-bookings' ), $year ) ); ?>">
						<canvas id="ioroot-yb-reports-year-chart"></canvas>
					</div>
					<p class="ioroot-yb-reports-actions">
						<button type="button" class="button button-secondary ioroot-yb-reports-btn" id="ioroot-yb-reports-chart-reset"><?php esc_html_e( 'Reset zoom', 'ioroot-yoga-bookings' ); ?></button>
					</p>
				<?php endif; ?>
			</section>

			<section class="ioroot-yb-reports-panel" aria-labelledby="ioroot-yb-reports-upcoming-heading">
				<div class="ioroot-yb-reports-panel__head">
					<h2 id="ioroot-yb-reports-upcoming-heading" class="ioroot-yb-reports-panel__title"><?php esc_html_e( 'Booked people for upcoming classes', 'ioroot-yoga-bookings' ); ?></h2>
					<p class="ioroot-yb-reports-panel__desc"><?php esc_html_e( 'Next sessions across all active Stripe Classes, ordered by date.', 'ioroot-yoga-bookings' ); ?></p>
				</div>
				<div class="ioroot-yb-reports-table-scroll">
					<table class="widefat striped ioroot-yb-reports-table">
						<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Class', 'ioroot-yoga-bookings' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Date', 'ioroot-yoga-bookings' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Time', 'ioroot-yoga-bookings' ); ?></th>
							<th scope="col"><?php esc_html_e( 'People booked', 'ioroot-yoga-bookings' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Capacity', 'ioroot-yoga-bookings' ); ?></th>
						</tr>
						</thead>
						<tbody>
						<?php if ( empty( $upcoming_sessions ) ) : ?>
							<tr><td colspan="5"><?php esc_html_e( 'No upcoming classes found.', 'ioroot-yoga-bookings' ); ?></td></tr>
						<?php else : ?>
							<?php foreach ( $upcoming_sessions as $session ) : ?>
								<?php
								$people = self::people_booked_count( (int) $session['class_id'], (string) $session['date'] );
								$cap    = (int) $session['capacity'];
								$pct    = $cap > 0 ? min( 100, round( 100 * $people / $cap ) ) : 0;
								?>
								<tr>
									<td data-label="<?php esc_attr_e( 'Class', 'ioroot-yoga-bookings' ); ?>"><?php echo esc_html( (string) $session['class_name'] ); ?></td>
									<td data-label="<?php esc_attr_e( 'Date', 'ioroot-yoga-bookings' ); ?>"><?php echo esc_html( Helpers::format_date( (string) $session['date'] ) ); ?></td>
									<td data-label="<?php esc_attr_e( 'Time', 'ioroot-yoga-bookings' ); ?>"><?php echo esc_html( Helpers::format_time( (string) $session['start_time'] ) ); ?></td>
									<td data-label="<?php esc_attr_e( 'People booked', 'ioroot-yoga-bookings' ); ?>">
										<span class="ioroot-yb-reports-meter">
											<span class="ioroot-yb-reports-meter__text"><?php echo esc_html( (string) $people ); ?></span>
											<span class="ioroot-yb-reports-meter__bar" style="--ioroot-yb-meter: <?php echo esc_attr( (string) $pct ); ?>%;"></span>
										</span>
									</td>
									<td data-label="<?php esc_attr_e( 'Capacity', 'ioroot-yoga-bookings' ); ?>"><?php echo esc_html( (string) $cap ); ?></td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
						</tbody>
					</table>
				</div>
			</section>

			<section class="ioroot-yb-reports-panel" aria-labelledby="ioroot-yb-reports-by-class-heading">
				<div class="ioroot-yb-reports-panel__head">
					<h2 id="ioroot-yb-reports-by-class-heading" class="ioroot-yb-reports-panel__title"><?php esc_html_e( 'Next three sessions per Stripe Class', 'ioroot-yoga-bookings' ); ?></h2>
					<p class="ioroot-yb-reports-panel__desc"><?php esc_html_e( 'Guest names and emails for each upcoming date.', 'ioroot-yoga-bookings' ); ?></p>
				</div>
				<?php if ( empty( $by_class ) ) : ?>
					<div class="ioroot-yb-reports-empty">
						<p><?php esc_html_e( 'No upcoming classes found.', 'ioroot-yoga-bookings' ); ?></p>
					</div>
				<?php else : ?>
					<div class="ioroot-yb-reports-table-scroll ioroot-yb-reports-table-scroll--wide">
						<table class="widefat striped ioroot-yb-reports-table ioroot-yb-reports-table--nested">
							<thead>
							<tr>
								<th scope="col" class="ioroot-yb-reports-table__class-col"><?php esc_html_e( 'Stripe Class', 'ioroot-yoga-bookings' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Upcoming #1', 'ioroot-yoga-bookings' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Upcoming #2', 'ioroot-yoga-bookings' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Upcoming #3', 'ioroot-yoga-bookings' ); ?></th>
							</tr>
							</thead>
							<tbody>
							<?php foreach ( $by_class as $class_row ) : ?>
								<tr>
									<td class="ioroot-yb-reports-table__class-name" data-label="<?php esc_attr_e( 'Stripe Class', 'ioroot-yoga-bookings' ); ?>">
										<strong><?php echo esc_html( (string) $class_row['class_name'] ); ?></strong>
									</td>
									<?php for ( $i = 0; $i < 3; $i++ ) : ?>
										<?php $session = $class_row['sessions'][ $i ] ?? null; ?>
										<td class="ioroot-yb-reports-session" data-label="<?php echo esc_attr( sprintf( /* translators: %d: slot number 1–3 */ __( 'Upcoming #%d', 'ioroot-yoga-bookings' ), $i + 1 ) ); ?>">
											<?php if ( ! $session ) : ?>
												<span class="ioroot-yb-reports-session__empty"><?php esc_html_e( 'No session', 'ioroot-yoga-bookings' ); ?></span>
											<?php else : ?>
												<?php $rows = self::bookings_for_session( (int) $session['class_id'], (string) $session['date'] ); ?>
												<div class="ioroot-yb-reports-session__when">
													<?php
													printf(
														/* translators: 1: date, 2: time */
														esc_html__( '%1$s · %2$s', 'ioroot-yoga-bookings' ),
														esc_html( Helpers::format_date( (string) $session['date'] ) ),
														esc_html( Helpers::format_time( (string) $session['start_time'] ) )
													);
													?>
												</div>
												<table class="ioroot-yb-reports-mini">
													<thead>
													<tr>
														<th scope="col"><?php esc_html_e( 'Name', 'ioroot-yoga-bookings' ); ?></th>
														<th scope="col"><?php esc_html_e( 'Email', 'ioroot-yoga-bookings' ); ?></th>
														<th scope="col" class="ioroot-yb-reports-mini__seats"><?php esc_html_e( 'Seats', 'ioroot-yoga-bookings' ); ?></th>
													</tr>
													</thead>
													<tbody>
													<?php if ( empty( $rows ) ) : ?>
														<tr><td colspan="3" class="ioroot-yb-reports-mini__empty"><?php esc_html_e( 'No bookings yet.', 'ioroot-yoga-bookings' ); ?></td></tr>
													<?php else : ?>
														<?php foreach ( $rows as $row ) : ?>
															<tr>
																<td><?php echo esc_html( (string) $row['name'] ); ?></td>
																<td>
																	<?php if ( (string) $row['email'] !== '' ) : ?>
																		<a href="<?php echo esc_url( 'mailto:' . sanitize_email( (string) $row['email'] ) ); ?>"><?php echo esc_html( (string) $row['email'] ); ?></a>
																	<?php endif; ?>
																</td>
																<td class="ioroot-yb-reports-mini__seats"><?php echo esc_html( (string) (int) $row['seats'] ); ?></td>
															</tr>
														<?php endforeach; ?>
													<?php endif; ?>
													</tbody>
												</table>
											<?php endif; ?>
										</td>
									<?php endfor; ?>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php endif; ?>
			</section>
		</div>
		<?php
	}

	/**
	 * @return array<int, array{class_id:int,class_name:string,date:string,start_time:string,capacity:int,ts:int}>
	 */
	private static function next_sessions( int $limit ): array {
		$classes = get_posts(
			[
				'post_type'      => CPT::CLASS_PT,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'fields'         => 'ids',
			]
		);
		$sessions = [];
		foreach ( $classes as $class_id ) {
			$class = Helpers::get_class_data( (int) $class_id );
			if ( ! $class || empty( $class['class_active'] ) ) {
				continue;
			}
			$dates = self::upcoming_dates_for_class( $class, 3 );
			foreach ( $dates as $date ) {
				if ( '' === $date ) {
					continue;
				}
				$ts = strtotime( $date . ' ' . (string) $class['start_time'] );
				$sessions[] = [
					'class_id'    => (int) $class['id'],
					'class_name'  => (string) $class['name'],
					'date'        => $date,
					'start_time'  => (string) $class['start_time'],
					'capacity'    => (int) $class['capacity'],
					'ts'          => (int) ( $ts ?: 0 ),
				];
			}
		}

		usort(
			$sessions,
			static fn( array $a, array $b ): int => (int) $a['ts'] <=> (int) $b['ts']
		);
		return array_slice( $sessions, 0, max( 1, $limit ) );
	}

	private static function people_booked_count( int $class_id, string $date ): int {
		$total = 0;
		foreach ( self::bookings_for_session( $class_id, $date ) as $row ) {
			$total += (int) $row['seats'];
		}
		return $total;
	}

	/**
	 * @return array<int, array{class_id:int,class_name:string,sessions:array<int, array{class_id:int,class_name:string,date:string,start_time:string,capacity:int,ts:int}>}>
	 */
	private static function next_sessions_by_class( int $count_per_class ): array {
		$classes = get_posts(
			[
				'post_type'      => CPT::CLASS_PT,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'fields'         => 'ids',
			]
		);
		$out = [];
		foreach ( $classes as $class_id ) {
			$class = Helpers::get_class_data( (int) $class_id );
			if ( ! $class || empty( $class['class_active'] ) ) {
				continue;
			}
			$sessions = [];
			foreach ( self::upcoming_dates_for_class( $class, $count_per_class ) as $date ) {
				if ( '' === $date ) {
					continue;
				}
				$ts = strtotime( $date . ' ' . (string) $class['start_time'] );
				$sessions[] = [
					'class_id'    => (int) $class['id'],
					'class_name'  => (string) $class['name'],
					'date'        => $date,
					'start_time'  => (string) $class['start_time'],
					'capacity'    => (int) $class['capacity'],
					'ts'          => (int) ( $ts ?: 0 ),
				];
			}
			$out[] = [
				'class_id'   => (int) $class['id'],
				'class_name' => (string) $class['name'],
				'sessions'   => $sessions,
			];
		}
		return $out;
	}

	/**
	 * Build upcoming session dates by combining:
	 * - next available dates, and
	 * - future dates that already have paid bookings.
	 *
	 * @param array<string,mixed> $class
	 * @return array<int,string> Y-m-d
	 */
	private static function upcoming_dates_for_class( array $class, int $limit ): array {
		$class_id = (int) ( $class['id'] ?? 0 );
		if ( $class_id <= 0 ) {
			return [];
		}

		$available = array_map(
			static fn( array $row ): string => (string) ( $row['date'] ?? '' ),
			Bookings::next_available_dates( $class, max( 3, $limit ) )
		);
		$booked = self::future_booked_dates_for_class( $class_id );
		$dates = array_values( array_unique( array_filter( array_merge( $available, $booked ) ) ) );
		usort(
			$dates,
			static fn( string $a, string $b ): int => strcmp( $a, $b )
		);
		return array_slice( $dates, 0, max( 1, $limit ) );
	}

	/**
	 * Future class dates with at least one paid booking.
	 *
	 * @return array<int,string> Y-m-d
	 */
	private static function future_booked_dates_for_class( int $class_id ): array {
		$today = wp_date( 'Y-m-d' );
		$query = new \WP_Query(
			[
				'post_type'      => CPT::BOOKING_PT,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => [
					'relation' => 'AND',
					[
						'key'   => '_yb_class_id',
						'value' => $class_id,
					],
					[
						'key'   => '_yb_status',
						'value' => Bookings::STATUS_PAID,
					],
					[
						'key'     => '_yb_class_date',
						'value'   => $today,
						'compare' => '>=',
						'type'    => 'DATE',
					],
				],
			]
		);
		$dates = [];
		foreach ( $query->posts as $booking_id ) {
			$date = (string) get_post_meta( (int) $booking_id, '_yb_class_date', true );
			if ( '' !== $date ) {
				$dates[] = $date;
			}
		}
		return array_values( array_unique( $dates ) );
	}

	/**
	 * @return array<int,array{name:string,email:string,seats:int}>
	 */
	private static function bookings_for_session( int $class_id, string $date ): array {
		$query = new \WP_Query(
			[
				'post_type'      => CPT::BOOKING_PT,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'orderby'        => 'date',
				'order'          => 'ASC',
				'no_found_rows'  => true,
				'meta_query'     => [
					'relation' => 'AND',
					[
						'key'   => '_yb_class_id',
						'value' => $class_id,
					],
					[
						'key'   => '_yb_class_date',
						'value' => $date,
					],
					[
						'key'   => '_yb_status',
						'value' => Bookings::STATUS_PAID,
					],
				],
			]
		);

		$rows = [];
		foreach ( $query->posts as $booking_id ) {
			$rows[] = [
				'name'  => (string) get_post_meta( (int) $booking_id, '_yb_customer_name', true ),
				'email' => (string) get_post_meta( (int) $booking_id, '_yb_customer_email', true ),
				'seats' => (int) get_post_meta( (int) $booking_id, '_yb_seats', true ),
			];
		}
		return $rows;
	}

	/**
	 * Calendar year for the reports chart (GET `yb_year`, clamped).
	 */
	private static function reports_year(): int {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter UI.
		$requested = isset( $_GET['yb_year'] ) ? absint( (string) wp_unslash( $_GET['yb_year'] ) ) : 0;
		$current   = (int) wp_date( 'Y' );
		if ( $requested < 2000 || $requested > 2100 ) {
			$requested = $current;
		}
		if ( $requested > $current + 1 ) {
			$requested = $current + 1;
		}
		return $requested;
	}

	/**
	 * Every calendar day in a year (Y-m-d), inclusive.
	 *
	 * @return array<int, string>
	 */
	private static function dates_in_calendar_year( int $year ): array {
		$tz  = function_exists( 'wp_timezone' ) ? wp_timezone() : new \DateTimeZone( 'UTC' );
		$out = [];
		$d   = new \DateTimeImmutable( sprintf( '%d-01-01', $year ), $tz );
		$end = new \DateTimeImmutable( sprintf( '%d-12-31', $year ), $tz );
		while ( $d <= $end ) {
			$out[] = $d->format( 'Y-m-d' );
			$d     = $d->modify( '+1 day' );
		}
		return $out;
	}

	private static function chart_line_color( int $index, int $total ): string {
		if ( $total < 1 ) {
			return '#0e7490';
		}
		$hue = (int) round( ( $index * 360 / $total ) % 360 );
		return sprintf( 'hsl(%d, 58%%, 40%%)', $hue );
	}

	/**
	 * Chart.js payload: one line per Stripe Class with paid bookings in the year; points for each day Jan–Dec.
	 *
	 * @return array<string, mixed>
	 */
	private static function yearly_bookings_chart_data( int $year ): array {
		if ( isset( self::$yearly_chart_cache[ $year ] ) ) {
			return self::$yearly_chart_cache[ $year ];
		}

		$start = sprintf( '%d-01-01', $year );
		$end   = sprintf( '%d-12-31', $year );
		$dates = self::dates_in_calendar_year( $year );

		$i18n = [
			'studentsBooked' => __( 'Students booked', 'ioroot-yoga-bookings' ),
			'dateAxis'       => __( 'Date', 'ioroot-yoga-bookings' ),
			'resetZoom'      => __( 'Reset zoom', 'ioroot-yoga-bookings' ),
		];

		$query = new \WP_Query(
			[
				'post_type'      => CPT::BOOKING_PT,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => [
					'relation' => 'AND',
					[
						'key'   => '_yb_status',
						'value' => Bookings::STATUS_PAID,
					],
					[
						'key'     => '_yb_class_date',
						'value'   => $start,
						'compare' => '>=',
						'type'    => 'DATE',
					],
					[
						'key'     => '_yb_class_date',
						'value'   => $end,
						'compare' => '<=',
						'type'    => 'DATE',
					],
				],
			]
		);

		/** @var array<int, array<string, int>> $counts class_id => Y-m-d => seat sum */
		$counts = [];
		foreach ( $query->posts as $booking_id ) {
			$booking_id = (int) $booking_id;
			$class_id   = (int) get_post_meta( $booking_id, '_yb_class_id', true );
			$class_date = (string) get_post_meta( $booking_id, '_yb_class_date', true );
			$seats      = (int) get_post_meta( $booking_id, '_yb_seats', true );
			if ( $class_id <= 0 || '' === $class_date ) {
				continue;
			}
			if ( ! isset( $counts[ $class_id ] ) ) {
				$counts[ $class_id ] = [];
			}
			if ( ! isset( $counts[ $class_id ][ $class_date ] ) ) {
				$counts[ $class_id ][ $class_date ] = 0;
			}
			$counts[ $class_id ][ $class_date ] += max( 1, $seats );
		}

		if ( empty( $counts ) ) {
			$payload = [
				'year'     => $year,
				'hasData'  => false,
				'xMin'     => $start,
				'xMax'     => $end,
				'datasets' => [],
				'i18n'     => $i18n,
			];
			self::$yearly_chart_cache[ $year ] = $payload;
			return $payload;
		}

		$class_ids = array_keys( $counts );
		usort(
			$class_ids,
			static function ( int $a, int $b ): int {
				return strcasecmp( get_the_title( $a ), get_the_title( $b ) );
			}
		);

		$total    = count( $class_ids );
		$datasets = [];
		$i        = 0;
		foreach ( $class_ids as $class_id ) {
			$title = get_the_title( $class_id );
			if ( '' === $title ) {
				$title = sprintf(
					/* translators: %d: Stripe Class post ID */
					__( 'Class #%d', 'ioroot-yoga-bookings' ),
					$class_id
				);
			}
			$points = [];
			foreach ( $dates as $d ) {
				$points[] = [
					'x' => $d,
					'y' => (int) ( $counts[ $class_id ][ $d ] ?? 0 ),
				];
			}
			$datasets[] = [
				'label'       => $title,
				'data'        => $points,
				'borderColor' => self::chart_line_color( $i, $total ),
			];
			++$i;
		}

		$payload = [
			'year'     => $year,
			'hasData'  => true,
			'xMin'     => $start,
			'xMax'     => $end,
			'datasets' => $datasets,
			'i18n'     => $i18n,
		];
		self::$yearly_chart_cache[ $year ] = $payload;
		return $payload;
	}
}
