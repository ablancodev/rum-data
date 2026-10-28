<?php
/**
 * Carga de web-vitals en el frontend.
 *
 * @package rum-data
 */

defined( 'ABSPATH' ) || exit;

class Rum_Data_Frontend {

	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_head', array( $this, 'print_gtag' ), 1 );
	}

	/**
	 * ¿Debe medirse esta petición?
	 *
	 * @return bool
	 */
	private function should_track() {
		$s = rum_data_get_settings();

		if ( empty( $s['enabled'] ) || empty( $s['metrics'] ) ) {
			return false;
		}
		if ( is_admin() || is_customize_preview() || wp_doing_ajax() || is_feed() || is_robots() || is_trackback() ) {
			return false;
		}
		if ( ! empty( $s['exclude_logged_in'] ) && is_user_logged_in() ) {
			return false;
		}
		if ( in_array( $s['destination'], array( 'gtag', 'both' ), true ) && empty( $s['measurement_id'] ) && ! empty( $s['load_gtag'] ) ) {
			return false;
		}

		/**
		 * Permite desactivar la medición en páginas concretas.
		 *
		 * @param bool $track Si se mide o no.
		 */
		return (bool) apply_filters( 'rum_data_should_track', true );
	}

	/**
	 * Imprime gtag.js si el usuario ha pedido que lo cargue el plugin.
	 */
	public function print_gtag() {
		$s = rum_data_get_settings();

		if ( empty( $s['load_gtag'] ) || empty( $s['measurement_id'] ) || 'datalayer' === $s['destination'] || ! $this->should_track() ) {
			return;
		}

		$id = $s['measurement_id'];
		?>
<!-- RUM Data: Google tag -->
<script async src="<?php echo esc_url( 'https://www.googletagmanager.com/gtag/js?id=' . rawurlencode( $id ) ); ?>"></script>
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('js', new Date());
gtag('config', <?php echo wp_json_encode( $id ); ?>);
</script>
		<?php
	}

	public function enqueue() {
		if ( ! $this->should_track() ) {
			return;
		}

		$s      = rum_data_get_settings();
		$vendor = ! empty( $s['attribution'] ) ? 'web-vitals.attribution.iife.js' : 'web-vitals.iife.js';

		wp_register_script(
			'rum-data-web-vitals',
			RUM_DATA_URL . 'assets/vendor/' . $vendor,
			array(),
			RUM_DATA_WEB_VITALS_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);

		wp_enqueue_script(
			'rum-data',
			RUM_DATA_URL . 'assets/js/rum-data.js',
			array( 'rum-data-web-vitals' ),
			RUM_DATA_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);

		$config = array(
			'destination'      => $s['destination'],
			'measurementId'    => $s['measurement_id'],
			'eventNameMode'    => $s['event_name_mode'],
			'eventName'        => $s['event_name'],
			'dataLayerEvent'   => $s['datalayer_event'],
			'metrics'          => array_values( $s['metrics'] ),
			'attribution'      => (bool) $s['attribution'],
			'reportAllChanges' => (bool) $s['report_all_changes'],
			'sampleRate'       => (int) $s['sample_rate'],
			'debug'            => (bool) $s['debug'],
			'pageType'         => $this->page_type(),
		);

		/**
		 * Permite modificar la configuración enviada al navegador.
		 *
		 * @param array $config Configuración.
		 */
		$config = apply_filters( 'rum_data_js_config', $config );

		wp_add_inline_script( 'rum-data', 'window.rumDataConfig = ' . wp_json_encode( $config ) . ';', 'before' );
	}

	/**
	 * Tipo de página, útil para segmentar las métricas en GA4.
	 *
	 * @return string
	 */
	private function page_type() {
		if ( is_front_page() ) {
			return 'home';
		}
		if ( is_singular() ) {
			return get_post_type() ?: 'singular';
		}
		if ( is_category() || is_tag() || is_tax() ) {
			return 'taxonomy';
		}
		if ( is_search() ) {
			return 'search';
		}
		if ( is_404() ) {
			return '404';
		}
		if ( is_archive() || is_home() ) {
			return 'archive';
		}
		return 'other';
	}
}
