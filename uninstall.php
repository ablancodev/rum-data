<?php
/**
 * Limpieza al desinstalar.
 *
 * @package rum-data
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'rum_data_settings' );
