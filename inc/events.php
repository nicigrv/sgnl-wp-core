<?php
/**
 * Termine (Terminplan + Archiv).
 *
 * - Menüpunkt "Termine": Veranstaltungen mit Datum, Ort, Bahn, Mannschaft, Ausschreibung
 * - Vergangene Termine verschwinden automatisch aus dem Terminplan und erscheinen
 *   im Archiv ihres Jahres – dort können Ergebnisse und Protokoll ergänzt werden
 * - Block "Termine" (Liste oder Kacheln, umschaltbar) bzw. Shortcode [sgnl_termine]
 * - Terminplan als PDF (A4), wird automatisch aus den Terminen erzeugt
 *
 * @package sgnl-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Felder eines Termins (Meta-Schlüssel => Bezeichnung) */
function sgnl_event_fields() {
	return array(
		'start'         => __( 'Datum von', 'sgnl-core' ),
		'end'           => __( 'Datum bis', 'sgnl-core' ),
		'ort'           => __( 'Ort', 'sgnl-core' ),
		'bahn'          => __( 'Bahn', 'sgnl-core' ),
		'team'          => __( 'Mannschaft', 'sgnl-core' ),
		'ausschreibung' => __( 'Ausschreibung', 'sgnl-core' ),
		'ergebnis'      => __( 'Ergebnisse', 'sgnl-core' ),
		'protokoll'     => __( 'Protokoll', 'sgnl-core' ),
	);
}

/* =========================================================================
 * Inhaltstyp, Block, Shortcode
 * ====================================================================== */
add_action( 'init', function () {
	register_post_type( 'sgnl_event', array(
		'labels'          => array(
			'name'               => __( 'Termine', 'sgnl-core' ),
			'singular_name'      => __( 'Termin', 'sgnl-core' ),
			'add_new'            => __( 'Neuer Termin', 'sgnl-core' ),
			'add_new_item'       => __( 'Neuen Termin anlegen', 'sgnl-core' ),
			'edit_item'          => __( 'Termin bearbeiten', 'sgnl-core' ),
			'new_item'           => __( 'Neuer Termin', 'sgnl-core' ),
			'all_items'          => __( 'Alle Termine', 'sgnl-core' ),
			'search_items'       => __( 'Termine durchsuchen', 'sgnl-core' ),
			'not_found'          => __( 'Keine Termine gefunden.', 'sgnl-core' ),
			'not_found_in_trash' => __( 'Keine Termine im Papierkorb.', 'sgnl-core' ),
			'item_published'     => __( 'Termin gespeichert.', 'sgnl-core' ),
			'item_updated'       => __( 'Termin aktualisiert.', 'sgnl-core' ),
			'menu_name'          => __( 'Termine', 'sgnl-core' ),
		),
		'public'          => false,
		'show_ui'         => true,
		'show_in_menu'    => true,
		'show_in_rest'    => false,
		'menu_icon'       => 'dashicons-calendar-alt',
		'menu_position'   => 20,
		'supports'        => array( 'title', 'revisions' ),
		'capability_type' => 'page',
		'map_meta_cap'    => true,
	) );

	foreach ( array_keys( sgnl_event_fields() ) as $key ) {
		register_post_meta( 'sgnl_event', '_sgnl_' . $key, array(
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => false,
			'revisions_enabled' => true,
			'auth_callback'     => function () {
				return current_user_can( 'edit_pages' );
			},
		) );
	}

	wp_register_style( 'sgnl-events', SGNL_CORE_URL . 'assets/events.css', array(), sgnl_core_ver( 'assets/events.css' ) );
	wp_register_script( 'sgnl-events', SGNL_CORE_URL . 'assets/events.js', array(), sgnl_core_ver( 'assets/events.js' ), array(
		'in_footer' => true,
		'strategy'  => 'defer',
	) );
	wp_register_script(
		'sgnl-events-block',
		SGNL_CORE_URL . 'assets/events-block.js',
		array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render' ),
		sgnl_core_ver( 'assets/events-block.js' ),
		true
	);
	wp_localize_script( 'sgnl-events-block', 'sgnlEventsBlock', array(
		'years'   => sgnl_events_years(),
		'current' => (int) wp_date( 'Y' ),
		'listUrl' => admin_url( 'edit.php?post_type=sgnl_event' ),
		'newUrl'  => admin_url( 'post-new.php?post_type=sgnl_event' ),
	) );

	register_block_type( 'sgnl/events', array(
		'api_version'     => 3,
		'title'           => __( 'Termine', 'sgnl-core' ),
		'description'     => __( 'Terminplan oder Archiv eines Jahres – Termine werden unter Dashboard → Termine gepflegt.', 'sgnl-core' ),
		'category'        => 'widgets',
		'icon'            => 'calendar-alt',
		'keywords'        => array( 'termine', 'terminplan', 'archiv', 'wettkampf', 'veranstaltung' ),
		'editor_script'   => 'sgnl-events-block',
		'style'           => 'sgnl-events',
		'supports'        => array( 'align' => array( 'wide', 'full' ), 'html' => false ),
		'attributes'      => array(
			'mode'     => array( 'type' => 'string', 'default' => 'upcoming' ),
			'year'     => array( 'type' => 'number', 'default' => 0 ),
			'view'     => array( 'type' => 'string', 'default' => 'list' ),
			'switcher' => array( 'type' => 'boolean', 'default' => true ),
			'pdf'      => array( 'type' => 'boolean', 'default' => true ),
			'align'    => array( 'type' => 'string' ),
		),
		'render_callback' => 'sgnl_events_render',
	) );

	add_shortcode( 'sgnl_termine', function ( $atts ) {
		$atts = shortcode_atts( array(
			'archiv'    => '',
			'ansicht'   => 'liste',
			'umschalter' => 'ja',
			'pdf'       => 'ja',
		), $atts, 'sgnl_termine' );
		return sgnl_events_render( array(
			'mode'     => $atts['archiv'] ? 'archive' : 'upcoming',
			'year'     => (int) $atts['archiv'],
			'view'     => in_array( $atts['ansicht'], array( 'kacheln', 'tiles' ), true ) ? 'tiles' : 'list',
			'switcher' => 'nein' !== $atts['umschalter'],
			'pdf'      => 'nein' !== $atts['pdf'],
			'align'    => 'wide',
		) );
	} );
} );

/* Kein Block-Editor für Termine – einfaches Formular */
add_filter( 'use_block_editor_for_post_type', function ( $use, $type ) {
	return 'sgnl_event' === $type ? false : $use;
}, 10, 2 );

