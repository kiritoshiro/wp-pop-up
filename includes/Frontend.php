<?php
/**
 * Loads the pop-up on the pages its settings select.
 *
 * @package Adventistai\PopUp
 */

declare( strict_types=1 );

namespace Adventistai\PopUp;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The page decides where the pop-up may appear; the script decides whether
 * this visitor sees it now (frequency, dates and device are checked in the
 * browser too, so cached pages stay correct).
 */
final class Frontend {

	public const HANDLE      = 'wppu-popup';
	public const PREVIEW_ARG = 'wppu_preview';

	/**
	 * Register hooks.
	 */
	public function hooks(): void {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue' ] );
	}

	/**
	 * Enqueue the script and style, with the settings the script needs.
	 */
	public function enqueue(): void {
		if ( is_feed() || is_embed() || is_customize_preview() ) {
			return;
		}

		$settings = Settings::all();
		$preview  = self::is_preview();

		if ( ! $preview && ! self::should_load( $settings, time() ) ) {
			return;
		}

		$image = Settings::image_url( $settings );
		if ( '' === $image ) {
			return;
		}

		[ $start, $end ] = Settings::window( $settings );

		$config = [
			'image'          => $image,
			'alt'            => '' !== $settings['image_alt'] ? $settings['image_alt'] : get_bloginfo( 'name' ),
			'link'           => $settings['link_url'],
			'newTab'         => (bool) $settings['link_new_tab'],
			'frequency'      => $preview ? 'always' : $settings['frequency'],
			'days'           => (int) $settings['frequency_days'],
			'delay'          => $preview ? 0 : (int) $settings['delay'],
			'start'          => $preview ? 0 : $start * 1000,
			'end'            => $preview ? 0 : $end * 1000,
			'devices'        => $preview ? 'all' : $settings['devices'],
			'closeOnOverlay' => (bool) $settings['close_overlay'],
			'opacity'        => (int) $settings['overlay_opacity'] / 100,
			'maxWidth'       => (int) $settings['max_width'],
			'revision'       => (int) $settings['revision'],
			'closeLabel'     => __( 'Uždaryti', 'wp-pop-up' ),
			'newTabLabel'    => __( '(atsidaro naujame skirtuke)', 'wp-pop-up' ),
		];

		wp_enqueue_style( self::HANDLE, WPPU_URL . 'assets/css/popup.css', [], VERSION );
		wp_enqueue_script(
			self::HANDLE,
			WPPU_URL . 'assets/js/popup.js',
			[],
			VERSION,
			[
				'in_footer' => true,
				'strategy'  => 'defer',
			]
		);
		wp_add_inline_script( self::HANDLE, 'window.wppuConfig = ' . wp_json_encode( $config ) . ';', 'before' );
	}

	/**
	 * Whether the current page is one the settings select, on this date.
	 *
	 * @param array<string, mixed> $settings Settings.
	 * @param int                  $now Current Unix time.
	 * @return bool
	 */
	public static function should_load( array $settings, int $now ): bool {
		if ( empty( $settings['enabled'] ) ) {
			return false;
		}

		[ $start, $end ] = Settings::window( $settings );
		if ( ( $start && $now < $start ) || ( $end && $now > $end ) ) {
			return false;
		}

		switch ( $settings['display'] ) {
			case 'all':
				return true;
			case 'pages':
				return is_page() && in_array( (int) get_queried_object_id(), array_map( 'intval', (array) $settings['page_ids'] ), true );
			default:
				return is_front_page();
		}
	}

	/**
	 * "?wppu_preview=1" shows the pop-up at once to an administrator, on any
	 * page and whatever the other settings say (even when it is turned off).
	 *
	 * @return bool
	 */
	public static function is_preview(): bool {
		return ! empty( $_GET[ self::PREVIEW_ARG ] ) && current_user_can( 'manage_options' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only preview for administrators; nothing is changed.
	}
}
