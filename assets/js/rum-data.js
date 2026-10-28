/**
 * RUM Data – envía Core Web Vitals a GA4 / dataLayer.
 */
(function () {
	'use strict';

	var cfg = window.rumDataConfig;
	var wv = window.webVitals;

	if (!cfg || !wv) {
		return;
	}

	// Muestreo: se decide una vez por carga de página.
	if (cfg.sampleRate < 100 && Math.random() * 100 >= cfg.sampleRate) {
		if (cfg.debug) {
			console.info('[RUM Data] Página fuera de la muestra (' + cfg.sampleRate + '%).');
		}
		return;
	}

	function round(n, digits) {
		if (typeof n !== 'number' || !isFinite(n)) {
			return undefined;
		}
		var f = Math.pow(10, digits || 0);
		return Math.round(n * f) / f;
	}

	// GA4 limita los valores de parámetros a 100 caracteres.
	function trim(str) {
		return typeof str === 'string' ? str.slice(0, 100) : undefined;
	}

	/**
	 * Datos de diagnóstico (build de atribución).
	 */
	function getAttribution(metric) {
		var a = metric.attribution;
		if (!a) {
			return {};
		}

		switch (metric.name) {
			case 'CLS':
				return {
					debug_target: trim(a.largestShiftTarget),
					debug_time: round(a.largestShiftTime),
					debug_load_state: a.loadState
				};
			case 'INP':
				return {
					debug_target: trim(a.interactionTarget),
					debug_event: a.interactionType,
					debug_load_state: a.loadState,
					debug_input_delay: round(a.inputDelay),
					debug_processing_duration: round(a.processingDuration),
					debug_presentation_delay: round(a.presentationDelay),
					debug_script: a.longestScript && a.longestScript.entry
						? trim(a.longestScript.entry.sourceURL || a.longestScript.entry.invoker)
						: undefined
				};
			case 'LCP':
				return {
					debug_target: trim(a.target),
					debug_url: trim(a.url),
					debug_ttfb: round(a.timeToFirstByte),
					debug_resource_load_delay: round(a.resourceLoadDelay),
					debug_resource_load_duration: round(a.resourceLoadDuration),
					debug_element_render_delay: round(a.elementRenderDelay)
				};
			case 'FCP':
				return {
					debug_ttfb: round(a.timeToFirstByte),
					debug_first_byte_to_fcp: round(a.firstByteToFCP),
					debug_load_state: a.loadState
				};
			case 'TTFB':
				return {
					debug_waiting_duration: round(a.waitingDuration),
					debug_cache_duration: round(a.cacheDuration),
					debug_dns_duration: round(a.dnsDuration),
					debug_connection_duration: round(a.connectionDuration),
					debug_request_duration: round(a.requestDuration)
				};
		}
		return {};
	}

	function clean(obj) {
		Object.keys(obj).forEach(function (k) {
			if (obj[k] === undefined || obj[k] === '') {
				delete obj[k];
			}
		});
		return obj;
	}

	function buildParams(metric) {
		var params = {
			// GA4 suma el "value"; enviando el delta, la suma es el valor final.
			// CLS se multiplica por 1000 porque GA solo trata enteros bien.
			value: Math.round(metric.name === 'CLS' ? metric.delta * 1000 : metric.delta),
			metric_name: metric.name,
			metric_id: metric.id,
			metric_value: round(metric.value, metric.name === 'CLS' ? 4 : 0),
			metric_delta: round(metric.delta, metric.name === 'CLS' ? 4 : 0),
			metric_rating: metric.rating,
			navigation_type: metric.navigationType,
			page_type: cfg.pageType,
			non_interaction: true
		};

		if (cfg.attribution) {
			var extra = getAttribution(metric);
			for (var k in extra) {
				params[k] = extra[k];
			}
		}

		return clean(params);
	}

	function sendGtag(metric, params) {
		window.dataLayer = window.dataLayer || [];
		if (typeof window.gtag !== 'function') {
			window.gtag = function () {
				window.dataLayer.push(arguments);
			};
		}

		var eventParams = {};
		for (var k in params) {
			eventParams[k] = params[k];
		}
		if (cfg.measurementId) {
			eventParams.send_to = cfg.measurementId;
		}
		if (cfg.debug) {
			eventParams.debug_mode = true;
		}

		var eventName = cfg.eventNameMode === 'single' ? (cfg.eventName || 'web_vitals') : metric.name;
		window.gtag('event', eventName, eventParams);
	}

	function sendDataLayer(metric, params) {
		window.dataLayer = window.dataLayer || [];
		var payload = { event: cfg.dataLayerEvent || 'web_vitals' };
		for (var k in params) {
			payload['web_vitals_' + k] = params[k];
		}
		window.dataLayer.push(payload);
	}

	function report(metric) {
		var params = buildParams(metric);

		if (cfg.debug) {
			console.log('[RUM Data]', metric.name, params, metric);
		}

		if (cfg.destination === 'gtag' || cfg.destination === 'both') {
			sendGtag(metric, params);
		}
		if (cfg.destination === 'datalayer' || cfg.destination === 'both') {
			sendDataLayer(metric, params);
		}
	}

	var opts = { reportAllChanges: !!cfg.reportAllChanges };

	(cfg.metrics || []).forEach(function (name) {
		var fn = wv['on' + name];
		if (typeof fn === 'function') {
			fn(report, opts);
		}
	});
})();