/* =========================================================================
 * Daten
 * ====================================================================== */

/**
 * Heutiges Datum (Zeitzone der Website).
 *
 * @return string Y-m-d
 */
function sgnl_events_today() {
	return wp_date( 'Y-m-d' );
}

/**
 * Termin als Array.
 *
 * @param WP_Post|int $post Termin.
 * @return array
 */
function sgnl_event_get( $post ) {
	$post = get_post( $post );
	$e    = array(
		'id'    => $post->ID,
		'title' => get_the_title( $post ),
	);
	foreach ( array_keys( sgnl_event_fields() ) as $key ) {
		$e[ $key ] = (string) get_post_meta( $post->ID, '_sgnl_' . $key, true );
	}
	if ( $e['end'] && $e['end'] < $e['start'] ) {
		$e['end'] = '';
	}
	$e['last'] = $e['end'] ? $e['end'] : $e['start'];
	$e['past'] = $e['last'] && $e['last'] < sgnl_events_today();
	$e['year'] = $e['start'] ? (int) substr( $e['start'], 0, 4 ) : 0;
	return $e;
}

/**
 * Termine abfragen.
 *
 * @param string $mode upcoming|archive|all.
 * @param int    $year Jahr (nur Archiv).
 * @return array[]
 */
function sgnl_events_query( $mode = 'upcoming', $year = 0 ) {
	$today = sgnl_events_today();
	$args  = array(
		'post_type'              => 'sgnl_event',
		'post_status'            => 'publish',
		'posts_per_page'         => -1,
		'meta_key'               => '_sgnl_start', // phpcs:ignore WordPress.DB.SlowDBQuery
		'orderby'                => array( 'meta_value' => 'archive' === $mode ? 'DESC' : 'ASC', 'title' => 'ASC' ),
		'no_found_rows'          => true,
		'update_post_term_cache' => false,
	);
	if ( 'archive' === $mode && $year ) {
		$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery
			array(
				'key'     => '_sgnl_start',
				'value'   => array( $year . '-01-01', $year . '-12-31' ),
				'compare' => 'BETWEEN',
			),
		);
	}
	$out = array();
	foreach ( get_posts( $args ) as $p ) {
		$e = sgnl_event_get( $p );
		if ( ! $e['start'] ) {
			continue;
		}
		if ( 'upcoming' === $mode && $e['last'] < $today ) {
			continue;
		}
		if ( 'archive' === $mode && $e['last'] >= $today ) {
			continue;
		}
		$out[] = $e;
	}
	return $out;
}

/**
 * Jahre, in denen es Termine gibt (absteigend).
 *
 * @return int[]
 */
function sgnl_events_years() {
	global $wpdb;
	$years = $wpdb->get_col(
		"SELECT DISTINCT LEFT(m.meta_value, 4) FROM {$wpdb->postmeta} m
		 INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id
		 WHERE m.meta_key = '_sgnl_start' AND m.meta_value <> '' AND p.post_type = 'sgnl_event' AND p.post_status = 'publish'
		 ORDER BY 1 DESC"
	);
	return array_values( array_filter( array_map( 'intval', $years ) ) );
}

/**
 * Stand des Terminplans = letzte Änderung eines Termins.
 *
 * @return int Zeitstempel
 */
function sgnl_events_updated() {
	global $wpdb;
	$last = $wpdb->get_var( "SELECT MAX(post_modified_gmt) FROM {$wpdb->posts} WHERE post_type = 'sgnl_event' AND post_status = 'publish'" );
	return $last ? strtotime( $last . ' UTC' ) : time();
}

/* =========================================================================
 * Datumsangaben
 * ====================================================================== */

/**
 * Monatsnamen.
 *
 * @param int  $m     Monat 1–12.
 * @param bool $short Kurzform.
 * @return string
 */
