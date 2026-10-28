<?php
/**
 * Página de ajustes en Ajustes > RUM Data.
 *
 * @package rum-data
 */

defined( 'ABSPATH' ) || exit;

class Rum_Data_Settings {

	const PAGE = 'rum-data';

	private static $metrics = array(
		'LCP'  => 'LCP – Largest Contentful Paint (Core Web Vital)',
		'INP'  => 'INP – Interaction to Next Paint (Core Web Vital)',
		'CLS'  => 'CLS – Cumulative Layout Shift (Core Web Vital)',
		'FCP'  => 'FCP – First Contentful Paint',
		'TTFB' => 'TTFB – Time to First Byte',
	);

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_init', array( $this, 'register' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( RUM_DATA_FILE ), array( $this, 'action_links' ) );
	}

	public function add_page() {
		add_options_page( 'RUM Data – Web Vitals', 'RUM Data', 'manage_options', self::PAGE, array( $this, 'render_page' ) );
	}

	public function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=' . self::PAGE ) ) . '">Ajustes</a>' );
		return $links;
	}

	public function register() {
		register_setting(
			self::PAGE,
			RUM_DATA_OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => rum_data_defaults(),
			)
		);

		add_settings_section( 'rum_data_general', 'General', '__return_false', self::PAGE );
		add_settings_section( 'rum_data_ga', 'Google Analytics 4 / Tag Manager', array( $this, 'section_ga' ), self::PAGE );
		add_settings_section( 'rum_data_metrics', 'Métricas', '__return_false', self::PAGE );

		$this->field( 'enabled', 'Activar medición', 'rum_data_general', 'checkbox', 'Cargar web-vitals en el frontend y enviar datos.' );
		$this->field( 'exclude_logged_in', 'Excluir usuarios conectados', 'rum_data_general', 'checkbox', 'No medir a usuarios con sesión iniciada (admins, editores…), para no contaminar los datos.' );
		$this->field( 'sample_rate', 'Muestreo (%)', 'rum_data_general', 'number', 'Porcentaje de páginas vistas que se miden. 100 = todas. Útil en sitios con mucho tráfico.' );
		$this->field( 'debug', 'Modo depuración', 'rum_data_general', 'checkbox', 'Muestra las métricas en la consola del navegador y añade <code>debug_mode</code> para verlas en GA4 > Admin > DebugView.' );

		$this->field( 'destination', 'Destino', 'rum_data_ga', 'destination' );
		$this->field( 'measurement_id', 'ID de medición GA4', 'rum_data_ga', 'text', 'Formato <code>G-XXXXXXXXXX</code>. Se usa como <code>send_to</code> para que los eventos vayan solo a esta propiedad.' );
		$this->field( 'load_gtag', 'Cargar gtag.js', 'rum_data_ga', 'checkbox', 'Márcalo solo si GA4 <strong>no</strong> está ya instalado en la web (Site Kit, GTM, otro plugin o el tema). Si no, se duplicarían las páginas vistas.' );
		$this->field( 'event_name_mode', 'Nombre del evento (gtag)', 'rum_data_ga', 'event_name_mode' );
		$this->field( 'datalayer_event', 'Evento dataLayer (GTM)', 'rum_data_ga', 'text', 'Nombre del <code>event</code> en el push al dataLayer. Los parámetros llevan prefijo <code>web_vitals_</code> (p. ej. <code>web_vitals_metric_name</code>).' );

		$this->field( 'metrics', 'Métricas a medir', 'rum_data_metrics', 'metrics' );
		$this->field( 'attribution', 'Datos de diagnóstico', 'rum_data_metrics', 'checkbox', 'Usa el build de <em>attribution</em> (~6 KB más) para enviar qué elemento causa el LCP/CLS/INP y el desglose de tiempos (parámetros <code>debug_*</code>).' );
		$this->field( 'report_all_changes', 'Informar de todos los cambios', 'rum_data_metrics', 'checkbox', 'Envía un evento cada vez que la métrica cambia, no solo el valor final. Genera muchos más eventos; normalmente déjalo desactivado.' );
	}

	private function field( $key, $label, $section, $type, $help = '' ) {
		add_settings_field(
			'rum_data_' . $key,
			$label,
			array( $this, 'render_field' ),
			self::PAGE,
			$section,
			array(
				'key'       => $key,
				'type'      => $type,
				'help'      => $help,
				'label_for' => in_array( $type, array( 'text', 'number' ), true ) ? 'rum_data_' . $key : null,
			)
		);
	}

	public function section_ga() {
		echo '<p>Con <strong>gtag</strong> los eventos se envían directamente a GA4. Con <strong>dataLayer</strong> se publican para que los recoja una etiqueta en Google Tag Manager.</p>';
	}

	public function render_field( $args ) {
		$s    = rum_data_get_settings();
		$key  = $args['key'];
		$name = RUM_DATA_OPTION . '[' . $key . ']';
		$id   = 'rum_data_' . $key;

		switch ( $args['type'] ) {
			case 'checkbox':
				printf(
					'<label><input type="checkbox" name="%s" value="1" %s> %s</label>',
					esc_attr( $name ),
					checked( ! empty( $s[ $key ] ), true, false ),
					wp_kses_post( $args['help'] )
				);
				return;

			case 'text':
				printf( '<input type="text" class="regular-text" id="%s" name="%s" value="%s">', esc_attr( $id ), esc_attr( $name ), esc_attr( $s[ $key ] ) );
				break;

			case 'number':
				printf( '<input type="number" min="1" max="100" step="1" class="small-text" id="%s" name="%s" value="%s">', esc_attr( $id ), esc_attr( $name ), esc_attr( $s[ $key ] ) );
				break;

			case 'destination':
				$options = array(
					'gtag'      => 'GA4 vía gtag.js',
					'datalayer' => 'Google Tag Manager (dataLayer)',
					'both'      => 'Ambos',
				);
				foreach ( $options as $value => $label ) {
					printf(
						'<label style="display:block;margin-bottom:4px"><input type="radio" name="%s" value="%s" %s> %s</label>',
						esc_attr( $name ),
						esc_attr( $value ),
						checked( $s[ $key ], $value, false ),
						esc_html( $label )
					);
				}
				break;

			case 'event_name_mode':
				printf(
					'<label style="display:block;margin-bottom:4px"><input type="radio" name="%s" value="metric" %s> Un evento por métrica: <code>LCP</code>, <code>INP</code>, <code>CLS</code>… (recomendado por Google)</label>',
					esc_attr( $name ),
					checked( $s[ $key ], 'metric', false )
				);
				printf(
					'<label style="display:block"><input type="radio" name="%s" value="single" %s> Un único evento: <input type="text" class="regular-text" name="%s" value="%s"> con el parámetro <code>metric_name</code></label>',
					esc_attr( $name ),
					checked( $s[ $key ], 'single', false ),
					esc_attr( RUM_DATA_OPTION . '[event_name]' ),
					esc_attr( $s['event_name'] )
				);
				break;

			case 'metrics':
				foreach ( self::$metrics as $metric => $label ) {
					printf(
						'<label style="display:block;margin-bottom:4px"><input type="checkbox" name="%s[]" value="%s" %s> %s</label>',
						esc_attr( $name ),
						esc_attr( $metric ),
						checked( in_array( $metric, (array) $s['metrics'], true ), true, false ),
						esc_html( $label )
					);
				}
				break;
		}

		if ( $args['help'] ) {
			echo '<p class="description">' . wp_kses_post( $args['help'] ) . '</p>';
		}
	}

	public function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();
		$d     = rum_data_defaults();
		$out   = array();

		foreach ( array( 'enabled', 'load_gtag', 'attribution', 'report_all_changes', 'exclude_logged_in', 'debug' ) as $key ) {
			$out[ $key ] = empty( $input[ $key ] ) ? 0 : 1;
		}

		$out['destination']     = in_array( $input['destination'] ?? '', array( 'gtag', 'datalayer', 'both' ), true ) ? $input['destination'] : $d['destination'];
		$out['event_name_mode'] = in_array( $input['event_name_mode'] ?? '', array( 'metric', 'single' ), true ) ? $input['event_name_mode'] : $d['event_name_mode'];
		$out['event_name']      = $this->sanitize_event_name( $input['event_name'] ?? '', $d['event_name'] );
		$out['datalayer_event'] = $this->sanitize_event_name( $input['datalayer_event'] ?? '', $d['datalayer_event'] );

		$id = strtoupper( trim( sanitize_text_field( $input['measurement_id'] ?? '' ) ) );
		if ( '' !== $id && ! preg_match( '/^G-[A-Z0-9]{4,}$/', $id ) ) {
			add_settings_error( RUM_DATA_OPTION, 'measurement_id', 'El ID de medición no parece válido (debe empezar por G-). No se ha guardado.' );
			$id = rum_data_get_settings()['measurement_id'];
		}
		$out['measurement_id'] = $id;

		if ( $out['load_gtag'] && '' === $out['measurement_id'] && 'datalayer' !== $out['destination'] ) {
			add_settings_error( RUM_DATA_OPTION, 'load_gtag', 'Para cargar gtag.js necesitas indicar el ID de medición GA4.', 'warning' );
		}

		$out['sample_rate'] = max( 1, min( 100, absint( $input['sample_rate'] ?? 100 ) ) );

		$metrics        = isset( $input['metrics'] ) ? (array) $input['metrics'] : array();
		$out['metrics'] = array_values( array_intersect( array_keys( self::$metrics ), $metrics ) );

		return $out;
	}

	/**
	 * Nombres de evento GA4: letras, números y guion bajo, empezando por letra, máx. 40.
	 */
	private function sanitize_event_name( $value, $fallback ) {
		$value = substr( preg_replace( '/[^A-Za-z0-9_]/', '_', trim( (string) $value ) ), 0, 40 );
		return preg_match( '/^[A-Za-z]/', $value ) ? $value : $fallback;
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1>RUM Data – Core Web Vitals</h1>
			<p>Mide las Core Web Vitals de tus visitantes reales con la librería <a href="https://github.com/GoogleChrome/web-vitals" target="_blank" rel="noopener">web-vitals</a> de Google (v<?php echo esc_html( RUM_DATA_WEB_VITALS_VERSION ); ?>, servida desde el propio plugin).</p>

			<form action="options.php" method="post">
				<?php
				settings_fields( self::PAGE );
				do_settings_sections( self::PAGE );
				submit_button();
				?>
			</form>

			<hr>
			<h2>Configuración necesaria en Google Analytics 4</h2>
			<p>Para poder ver y filtrar los datos en los informes de GA4, crea estas <strong>dimensiones y métricas personalizadas</strong> en <em>Administrar &gt; Visualización de datos &gt; Definiciones personalizadas</em> (ámbito: <strong>Evento</strong>):</p>
			<table class="widefat striped" style="max-width:900px">
				<thead><tr><th>Parámetro del evento</th><th>Tipo</th><th>Descripción</th></tr></thead>
				<tbody>
					<tr><td><code>metric_rating</code></td><td>Dimensión</td><td>good / needs-improvement / poor</td></tr>
					<tr><td><code>metric_id</code></td><td>Dimensión</td><td>ID único por carga de página (permite agrupar los deltas)</td></tr>
					<tr><td><code>metric_name</code></td><td>Dimensión</td><td>Solo necesaria si usas un único evento</td></tr>
					<tr><td><code>navigation_type</code></td><td>Dimensión</td><td>navigate, reload, back-forward, prerender…</td></tr>
					<tr><td><code>page_type</code></td><td>Dimensión</td><td>home, post, page, archive, 404…</td></tr>
					<tr><td><code>debug_target</code></td><td>Dimensión</td><td>Selector CSS del elemento responsable (LCP, CLS, INP)</td></tr>
					<tr><td><code>debug_url</code></td><td>Dimensión</td><td>URL del recurso LCP (imagen)</td></tr>
					<tr><td><code>debug_event</code></td><td>Dimensión</td><td>Tipo de interacción en INP (pointer / keyboard)</td></tr>
					<tr><td><code>debug_load_state</code></td><td>Dimensión</td><td>Estado de carga cuando ocurrió</td></tr>
					<tr><td><code>debug_script</code></td><td>Dimensión</td><td>Script más largo durante la interacción INP</td></tr>
					<tr><td><code>metric_value</code></td><td>Métrica (ms / sin unidad para CLS)</td><td>Valor de la métrica</td></tr>
					<tr><td><code>debug_ttfb</code>, <code>debug_resource_load_delay</code>, <code>debug_resource_load_duration</code>, <code>debug_element_render_delay</code></td><td>Métrica (ms)</td><td>Desglose del LCP</td></tr>
					<tr><td><code>debug_input_delay</code>, <code>debug_processing_duration</code>, <code>debug_presentation_delay</code></td><td>Métrica (ms)</td><td>Desglose del INP</td></tr>
				</tbody>
			</table>
			<p>El parámetro estándar <code>value</code> lleva el delta de la métrica (en CLS multiplicado por 1000). Para un análisis a fondo (percentil 75 por página), exporta GA4 a <strong>BigQuery</strong>.</p>
		</div>
		<?php
	}
}
