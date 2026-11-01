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
		'start'           => __( 'Datum von', 'sgnl-core' ),
		'end'             => __( 'Datum bis', 'sgnl-core' ),
		'start_time'      => __( 'Uhrzeit von', 'sgnl-core' ),
		'end_time'        => __( 'Uhrzeit bis', 'sgnl-core' ),
		'ort'             => __( 'Ort', 'sgnl-core' ),
		'bahn'            => __( 'Bahn', 'sgnl-core' ),
		'team'            => __( 'Mannschaft', 'sgnl-core' ),
		'ausschreibung'   => __( 'Ausschreibung', 'sgnl-core' ),
		'meldeuebersicht' => __( 'Meldeübersicht', 'sgnl-core' ),
		'meldeergebnis'   => __( 'Meldeergebnis', 'sgnl-core' ),
		'livetiming'      => __( 'Live-Timing', 'sgnl-core' ),
		'ergebnis'        => __( 'Ergebnisse', 'sgnl-core' ),
		'protokoll'       => __( 'Protokoll', 'sgnl-core' ),
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
		'capability_type' => array( 'sgnl_event', 'sgnl_events' ),
		'map_meta_cap'    => true,
	) );

	foreach ( array_keys( sgnl_event_fields() ) as $key ) {
		register_post_meta( 'sgnl_event', '_sgnl_' . $key, array(
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => false,
			'revisions_enabled' => true,
			'auth_callback'     => function () {
				return current_user_can( 'edit_sgnl_events' );
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
			'cats'     => array( 'type' => 'string', 'default' => '' ),
			'filter'   => array( 'type' => 'boolean', 'default' => true ),
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
			'kategorie' => '',
		), $atts, 'sgnl_termine' );
		return sgnl_events_render( array(
			'mode'     => $atts['archiv'] ? 'archive' : 'upcoming',
			'year'     => (int) $atts['archiv'],
			'view'     => in_array( $atts['ansicht'], array( 'kacheln', 'tiles' ), true ) ? 'tiles' : 'list',
			'switcher' => 'nein' !== $atts['umschalter'],
			'pdf'      => 'nein' !== $atts['pdf'],
			'cats'     => $atts['kategorie'],
			'align'    => 'wide',
		) );
	} );
} );

/* =========================================================================
 * Kategorien (Wettkämpfe, Veranstaltungen, Ferien, Jugendveranstaltungen …)
 * ====================================================================== */

/**
 * Standard-Kategorien: slug => [Name, Farbe].
 *
 * @return array
 */
function sgnl_event_default_cats() {
	return array(
		'wettkampf'     => array( __( 'Wettkämpfe', 'sgnl-core' ), '#1F44B8' ),
		'veranstaltung' => array( __( 'Veranstaltungen', 'sgnl-core' ), '#0B86B5' ),
		'ferien'        => array( __( 'Ferien', 'sgnl-core' ), '#2E9E5B' ),
		'jugend'        => array( __( 'Jugendveranstaltungen', 'sgnl-core' ), '#E0761F' ),
	);
}

add_action( 'init', function () {
	register_taxonomy( 'sgnl_event_cat', 'sgnl_event', array(
		'labels'            => array(
			'name'          => __( 'Kategorien', 'sgnl-core' ),
			'singular_name' => __( 'Kategorie', 'sgnl-core' ),
			'menu_name'     => __( 'Kategorien', 'sgnl-core' ),
			'all_items'     => __( 'Alle Kategorien', 'sgnl-core' ),
			'add_new_item'  => __( 'Neue Kategorie', 'sgnl-core' ),
			'edit_item'     => __( 'Kategorie bearbeiten', 'sgnl-core' ),
			'search_items'  => __( 'Kategorien durchsuchen', 'sgnl-core' ),
			'not_found'     => __( 'Keine Kategorien.', 'sgnl-core' ),
		),
		'public'            => false,
		'show_ui'           => true,
		'show_in_rest'      => false,
		'show_admin_column' => true,
		'hierarchical'      => true,
		'rewrite'           => false,
		'query_var'         => 'sgnl_event_cat',
		'capabilities'      => array(
			'manage_terms' => 'edit_others_sgnl_events',
			'edit_terms'   => 'edit_others_sgnl_events',
			'delete_terms' => 'edit_others_sgnl_events',
			'assign_terms' => 'edit_sgnl_events',
		),
	) );
	register_term_meta( 'sgnl_event_cat', '_sgnl_color', array( 'type' => 'string', 'single' => true, 'show_in_rest' => false ) );

	if ( '1' !== get_option( 'sgnl_event_cats' ) ) {
		foreach ( sgnl_event_default_cats() as $slug => $conf ) {
			if ( ! term_exists( $slug, 'sgnl_event_cat' ) ) {
				$t = wp_insert_term( $conf[0], 'sgnl_event_cat', array( 'slug' => $slug ) );
				if ( ! is_wp_error( $t ) ) {
					update_term_meta( $t['term_id'], '_sgnl_color', $conf[1] );
				}
			}
		}
		// Vorhandene Termine ohne Kategorie einsortieren.
		foreach ( get_posts( array( 'post_type' => 'sgnl_event', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ) ) as $id ) {
			if ( ! wp_get_object_terms( $id, 'sgnl_event_cat', array( 'fields' => 'ids' ) ) ) {
				wp_set_object_terms( $id, sgnl_event_guess_cat( sgnl_event_get( $id ) ), 'sgnl_event_cat' );
			}
		}
		update_option( 'sgnl_event_cats', '1' );
	}
}, 11 );

