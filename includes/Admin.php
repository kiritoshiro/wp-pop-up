<?php
/**
 * Settings → Pop-up screen.
 *
 * @package Adventistai\PopUp
 */

declare( strict_types=1 );

namespace Adventistai\PopUp;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the setting and renders its form (saved through options.php).
 */
final class Admin {

	public const SLUG  = 'wp-pop-up';
	private const GROUP = 'wppu_settings_group';

	/**
	 * Register hooks.
	 */
	public function hooks(): void {
		add_action( 'admin_menu', [ $this, 'menu' ] );
		add_action( 'admin_init', [ $this, 'register' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'assets' ] );
		add_filter( 'plugin_action_links_' . plugin_basename( WPPU_FILE ), [ $this, 'action_links' ] );
	}

	/**
	 * Add the screen under Settings.
	 */
	public function menu(): void {
		add_options_page(
			__( 'Pop-up nustatymai', 'wp-pop-up' ),
			__( 'Pop-up', 'wp-pop-up' ),
			'manage_options',
			self::SLUG,
			[ $this, 'render' ]
		);
	}

	/**
	 * Register the option and its sanitizer.
	 */
	public function register(): void {
		register_setting(
			self::GROUP,
			Settings::OPTION,
			[
				'type'              => 'array',
				'sanitize_callback' => [ Settings::class, 'sanitize' ],
				'default'           => Settings::defaults(),
			]
		);
	}

