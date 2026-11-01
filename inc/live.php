<?php
/**
 * Live-Wettkämpfe (früher datenbank.sgnl.de → Reiter "Live").
 *
 * Quelle sind die Termine: Ein Wettkampf erscheint im Live-Bereich, solange er
 * läuft – und jeder Termin vorher bzw. bis 48 Stunden danach, wenn mindestens ein Link
 * (Meldeübersicht, Meldeergebnis, Live-Timing, Protokoll) oder eine Nachricht
 * eingetragen ist.
 *
 * - REST: /wp-json/sgnl/v1/live  (gleiches Format wie früher wettkaempfe.php)
 * - Block "Live-Wettkämpfe" (aktualisiert sich alle 45 Sekunden)
 *
 * @package sgnl-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const SGNL_LIVE_AFTER = 48 * HOUR_IN_SECONDS;

/**
 * Termine für den Live-Bereich.
 *
 * @return array[]
 */
function sgnl_live_events() {
	$now  = time();
	$from = wp_date( 'Y-m-d', $now - SGNL_LIVE_AFTER - DAY_IN_SECONDS );
	$q    = get_posts( array(
		'post_type'      => 'sgnl_event',
		'post_status'    => 'publish',
		'posts_per_page' => 100,
		'meta_key'       => '_sgnl_start', // phpcs:ignore WordPress.DB.SlowDBQuery
		'orderby'        => 'meta_value',
		'order'          => 'ASC',
		'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
			'relation' => 'OR',
			array( 'key' => '_sgnl_end', 'value' => $from, 'compare' => '>=' ),
			array( 'key' => '_sgnl_start', 'value' => $from, 'compare' => '>=' ),
		),
		'no_found_rows'  => true,
	) );
	$out = array();
	foreach ( $q as $p ) {
		$e = sgnl_event_get( $p );
		if ( ! $e['ts_start'] || $e['ts_end'] < $now - SGNL_LIVE_AFTER ) {
			continue;
		}
		// Laufende Termine immer; kommende und beendete nur mit Links oder Nachrichten.
		$has_info = $e['meldeuebersicht'] || $e['meldeergebnis'] || $e['livetiming'] || $e['protokoll'] || $e['messages'];
		// "Live jetzt" ohne Links nur für Wettkämpfe (nicht z. B. Ferien).
		$running  = $e['ts_start'] <= $now && $e['ts_end'] >= $now && ( ! $e['cat_slugs'] || in_array( 'wettkampf', $e['cat_slugs'], true ) );
		if ( ! $running && ! $has_info ) {
			continue;
		}
		$out[] = $e;
	}
	usort( $out, function ( $a, $b ) {
		return $a['ts_start'] - $b['ts_start'];
	} );
	return $out;
}

/**
 * Für JSON aufbereiten (Format wie früher wettkaempfe.php).
 *
 * @param array $e Termin.
 * @return array
 */
function sgnl_live_json( array $e ) {
	return array(
		'id'              => $e['id'],
		'name'            => $e['title'],
		'ort'             => $e['ort'],
		'start'           => $e['ts_start'],
		'ende'            => $e['ts_end'],
		'zeit'            => (bool) $e['start_time'],
		'wettkampf'       => ! $e['cat_slugs'] || in_array( 'wettkampf', $e['cat_slugs'], true ),
		'meldeuebersicht' => $e['meldeuebersicht'] ? $e['meldeuebersicht'] : null,
		'meldeergebnis'   => $e['meldeergebnis'] ? $e['meldeergebnis'] : null,
		'livetiming'      => $e['livetiming'] ? $e['livetiming'] : null,
		'protokoll'       => $e['protokoll'] ? $e['protokoll'] : null,
		'ausschreibung'   => $e['ausschreibung'] ? $e['ausschreibung'] : null,
		'nachrichten'     => array_map( function ( $m ) {
			return array(
				'text'  => (string) $m['text'],
				'autor' => (string) $m['autor'],
				'zeit'  => (int) $m['zeit'],
			);
		}, $e['messages'] ),
	);
}