/**
 * Gehört der Termin ins Archiv? Nur Wettkämpfe (und Termine ohne Kategorie).
 *
 * @param array $e Termin.
 * @return bool
 */
function sgnl_event_in_archive( array $e ) {
	return empty( $e['cat_slugs'] ) || in_array( 'wettkampf', $e['cat_slugs'], true );
}

/**
 * Kategorien, die nicht ins Archiv gehören (alles außer Wettkämpfe).
 *
 * @return string[]
 */
function sgnl_event_non_archive_cats() {
	$terms = get_terms( array( 'taxonomy' => 'sgnl_event_cat', 'hide_empty' => false, 'fields' => 'slugs' ) );
	return array_values( array_diff( is_array( $terms ) ? $terms : array(), array( 'wettkampf' ) ) );
}

/**
 * Kategorie anhand der Angaben erraten (für übernommene Termine).
 *
 * @param array $e Termin.
 * @return string Slug
 */
function sgnl_event_guess_cat( array $e ) {
	$t = mb_strtolower( $e['title'] . ' ' . $e['team'] );
	if ( false !== strpos( $t, 'ferien' ) ) {
		return 'ferien';
	}
	if ( preg_match( '/meisterschaft|schwimmfest|pokal|\bdms|sprint|meeting|wettkampf|schwimmtest|bestenkampf|kurzbahn|langbahn|freiwasser|\bbm\b|\bhm\b|\bdm\b/u', $t ) || $e['bahn'] || $e['protokoll'] || $e['ergebnis'] || $e['meldeergebnis'] || $e['livetiming'] ) {
		return 'wettkampf';
	}
	if ( preg_match( '/jugend|kinder|halloween|zeltlager|swim ?in|bastel|kids/u', $t ) ) {
		return 'jugend';
	}
	return 'veranstaltung';
}

/**
 * Kategorien eines Termins.
 *
 * @param int $id Termin.
 * @return array[] slug, name, color
 */
function sgnl_event_cats( $id ) {
	$out      = array();
	$defaults = sgnl_event_default_cats();
	$terms    = get_the_terms( $id, 'sgnl_event_cat' );
	foreach ( is_array( $terms ) ? $terms : array() as $t ) {
		$c     = (string) get_term_meta( $t->term_id, '_sgnl_color', true );
		$out[] = array(
			'slug'  => $t->slug,
			'name'  => $t->name,
			'color' => preg_match( '/^#[0-9a-f]{6}$/i', $c ) ? $c : ( $defaults[ $t->slug ][1] ?? '#5B6B83' ),
		);
	}
	return $out;
}

