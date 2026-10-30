<?php
/**
 * Plugin Name:       Pop-up
 * Plugin URI:        https://github.com/kiritoshiro/wp-pop-up
 * Description:       Iššokantis langas su paveikslėliu (pvz., kampanijos reklama): paveikslėlis, nuoroda, puslapiai, rodymo dažnumas, delsa, rodymo laikotarpis ir įrenginiai nustatomi Nustatymai → Pop-up.
 * Version:           1.0.1
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Update URI:        https://github.com/kiritoshiro/wp-pop-up
 * Author:            adventistai
 * Author URI:        https://adventistai.lt/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-pop-up
 *
 * @package Adventistai\PopUp
 */

declare( strict_types=1 );

namespace Adventistai\PopUp;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const VERSION = '1.0.1';

define( 'WPPU_FILE', __FILE__ );
define( 'WPPU_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPPU_URL', plugin_dir_url( __FILE__ ) );

require_once WPPU_DIR . 'includes/Settings.php';
require_once WPPU_DIR . 'includes/Frontend.php';
require_once WPPU_DIR . 'includes/Admin.php';
require_once WPPU_DIR . 'includes/Updater.php';

add_action(
	'plugins_loaded',
	static function (): void {
		( new Frontend() )->hooks();

		if ( is_admin() ) {
			( new Admin() )->hooks();
		}

		( new Updater() )->hooks();
	}
);
