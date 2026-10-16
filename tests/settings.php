<?php
/**
 * Settings::sanitize() and Frontend::should_load(). WordPress is stubbed.
 * Run: php tests/settings.php
 */

declare( strict_types=1 );

namespace {
	define( 'ABSPATH', __DIR__ . '/' );

	$GLOBALS['option']   = [];
	$GLOBALS['errors']   = [];
	$GLOBALS['front']    = false;
	$GLOBALS['page_id']  = 0;
	$GLOBALS['is_page']  = false;
	$GLOBALS['images']   = [ 42 => 'https://example.test/wp-content/uploads/banner.jpg' ];
	$GLOBALS['pages']    = [ 5, 9 ];

	function get_option( $name, $fallback = false ) { return $GLOBALS['option'] ?: $fallback; }
	function wp_parse_args( $args, $defaults ) { return array_merge( $defaults, $args ); }
	function absint( $value ) { return abs( (int) $value ); }
	function wp_attachment_is_image( $id ) { return isset( $GLOBALS['images'][ $id ] ); }
	function wp_get_attachment_image_url( $id ) { return $GLOBALS['images'][ $id ] ?? false; }
	function esc_url_raw( $url, $protocols ) {
		$scheme = strtolower( (string) parse_url( $url, PHP_URL_SCHEME ) );
		return '' !== $url && in_array( $scheme, $protocols, true ) ? $url : '';
	}
	function sanitize_text_field( $text ) { return trim( strip_tags( $text ) ); }
	function sanitize_key( $key ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $key ) ); }
	function add_settings_error( $setting, $code ) { $GLOBALS['errors'][] = $code; }
	function __( $text ) { return $text; }
	function get_post_type( $id ) { return in_array( $id, $GLOBALS['pages'], true ) ? 'page' : false; }
	function wp_timezone() { return new DateTimeZone( 'Europe/Vilnius' ); }
	function is_front_page() { return $GLOBALS['front']; }
	function is_page() { return $GLOBALS['is_page']; }
	function get_queried_object_id() { return $GLOBALS['page_id']; }

	require dirname( __DIR__ ) . '/includes/Settings.php';
	require dirname( __DIR__ ) . '/includes/Frontend.php';

	use Adventistai\PopUp\Frontend;
	use Adventistai\PopUp\Settings;

	$failures = 0;
	function check( string $label, bool $condition ): void {
		global $failures;
		echo ( $condition ? 'PASS ' : 'FAIL ' ) . $label . "\n";
		$failures += $condition ? 0 : 1;
	}

	// Sanitizing.
	$out = Settings::sanitize( 'not an array' );
	check( 'non-array input falls back to defaults (checkboxes off)', 0 === $out['enabled'] && 'home' === $out['display'] );

	$out = Settings::sanitize(
		[
			'enabled'         => '1',
			'image_url'       => 'javascript:alert(1)',
			'link_url'        => 'data:text/html,x',
			'image_alt'       => '<b>Paramos</b> kvietimas',
			'display'         => 'everywhere',
			'frequency'       => 'hourly',
			'devices'         => 'tv',
			'delay'           => '999',
			'overlay_opacity' => '-5',
			'frequency_days'  => '0',
			'start_date'      => '2026-02-30',
			'end_date'        => 'tomorrow',
		]
	);
	check( 'unsafe image URL is dropped', '' === $out['image_url'] && in_array( 'wppu_image', $GLOBALS['errors'], true ) );
	check( 'unsafe link URL is dropped', '' === $out['link_url'] );
	check( 'alt text loses markup', 'Paramos kvietimas' === $out['image_alt'] );
	check( 'unknown choices fall back', 'home' === $out['display'] && 'session' === $out['frequency'] && 'all' === $out['devices'] );
	check( 'numbers are clamped', 120 === $out['delay'] && 0 === $out['overlay_opacity'] && 1 === $out['frequency_days'] );
	check( 'invalid dates are cleared', '' === $out['start_date'] && '' === $out['end_date'] );

	$GLOBALS['errors'] = [];
	$out = Settings::sanitize(
		[
			'image_id'   => '42',
			'image_url'  => 'https://example.test/other.jpg',
			'display'    => 'pages',
			'page_ids'   => [ '5', '5', '7', 'x', '9' ],
			'start_date' => '2026-11-10',
			'end_date'   => '2026-11-01',
		]
	);
	check( 'media library image wins over a typed URL', 42 === $out['image_id'] && $GLOBALS['images'][42] === $out['image_url'] );
	check( 'only existing pages are kept, once each', [ 5, 9 ] === $out['page_ids'] );
	check( 'end date before start date is cleared', '2026-11-10' === $out['start_date'] && '' === $out['end_date'] && in_array( 'wppu_dates', $GLOBALS['errors'], true ) );

	$GLOBALS['option'] = Settings::defaults();
	$same              = Settings::sanitize( Settings::defaults() );
	check( 'unchanged image and link keep the revision', 1 === $same['revision'] );
	$new_link = Settings::sanitize( [ 'link_url' => 'https://example.test/new' ] + Settings::defaults() );
	check( 'a new link bumps the revision', 2 === $new_link['revision'] );
	$reset = Settings::sanitize( [ 'reset_seen' => '1' ] + Settings::defaults() );
	check( '"show again" bumps the revision', 2 === $reset['revision'] );

	// Where it loads.
	$s = Settings::defaults();
	check( 'home: not on other pages', ! Frontend::should_load( $s, time() ) );
	$GLOBALS['front'] = true;
	check( 'home: on the front page', Frontend::should_load( $s, time() ) );
	check( 'disabled: nowhere', ! Frontend::should_load( [ 'enabled' => 0 ] + $s, time() ) );

	$pages = [ 'display' => 'pages', 'page_ids' => [ 5 ] ] + $s;
	$GLOBALS['is_page'] = true;
	$GLOBALS['page_id'] = 5;
	check( 'pages: on a selected page', Frontend::should_load( $pages, time() ) );
	$GLOBALS['page_id'] = 9;
	check( 'pages: not on another page', ! Frontend::should_load( $pages, time() ) );
	check( 'all: everywhere', Frontend::should_load( [ 'display' => 'all' ] + $s, time() ) );

	$dated = [ 'start_date' => '2026-11-01', 'end_date' => '2026-11-30' ] + $s;
	$zone  = new DateTimeZone( 'Europe/Vilnius' );
	$at    = static fn ( string $time ): int => ( new DateTimeImmutable( $time, $zone ) )->getTimestamp();
	check( 'dates: not before the start day', ! Frontend::should_load( $dated, $at( '2026-10-31 23:59:59' ) ) );
	check( 'dates: from the start of the first day', Frontend::should_load( $dated, $at( '2026-11-01 00:00:00' ) ) );
	check( 'dates: through the end of the last day', Frontend::should_load( $dated, $at( '2026-11-30 23:59:59' ) ) );
	check( 'dates: not after the end day', ! Frontend::should_load( $dated, $at( '2026-12-01 00:00:00' ) ) );

	echo $failures ? "$failures check(s) failed\n" : "All checks passed\n";
	exit( $failures ? 1 : 0 );
}