/* Farbe einer Kategorie im Dashboard einstellbar */
add_action( 'sgnl_event_cat_add_form_fields', function () {
	echo '<div class="form-field"><label for="sgnl-cat-color">' . esc_html__( 'Farbe', 'sgnl-core' ) . '</label><input type="color" name="sgnl_color" id="sgnl-cat-color" value="#1F44B8"></div>';
} );
add_action( 'sgnl_event_cat_edit_form_fields', function ( $term ) {
	$c = (string) get_term_meta( $term->term_id, '_sgnl_color', true );
	echo '<tr class="form-field"><th><label for="sgnl-cat-color">' . esc_html__( 'Farbe', 'sgnl-core' ) . '</label></th><td><input type="color" name="sgnl_color" id="sgnl-cat-color" value="' . esc_attr( $c ? $c : '#1F44B8' ) . '"></td></tr>';
} );
foreach ( array( 'created_sgnl_event_cat', 'edited_sgnl_event_cat' ) as $hook ) {
	add_action( $hook, function ( $term_id ) {
		if ( isset( $_POST['sgnl_color'] ) && preg_match( '/^#[0-9a-f]{6}$/i', (string) $_POST['sgnl_color'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			update_term_meta( $term_id, '_sgnl_color', sanitize_hex_color( wp_unslash( $_POST['sgnl_color'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
		}
	} );
}

/* Kategorien für den Block-Editor */
add_action( 'enqueue_block_editor_assets', function () {
	$cats = array();
	foreach ( get_terms( array( 'taxonomy' => 'sgnl_event_cat', 'hide_empty' => false ) ) as $t ) {
		$cats[] = array( 'slug' => $t->slug, 'name' => $t->name );
	}
	wp_add_inline_script( 'sgnl-events-block', 'window.sgnlEventsBlock = Object.assign(window.sgnlEventsBlock || {}, ' . wp_json_encode( array( 'cats' => $cats ) ) . ');', 'before' );
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
		'title' => html_entity_decode( (string) $post->post_title, ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
	);
	foreach ( array_keys( sgnl_event_fields() ) as $key ) {
		$e[ $key ] = (string) get_post_meta( $post->ID, '_sgnl_' . $key, true );
	}
	if ( $e['end'] && $e['end'] < $e['start'] ) {
		$e['end'] = '';
	}
	$e['last']     = $e['end'] ? $e['end'] : $e['start'];
	$e['messages'] = sgnl_event_messages( $post->ID );
	$e['cats']     = sgnl_event_cats( $post->ID );
	$e['cat_slugs'] = wp_list_pluck( $e['cats'], 'slug' );
	$e['ts_start'] = $e['start'] ? sgnl_event_ts( $e['start'], $e['start_time'] ? $e['start_time'] : '00:00' ) : 0;
	$e['ts_end']   = $e['start'] ? sgnl_event_ts( $e['last'], $e['end_time'] ? $e['end_time'] : '23:59' ) : 0;
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
function sgnl_events_query( $mode = 'upcoming', $year = 0, array $cats = array(), array $exclude = array() ) {
	$today = sgnl_events_today();
	// Im Archiv stehen nur Wettkämpfe – alles andere wird nach dem Termin einfach ausgeblendet.
	if ( 'archive' === $mode && ! $cats && ! $exclude ) {
		$exclude = sgnl_event_non_archive_cats();
	}
	$args  = array(
		'post_type'              => 'sgnl_event',
		'post_status'            => 'publish',
		'posts_per_page'         => -1,
		'meta_key'               => '_sgnl_start', // phpcs:ignore WordPress.DB.SlowDBQuery
		'orderby'                => array( 'meta_value' => 'archive' === $mode ? 'DESC' : 'ASC', 'title' => 'ASC' ),
		'no_found_rows'          => true,
	);
	$tax = array();
	if ( $cats ) {
		$tax[] = array( 'taxonomy' => 'sgnl_event_cat', 'field' => 'slug', 'terms' => $cats );
	}
	if ( $exclude ) {
		$tax[] = array( 'taxonomy' => 'sgnl_event_cat', 'field' => 'slug', 'terms' => $exclude, 'operator' => 'NOT IN' );
	}
	if ( $tax ) {
		$args['tax_query'] = $tax; // phpcs:ignore WordPress.DB.SlowDBQuery
	}
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
		'cal'    => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 9h18M8 3v4M16 3v4"/>',
		'note'   => '<path d="M12 9v4"/><path d="M12 17h.01"/><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/>',
		'ext'    => '<path d="M14 4h6v6"/><path d="M10 14 20 4"/><path d="M20 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1h5"/>',
		'live'   => '<circle cx="12" cy="12" r="2.5"/><path d="M7.8 7.8a6 6 0 0 0 0 8.4M16.2 16.2a6 6 0 0 0 0-8.4M5 5a10 10 0 0 0 0 14M19 19a10 10 0 0 0 0-14"/>',
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
 * Alle Unterlagen eines kommenden Termins (Ausschreibung, Meldungen, Live-Timing).
 *
 * @param array $e     Termin.
 * @param bool  $tiles Kachel-Ansicht (erster Link hervorgehoben).
 * @return string
 */
function sgnl_events_doc_links( array $e, $tiles = false ) {
	$out   = '';
	$first = true;
	foreach ( array(
		'ausschreibung'   => array( __( 'Ausschreibung', 'sgnl-core' ), 'doc' ),
		'meldeuebersicht' => array( __( 'Meldeübersicht', 'sgnl-core' ), 'list' ),
		'meldeergebnis'   => array( __( 'Meldeergebnis', 'sgnl-core' ), 'team' ),
		'livetiming'      => array( __( 'Live-Timing', 'sgnl-core' ), 'live' ),
		'protokoll'       => array( __( 'Protokoll', 'sgnl-core' ), 'doc' ),
	) as $k => $conf ) {
		if ( empty( $e[ $k ] ) ) {
			continue;
		}
		$out  .= sgnl_events_doc_link( $e[ $k ], $conf[0], $conf[1], $tiles && $first ? 'is-primary' : '' );
		$first = false;
	}
	return $tiles || ! $out ? $out : '<span class="sgnl-docs">' . $out . '</span>';
}

/**
 * Datum + Uhrzeit (Zeitzone der Website) als Zeitstempel.
 *
 * @param string $ymd  Datum.
 * @param string $time Uhrzeit HH:MM.
 * @return int
 */
function sgnl_event_ts( $ymd, $time ) {
	try {
		$dt = new DateTimeImmutable( $ymd . ' ' . $time . ':00', wp_timezone() );
		return $dt->getTimestamp();
	} catch ( Exception $ex ) {
		return 0;
	}
}

/**
 * Nachrichten eines Termins (neueste zuerst).
 *
 * @param int $id Termin.
 * @return array[] id, text, autor, zeit
 */
function sgnl_event_messages( $id ) {
	$m = get_post_meta( $id, '_sgnl_messages', true );
	$m = is_array( $m ) ? array_values( $m ) : array();
	usort( $m, function ( $a, $b ) {
		return (int) $b['zeit'] - (int) $a['zeit'];
	} );
	return $m;
}

/**
 * Kategorie-Etiketten.
 *
 * @param array $e Termin.
 * @return string
 */
function sgnl_event_cat_chips( array $e ) {
	if ( empty( $e['cats'] ) ) {
		return '';
	}
	$h = '<span class="sgnl-cats">';
	foreach ( $e['cats'] as $c ) {
		$h .= '<span class="sgnl-cat" style="--cat:' . esc_attr( $c['color'] ) . '">' . esc_html( $c['name'] ) . '</span>';
	}
	return $h . '</span>';
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
		'cats'     => '',
		'filter'   => true,
	) );
	$archive = 'archive' === $a['mode'];
	$cats    = array_filter( array_map( 'sanitize_title', explode( ',', (string) $a['cats'] ) ) );
	$year    = $archive ? ( (int) $a['year'] ? (int) $a['year'] : (int) wp_date( 'Y' ) ) : 0;
	$view    = 'tiles' === $a['view'] ? 'tiles' : 'list';
	$events  = sgnl_events_query( $archive ? 'archive' : 'upcoming', $year, $cats );

	wp_enqueue_style( 'sgnl-events' );
	// Filter nach Kategorie (nur wenn mehr als eine vorkommt).
	$present = array();
	foreach ( $events as $e ) {
		foreach ( $e['cats'] as $c ) {
			$present[ $c['slug'] ] = $c;
		}
	}
	$show_filter = $a['filter'] && count( $present ) > 1;
	if ( ( $a['switcher'] || $show_filter ) && $events ) {
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
	if ( $show_filter ) {
		$order = array_keys( sgnl_event_default_cats() );
		uksort( $present, function ( $x, $y ) use ( $order ) {
			$ix = array_search( $x, $order, true );
			$iy = array_search( $y, $order, true );
			return ( false === $ix ? 99 : $ix ) - ( false === $iy ? 99 : $iy );
		} );
		$html .= '<div class="sgnl-events__filter" role="group" aria-label="' . esc_attr__( 'Nach Kategorie filtern', 'sgnl-core' ) . '"><button type="button" data-cat="" aria-pressed="true">' . esc_html__( 'Alle', 'sgnl-core' ) . '</button>';
		foreach ( $present as $c ) {
			$html .= '<button type="button" data-cat="' . esc_attr( $c['slug'] ) . '" aria-pressed="false" style="--cat:' . esc_attr( $c['color'] ) . '"><i aria-hidden="true"></i>' . esc_html( $c['name'] ) . '</button>';
		}
		$html .= '</div>';
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

	if ( current_user_can( 'edit_sgnl_events' ) ) {
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
		: array( 'date' => __( 'Datum', 'sgnl-core' ), 'title' => __( 'Veranstaltung', 'sgnl-core' ), 'ort' => __( 'Ort', 'sgnl-core' ), 'bahn' => __( 'Bahn', 'sgnl-core' ), 'team' => __( 'Mannschaft', 'sgnl-core' ), 'docs' => __( 'Unterlagen', 'sgnl-core' ) );

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
			$h    .= '<tr class="is-group-row" data-group="' . esc_attr( $g ) . '"><td colspan="' . count( $cols ) . '">' . esc_html( $label ) . '</td></tr>';
		}
		$h .= '<tr data-cat="' . esc_attr( implode( ' ', $e['cat_slugs'] ) ) . '">';
		foreach ( $cols as $k => $label ) {
			switch ( $k ) {
				case 'date':
					$val = esc_html( sgnl_event_date_label( $e ) );
					break;
				case 'title':
					$val = '<strong>' . esc_html( $e['title'] ) . '</strong>' . sgnl_event_cat_chips( $e );
					break;
				case 'docs':
					$val = sgnl_events_doc_links( $e );
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
			: sgnl_events_doc_links( $e, true );

		$cat = $e['cats'] ? $e['cats'][0]['color'] : '';
		$h  .= '<article class="sgnl-event' . ( $e['cats'] ? ' has-cat' : '' ) . '" data-cat="' . esc_attr( implode( ' ', $e['cat_slugs'] ) ) . '"' . ( $cat ? ' style="--cat:' . esc_attr( $cat ) . '"' : '' ) . '>'
			. '<div class="sgnl-event__date' . $long . '"><span class="sgnl-event__wd">' . esc_html( $wd ) . '</span><span class="sgnl-event__days">' . esc_html( $days ) . '</span><span class="sgnl-event__month">' . esc_html( $month ) . '</span></div>'
			. '<div class="sgnl-event__body">' . sgnl_event_cat_chips( $e ) . '<h3 class="sgnl-event__title">' . esc_html( $e['title'] ) . '</h3>'
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
	add_meta_box( 'sgnl_event_live', __( 'Meldungen & Live-Timing (Datenbank → Live)', 'sgnl-core' ), 'sgnl_event_live_box', 'sgnl_event', 'normal', 'default' );
	add_meta_box( 'sgnl_event_msgs', __( 'Nachrichten (Live)', 'sgnl-core' ), 'sgnl_event_msgs_box', 'sgnl_event', 'normal', 'default' );
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
		wp_enqueue_script( 'sgnl-markdown', SGNL_CORE_URL . 'assets/markdown.js', array(), sgnl_core_ver( 'assets/markdown.js' ), true );
		wp_enqueue_script( 'sgnl-events-admin', SGNL_CORE_URL . 'assets/events-admin.js', array( 'jquery', 'sgnl-markdown' ), sgnl_core_ver( 'assets/events-admin.js' ), true );
		$wk = get_term_by( 'slug', 'wettkampf', 'sgnl_event_cat' );
		wp_localize_script( 'sgnl-events-admin', 'sgnlEventsAdmin', array( 'wettkampf' => $wk ? (int) $wk->term_id : 0 ) );
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
		$arch   = sgnl_event_in_archive( $e );
		$status = $e['past']
			? ( $arch
				/* translators: %d: Jahr */
				? sprintf( __( 'Vorbei – erscheint im Archiv %d. Ergebnisse und Protokoll können unten ergänzt werden.', 'sgnl-core' ), $e['year'] )
				: __( 'Vorbei – wird auf der Website nicht mehr angezeigt (nur Wettkämpfe kommen ins Archiv).', 'sgnl-core' ) )
			: ( $arch
				? __( 'Steht im Terminplan. Nach dem letzten Tag wandert der Termin automatisch ins Archiv.', 'sgnl-core' )
				: __( 'Steht im Terminplan. Nach dem letzten Tag wird er automatisch ausgeblendet.', 'sgnl-core' ) );
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
			<label for="sgnl-ev-start-time"><?php esc_html_e( 'Uhrzeit Beginn', 'sgnl-core' ); ?> <small><?php esc_html_e( '(optional, für „Live jetzt“)', 'sgnl-core' ); ?></small></label>
			<input type="time" id="sgnl-ev-start-time" name="sgnl_event[start_time]" value="<?php echo esc_attr( $e['start_time'] ); ?>">
		</div>
		<div class="sgnl-ev__field">
			<label for="sgnl-ev-end-time"><?php esc_html_e( 'Uhrzeit Ende', 'sgnl-core' ); ?> <small><?php esc_html_e( '(am letzten Tag)', 'sgnl-core' ); ?></small></label>
			<input type="time" id="sgnl-ev-end-time" name="sgnl_event[end_time]" value="<?php echo esc_attr( $e['end_time'] ); ?>">
		</div>
		<div class="sgnl-ev__field">
			<label for="sgnl-ev-ort"><?php esc_html_e( 'Ort', 'sgnl-core' ); ?></label>
			<input type="text" class="widefat" id="sgnl-ev-ort" name="sgnl_event[ort]" value="<?php echo esc_attr( $e['ort'] ); ?>" list="sgnl-ev-list-ort" autocomplete="off">
		</div>
		<div class="sgnl-ev__field sgnl-ev--wk">
			<label for="sgnl-ev-bahn"><?php esc_html_e( 'Bahn', 'sgnl-core' ); ?> <small><?php esc_html_e( '(nur bei Wettkämpfen)', 'sgnl-core' ); ?></small></label>
			<input type="text" class="widefat" id="sgnl-ev-bahn" name="sgnl_event[bahn]" value="<?php echo esc_attr( $e['bahn'] ); ?>" list="sgnl-ev-list-bahn" autocomplete="off" placeholder="25 m">
		</div>
		<div class="sgnl-ev__field is-wide">
			<label for="sgnl-ev-team"><?php esc_html_e( 'Mannschaft', 'sgnl-core' ); ?></label>
			<input type="text" class="widefat" id="sgnl-ev-team" name="sgnl_event[team]" value="<?php echo esc_attr( $e['team'] ); ?>" list="sgnl-ev-list-team" autocomplete="off" placeholder="<?php esc_attr_e( 'z. B. 1. Mannschaft, Masters', 'sgnl-core' ); ?>">
		</div>
		<?php sgnl_events_file_field( 'ausschreibung', $e['ausschreibung'], __( 'Ausschreibung (optional)', 'sgnl-core' ), __( 'PDF aus der Mediathek wählen bzw. hochladen – oder einen Link einfügen. Kann leer bleiben, z. B. bei Ferien oder Jugendveranstaltungen.', 'sgnl-core' ) ); ?>
	</div>
	<?php
}

/**
 * Meldungen & Live-Timing.
 *
 * @param WP_Post $post Termin.
 */
function sgnl_event_live_box( $post ) {
	$e = sgnl_event_get( $post );
	echo '<p class="description" style="margin-top:0">' . esc_html__( 'Sobald hier ein Link steht, erscheint der Wettkampf im Reiter „Live“ der Datenbank – bis 48 Stunden nach dem Ende. Während der Wettkampf läuft, wird er dort als „Live jetzt“ markiert.', 'sgnl-core' ) . '</p>';
	echo '<div class="sgnl-ev">';
	sgnl_events_file_field( 'meldeuebersicht', $e['meldeuebersicht'], __( 'Meldeübersicht', 'sgnl-core' ) );
	sgnl_events_file_field( 'meldeergebnis', $e['meldeergebnis'], __( 'Meldeergebnis', 'sgnl-core' ) );
	sgnl_events_file_field( 'livetiming', $e['livetiming'], __( 'Live-Timing', 'sgnl-core' ) );
	echo '</div>';
}

/**
 * Nachrichten (z. B. geänderte Startzeit) – werden im Live-Reiter groß angezeigt.
 *
 * @param WP_Post $post Termin.
 */
function sgnl_event_msgs_box( $post ) {
	if ( 'auto-draft' === $post->post_status ) {
		echo '<p>' . esc_html__( 'Nach dem ersten Speichern können hier Nachrichten veröffentlicht werden.', 'sgnl-core' ) . '</p>';
		return;
	}
	?>
	<div class="sgnl-msgs" data-post="<?php echo (int) $post->ID; ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'sgnl_event_msg_' . $post->ID ) ); ?>">
		<p class="description" style="margin-top:0"><?php esc_html_e( 'Kurze Info für den Wettkampftag, z. B. geänderte Startzeit. Erscheint sofort groß auf der Live-Karte. Formatierung: **fett**, *kursiv*, # Überschrift, - Liste, [Text](https://…).', 'sgnl-core' ); ?></p>
		<textarea class="widefat sgnl-msgs__input" id="sgnl-msg-text" rows="3" maxlength="1000" placeholder="<?php esc_attr_e( 'z. B. **Einschwimmen** heute erst ab 8:30 Uhr', 'sgnl-core' ); ?>"></textarea>
		<div class="sgnl-msgs__preview" data-md-preview="sgnl-msg-text" hidden><span><?php esc_html_e( 'Vorschau', 'sgnl-core' ); ?></span><div data-md-out></div></div>
		<p><button type="button" class="button button-primary sgnl-msgs__add"><?php esc_html_e( 'Nachricht veröffentlichen', 'sgnl-core' ); ?></button> <span class="sgnl-msgs__status" aria-live="polite"></span></p>
		<div class="sgnl-msgs__list"><?php echo sgnl_event_messages_admin_html( $post->ID ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
	</div>
	<?php
}

/**
 * Liste der Nachrichten im Dashboard.
 *
 * @param int $id Termin.
 * @return string
 */
function sgnl_event_messages_admin_html( $id ) {
	$msgs = sgnl_event_messages( $id );
	if ( ! $msgs ) {
		return '<p class="sgnl-msgs__empty">' . esc_html__( 'Noch keine Nachrichten.', 'sgnl-core' ) . '</p>';
	}
	$h = '';
	foreach ( $msgs as $m ) {
		$h .= '<div class="sgnl-msg"><div class="sgnl-msg__text" data-md>' . esc_html( $m['text'] ) . '</div>'
			. '<div class="sgnl-msg__meta"><strong>' . esc_html( $m['autor'] ) . '</strong> · ' . esc_html( wp_date( 'd.m.Y, H:i', (int) $m['zeit'] ) ) . ' Uhr'
			. ' <button type="button" class="button-link sgnl-msg__del" data-id="' . esc_attr( $m['id'] ) . '">' . esc_html__( 'Löschen', 'sgnl-core' ) . '</button></div></div>';
	}
	return $h;
}

/* Nachrichten hinzufügen/löschen (ohne die Seite neu zu laden) */
add_action( 'wp_ajax_sgnl_event_msg', function () {
	$id = absint( $_POST['post'] ?? 0 );
	if ( ! $id || 'sgnl_event' !== get_post_type( $id ) || ! check_ajax_referer( 'sgnl_event_msg_' . $id, 'nonce', false ) || ! current_user_can( 'edit_post', $id ) ) {
		wp_send_json_error( array( 'message' => __( 'Keine Berechtigung.', 'sgnl-core' ) ), 403 );
	}
	$msgs = get_post_meta( $id, '_sgnl_messages', true );
	$msgs = is_array( $msgs ) ? $msgs : array();
	$op   = sanitize_key( $_POST['op'] ?? '' );
	if ( 'add' === $op ) {
		$text = trim( sanitize_textarea_field( wp_unslash( $_POST['text'] ?? '' ) ) );
		if ( '' === $text ) {
			wp_send_json_error( array( 'message' => __( 'Bitte einen Text eingeben.', 'sgnl-core' ) ) );
		}
		$user   = wp_get_current_user();
		$msgs[] = array(
			'id'    => wp_generate_password( 10, false ),
			'text'  => mb_substr( $text, 0, 1000 ),
			'autor' => $user->display_name ? $user->display_name : $user->user_login,
			'zeit'  => time(),
		);
	} elseif ( 'delete' === $op ) {
		$del  = sanitize_text_field( wp_unslash( $_POST['id'] ?? '' ) );
		$msgs = array_values( array_filter( $msgs, function ( $m ) use ( $del ) {
			return ( $m['id'] ?? '' ) !== $del;
		} ) );
	}
	update_post_meta( $id, '_sgnl_messages', $msgs );
	wp_send_json_success( array( 'html' => sgnl_event_messages_admin_html( $id ) ) );
} );

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
	foreach ( array( 'start_time', 'end_time' ) as $k ) {
		$v          = trim( (string) ( $in[ $k ] ?? '' ) );
		$vals[ $k ] = preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', substr( $v, 0, 5 ) ) ? substr( $v, 0, 5 ) : '';
	}
	foreach ( array( 'ausschreibung', 'meldeuebersicht', 'meldeergebnis', 'livetiming', 'ergebnis', 'protokoll' ) as $k ) {
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
			echo $e['start'] ? '<strong>' . esc_html( sgnl_event_date_label( $e, true ) ) . '</strong><br><small>' . esc_html( sgnl_weekday( $e['start'] ) . ( $e['end'] ? ' – ' . sgnl_weekday( $e['end'] ) : '' ) . ( $e['start_time'] ? ' · ' . $e['start_time'] . ( $e['end_time'] ? '–' . $e['end_time'] : '' ) . ' Uhr' : '' ) ) . '</small>' : '<span class="sgnl-ev-missing">' . esc_html__( 'Datum fehlt', 'sgnl-core' ) . '</span>';
			break;
		case 'sgnl_ort':
			echo esc_html( $e['ort'] . ( $e['bahn'] ? ' · ' . $e['bahn'] : '' ) );
			break;
		case 'sgnl_team':
			echo esc_html( $e['team'] );
			break;
		case 'sgnl_docs':
			foreach ( array( 'ausschreibung' => 'A', 'meldeergebnis' => 'M', 'livetiming' => 'L', 'ergebnis' => 'E', 'protokoll' => 'P' ) as $k => $abbr ) {
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
			} elseif ( $e['past'] && ! sgnl_event_in_archive( $e ) ) {
				echo '<span class="sgnl-ev-state is-past">' . esc_html__( 'ausgeblendet', 'sgnl-core' ) . '</span>';
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
		sgnl_event_save_fields( $new, array_merge( $e, array( 'start' => '', 'end' => '', 'ergebnis' => '', 'protokoll' => '', 'ausschreibung' => '', 'meldeuebersicht' => '', 'meldeergebnis' => '', 'livetiming' => '' ) ) );
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
	wp_dropdown_categories( array(
		'taxonomy'        => 'sgnl_event_cat',
		'name'            => 'sgnl_event_cat',
		'value_field'     => 'slug',
		'show_option_all' => __( 'Alle Kategorien', 'sgnl-core' ),
		'selected'        => sanitize_title( wp_unslash( $_GET['sgnl_event_cat'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification
		'hide_empty'      => false,
	) );
	printf( '<a class="button" href="%s" target="_blank" rel="noopener" style="margin-left:4px">%s</a>', esc_url( sgnl_events_pdf_url() ), esc_html__( 'Terminplan als PDF', 'sgnl-core' ) );
} );

/* Monatsfilter (Veröffentlichungsdatum) ist bei Terminen irreführend */
add_filter( 'disable_months_dropdown', function ( $disable, $type ) {
	return 'sgnl_event' === $type ? true : $disable;
}, 10, 2 );

/* =========================================================================
 * Rechte: Admins + Redakteure dürfen Termine pflegen; zusätzlich gibt es
 * die Rolle "Terminverwaltung" (nur Termine + Mediathek), z. B. für Trainer,
 * die am Wettkampftag Meldeergebnis, Live-Timing und Nachrichten eintragen.
 * ====================================================================== */
add_action( 'init', function () {
	if ( '2' === get_option( 'sgnl_event_caps' ) ) {
		return;
	}
	$caps = array( 'edit_sgnl_events', 'edit_others_sgnl_events', 'edit_published_sgnl_events', 'edit_private_sgnl_events', 'publish_sgnl_events', 'read_private_sgnl_events', 'delete_sgnl_events', 'delete_others_sgnl_events', 'delete_published_sgnl_events', 'delete_private_sgnl_events' );
	foreach ( array( 'administrator', 'editor' ) as $r ) {
		$role = get_role( $r );
		if ( $role ) {
			foreach ( $caps as $c ) {
				$role->add_cap( $c );
			}
		}
	}
	remove_role( 'sgnl_terminverwaltung' );
	add_role( 'sgnl_terminverwaltung', __( 'Terminverwaltung', 'sgnl-core' ), array_merge( array( 'read' => true, 'upload_files' => true ), array_fill_keys( $caps, true ) ) );
	update_option( 'sgnl_event_caps', '2' );
}, 5 );

/**
 * Wie sicher sind zwei Einträge desselben Tages dieselbe Veranstaltung? (0–100)
 *
 * Am selben Tag – auch am selben Ort – kann es mehrere Veranstaltungen geben
 * (z. B. Bezirks-Masters und Kreismeisterschaften). Deshalb zählt vor allem,
 * ob die unterscheidenden Wörter im Namen übereinstimmen; allgemeine Wörter wie
 * "Meisterschaften" oder "Schwimmfest" werden ignoriert.
 *
 * @param string $t1 Name 1.
 * @param string $o1 Ort 1.
 * @param string $t2 Name 2.
 * @param string $o2 Ort 2.
 * @return float
 */
function sgnl_event_match_score( $t1, $o1, $t2, $o2 ) {
	$tokens = function ( $t ) {
		$t = mb_strtolower( (string) $t );
		$t = str_replace( array( 'ä', 'ö', 'ü', 'ß' ), array( 'ae', 'oe', 'ue', 'ss' ), $t );
		$t = preg_replace( '/(meisterschaften|meisterschaft|schwimmfest|schwimmen)/u', ' ', $t );
		$stop = array( 'nationales', 'nationale', 'internationales', 'internationale', 'int', 'nat', 'der', 'des', 'die', 'das', 'und', 'auf', 'im', 'in', 'am', 'von', 'fuer', 'mit', 'kreis-', 'ev' );
		$out  = array();
		foreach ( preg_split( '/[^a-z]+/', $t ) as $w ) {
			if ( strlen( $w ) >= 3 && ! in_array( $w, $stop, true ) ) {
				$out[ $w ] = true;
			}
		}
		return array_keys( $out );
	};
	$a        = $tokens( $t1 );
	$b        = $tokens( $t2 );
	$norm_ort = function ( $o ) {
		return preg_replace( '/[^a-z0-9]/', '', str_replace( array( 'ä', 'ö', 'ü', 'ß' ), array( 'ae', 'oe', 'ue', 'ss' ), mb_strtolower( (string) $o ) ) );
	};
	$same_ort = $o1 && $o2 && $norm_ort( $o1 ) === $norm_ort( $o2 );
	if ( ! $a || ! $b ) {
		return $same_ort ? 55 : 0; // Nur allgemeine Wörter im Namen – Ort entscheidet.
	}
	$hit = 0;
	foreach ( $a as $x ) {
		foreach ( $b as $y ) {
			similar_text( $x, $y, $p );
			if ( $x === $y || $p >= 80 || ( strlen( $x ) >= 5 && strlen( $y ) >= 5 && ( 0 === strpos( $x, $y ) || 0 === strpos( $y, $x ) ) ) ) {
				$hit++;
				break;
			}
		}
	}
	return 80 * $hit / min( count( $a ), count( $b ) ) + ( $same_ort ? 20 : 0 );
}