function sgnl_month_name( $m, $short = false ) {
	$long = array( 1 => 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember' );
	$abbr = array( 1 => 'Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez' );
	$m    = (int) $m;
	return $short ? ( $abbr[ $m ] ?? '' ) : ( $long[ $m ] ?? '' );
}

/**
 * Wochentag kurz.
 *
 * @param string $ymd Datum.
 * @return string
 */
function sgnl_weekday( $ymd ) {
	$d = array( 'So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa' );
	$t = strtotime( $ymd . ' 12:00:00' );
	return $t ? $d[ (int) gmdate( 'w', $t ) ] : '';
}

/**
 * Zeitraum lesbar: "26.09." / "26.09. – 27.09." / "30.12.2026 – 02.01.2027".
 *
 * @param array $e         Termin.
 * @param bool  $with_year Jahr immer anzeigen.
 * @return string
 */
function sgnl_event_date_label( array $e, $with_year = false ) {
	$fmt = function ( $ymd, $y ) {
		list( $yy, $mm, $dd ) = array_map( 'intval', explode( '-', $ymd ) );
		return sprintf( '%02d.%02d.', $dd, $mm ) . ( $y ? $yy : '' );
	};
	if ( ! $e['start'] ) {
		return '';
	}
	if ( ! $e['end'] || $e['end'] === $e['start'] ) {
		return $fmt( $e['start'], $with_year );
	}
	$diff_year = substr( $e['start'], 0, 4 ) !== substr( $e['end'], 0, 4 );
	return $fmt( $e['start'], $with_year || $diff_year ) . ' – ' . $fmt( $e['end'], $with_year || $diff_year );
}

/* =========================================================================
 * Ausgabe
 * ====================================================================== */

/**
 * Kleine Symbole.
 *
 * @param string $name Name.
 * @return string
 */
function sgnl_events_icon( $name ) {
	$paths = array(
		'pin'    => '<path d="M12 21s-7-6.2-7-11.5A7 7 0 0 1 19 9.5C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>',
		'lane'   => '<path d="M3 7c1.5 0 1.5-1.5 3-1.5S7.5 7 9 7s1.5-1.5 3-1.5S13.5 7 15 7s1.5-1.5 3-1.5S19.5 7 21 7M3 12c1.5 0 1.5-1.5 3-1.5s1.5 1.5 3 1.5 1.5-1.5 3-1.5 1.5 1.5 3 1.5 1.5-1.5 3-1.5 1.5 1.5 3 1.5M3 17c1.5 0 1.5-1.5 3-1.5s1.5 1.5 3 1.5 1.5-1.5 3-1.5 1.5 1.5 3 1.5 1.5-1.5 3-1.5 1.5 1.5 3 1.5"/>',
		'team'   => '<circle cx="9" cy="8" r="3"/><path d="M3.5 19a5.5 5.5 0 0 1 11 0"/><path d="M16 5.2a3 3 0 0 1 0 5.6M17.5 13.6A5.5 5.5 0 0 1 20.5 19"/>',
		'doc'    => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h4"/>',
		'list'   => '<path d="M8 6h13M8 12h13M8 18h13"/><circle cx="3.5" cy="6" r="1"/><circle cx="3.5" cy="12" r="1"/><circle cx="3.5" cy="18" r="1"/>',
		'grid'   => '<rect x="3" y="3" width="7.5" height="7.5" rx="1.5"/><rect x="13.5" y="3" width="7.5" height="7.5" rx="1.5"/><rect x="3" y="13.5" width="7.5" height="7.5" rx="1.5"/><rect x="13.5" y="13.5" width="7.5" height="7.5" rx="1.5"/>',
		'trophy' => '<path d="M8 4h8v5a4 4 0 0 1-8 0zM8 6H4.5a3 3 0 0 0 3.5 4M16 6h3.5a3 3 0 0 1-3.5 4M12 13v4M8.5 20h7M10 17h4"/>',
	);
	return '<svg class="sgnl-ico" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . ( $paths[ $name ] ?? '' ) . '</svg>';
}

/**
 * Link zu Ausschreibung/Ergebnis/Protokoll.
 *
 * @param string $url   Adresse.
 * @param string $label Text.
 * @param string $icon  Symbol.
 * @param string $class Klasse.
 * @return string
 */
function sgnl_events_doc_link( $url, $label, $icon = 'doc', $class = '' ) {
	if ( ! $url ) {
		return '';
	}
	$ext = sgnl_is_own_host( $url ) ? '' : ' target="_blank" rel="noopener"';
	return '<a class="sgnl-doc' . ( $class ? ' ' . esc_attr( $class ) : '' ) . '" href="' . esc_url( $url ) . '"' . $ext . '>' . sgnl_events_icon( $icon ) . '<span>' . esc_html( $label ) . '</span></a>';
}

/**
 * Adresse des PDF-Terminplans.
 *
 * @return string
 */
function sgnl_events_pdf_url() {
	return add_query_arg( 'sgnl-terminplan', 'pdf', home_url( '/' ) );
}

/**
 * Block/Shortcode ausgeben.
 *
 * @param array $a Attribute.
 * @return string
 */
function sgnl_events_render( $a ) {
	$a = wp_parse_args( (array) $a, array(
		'mode'     => 'upcoming',
		'year'     => 0,
		'view'     => 'list',
		'switcher' => true,
		'pdf'      => true,
		'align'    => null,
	) );
	$archive = 'archive' === $a['mode'];
	$year    = $archive ? ( (int) $a['year'] ? (int) $a['year'] : (int) wp_date( 'Y' ) ) : 0;
	$view    = 'tiles' === $a['view'] ? 'tiles' : 'list';
	$events  = sgnl_events_query( $archive ? 'archive' : 'upcoming', $year );

	wp_enqueue_style( 'sgnl-events' );
	if ( $a['switcher'] && $events ) {
		wp_enqueue_script( 'sgnl-events' );
	}

	$align = $a['align'] ? $a['align'] : 'wide';
	$cls   = 'sgnl-events' . ( in_array( $align, array( 'wide', 'full' ), true ) ? ' align' . $align : '' ) . ( $archive ? ' is-archive' : ' is-upcoming' );

	$html = '<div class="' . esc_attr( $cls ) . '" data-view="' . esc_attr( $view ) . '" data-default-view="' . esc_attr( $view ) . '">';

	// Leiste: Ansicht umschalten + PDF.
	$bar = '';
	if ( $a['switcher'] && $events ) {
		$bar .= '<div class="sgnl-events__switch" role="group" aria-label="' . esc_attr__( 'Ansicht', 'sgnl-core' ) . '">';
		foreach ( array( 'list' => array( 'list', __( 'Liste', 'sgnl-core' ) ), 'tiles' => array( 'grid', __( 'Kacheln', 'sgnl-core' ) ) ) as $v => $conf ) {
			$bar .= '<button type="button" data-view="' . esc_attr( $v ) . '" aria-pressed="' . ( $v === $view ? 'true' : 'false' ) . '">' . sgnl_events_icon( $conf[0] ) . '<span>' . esc_html( $conf[1] ) . '</span></button>';
		}
		$bar .= '</div>';
	}
	if ( ! $archive && $a['pdf'] && $events ) {
		$bar .= '<div class="sgnl-events__pdf"><div class="wp-block-button is-style-sgnl-download"><a class="wp-block-button__link wp-element-button" href="' . esc_url( sgnl_events_pdf_url() ) . '" target="_blank" rel="noopener">' . esc_html__( 'Terminplan herunterladen', 'sgnl-core' ) . '</a></div>'
			. '<span class="sgnl-events__stand">' . esc_html( sprintf( /* translators: %s: Datum */ __( 'PDF · Stand: %s', 'sgnl-core' ), wp_date( 'd.m.Y', sgnl_events_updated() ) ) ) . '</span></div>';
	}
	if ( $bar ) {
		$html .= '<div class="sgnl-events__bar">' . $bar . '</div>';
	}

	if ( ! $events ) {
		$html .= '<p class="sgnl-events__empty">' . esc_html(
			$archive
				/* translators: %d: Jahr */
				? sprintf( __( 'Für %d sind noch keine vergangenen Termine eingetragen.', 'sgnl-core' ), $year )
				: __( 'Aktuell sind keine Termine eingetragen.', 'sgnl-core' )
		) . '</p>';
	} else {
		$html .= sgnl_events_render_list( $events, $archive );
		$html .= sgnl_events_render_tiles( $events, $archive );
	}

	if ( current_user_can( 'edit_pages' ) ) {
		$html .= '<p class="sgnl-events__edit"><a href="' . esc_url( admin_url( 'edit.php?post_type=sgnl_event' . ( $archive ? '&sgnl_zeit=' . $year : '&sgnl_zeit=kommend' ) ) ) . '">' . esc_html__( 'Termine verwalten', 'sgnl-core' ) . '</a></p>';
	}

	return $html . '</div>';
}

/**
 * Listenansicht (Tabelle, nach Monaten gruppiert).
 *
 * @param array[] $events  Termine.
 * @param bool    $archive Archiv?
 * @return string
 */
function sgnl_events_render_list( array $events, $archive ) {
	$cols = $archive
		? array( 'date' => __( 'Datum', 'sgnl-core' ), 'title' => __( 'Veranstaltung', 'sgnl-core' ), 'ort' => __( 'Ort', 'sgnl-core' ), 'protokoll' => __( 'Protokoll', 'sgnl-core' ), 'ergebnis' => __( 'Ergebnisse', 'sgnl-core' ) )
		: array( 'date' => __( 'Datum', 'sgnl-core' ), 'title' => __( 'Veranstaltung', 'sgnl-core' ), 'ort' => __( 'Ort', 'sgnl-core' ), 'bahn' => __( 'Bahn', 'sgnl-core' ), 'team' => __( 'Mannschaft', 'sgnl-core' ), 'ausschreibung' => __( 'Ausschreibung', 'sgnl-core' ) );

	$h = '<figure class="wp-block-table is-style-sgnl-table sgnl-events__list"><table><thead><tr>';
	foreach ( $cols as $k => $label ) {
		$h .= '<th class="col-' . esc_attr( $k ) . '">' . esc_html( $label ) . '</th>';
	}
	$h .= '</tr></thead><tbody>';

	$group = '';
	foreach ( $events as $e ) {
		$g = substr( $e['start'], 0, 7 );
		if ( $g !== $group ) {
			$group = $g;
			$label = sgnl_month_name( (int) substr( $g, 5, 2 ) ) . ( $archive ? '' : ' ' . substr( $g, 0, 4 ) );
			$h    .= '<tr class="is-group-row"><td colspan="' . count( $cols ) . '">' . esc_html( $label ) . '</td></tr>';
		}
		$h .= '<tr>';
		foreach ( $cols as $k => $label ) {
			switch ( $k ) {
				case 'date':
					$val = esc_html( sgnl_event_date_label( $e ) );
					break;
				case 'title':
					$val = '<strong>' . esc_html( $e['title'] ) . '</strong>';
					break;
				case 'ausschreibung':
					$val = sgnl_events_doc_link( $e['ausschreibung'], __( 'Ausschreibung', 'sgnl-core' ) );
					break;
				case 'protokoll':
					$val = sgnl_events_doc_link( $e['protokoll'], __( 'Protokoll', 'sgnl-core' ) );
					break;
				case 'ergebnis':
					$val = sgnl_events_doc_link( $e['ergebnis'], __( 'Ergebnisse', 'sgnl-core' ), 'trophy' );
					break;
				default:
					$val = esc_html( $e[ $k ] );
			}
			$empty = '' === $val;
			$h    .= '<td class="col-' . esc_attr( $k ) . ( $empty ? ' is-empty' : '' ) . '" data-label="' . esc_attr( $label ) . '">' . $val . '</td>';
		}
		$h .= '</tr>';
	}
	return $h . '</tbody></table></figure>';
}

/**
 * Kachelansicht.
 *
 * @param array[] $events  Termine.
 * @param bool    $archive Archiv?
 * @return string
 */
function sgnl_events_render_tiles( array $events, $archive ) {
	$h = '<div class="sgnl-events__tiles">';
	foreach ( $events as $e ) {
		list( $y, $m, $d ) = array_map( 'intval', explode( '-', $e['start'] ) );
		$end   = $e['end'] && $e['end'] !== $e['start'] ? array_map( 'intval', explode( '-', $e['end'] ) ) : null;
		$days  = sprintf( '%d', $d );
		$month = sgnl_month_name( $m, true ) . ' ' . $y;
		$wd    = sgnl_weekday( $e['start'] );
		if ( $end ) {
			$wd .= '–' . sgnl_weekday( $e['end'] );
			if ( $end[1] === $m && $end[0] === $y ) {
				$days = sprintf( '%d.–%d.', $d, $end[2] );
			} else {
				$days  = sprintf( '%02d.%02d.', $d, $m );
				$month = sprintf( 'bis %02d.%02d.', $end[2], $end[1] ) . ( $end[0] !== $y ? $end[0] : '' );
			}
		}
		$long = mb_strlen( $days ) >= 6 ? ' is-long' : '';

		$meta = '';
		if ( $e['ort'] ) {
			$meta .= '<li>' . sgnl_events_icon( 'pin' ) . '<span>' . esc_html( $e['ort'] ) . '</span></li>';
		}
		if ( $e['bahn'] ) {
			$meta .= '<li>' . sgnl_events_icon( 'lane' ) . '<span>' . esc_html( $e['bahn'] ) . '</span></li>';
		}
		if ( $e['team'] ) {
			$meta .= '<li>' . sgnl_events_icon( 'team' ) . '<span>' . esc_html( $e['team'] ) . '</span></li>';
		}

		$links = $archive
			? sgnl_events_doc_link( $e['ergebnis'], __( 'Ergebnisse', 'sgnl-core' ), 'trophy', 'is-primary' ) . sgnl_events_doc_link( $e['protokoll'], __( 'Protokoll', 'sgnl-core' ) )
			: sgnl_events_doc_link( $e['ausschreibung'], __( 'Ausschreibung', 'sgnl-core' ), 'doc', 'is-primary' );

		$h .= '<article class="sgnl-event">'
			. '<div class="sgnl-event__date' . $long . '"><span class="sgnl-event__wd">' . esc_html( $wd ) . '</span><span class="sgnl-event__days">' . esc_html( $days ) . '</span><span class="sgnl-event__month">' . esc_html( $month ) . '</span></div>'
			. '<div class="sgnl-event__body"><h3 class="sgnl-event__title">' . esc_html( $e['title'] ) . '</h3>'
			. ( $meta ? '<ul class="sgnl-event__meta">' . $meta . '</ul>' : '' )
			. ( $links ? '<div class="sgnl-event__links">' . $links . '</div>' : '' )
			. '</div></article>';
	}
	return $h . '</div>';
}

/* =========================================================================
 * PDF
 * ====================================================================== */
add_action( 'init', function () {
	if ( ! isset( $_GET['sgnl-terminplan'] ) || 'pdf' !== $_GET['sgnl-terminplan'] ) { // phpcs:ignore WordPress.Security.NonceVerification
		return;
	}
	require_once SGNL_CORE_DIR . 'inc/class-events-pdf.php';
	$pdf = SGNL_Events_PDF::terminplan( sgnl_events_query( 'upcoming' ), sgnl_events_updated() );
	nocache_headers();
	header( 'Content-Type: application/pdf' );
	header( 'Content-Disposition: inline; filename="Terminplan-SG-Neptun-Lampertheim.pdf"' );
	header( 'Content-Length: ' . strlen( $pdf ) );
	header( 'X-Robots-Tag: noindex' );
	echo $pdf; // phpcs:ignore WordPress.Security.EscapeOutput
	exit;
}, 99 );

/* =========================================================================
 * Archivseite für ein neues Jahr automatisch anlegen
 * ====================================================================== */

/**
 * Seiten, die das Archiv eines Jahres zeigen.
 *
 * @return array Jahr => Seiten-ID
 */
function sgnl_events_archive_pages() {
	global $wpdb;
	$rows  = $wpdb->get_results(
		"SELECT ID, post_content FROM {$wpdb->posts} WHERE post_type = 'page' AND post_status IN ('publish','draft','private','future')
		 AND post_content LIKE '%wp:sgnl/events {%\"mode\":\"archive\"%'"
	);
	$pages = array();
	foreach ( $rows as $r ) {
		if ( preg_match_all( '/<!-- wp:sgnl\/events (\{[^}]*\}) \/-->/', $r->post_content, $m ) ) {
			foreach ( $m[1] as $json ) {
				$attr = json_decode( $json, true );
				if ( is_array( $attr ) && 'archive' === ( $attr['mode'] ?? '' ) && ! empty( $attr['year'] ) ) {
					$pages[ (int) $attr['year'] ] = (int) $r->ID;
				}
			}
		}
	}
	krsort( $pages );
	return $pages;
}

/**
 * Fehlende Archivseiten anlegen (nach dem Muster der vorhandenen).
 *
 * @return int[] Neu angelegte Jahre
 */
function sgnl_events_maybe_create_archive_pages() {
	if ( ! apply_filters( 'sgnl_events_auto_archive_pages', true ) ) {
		return array();
	}
	$pages = sgnl_events_archive_pages();
	if ( ! $pages ) {
		return array(); // Noch keine Vorlage vorhanden.
	}
	$created = array();
	foreach ( sgnl_events_years() as $year ) {
		if ( isset( $pages[ $year ] ) || $year > (int) wp_date( 'Y' ) || ! sgnl_events_query( 'archive', $year ) ) {
			continue;
		}
		$template = get_post( reset( $pages ) );
		$page_id  = wp_insert_post( array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			/* translators: %d: Jahr */
			'post_title'   => sprintf( __( 'Archiv %d', 'sgnl-core' ), $year ),
			'post_name'    => 'archiv-' . $year,
			'post_parent'  => $template ? $template->post_parent : 0,
			'menu_order'   => $template ? $template->menu_order : 0,
			'post_content' => "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">" . esc_html( sprintf( __( 'Archiv %d', 'sgnl-core' ), $year ) ) . "</h2>\n<!-- /wp:heading -->\n\n"
				. '<!-- wp:sgnl/events ' . wp_json_encode( array( 'mode' => 'archive', 'year' => $year, 'align' => 'wide' ) ) . ' /-->',
		), true );
		if ( is_wp_error( $page_id ) ) {
			continue;
		}
		$created[] = $year;
		// Im Menü neben die passende Archivseite (neuere Jahre zuerst).
		$older = array_filter( array_keys( $pages ), function ( $y ) use ( $year ) {
			return $y < $year;
		} );
		if ( $older ) {
			sgnl_events_add_menu_item( $page_id, $pages[ max( $older ) ] );
		} else {
			$others = array_diff( array_keys( $pages ), array( $year ) );
			if ( $others ) {
				sgnl_events_add_menu_item( $page_id, $pages[ min( $others ) ], true );
			}
		}
		$pages[ $year ] = $page_id;
		krsort( $pages );
	}
	return $created;
}