	/**
	 * Media library and the small form script, on this screen only.
	 *
	 * @param string $hook_suffix Current admin screen.
	 */
	public function assets( string $hook_suffix ): void {
		if ( 'settings_page_' . self::SLUG !== $hook_suffix ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_style( 'wppu-admin', WPPU_URL . 'assets/css/admin.css', [], VERSION );
		wp_enqueue_script( 'wppu-admin', WPPU_URL . 'assets/js/admin.js', [ 'jquery' ], VERSION, true );
		wp_localize_script(
			'wppu-admin',
			'wppuAdmin',
			[
				'title'  => __( 'Pasirinkite iššokančio lango paveikslėlį', 'wp-pop-up' ),
				'button' => __( 'Naudoti šį paveikslėlį', 'wp-pop-up' ),
			]
		);
	}

	/**
	 * "Nustatymai" link on the Plugins screen.
	 *
	 * @param string[] $links Existing links.
	 * @return string[]
	 */
	public function action_links( array $links ): array {
		array_unshift(
			$links,
			sprintf( '<a href="%s">%s</a>', esc_url( admin_url( 'options-general.php?page=' . self::SLUG ) ), esc_html__( 'Nustatymai', 'wp-pop-up' ) )
		);

		return $links;
	}

	/**
	 * Render the settings form.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$s     = Settings::all();
		$name  = Settings::OPTION;
		$image = Settings::image_url( $s );
		$pages = get_pages(
			[
				'sort_column' => 'post_title',
				'post_status' => 'publish',
			]
		);
		?>
		<div class="wrap wppu-settings">
			<h1><?php esc_html_e( 'Pop-up nustatymai', 'wp-pop-up' ); ?></h1>

			<p>
				<a class="button" href="<?php echo esc_url( add_query_arg( Frontend::PREVIEW_ARG, '1', home_url( '/' ) ) ); ?>" target="_blank" rel="noopener">
					<?php esc_html_e( 'Peržiūrėti svetainėje', 'wp-pop-up' ); ?>
				</a>
				<span class="description"><?php esc_html_e( 'Peržiūra matoma tik administratoriams ir rodo išsaugotus nustatymus nepaisant puslapių, datų ir dažnumo.', 'wp-pop-up' ); ?></span>
			</p>

			<form method="post" action="options.php">
				<?php settings_fields( self::GROUP ); ?>

				<h2 class="title"><?php esc_html_e( 'Turinys', 'wp-pop-up' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Įjungta', 'wp-pop-up' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[enabled]" value="1" <?php checked( 1, (int) $s['enabled'] ); ?>>
								<?php esc_html_e( 'Rodyti iššokantį langą lankytojams', 'wp-pop-up' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wppu-image-url"><?php esc_html_e( 'Paveikslėlis', 'wp-pop-up' ); ?></label></th>
						<td>
							<div class="wppu-image-preview" id="wppu-image-preview">
								<?php if ( '' !== $image ) : ?>
									<img src="<?php echo esc_url( $image ); ?>" alt="">
								<?php endif; ?>
							</div>
							<input type="hidden" id="wppu-image-id" name="<?php echo esc_attr( $name ); ?>[image_id]" value="<?php echo esc_attr( (string) (int) $s['image_id'] ); ?>">
							<input type="url" class="large-text code" id="wppu-image-url" name="<?php echo esc_attr( $name ); ?>[image_url]" value="<?php echo esc_attr( $image ); ?>" placeholder="https://">
							<p>
								<button type="button" class="button" id="wppu-image-select"><?php esc_html_e( 'Pasirinkti iš mediatekos', 'wp-pop-up' ); ?></button>
								<button type="button" class="button-link button-link-delete" id="wppu-image-clear"><?php esc_html_e( 'Pašalinti', 'wp-pop-up' ); ?></button>
							</p>
							<p class="description"><?php esc_html_e( 'Pasirinkite paveikslėlį iš mediatekos arba įklijuokite jo adresą.', 'wp-pop-up' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wppu-image-alt"><?php esc_html_e( 'Alternatyvusis tekstas', 'wp-pop-up' ); ?></label></th>
						<td>
							<input type="text" class="regular-text" id="wppu-image-alt" name="<?php echo esc_attr( $name ); ?>[image_alt]" value="<?php echo esc_attr( $s['image_alt'] ); ?>" maxlength="200">
							<p class="description"><?php esc_html_e( 'Ką rodo paveikslėlis (ekrano skaitytuvams). Jei paliksite tuščią, bus naudojamas svetainės pavadinimas.', 'wp-pop-up' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wppu-link-url"><?php esc_html_e( 'Nuoroda', 'wp-pop-up' ); ?></label></th>
						<td>
							<input type="url" class="large-text code" id="wppu-link-url" name="<?php echo esc_attr( $name ); ?>[link_url]" value="<?php echo esc_attr( $s['link_url'] ); ?>" placeholder="https://">
							<p class="description"><?php esc_html_e( 'Kur nuveda paspaudus paveikslėlį. Palikite tuščią, jei paveikslėlis neturi būti nuoroda.', 'wp-pop-up' ); ?></p>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[link_new_tab]" value="1" <?php checked( 1, (int) $s['link_new_tab'] ); ?>>
								<?php esc_html_e( 'Atidaryti naujame skirtuke', 'wp-pop-up' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'Kur ir kada rodyti', 'wp-pop-up' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Puslapiai', 'wp-pop-up' ); ?></th>
						<td>
							<fieldset>
								<legend class="screen-reader-text"><?php esc_html_e( 'Puslapiai', 'wp-pop-up' ); ?></legend>
								<?php
								$display = [
									'home'  => __( 'Tik pradiniame puslapyje', 'wp-pop-up' ),
									'all'   => __( 'Visuose puslapiuose', 'wp-pop-up' ),
									'pages' => __( 'Pasirinktuose puslapiuose', 'wp-pop-up' ),
								];
								foreach ( $display as $value => $label ) :
									?>
									<label>
										<input type="radio" name="<?php echo esc_attr( $name ); ?>[display]" value="<?php echo esc_attr( $value ); ?>" <?php checked( $value, $s['display'] ); ?>>
										<?php echo esc_html( $label ); ?>
									</label><br>
								<?php endforeach; ?>
							</fieldset>
							<div class="wppu-pages" id="wppu-pages">
								<?php if ( [] === $pages ) : ?>
									<p class="description"><?php esc_html_e( 'Svetainėje nėra paskelbtų puslapių.', 'wp-pop-up' ); ?></p>
								<?php endif; ?>
								<?php foreach ( $pages as $page ) : ?>
									<label>
										<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[page_ids][]" value="<?php echo esc_attr( (string) $page->ID ); ?>" <?php checked( in_array( (int) $page->ID, array_map( 'intval', (array) $s['page_ids'] ), true ) ); ?>>
										<?php echo esc_html( '' !== $page->post_title ? $page->post_title : '#' . $page->ID ); ?>
									</label>
								<?php endforeach; ?>
							</div>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Dažnumas', 'wp-pop-up' ); ?></th>
						<td>
							<fieldset>
								<legend class="screen-reader-text"><?php esc_html_e( 'Dažnumas', 'wp-pop-up' ); ?></legend>
								<label>
									<input type="radio" name="<?php echo esc_attr( $name ); ?>[frequency]" value="session" <?php checked( 'session', $s['frequency'] ); ?>>
									<?php esc_html_e( 'Kartą per apsilankymą (kol neuždaryta naršyklė)', 'wp-pop-up' ); ?>
								</label><br>
								<label>
									<input type="radio" name="<?php echo esc_attr( $name ); ?>[frequency]" value="days" <?php checked( 'days', $s['frequency'] ); ?>>
									<?php esc_html_e( 'Kartą per', 'wp-pop-up' ); ?>
								</label>
								<input type="number" class="small-text" name="<?php echo esc_attr( $name ); ?>[frequency_days]" value="<?php echo esc_attr( (string) (int) $s['frequency_days'] ); ?>" min="1" max="365" aria-label="<?php esc_attr_e( 'Dienų skaičius', 'wp-pop-up' ); ?>">
								<?php esc_html_e( 'd.', 'wp-pop-up' ); ?><br>
								<label>
									<input type="radio" name="<?php echo esc_attr( $name ); ?>[frequency]" value="always" <?php checked( 'always', $s['frequency'] ); ?>>
									<?php esc_html_e( 'Kiekvieną kartą atvėrus puslapį', 'wp-pop-up' ); ?>
								</label>
							</fieldset>
							<p>
								<label>
									<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[reset_seen]" value="1">
									<?php esc_html_e( 'Išsaugojus parodyti dar kartą ir tiems, kurie jau matė', 'wp-pop-up' ); ?>
								</label>
							</p>
							<p class="description"><?php esc_html_e( 'Pakeitus paveikslėlį ar nuorodą, iššokantis langas visiems parodomas iš naujo automatiškai.', 'wp-pop-up' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wppu-delay"><?php esc_html_e( 'Delsa', 'wp-pop-up' ); ?></label></th>
						<td>
							<input type="number" class="small-text" id="wppu-delay" name="<?php echo esc_attr( $name ); ?>[delay]" value="<?php echo esc_attr( (string) (int) $s['delay'] ); ?>" min="0" max="120">
							<?php esc_html_e( 'sek. po puslapio įkėlimo', 'wp-pop-up' ); ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Rodymo laikotarpis', 'wp-pop-up' ); ?></th>
						<td>
							<label>
								<?php esc_html_e( 'Nuo', 'wp-pop-up' ); ?>
								<input type="date" name="<?php echo esc_attr( $name ); ?>[start_date]" value="<?php echo esc_attr( $s['start_date'] ); ?>">
							</label>
							<label>
								<?php esc_html_e( 'iki', 'wp-pop-up' ); ?>
								<input type="date" name="<?php echo esc_attr( $name ); ?>[end_date]" value="<?php echo esc_attr( $s['end_date'] ); ?>">
							</label>
							<p class="description">
								<?php
								/* translators: %s: site time zone, for example Europe/Vilnius. */
								echo esc_html( sprintf( __( 'Abi datos imtinai, svetainės laiko juosta (%s). Palikite tuščias, jei laikotarpis neribojamas.', 'wp-pop-up' ), wp_timezone_string() ) );
								?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Įrenginiai', 'wp-pop-up' ); ?></th>
						<td>
							<fieldset>
								<legend class="screen-reader-text"><?php esc_html_e( 'Įrenginiai', 'wp-pop-up' ); ?></legend>
								<?php
								$devices = [
									'all'     => __( 'Visi', 'wp-pop-up' ),
									'desktop' => __( 'Tik kompiuteriai ir planšetės (ekranas platesnis nei 767 px)', 'wp-pop-up' ),
									'mobile'  => __( 'Tik telefonai (ekranas iki 767 px)', 'wp-pop-up' ),
								];
								foreach ( $devices as $value => $label ) :
									?>
									<label>
										<input type="radio" name="<?php echo esc_attr( $name ); ?>[devices]" value="<?php echo esc_attr( $value ); ?>" <?php checked( $value, $s['devices'] ); ?>>
										<?php echo esc_html( $label ); ?>
									</label><br>
								<?php endforeach; ?>
							</fieldset>
						</td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'Išvaizda', 'wp-pop-up' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="wppu-opacity"><?php esc_html_e( 'Fono tamsumas', 'wp-pop-up' ); ?></label></th>
						<td>
							<input type="number" class="small-text" id="wppu-opacity" name="<?php echo esc_attr( $name ); ?>[overlay_opacity]" value="<?php echo esc_attr( (string) (int) $s['overlay_opacity'] ); ?>" min="0" max="100"> %
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wppu-max-width"><?php esc_html_e( 'Didžiausias plotis', 'wp-pop-up' ); ?></label></th>
						<td>
							<input type="number" class="small-text" id="wppu-max-width" name="<?php echo esc_attr( $name ); ?>[max_width]" value="<?php echo esc_attr( (string) (int) $s['max_width'] ); ?>" min="0" max="4000"> px
							<p class="description"><?php esc_html_e( '0 – kiek leidžia ekranas (iki 90 % pločio ir aukščio).', 'wp-pop-up' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Uždarymas', 'wp-pop-up' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[close_overlay]" value="1" <?php checked( 1, (int) $s['close_overlay'] ); ?>>
								<?php esc_html_e( 'Uždaryti paspaudus šalia paveikslėlio', 'wp-pop-up' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Mygtukas × ir klavišas Esc uždaro visada.', 'wp-pop-up' ); ?></p>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