add_action( 'rest_api_init', function () {
	register_rest_route( 'sgnl/v1', '/live', array(
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'callback'            => function () {
			$res = new WP_REST_Response( array(
				'now'    => time(),
				'events' => array_map( 'sgnl_live_json', sgnl_live_events() ),
			) );
			$res->header( 'Cache-Control', 'public, max-age=30' );
			return $res;
		},
	) );
} );

/* =========================================================================
 * Ausgabe (serverseitig – JavaScript aktualisiert danach im Hintergrund)
 * ====================================================================== */

/**
 * Zeitraum lesbar.
 *
 * @param array $e Termin (JSON-Format).
 * @return string
 */
function sgnl_live_when( array $e ) {
	$days = array( 'So.', 'Mo.', 'Di.', 'Mi.', 'Do.', 'Fr.', 'Sa.' );
	$day  = function ( $ts ) use ( $days ) {
		return $days[ (int) wp_date( 'w', $ts ) ] . ', ' . wp_date( 'd.m.Y', $ts );
	};
	if ( wp_date( 'Y-m-d', $e['start'] ) === wp_date( 'Y-m-d', $e['ende'] ) ) {
		return $day( $e['start'] ) . ( $e['zeit'] ? ' · ' . wp_date( 'H:i', $e['start'] ) . '–' . wp_date( 'H:i', $e['ende'] ) . ' Uhr' : '' );
	}
	return $day( $e['start'] ) . ' – ' . $day( $e['ende'] );
}

/**
 * Eine Live-Karte (muss zu assets/live.js passen).
 *
 * @param array $e   Termin (JSON-Format).
 * @param int   $now Jetzt.
 * @return string
 */
function sgnl_live_card( array $e, $now ) {
	$live = $now >= $e['start'] && $now <= $e['ende'];
	$done = $now > $e['ende'];
	$h    = '<article class="live-card' . ( $live ? ' live-card--now' : '' ) . '">';
	$h   .= $live ? '<span class="live-badge live-badge--now">' . esc_html__( 'Live jetzt', 'sgnl-core' ) . '</span>'
		: ( $done ? '<span class="live-badge live-badge--done">' . esc_html__( 'Beendet', 'sgnl-core' ) . '</span>'
		: '<span class="live-badge live-badge--soon">' . esc_html__( 'Bevorstehend', 'sgnl-core' ) . '</span>' );
	$h   .= '<h3>' . esc_html( $e['name'] ) . '</h3><div class="live-meta">';
	if ( $e['ort'] ) {
		$h .= '<span>' . sgnl_events_icon( 'pin' ) . esc_html( $e['ort'] ) . '</span>';
	}
	$h .= '<span>' . sgnl_events_icon( 'cal' ) . esc_html( sgnl_live_when( $e ) ) . '</span></div>';
	if ( $e['nachrichten'] ) {
		$h .= '<div class="live-notes">';
		foreach ( $e['nachrichten'] as $n ) {
			$h .= '<div class="live-note" role="note"><span class="live-note__label">' . sgnl_events_icon( 'note' ) . esc_html__( 'Wichtige Info', 'sgnl-core' ) . '</span>'
				. '<div class="live-note__text" data-md>' . esc_html( $n['text'] ) . '</div>'
				. '<div class="live-note__meta"><strong>' . esc_html( $n['autor'] ) . '</strong> · ' . esc_html( wp_date( 'D, d.m., H:i', $n['zeit'] ) ) . ' Uhr</div></div>';
		}
		$h .= '</div>';
	}
	$links = '';
	foreach ( array(
		'meldeuebersicht' => array( __( 'Meldeübersicht', 'sgnl-core' ), true ),
		'meldeergebnis'   => array( __( 'Meldeergebnis', 'sgnl-core' ), false ),
		'livetiming'      => array( __( 'Live-Timing', 'sgnl-core' ), true ),
		'protokoll'       => array( __( 'Protokoll', 'sgnl-core' ), false ),
	) as $k => $conf ) {
		if ( ! empty( $e[ $k ] ) ) {
			$links .= '<a class="live-link' . ( $conf[1] ? ' live-link--ghost' : '' ) . '" href="' . esc_url( $e[ $k ] ) . '" target="_blank" rel="noopener noreferrer">' . sgnl_events_icon( 'ext' ) . esc_html( $conf[0] ) . '</a>';
		}
	}
	if ( $links ) {
		$h .= '<div class="live-links">' . $links . '</div>';
	}
	return $h . '</article>';
}