/**
 * Neue Archivseite im Menü neben einer vorhandenen Archivseite einsortieren.
 *
 * @param int  $page_id Neue Seite.
 * @param int  $ref_id  Vorhandene Archivseite.
 * @param bool $after   Dahinter statt davor.
 */
function sgnl_events_add_menu_item( $page_id, $ref_id, $after = false ) {
	$before_id = $ref_id;
	foreach ( wp_get_nav_menus() as $menu ) {
		$items = wp_get_nav_menu_items( $menu->term_id, array( 'post_status' => 'any' ) );
		if ( ! $items ) {
			continue;
		}
		foreach ( $items as $item ) {
			if ( 'page' !== $item->object || (int) $item->object_id !== (int) $before_id ) {
				continue;
			}
			$pos = (int) $item->menu_order + ( $after ? 1 : 0 );
			foreach ( $items as $other ) {
				if ( (int) $other->menu_order >= $pos ) {
					wp_update_post( array( 'ID' => $other->ID, 'menu_order' => (int) $other->menu_order + 1 ) );
				}
			}
			wp_update_nav_menu_item( $menu->term_id, 0, array(
				'menu-item-object-id' => $page_id,
				'menu-item-object'    => 'page',
				'menu-item-type'      => 'post_type',
				'menu-item-parent-id' => (int) $item->menu_item_parent,
				'menu-item-position'  => $pos,
				'menu-item-status'    => 'publish',
			) );
			break;
		}
	}
}

