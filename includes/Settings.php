<?php
/**
 * Pop-up settings: defaults, access and sanitizing.
 *
 * @package Adventistai\PopUp
 */

declare( strict_types=1 );

namespace Adventistai\PopUp;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings are one array option, so a single read serves every request.
 */
final class Settings {

	public const OPTION = 'wppu_settings';

	public const DISPLAY     = [ 'home', 'all', 'pages' ];
	public const FREQUENCY   = [ 'session', 'days', 'always' ];
	public const DEVICES     = [ 'all', 'desktop', 'mobile' ];
	private const DATE_SHAPE = '/^\d{4}-\d{2}-\d{2}$/D';

	/**
	 * Default values; they match the original snippet, so the plugin behaves
	 * the same until its settings are changed.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return [
			'enabled'         => 1,
			'image_id'        => 0,
			'image_url'       => 'https://dievozodis.lt/wp-content/uploads/2026/02/pamokeliu-reklama-1-1.avif',
			'image_alt'       => '',
			'link_url'        => 'https://adventistai.lt/parama/',
			'link_new_tab'    => 1,
			'display'         => 'home',     // home | all | pages
			'page_ids'        => [],
			'frequency'       => 'session',  // session | days | always
			'frequency_days'  => 7,
			'delay'           => 0,          // seconds
			'start_date'      => '',         // Y-m-d, site time zone
			'end_date'        => '',         // Y-m-d, inclusive
			'devices'         => 'all',      // all | desktop | mobile
			'close_overlay'   => 1,
			'overlay_opacity' => 70,         // percent
			'max_width'       => 0,          // px, 0 = as wide as the screen allows
			'revision'        => 1,          // bumped to show the pop-up again to everyone
		];
	}

	/**
	 * Stored settings merged over the defaults.
	 *
	 * @return array<string, mixed>
	 */
	public static function all(): array {
		$stored = get_option( self::OPTION, [] );

		return wp_parse_args( is_array( $stored ) ? $stored : [], self::defaults() );
	}

	/**
	 * Image URL to show: the media-library attachment when one is chosen.
	 *
	 * @param array<string, mixed> $settings Settings.
	 * @return string
	 */
	public static function image_url( array $settings ): string {
		$id = (int) $settings['image_id'];
		if ( $id > 0 ) {
			$url = wp_get_attachment_image_url( $id, 'full' );
			if ( is_string( $url ) && '' !== $url ) {
				return $url;
			}
		}

		return (string) $settings['image_url'];
	}

	/**
	 * Start and end of the display window as Unix timestamps in the site's
	 * time zone; 0 means open-ended.
	 *
	 * @param array<string, mixed> $settings Settings.
	 * @return array{0:int, 1:int}
	 */
	public static function window( array $settings ): array {
		$zone  = wp_timezone();
		$start = '' !== $settings['start_date'] ? ( new \DateTimeImmutable( $settings['start_date'] . ' 00:00:00', $zone ) )->getTimestamp() : 0;
		$end   = '' !== $settings['end_date'] ? ( new \DateTimeImmutable( $settings['end_date'] . ' 23:59:59', $zone ) )->getTimestamp() : 0;

		return [ $start, $end ];
	}

