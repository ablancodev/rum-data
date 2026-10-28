<?php
/**
 * Plugin Name:       RUM Data – Core Web Vitals
 * Description:       Mide Core Web Vitals de usuarios reales con la librería web-vitals de Google y envía los datos a Google Analytics 4 (gtag) o Google Tag Manager (dataLayer).
 * Version:           1.0.0
 * Requires at least: 6.3
 * Requires PHP:      7.4
 * Author:            ablancodev
 * Author URI:        https://ablancodev.com
 * License:           GPL-2.0-or-later
 * Text Domain:       rum-data
 */

defined( 'ABSPATH' ) || exit;

define( 'RUM_DATA_VERSION', '1.0.0' );
define( 'RUM_DATA_WEB_VITALS_VERSION', '6.2.3' );
define( 'RUM_DATA_FILE', __FILE__ );
define( 'RUM_DATA_DIR', plugin_dir_path( __FILE__ ) );
define( 'RUM_DATA_URL', plugin_dir_url( __FILE__ ) );
define( 'RUM_DATA_OPTION', 'rum_data_settings' );

require_once RUM_DATA_DIR . 'includes/class-rum-data-settings.php';
require_once RUM_DATA_DIR . 'includes/class-rum-data-frontend.php';

/**
 * Opciones por defecto.
 *
 * @return array
 */
function rum_data_defaults() {
	return array(
		'enabled'            => 0,
		'destination'        => 'gtag',  // gtag | datalayer | both.
		'measurement_id'     => '',
		'load_gtag'          => 0,
		'event_name_mode'    => 'metric', // metric (LCP, INP...) | single.
		'event_name'         => 'web_vitals',
		'datalayer_event'    => 'web_vitals',
		'metrics'            => array( 'CLS', 'INP', 'LCP', 'FCP', 'TTFB' ),
		'attribution'        => 1,
		'report_all_changes' => 0,
		'sample_rate'        => 100,
		'exclude_logged_in'  => 1,
		'debug'              => 0,
	);
}

/**
 * Opciones actuales mezcladas con los valores por defecto.
 *
 * @return array
 */
function rum_data_get_settings() {
	$saved = get_option( RUM_DATA_OPTION, array() );
	return wp_parse_args( is_array( $saved ) ? $saved : array(), rum_data_defaults() );
}

register_activation_hook(
	__FILE__,
	function () {
		add_option( RUM_DATA_OPTION, rum_data_defaults() );
	}
);

new Rum_Data_Settings();
new Rum_Data_Frontend();