/* Täglich prüfen (beim ersten Seitenaufruf des Tages) */
add_action( 'init', function () {
	if ( wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || get_transient( 'sgnl_events_daily' ) ) {
		return;
	}
	set_transient( 'sgnl_events_daily', 1, 12 * HOUR_IN_SECONDS );
	$created = sgnl_events_maybe_create_archive_pages();
	if ( $created ) {
		update_option( 'sgnl_events_new_archive', $created, false );
	}
}, 30 );

/* =========================================================================
 * Dashboard: Formular
 * ====================================================================== */
add_filter( 'enter_title_here', function ( $text, $post ) {
	return 'sgnl_event' === $post->post_type ? __( 'Name der Veranstaltung', 'sgnl-core' ) : $text;
}, 10, 2 );

add_action( 'add_meta_boxes_sgnl_event', function () {
	add_meta_box( 'sgnl_event_details', __( 'Termin', 'sgnl-core' ), 'sgnl_event_details_box', 'sgnl_event', 'normal', 'high' );
	add_meta_box( 'sgnl_event_after', __( 'Nach der Veranstaltung (Archiv)', 'sgnl-core' ), 'sgnl_event_after_box', 'sgnl_event', 'normal', 'default' );
} );

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	$screen = get_current_screen();
	if ( ! $screen || 'sgnl_event' !== $screen->post_type ) {
		return;
	}
	wp_enqueue_style( 'sgnl-events-admin', SGNL_CORE_URL . 'assets/events-admin.css', array(), sgnl_core_ver( 'assets/events-admin.css' ) );
	if ( in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
		wp_enqueue_media();
		wp_enqueue_script( 'sgnl-events-admin', SGNL_CORE_URL . 'assets/events-admin.js', array( 'jquery' ), sgnl_core_ver( 'assets/events-admin.js' ), true );
	}
} );