/**
 * Live-Bereich ausgeben.
 *
 * @return string
 */
function sgnl_live_render() {
	wp_enqueue_style( 'sgnl-live' );
	wp_enqueue_script( 'sgnl-live' );
	$now    = time();
	$events = array_map( 'sgnl_live_json', sgnl_live_events() );
	$cards  = '';
	foreach ( $events as $e ) {
		$cards .= sgnl_live_card( $e, $now );
	}
	$any = false;
	foreach ( $events as $e ) {
		$any = $any || ( $now >= $e['start'] && $now <= $e['ende'] );
	}
	return '<div class="sgnl-live" data-endpoint="' . esc_url( rest_url( 'sgnl/v1/live' ) ) . '" data-live-now="' . ( $any ? '1' : '0' ) . '">'
		. '<div class="live-list"' . ( $events ? '' : ' hidden' ) . '>' . $cards . '</div>'
		. '<div class="live-empty"' . ( $events ? ' hidden' : '' ) . '><strong>' . esc_html__( 'Zurzeit kein Wettkampf', 'sgnl-core' ) . '</strong><p>' . esc_html__( 'Sobald ein Wettkampf ansteht und Meldeergebnis oder Live-Timing feststehen, erscheint er hier.', 'sgnl-core' ) . '</p></div>'
		. '</div>';
}

add_action( 'init', function () {
	wp_register_style( 'sgnl-live', SGNL_CORE_URL . 'assets/live.css', array(), sgnl_core_ver( 'assets/live.css' ) );
	wp_register_script( 'sgnl-markdown', SGNL_CORE_URL . 'assets/markdown.js', array(), sgnl_core_ver( 'assets/markdown.js' ), true );
	wp_register_script( 'sgnl-live', SGNL_CORE_URL . 'assets/live.js', array( 'sgnl-markdown' ), sgnl_core_ver( 'assets/live.js' ), array(
		'in_footer' => true,
		'strategy'  => 'defer',
	) );
	wp_localize_script( 'sgnl-live', 'sgnlLive', array(
		'icons' => array(
			'pin'  => sgnl_events_icon( 'pin' ),
			'cal'  => sgnl_events_icon( 'cal' ),
			'note' => sgnl_events_icon( 'note' ),
			'ext'  => sgnl_events_icon( 'ext' ),
		),
	) );
	wp_register_script(
		'sgnl-live-block',
		SGNL_CORE_URL . 'assets/live-block.js',
		array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-server-side-render' ),
		sgnl_core_ver( 'assets/live-block.js' ),
		true
	);
	register_block_type( 'sgnl/live', array(
		'api_version'     => 3,
		'title'           => __( 'Live-Wettkämpfe', 'sgnl-core' ),
		'description'     => __( 'Laufende und anstehende Wettkämpfe mit Meldeergebnis, Live-Timing, Protokoll und Nachrichten. Gepflegt unter Dashboard → Termine.', 'sgnl-core' ),
		'category'        => 'widgets',
		'icon'            => 'megaphone',
		'keywords'        => array( 'live', 'wettkampf', 'meldeergebnis', 'live-timing' ),
		'editor_script'   => 'sgnl-live-block',
		'style'           => 'sgnl-live',
		'supports'        => array( 'align' => array( 'wide', 'full' ), 'html' => false ),
		'attributes'      => array( 'align' => array( 'type' => 'string' ) ),
		'render_callback' => function () {
			return sgnl_live_render();
		},
	) );
} );