	/**
	 * Sanitize callback for register_setting().
	 *
	 * @param mixed $input Submitted form values.
	 * @return array<string, mixed>
	 */
	public static function sanitize( $input ): array {
		$input    = is_array( $input ) ? $input : [];
		$defaults = self::defaults();
		$previous = self::all();
		$out      = $defaults;

		foreach ( [ 'enabled', 'link_new_tab', 'close_overlay' ] as $flag ) {
			$out[ $flag ] = empty( $input[ $flag ] ) ? 0 : 1;
		}

		$image_id         = absint( self::scalar( $input['image_id'] ?? 0 ) );
		$out['image_id']  = $image_id > 0 && wp_attachment_is_image( $image_id ) ? $image_id : 0;
		$out['image_url'] = self::url( $input['image_url'] ?? '' );
		if ( $out['image_id'] > 0 ) {
			$attachment_url   = wp_get_attachment_image_url( $out['image_id'], 'full' );
			$out['image_url'] = is_string( $attachment_url ) ? $attachment_url : $out['image_url'];
		}
		if ( '' === $out['image_url'] ) {
			self::notice( 'wppu_image', __( 'Pasirinkite paveikslėlį: be jo iššokantis langas nerodomas.', 'wp-pop-up' ), 'warning' );
		}

		$out['image_alt'] = mb_substr( sanitize_text_field( self::scalar( $input['image_alt'] ?? '' ) ), 0, 200 );
		$out['link_url']  = self::url( $input['link_url'] ?? '' );

		$out['display']   = self::choice( $input['display'] ?? '', self::DISPLAY, $defaults['display'] );
		$out['frequency'] = self::choice( $input['frequency'] ?? '', self::FREQUENCY, $defaults['frequency'] );
		$out['devices']   = self::choice( $input['devices'] ?? '', self::DEVICES, $defaults['devices'] );

		$page_ids        = is_array( $input['page_ids'] ?? null ) ? $input['page_ids'] : [];
		$page_ids        = array_values( array_unique( array_filter( array_map( 'absint', $page_ids ) ) ) );
		$out['page_ids'] =array_values( array_filter( $page_ids, static fn ( int $id ): bool => 'page' === get_post_type( $id ) ) );
		if ( 'pages' === $out['display'] && [] === $out['page_ids'] ) {
			self::notice( 'wppu_pages', __( 'Nepažymėtas nė vienas puslapis, todėl iššokantis langas nebus rodomas.', 'wp-pop-up' ), 'warning' );
		}

		$out['frequency_days']  = self::number( $input['frequency_days'] ?? $defaults['frequency_days'], 1, 365 );
		$out['delay']           = self::number( $input['delay'] ?? 0, 0, 120 );
		$out['overlay_opacity'] = self::number( $input['overlay_opacity'] ?? $defaults['overlay_opacity'], 0, 100 );
		$out['max_width']       = self::number( $input['max_width'] ?? 0, 0, 4000 );

		$out['start_date'] = self::date( $input['start_date'] ?? '' );
		$out['end_date']   = self::date( $input['end_date'] ?? '' );
		if ( '' !== $out['start_date'] && '' !== $out['end_date'] && $out['end_date'] < $out['start_date'] ) {
			self::notice( 'wppu_dates', __( 'Pabaigos data ankstesnė už pradžios datą, todėl pabaigos data išvalyta.', 'wp-pop-up' ) );
			$out['end_date'] = '';
		}

		// A new image or link is a new campaign: show it again to visitors who
		// already closed the old one. The "show again" box does the same on demand.
		$revision = max( 1, (int) $previous['revision'] );
		$changed  = $out['image_url'] !== $previous['image_url'] || $out['link_url'] !== $previous['link_url'];
		if ( $changed || ! empty( $input['reset_seen'] ) ) {
			$revision = $revision >= 1000000 ? 1 : $revision + 1;
		}
		$out['revision'] = $revision;

		return $out;
	}

	/**
	 * Add a settings notice once. WordPress runs the sanitizer twice when the
	 * option is first created, which would otherwise show every notice twice.
	 *
	 * @param string $code Notice code.
	 * @param string $message Notice text.
	 * @param string $type error | warning.
	 */
	private static function notice( string $code, string $message, string $type = 'error' ): void {
		static $added = [];
		if ( isset( $added[ $code ] ) ) {
			return;
		}
		$added[ $code ] = true;
		add_settings_error( self::OPTION, $code, $message, $type );
	}

	/**
	 * Keep only a scalar value.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	private static function scalar( $value ): string {
		return is_scalar( $value ) ? (string) $value : '';
	}

	/**
	 * An http(s) URL, or '' for anything else.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	private static function url( $value ): string {
		return esc_url_raw( trim( self::scalar( $value ) ), [ 'http', 'https' ] );
	}

	/**
	 * One of the allowed values, or the fallback.
	 *
	 * @param mixed    $value Raw value.
	 * @param string[] $allowed Allowed values.
	 * @param string   $fallback Fallback.
	 * @return string
	 */
	private static function choice( $value, array $allowed, string $fallback ): string {
		$value = sanitize_key( self::scalar( $value ) );

		return in_array( $value, $allowed, true ) ? $value : $fallback;
	}

	/**
	 * An integer clamped to [min, max].
	 *
	 * @param mixed $value Raw value.
	 * @param int   $min Minimum.
	 * @param int   $max Maximum.
	 * @return int
	 */
	private static function number( $value, int $min, int $max ): int {
		return max( $min, min( $max, (int) self::scalar( $value ) ) );
	}

	/**
	 * A real Y-m-d date, or ''.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	private static function date( $value ): string {
		$value = trim( self::scalar( $value ) );
		if ( ! preg_match( self::DATE_SHAPE, $value ) ) {
			return '';
		}
		[ $year, $month, $day ] = array_map( 'intval', explode( '-', $value ) );

		return checkdate( $month, $day, $year ) ? $value : '';
	}
}