/**
 * Bisher verwendete Werte (für Vorschläge beim Tippen).
 *
 * @param string $key Feld.
 * @return string[]
 */
function sgnl_events_suggestions( $key ) {
	global $wpdb;
	return $wpdb->get_col( $wpdb->prepare(
		"SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value <> '' GROUP BY meta_value ORDER BY COUNT(*) DESC, meta_value LIMIT 60",
		'_sgnl_' . $key
	) );
}

/**
 * Datalist ausgeben.
 *
 * @param string   $id     ID.
 * @param string[] $values Werte.
 */
function sgnl_events_datalist( $id, array $values ) {
	echo '<datalist id="' . esc_attr( $id ) . '">';
	foreach ( array_unique( $values ) as $v ) {
		echo '<option value="' . esc_attr( $v ) . '"></option>';
	}
	echo '</datalist>';
}

/**
 * Feld für Datei oder Link.
 *
 * @param string $key   Feld.
 * @param string $value Wert.
 * @param string $label Bezeichnung.
 * @param string $help  Hilfetext.
 */
function sgnl_events_file_field( $key, $value, $label, $help = '' ) {
	?>
	<div class="sgnl-ev__field sgnl-ev__file">
		<label for="sgnl-ev-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label>
		<div class="sgnl-ev__filerow">
			<input type="url" class="widefat" id="sgnl-ev-<?php echo esc_attr( $key ); ?>" name="sgnl_event[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $value ); ?>" placeholder="https://…">
			<button type="button" class="button sgnl-ev__pick" data-target="sgnl-ev-<?php echo esc_attr( $key ); ?>" data-title="<?php echo esc_attr( $label ); ?>"><?php esc_html_e( 'Datei wählen', 'sgnl-core' ); ?></button>
			<a class="button sgnl-ev__open" href="<?php echo esc_url( $value ); ?>" target="_blank" rel="noopener" <?php echo $value ? '' : 'hidden'; ?>><?php esc_html_e( 'Öffnen', 'sgnl-core' ); ?></a>
		</div>
		<?php if ( $help ) : ?>
			<p class="description"><?php echo esc_html( $help ); ?></p>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Hauptfelder.
 *
 * @param WP_Post $post Termin.
 */
function sgnl_event_details_box( $post ) {
	$e = sgnl_event_get( $post );
	wp_nonce_field( 'sgnl_event_save', 'sgnl_event_nonce' );
	sgnl_events_datalist( 'sgnl-ev-list-ort', sgnl_events_suggestions( 'ort' ) );
	sgnl_events_datalist( 'sgnl-ev-list-bahn', array_merge( array( '25 m', '50 m', 'Freiwasser' ), sgnl_events_suggestions( 'bahn' ) ) );
	sgnl_events_datalist( 'sgnl-ev-list-team', sgnl_events_suggestions( 'team' ) );

	if ( $e['start'] ) {
		$status = $e['past']
			/* translators: %d: Jahr */
			? sprintf( __( 'Vorbei – erscheint im Archiv %d. Ergebnisse und Protokoll können unten ergänzt werden.', 'sgnl-core' ), $e['year'] )
			: __( 'Steht im Terminplan. Nach dem letzten Tag wandert der Termin automatisch ins Archiv.', 'sgnl-core' );
		echo '<p class="sgnl-ev__status ' . ( $e['past'] ? 'is-past' : 'is-upcoming' ) . '">' . esc_html( $status ) . '</p>';
	}
	?>
	<div class="sgnl-ev">
		<div class="sgnl-ev__field">
			<label for="sgnl-ev-start"><?php esc_html_e( 'Datum von', 'sgnl-core' ); ?> <span class="req">*</span></label>
			<input type="date" id="sgnl-ev-start" name="sgnl_event[start]" value="<?php echo esc_attr( $e['start'] ); ?>" required>
		</div>
		<div class="sgnl-ev__field">
			<label for="sgnl-ev-end"><?php esc_html_e( 'Datum bis', 'sgnl-core' ); ?> <small><?php esc_html_e( '(bei mehrtägigen)', 'sgnl-core' ); ?></small></label>
			<input type="date" id="sgnl-ev-end" name="sgnl_event[end]" value="<?php echo esc_attr( $e['end'] ); ?>" min="<?php echo esc_attr( $e['start'] ); ?>">
		</div>
		<div class="sgnl-ev__field">
			<label for="sgnl-ev-ort"><?php esc_html_e( 'Ort', 'sgnl-core' ); ?></label>
			<input type="text" class="widefat" id="sgnl-ev-ort" name="sgnl_event[ort]" value="<?php echo esc_attr( $e['ort'] ); ?>" list="sgnl-ev-list-ort" autocomplete="off">
		</div>
		<div class="sgnl-ev__field">
			<label for="sgnl-ev-bahn"><?php esc_html_e( 'Bahn', 'sgnl-core' ); ?></label>
			<input type="text" class="widefat" id="sgnl-ev-bahn" name="sgnl_event[bahn]" value="<?php echo esc_attr( $e['bahn'] ); ?>" list="sgnl-ev-list-bahn" autocomplete="off" placeholder="25 m">
		</div>
		<div class="sgnl-ev__field is-wide">
			<label for="sgnl-ev-team"><?php esc_html_e( 'Mannschaft', 'sgnl-core' ); ?></label>
			<input type="text" class="widefat" id="sgnl-ev-team" name="sgnl_event[team]" value="<?php echo esc_attr( $e['team'] ); ?>" list="sgnl-ev-list-team" autocomplete="off" placeholder="<?php esc_attr_e( 'z. B. 1. Mannschaft, Masters', 'sgnl-core' ); ?>">
		</div>
		<?php sgnl_events_file_field( 'ausschreibung', $e['ausschreibung'], __( 'Ausschreibung', 'sgnl-core' ), __( 'PDF aus der Mediathek wählen bzw. hochladen – oder einen Link einfügen.', 'sgnl-core' ) ); ?>
	</div>
	<?php
}

/**
 * Felder fürs Archiv.
 *
 * @param WP_Post $post Termin.
 */
function sgnl_event_after_box( $post ) {
	$e = sgnl_event_get( $post );
	?>
	<div class="sgnl-ev">
		<?php
		sgnl_events_file_field( 'ergebnis', $e['ergebnis'], __( 'Ergebnisse', 'sgnl-core' ) );
		sgnl_events_file_field( 'protokoll', $e['protokoll'], __( 'Protokoll', 'sgnl-core' ) );
		?>
	</div>
	<?php
}

/* Speichern */
add_action( 'save_post_sgnl_event', function ( $post_id ) {
	if ( wp_is_post_revision( $post_id ) || ! isset( $_POST['sgnl_event_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['sgnl_event_nonce'] ), 'sgnl_event_save' ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$in = (array) wp_unslash( $_POST['sgnl_event'] ?? array() ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	sgnl_event_save_fields( $post_id, $in );
} );

/**
 * Felder speichern (auch für den Import).
 *
 * @param int   $post_id Termin.
 * @param array $in      Werte.
 */
function sgnl_event_save_fields( $post_id, array $in ) {
	$date = function ( $v ) {
		$v = trim( (string) $v );
		return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ) && checkdate( (int) substr( $v, 5, 2 ), (int) substr( $v, 8, 2 ), (int) substr( $v, 0, 4 ) ) ? $v : '';
	};
	$vals = array(
		'start' => $date( $in['start'] ?? '' ),
		'end'   => $date( $in['end'] ?? '' ),
	);
	if ( $vals['end'] && ( ! $vals['start'] || $vals['end'] <= $vals['start'] ) ) {
		$vals['end'] = '';
	}
	foreach ( array( 'ort', 'bahn', 'team' ) as $k ) {
		$vals[ $k ] = sanitize_text_field( $in[ $k ] ?? '' );
	}
	foreach ( array( 'ausschreibung', 'ergebnis', 'protokoll' ) as $k ) {
		$vals[ $k ] = esc_url_raw( trim( (string) ( $in[ $k ] ?? '' ) ) );
	}
	foreach ( $vals as $k => $v ) {
		if ( '' === $v ) {
			delete_post_meta( $post_id, '_sgnl_' . $k );
		} else {
			update_post_meta( $post_id, '_sgnl_' . $k, $v );
		}
	}
}

/* Hinweis, wenn das Datum fehlt */
add_action( 'admin_notices', function () {
	$screen = get_current_screen();
	if ( ! $screen || 'sgnl_event' !== $screen->post_type || 'post' !== $screen->base ) {
		return;
	}
	global $post;
	if ( $post && 'auto-draft' !== $post->post_status && ! get_post_meta( $post->ID, '_sgnl_start', true ) ) {
		echo '<div class="notice notice-warning"><p>' . esc_html__( 'Bitte ein Datum eintragen – ohne Datum wird der Termin nicht angezeigt.', 'sgnl-core' ) . '</p></div>';
	}
} );

/* Neu angelegte Archivseite melden */
add_action( 'admin_notices', function () {
	$years = get_option( 'sgnl_events_new_archive' );
	if ( ! $years || ! current_user_can( 'edit_pages' ) ) {
		return;
	}
	delete_option( 'sgnl_events_new_archive' );
	$pages = sgnl_events_archive_pages();
	foreach ( (array) $years as $y ) {
		if ( isset( $pages[ $y ] ) ) {
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s <a href="%s">%s</a></p></div>',
				/* translators: %d: Jahr */
				esc_html( sprintf( __( 'Die Seite „Archiv %d“ wurde automatisch angelegt und ins Menü eingefügt.', 'sgnl-core' ), $y ) ),
				esc_url( get_permalink( $pages[ $y ] ) ),
				esc_html__( 'Ansehen', 'sgnl-core' )
			);
		}
	}
} );

/* =========================================================================
 * Dashboard: Liste
 * ====================================================================== */
add_filter( 'manage_sgnl_event_posts_columns', function ( $cols ) {
	return array(
		'cb'         => $cols['cb'],
		'sgnl_date'  => __( 'Datum', 'sgnl-core' ),
		'title'      => __( 'Veranstaltung', 'sgnl-core' ),
		'sgnl_ort'   => __( 'Ort', 'sgnl-core' ),
		'sgnl_team'  => __( 'Mannschaft', 'sgnl-core' ),
		'sgnl_docs'  => __( 'Unterlagen', 'sgnl-core' ),
		'sgnl_state' => __( 'Erscheint in', 'sgnl-core' ),
	);
} );

add_filter( 'manage_edit-sgnl_event_sortable_columns', function ( $cols ) {
	$cols['sgnl_date'] = 'sgnl_start';
	$cols['title']     = 'title';
	return $cols;
} );

add_action( 'manage_sgnl_event_posts_custom_column', function ( $col, $id ) {
	$e = sgnl_event_get( $id );
	switch ( $col ) {
		case 'sgnl_date':
			echo $e['start'] ? '<strong>' . esc_html( sgnl_event_date_label( $e, true ) ) . '</strong><br><small>' . esc_html( sgnl_weekday( $e['start'] ) . ( $e['end'] ? ' – ' . sgnl_weekday( $e['end'] ) : '' ) ) . '</small>' : '<span class="sgnl-ev-missing">' . esc_html__( 'Datum fehlt', 'sgnl-core' ) . '</span>';
			break;
		case 'sgnl_ort':
			echo esc_html( $e['ort'] . ( $e['bahn'] ? ' · ' . $e['bahn'] : '' ) );
			break;
		case 'sgnl_team':
			echo esc_html( $e['team'] );
			break;
		case 'sgnl_docs':
			foreach ( array( 'ausschreibung' => 'A', 'ergebnis' => 'E', 'protokoll' => 'P' ) as $k => $abbr ) {
				$label = sgnl_event_fields()[ $k ];
				if ( $e[ $k ] ) {
					printf( '<a class="sgnl-ev-doc is-set" href="%s" target="_blank" title="%s">%s</a>', esc_url( $e[ $k ] ), esc_attr( $label ), esc_html( $abbr ) );
				} else {
					/* translators: %s: Ausschreibung/Ergebnisse/Protokoll */
					printf( '<span class="sgnl-ev-doc" title="%s">%s</span>', esc_attr( sprintf( __( '%s fehlt', 'sgnl-core' ), $label ) ), esc_html( $abbr ) );
				}
			}
			break;
		case 'sgnl_state':
			if ( ! $e['start'] ) {
				echo '–';
			} elseif ( $e['past'] ) {
				/* translators: %d: Jahr */
				echo '<span class="sgnl-ev-state is-past">' . esc_html( sprintf( __( 'Archiv %d', 'sgnl-core' ), $e['year'] ) ) . '</span>';
			} else {
				echo '<span class="sgnl-ev-state is-upcoming">' . esc_html__( 'Terminplan', 'sgnl-core' ) . '</span>';
			}
			break;
	}
}, 10, 2 );

/* Sortierung + Filter "Terminplan" / "Archiv JJJJ" */
add_action( 'pre_get_posts', function ( $q ) {
	if ( ! is_admin() || ! $q->is_main_query() || 'sgnl_event' !== $q->get( 'post_type' ) ) {
		return;
	}
	$zeit  = sanitize_key( wp_unslash( $_GET['sgnl_zeit'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
	$today = sgnl_events_today();
	$order = strtoupper( (string) $q->get( 'order' ) );
	$mq    = array();

	if ( 'kommend' === $zeit ) {
		$mq[] = array(
			'relation' => 'OR',
			array( 'key' => '_sgnl_end', 'value' => $today, 'compare' => '>=' ),
			array(
				'relation' => 'AND',
				array( 'key' => '_sgnl_end', 'compare' => 'NOT EXISTS' ),
				array( 'key' => '_sgnl_start', 'value' => $today, 'compare' => '>=' ),
			),
		);
		$order = $order ? $order : 'ASC';
	} elseif ( preg_match( '/^\d{4}$/', $zeit ) ) {
		$mq[] = array( 'key' => '_sgnl_start', 'value' => array( $zeit . '-01-01', $zeit . '-12-31' ), 'compare' => 'BETWEEN' );
	}
	if ( $mq ) {
		$q->set( 'meta_query', $mq );
	}
	if ( ! $q->get( 'orderby' ) || 'sgnl_start' === $q->get( 'orderby' ) ) {
		$q->set( 'meta_key', '_sgnl_start' );
		$q->set( 'orderby', array( 'meta_value' => $order ? $order : 'DESC', 'title' => 'ASC' ) );
	}
} );

add_filter( 'views_edit-sgnl_event', function ( $views ) {
	$zeit  = sanitize_key( wp_unslash( $_GET['sgnl_zeit'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
	$base  = admin_url( 'edit.php?post_type=sgnl_event' );
	$extra = array(
		'sgnl_kommend' => sprintf(
			'<a href="%s"%s>%s <span class="count">(%d)</span></a>',
			esc_url( add_query_arg( 'sgnl_zeit', 'kommend', $base ) ),
			'kommend' === $zeit ? ' class="current" aria-current="page"' : '',
			esc_html__( 'Terminplan', 'sgnl-core' ),
			count( sgnl_events_query( 'upcoming' ) )
		),
	);
	foreach ( array_slice( sgnl_events_years(), 0, 6 ) as $y ) {
		$n = count( sgnl_events_query( 'archive', $y ) );
		if ( ! $n ) {
			continue;
		}
		$extra[ 'sgnl_' . $y ] = sprintf(
			'<a href="%s"%s>%s <span class="count">(%d)</span></a>',
			esc_url( add_query_arg( 'sgnl_zeit', $y, $base ) ),
			(string) $y === $zeit ? ' class="current" aria-current="page"' : '',
			/* translators: %d: Jahr */
			esc_html( sprintf( __( 'Archiv %d', 'sgnl-core' ), $y ) ),
			$n
		);
	}
	if ( $zeit && isset( $views['all'] ) ) {
		$views['all'] = str_replace( array( ' class="current"', ' aria-current="page"' ), '', $views['all'] );
	}
	return array_merge( array_slice( $views, 0, 1, true ), $extra, array_slice( $views, 1, null, true ) );
} );

/* Zeilenaktion "Duplizieren" (z. B. für wiederkehrende Veranstaltungen) */
add_filter( 'post_row_actions', function ( $actions, $post ) {
	if ( 'sgnl_event' === $post->post_type && current_user_can( 'edit_post', $post->ID ) ) {
		$actions['sgnl_dup'] = '<a href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=sgnl_event_dup&id=' . $post->ID ), 'sgnl_event_dup_' . $post->ID ) ) . '">' . esc_html__( 'Duplizieren', 'sgnl-core' ) . '</a>';
	}
	return $actions;
}, 10, 2 );

add_action( 'admin_post_sgnl_event_dup', function () {
	$id = absint( $_GET['id'] ?? 0 );
	check_admin_referer( 'sgnl_event_dup_' . $id );
	if ( ! $id || ! current_user_can( 'edit_post', $id ) || 'sgnl_event' !== get_post_type( $id ) ) {
		wp_die( esc_html__( 'Keine Berechtigung.', 'sgnl-core' ) );
	}
	$e   = sgnl_event_get( $id );
	$new = wp_insert_post( array(
		'post_type'   => 'sgnl_event',
		'post_status' => 'draft',
		'post_title'  => $e['title'],
	) );
	if ( $new && ! is_wp_error( $new ) ) {
		// Datum und Ergebnisse/Protokoll gehören zur alten Veranstaltung – nicht übernehmen.
		sgnl_event_save_fields( $new, array_merge( $e, array( 'start' => '', 'end' => '', 'ergebnis' => '', 'protokoll' => '', 'ausschreibung' => '' ) ) );
		wp_safe_redirect( admin_url( 'post.php?action=edit&post=' . $new ) );
		exit;
	}
	wp_safe_redirect( admin_url( 'edit.php?post_type=sgnl_event' ) );
	exit;
} );

/* Link zum PDF in der Termin-Übersicht */
add_action( 'restrict_manage_posts', function ( $type ) {
	if ( 'sgnl_event' !== $type ) {
		return;
	}
	printf( '<a class="button" href="%s" target="_blank" rel="noopener" style="margin-left:4px">%s</a>', esc_url( sgnl_events_pdf_url() ), esc_html__( 'Terminplan als PDF', 'sgnl-core' ) );
} );

/* Monatsfilter (Veröffentlichungsdatum) ist bei Terminen irreführend */
add_filter( 'disable_months_dropdown', function ( $disable, $type ) {
	return 'sgnl_event' === $type ? true : $disable;
}, 10, 2 );
