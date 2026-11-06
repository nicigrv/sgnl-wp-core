<?php
/**
 * Trainingszeiten: im Backend pflegen (Termine → Trainingszeiten), auf der Website als
 * Wochenplan (nach Tagen oder nach Gruppen, mit Filter) – Block "Trainingszeiten".
 *
 * @package sgnl-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const SGNL_TRAINING_OPTION = 'sgnl_training';

/** Wochentage (ISO: 1 = Montag) */
function sgnl_training_days() {
	return array( 1 => 'Montag', 2 => 'Dienstag', 3 => 'Mittwoch', 4 => 'Donnerstag', 5 => 'Freitag', 6 => 'Samstag', 7 => 'Sonntag' );
}

/** Farben für Gruppen (Vereinsfarben + gut unterscheidbare Ergänzungen) */
function sgnl_training_palette() {
	return array( '#1F44B8', '#D7263D', '#0B86B5', '#13895B', '#E0A100', '#6B4FBB', '#E46A1E', '#0F8A84', '#C2185B', '#0B2A5B', '#5B6B83', '#8A6D3B' );
}

/**
 * Stand vom 15.10.2026 (gültig ab 19.10.2026) – wird beim ersten Mal übernommen.
 *
 * @return array
 */
function sgnl_training_defaults() {
	$hb = 'Lampertheim Hallenbad';
	$s  = function ( $day, $start, $end, $group, $place, $trainers, $season = '' ) {
		return compact( 'day', 'start', 'end', 'group', 'place', 'trainers', 'season' );
	};
	return array(
		'valid_from' => '2026-10-19',
		'note'       => '',
		'groups'     => array(),
		'sessions'   => array(
			$s( 1, '16:00', '17:00', '4. Mannschaft', $hb, "Tanja Mörstedt" ),
			$s( 1, '17:00', '18:00', 'Schwimmkurs', $hb, "Uschi Bischer\nNicolas Greulich\nHelena Platz" ),
			$s( 1, '17:00', '18:00', '3. Mannschaft', $hb, "Tanja Mörstedt" ),
			$s( 1, '17:00', '18:00', '2. Mannschaft', $hb, "Christel Mörstedt" ),
			$s( 1, '18:00', '19:30', '1. Mannschaft', $hb, "Uschi Bischer\nChristel Mörstedt" ),
			$s( 1, '19:30', '20:30', 'Masters', $hb, "Heike Grosse" ),
			$s( 2, '17:00', '18:00', 'Bambini', $hb, "Nicolas Greulich\nTom Wegerle" ),
			$s( 2, '17:00', '18:00', '2. Mannschaft', $hb, "Christel Mörstedt" ),
			$s( 2, '18:00', '19:00', '1. Mannschaft', $hb, "Christel Mörstedt" ),
			$s( 3, '17:30', '18:30', 'Trockentraining', 'Altrheinhalle / Berufsschule', "Heike Grosse", 'Okt. – März' ),
			$s( 3, '19:00', '20:00', 'Trockentraining', 'Altrheinhalle / Berufsschule', "Heike Grosse", 'April – Sept.' ),
			$s( 4, '15:45', '16:45', 'Athletiktraining', 'Hans-Pfeiffer-Halle', "Christel Mörstedt", '1. und 2. Mannschaft' ),
			$s( 5, '16:00', '17:00', '4. Mannschaft', $hb, "Claudia Uebach" ),
			$s( 5, '16:00', '17:00', '3. Mannschaft', $hb, "Tanja Mörstedt" ),
			$s( 5, '17:00', '18:00', 'Schwimmkurs', $hb, "Uschi Bischer\nNicolas Greulich\nHelena Platz" ),
			$s( 5, '17:00', '18:00', '2. Mannschaft', $hb, "Christel Mörstedt" ),
			$s( 5, '18:00', '19:30', '1. Mannschaft', $hb, "Uschi Bischer\nChristel Mörstedt" ),
			$s( 6, '11:00', '13:00', 'Bambini', $hb, "Miriam Ofenloch\nNicolas Greulich\nTom Wegerle" ),
			$s( 6, '13:00', '14:00', 'Schwimm- / Kraulkurs', $hb, "Uschi Bischer\nNicolas Greulich\nHelena Platz" ),
		),
	);
}

/**
 * Gespeicherte Trainingszeiten (beim ersten Mal: Stand 15.10.2026).
 *
 * @return array valid_from, note, groups, sessions
 */
function sgnl_training_get() {
	$d = get_option( SGNL_TRAINING_OPTION, null );
	if ( ! is_array( $d ) || ! isset( $d['sessions'] ) ) {
		$d = sgnl_training_defaults();
	}
	$d['sessions'] = array_values( array_filter( (array) $d['sessions'], 'is_array' ) );
	$d['groups']   = is_array( $d['groups'] ?? null ) ? $d['groups'] : array();
	return $d;
}

/**
 * Eingaben bereinigen und sortieren (Tag, Beginn, Reihenfolge).
 *
 * @param array $in Rohdaten.
 * @return array
 */
function sgnl_training_sanitize( $in ) {
	$time = function ( $v ) {
		$v = trim( str_replace( '.', ':', (string) $v ) );
		if ( preg_match( '/^(\d{1,2})(?::(\d{2}))?$/', $v, $m ) && (int) $m[1] < 24 && (int) ( $m[2] ?? 0 ) < 60 ) {
			return sprintf( '%02d:%02d', (int) $m[1], (int) ( $m[2] ?? 0 ) );
		}
		return '';
	};
	$out = array(
		'valid_from' => preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $in['valid_from'] ?? '' ) ) ? $in['valid_from'] : '',
		'note'       => sanitize_textarea_field( (string) ( $in['note'] ?? '' ) ),
		'groups'     => array(),
		'sessions'   => array(),
	);
	foreach ( (array) ( $in['sessions'] ?? array() ) as $i => $s ) {
		if ( ! is_array( $s ) ) {
			continue;
		}
		$row = array(
			'day'      => min( 7, max( 1, (int) ( $s['day'] ?? 1 ) ) ),
			'start'    => $time( $s['start'] ?? '' ),
			'end'      => $time( $s['end'] ?? '' ),
			'group'    => sanitize_text_field( (string) ( $s['group'] ?? '' ) ),
			'place'    => sanitize_text_field( (string) ( $s['place'] ?? '' ) ),
			'trainers' => implode( "\n", array_filter( array_map( 'sanitize_text_field', preg_split( '/[\r\n,;]+/', (string) ( $s['trainers'] ?? '' ) ) ) ) ),
			'season'   => sanitize_text_field( (string) ( $s['season'] ?? '' ) ),
			'_i'       => (int) $i,
		);
		if ( '' === $row['group'] && '' === $row['start'] ) {
			continue; // leere Zeile
		}
		$out['sessions'][] = $row;
	}
	usort( $out['sessions'], function ( $a, $b ) {
		return array( $a['day'], $a['start'], $a['_i'] ) <=> array( $b['day'], $b['start'], $b['_i'] );
	} );
	foreach ( $out['sessions'] as &$row ) {
		unset( $row['_i'] );
	}
	unset( $row );
	foreach ( (array) ( $in['groups'] ?? array() ) as $name => $g ) {
		$name  = sanitize_text_field( (string) $name );
		$color = sanitize_hex_color( (string) ( $g['color'] ?? '' ) );
		if ( '' !== $name && $color ) {
			$out['groups'][ $name ] = array( 'color' => $color );
		}
	}
	return $out;
}

/**
 * Gruppen in sinnvoller Reihenfolge (1.–4. Mannschaft, dann nach erstem Auftreten) mit Farbe.
 *
 * @param array $data Trainingszeiten.
 * @return array name => color
 */
function sgnl_training_groups( $data ) {
	$names = array();
	foreach ( $data['sessions'] as $s ) {
		if ( '' !== $s['group'] && ! in_array( $s['group'], $names, true ) ) {
			$names[] = $s['group'];
		}
	}
	$first = array_flip( $names );
	usort( $names, function ( $a, $b ) use ( $first ) {
		$na = preg_match( '/^(\d+)\.\s*Mannschaft$/u', $a, $ma ) ? (int) $ma[1] : 99;
		$nb = preg_match( '/^(\d+)\.\s*Mannschaft$/u', $b, $mb ) ? (int) $mb[1] : 99;
		return array( $na, $first[ $a ] ) <=> array( $nb, $first[ $b ] );
	} );
	$pal = sgnl_training_palette();
	$out = array();
	foreach ( $names as $i => $n ) {
		$out[ $n ] = $data['groups'][ $n ]['color'] ?? $pal[ $i % count( $pal ) ];
	}
	return $out;
}

/**
 * Passende Mannschaftsseite zu einer Gruppe (Unterseite von "Unsere Mannschaften").
 *
 * @param string $group Gruppe.
 * @return string URL oder ''
 */
function sgnl_training_group_url( $group ) {
	static $pages = null;
	if ( null === $pages ) {
		$pages  = array();
		$parent = function_exists( 'sgnl_teams_page' ) ? sgnl_teams_page() : null;
		if ( $parent ) {
			foreach ( sgnl_albums_children( $parent->ID ) as $p ) {
				$pages[ sgnl_training_key( $p->post_title ) ] = get_permalink( $p );
			}
		}
	}
	$k = sgnl_training_key( $group );
	if ( isset( $pages[ $k ] ) ) {
		return $pages[ $k ];
	}
	foreach ( $pages as $pk => $url ) { // "Bambini" ↔ "Bambinis"
		if ( strlen( $k ) > 4 && ( 0 === strpos( $pk, $k ) || 0 === strpos( $k, $pk ) ) && abs( strlen( $pk ) - strlen( $k ) ) <= 2 ) {
			return $url;
		}
	}
	return '';
}

/** Vergleichsschlüssel für Namen */
function sgnl_training_key( $s ) {
	return preg_replace( '/[^a-z0-9]/', '', remove_accents( mb_strtolower( wp_strip_all_tags( (string) $s ) ) ) );
}

/** "16:00" → "16:00" / Zeitspanne */
function sgnl_training_span( $s ) {
	return $s['start'] . ( $s['end'] ? '–' . $s['end'] : '' );
}

/* -------------------------------------------------------------------------
 * Block
 * ---------------------------------------------------------------------- */
add_action( 'init', function () {
	wp_register_style( 'sgnl-training', SGNL_CORE_URL . 'assets/training.css', array(), sgnl_core_ver( 'assets/training.css' ) );
	wp_register_script( 'sgnl-training', SGNL_CORE_URL . 'assets/training.js', array(), sgnl_core_ver( 'assets/training.js' ), array( 'in_footer' => true, 'strategy' => 'defer' ) );
	wp_register_script( 'sgnl-training-block', SGNL_CORE_URL . 'assets/training-block.js', array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render' ), sgnl_core_ver( 'assets/training-block.js' ), true );
	wp_localize_script( 'sgnl-training-block', 'sgnlTraining', array(
		'groups' => array_keys( sgnl_training_groups( sgnl_training_get() ) ),
		'admin'  => admin_url( 'edit.php?post_type=sgnl_event&page=sgnl-training' ),
	) );
	register_block_type( 'sgnl/training', array(
		'api_version'     => 3,
		'title'           => __( 'Trainingszeiten', 'sgnl-core' ),
		'description'     => __( 'Wochenplan der Trainingszeiten – nach Tagen oder nach Gruppen, mit Filter. Gepflegt unter Termine → Trainingszeiten.', 'sgnl-core' ),
		'category'        => 'widgets',
		'icon'            => 'clock',
		'keywords'        => array( 'training', 'trainingszeiten', 'wochenplan', 'mannschaft', 'zeiten' ),
		'editor_script'   => 'sgnl-training-block',
		'style'           => 'sgnl-training',
		'supports'        => array( 'html' => false, 'align' => array( 'wide', 'full' ) ),
		'attributes'      => array(
			'view'   => array( 'type' => 'string', 'default' => 'days' ),
			'group'  => array( 'type' => 'string', 'default' => '' ),
			'filter' => array( 'type' => 'boolean', 'default' => true ),
			'align'  => array( 'type' => 'string', 'default' => 'wide' ),
		),
		'render_callback' => 'sgnl_training_render',
	) );
} );

/**
 * Wochenplan ausgeben.
 *
 * @param array $a Attribute: view (days|groups), group (nur diese Gruppe), filter.
 * @return string
 */
function sgnl_training_render( $a = array() ) {
	wp_enqueue_style( 'sgnl-training' );
	$data   = sgnl_training_get();
	$groups = sgnl_training_groups( $data );
	$days   = sgnl_training_days();
	$view   = 'groups' === ( $a['view'] ?? '' ) ? 'groups' : 'days';
	$only   = trim( (string) ( $a['group'] ?? '' ) );
	$align  = $a['align'] ?? 'wide';
	$ses    = $data['sessions'];
	if ( '' !== $only ) {
		$ses    = array_values( array_filter( $ses, function ( $s ) use ( $only ) {
			return sgnl_training_key( $s['group'] ) === sgnl_training_key( $only ) || false !== mb_stripos( $s['group'], $only );
		} ) );
		$groups = array_intersect_key( $groups, array_flip( array_unique( wp_list_pluck( $ses, 'group' ) ) ) );
	}
	if ( ! $ses ) {
		return current_user_can( 'edit_sgnl_events' )
			? '<p class="sgnl-training__empty">' . esc_html__( 'Keine Trainingszeiten gefunden. Pflegen unter Termine → Trainingszeiten.', 'sgnl-core' ) . '</p>'
			: '';
	}
	$filter = ! empty( $a['filter'] ) && '' === $only && count( $groups ) > 1;
	if ( $filter ) {
		wp_enqueue_script( 'sgnl-training' );
	}
	$today = (int) wp_date( 'N' );
	$cls   = 'sgnl-training' . ( in_array( $align, array( 'wide', 'full' ), true ) ? ' align' . $align : '' ) . ( $only ? ' is-single' : '' );

	$h  = '<div class="' . esc_attr( $cls ) . '" data-view="' . esc_attr( $view ) . '">';
	// Kopf: gültig ab, Ansicht, Filter
	$h .= '<div class="sgnl-training__bar">';
	if ( $data['valid_from'] ) {
		$h .= '<span class="sgnl-training__valid"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>'
			. esc_html( sprintf( __( 'Gültig ab %s', 'sgnl-core' ), wp_date( 'j. F Y', strtotime( $data['valid_from'] . ' 12:00' ) ) ) ) . '</span>';
	}
	if ( $filter ) {
		$h .= '<div class="sgnl-training__views" role="tablist" aria-label="' . esc_attr__( 'Ansicht', 'sgnl-core' ) . '">'
			. '<button type="button" role="tab" data-view="days" aria-selected="' . ( 'days' === $view ? 'true' : 'false' ) . '">' . esc_html__( 'Nach Tagen', 'sgnl-core' ) . '</button>'
			. '<button type="button" role="tab" data-view="groups" aria-selected="' . ( 'groups' === $view ? 'true' : 'false' ) . '">' . esc_html__( 'Nach Gruppen', 'sgnl-core' ) . '</button></div>';
	}
	$h .= '</div>';
	if ( $filter ) {
		$h .= '<div class="sgnl-training__chips" role="group" aria-label="' . esc_attr__( 'Gruppe wählen', 'sgnl-core' ) . '">'
			. '<button type="button" class="sgnl-training__chip is-active" data-group="" aria-pressed="true">' . esc_html__( 'Alle Gruppen', 'sgnl-core' ) . '</button>';
		foreach ( $groups as $g => $col ) {
			$h .= '<button type="button" class="sgnl-training__chip" data-group="' . esc_attr( sgnl_training_key( $g ) ) . '" aria-pressed="false" style="--g:' . esc_attr( $col ) . '"><i aria-hidden="true"></i>' . esc_html( $g ) . '</button>';
		}
		$h .= '</div>';
	}

	$session_html = function ( $s, $with_group = true ) use ( $groups ) {
		$col  = $groups[ $s['group'] ] ?? '#1F44B8';
		$url  = $with_group ? sgnl_training_group_url( $s['group'] ) : '';
		$tr   = array_filter( explode( "\n", (string) $s['trainers'] ) );
		$o    = '<li class="sgnl-tr" data-group="' . esc_attr( sgnl_training_key( $s['group'] ) ) . '" style="--g:' . esc_attr( $col ) . '">';
		$o   .= '<div class="sgnl-tr__time"><strong>' . esc_html( $s['start'] ) . '</strong>' . ( $s['end'] ? '<span>' . esc_html( $s['end'] ) . '</span>' : '' ) . '</div>';
		$o   .= '<div class="sgnl-tr__body">';
		if ( $with_group ) {
			$o .= '<div class="sgnl-tr__group">' . ( $url ? '<a href="' . esc_url( $url ) . '">' . esc_html( $s['group'] ) . '</a>' : esc_html( $s['group'] ) ) . '</div>';
		}
		if ( $s['season'] ) {
			$o .= '<span class="sgnl-tr__season">' . esc_html( $s['season'] ) . '</span>';
		}
		if ( $s['place'] ) {
			$o .= '<div class="sgnl-tr__meta sgnl-tr__place"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0z"/><circle cx="12" cy="10" r="3"/></svg>' . esc_html( $s['place'] ) . '</div>';
		}
		if ( $tr ) {
			$o .= '<div class="sgnl-tr__meta sgnl-tr__trainers"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>' . esc_html( implode( ', ', $tr ) ) . '</div>';
		}
		return $o . '</div></li>';
	};

	// Ansicht: nach Tagen
	$h .= '<div class="sgnl-training__days"' . ( 'days' !== $view ? ' hidden' : '' ) . '>';
	foreach ( $days as $n => $label ) {
		$list = array_filter( $ses, function ( $s ) use ( $n ) {
			return (int) $s['day'] === $n;
		} );
		if ( ! $list ) {
			continue;
		}
		$h .= '<section class="sgnl-tday' . ( $n === $today ? ' is-today' : '' ) . '" aria-label="' . esc_attr( $label ) . '">'
			. '<h3 class="sgnl-tday__name">' . esc_html( $label ) . ( $n === $today ? '<span class="sgnl-tday__today">' . esc_html__( 'Heute', 'sgnl-core' ) . '</span>' : '' ) . '</h3><ul class="sgnl-tday__list">';
		foreach ( $list as $s ) {
			$h .= $session_html( $s, '' === $only );
		}
		$h .= '</ul></section>';
	}
	$h .= '</div>';

	// Ansicht: nach Gruppen
	$h .= '<div class="sgnl-training__groups"' . ( 'groups' !== $view ? ' hidden' : '' ) . '>';
	foreach ( $groups as $g => $col ) {
		$list = array_filter( $ses, function ( $s ) use ( $g ) {
			return $s['group'] === $g;
		} );
		$url      = sgnl_training_group_url( $g );
		$trainers = array();
		foreach ( $list as $s ) {
			$trainers = array_merge( $trainers, array_filter( explode( "\n", (string) $s['trainers'] ) ) );
		}
		$trainers = array_values( array_unique( $trainers ) );
		$h       .= '<section class="sgnl-tgroup" data-group="' . esc_attr( sgnl_training_key( $g ) ) . '" style="--g:' . esc_attr( $col ) . '">'
			. '<h3 class="sgnl-tgroup__name">' . ( $url ? '<a href="' . esc_url( $url ) . '">' . esc_html( $g ) . '</a>' : esc_html( $g ) ) . '</h3><ul class="sgnl-tgroup__list">';
		foreach ( $list as $s ) {
			$h .= '<li><span class="sgnl-tgroup__day">' . esc_html( mb_substr( $days[ (int) $s['day'] ], 0, 2 ) ) . '</span>'
				. '<span class="sgnl-tgroup__when"><strong>' . esc_html( sgnl_training_span( $s ) ) . ' ' . esc_html__( 'Uhr', 'sgnl-core' ) . '</strong>'
				. ( $s['season'] ? ' <span class="sgnl-tr__season">' . esc_html( $s['season'] ) . '</span>' : '' )
				. '<small>' . esc_html( $s['place'] ) . '</small></span></li>';
		}
		$h .= '</ul>';
		if ( $trainers ) {
			$h .= '<p class="sgnl-tgroup__trainers"><span>' . esc_html( _n( 'Trainer/in', 'Trainer/innen', count( $trainers ), 'sgnl-core' ) ) . '</span>' . esc_html( implode( ', ', $trainers ) ) . '</p>';
		}
		$h .= '</section>';
	}
	$h .= '</div>';

	if ( $data['note'] ) {
		$h .= '<p class="sgnl-training__note">' . nl2br( esc_html( $data['note'] ) ) . '</p>';
	}
	return $h . '</div>';
}

/* -------------------------------------------------------------------------
 * Backend: Termine → Trainingszeiten
 * ---------------------------------------------------------------------- */
add_action( 'admin_menu', function () {
	add_submenu_page( 'edit.php?post_type=sgnl_event', __( 'Trainingszeiten', 'sgnl-core' ), __( 'Trainingszeiten', 'sgnl-core' ), 'edit_sgnl_events', 'sgnl-training', 'sgnl_training_admin_page' );
} );

add_action( 'admin_post_sgnl_training_save', function () {
	if ( ! current_user_can( 'edit_sgnl_events' ) ) {
		wp_die( esc_html__( 'Keine Berechtigung.', 'sgnl-core' ), 403 );
	}
	check_admin_referer( 'sgnl_training_save' );
	$back = admin_url( 'edit.php?post_type=sgnl_event&page=sgnl-training' );
	if ( isset( $_POST['restore'] ) ) {
		$hist = (array) get_option( 'sgnl_training_history', array() );
		$i    = (int) $_POST['restore'];
		if ( isset( $hist[ $i ]['data'] ) ) {
			sgnl_training_store( $hist[ $i ]['data'] );
			wp_safe_redirect( add_query_arg( 'restored', 1, $back ) );
			exit;
		}
	}
	$raw = wp_unslash( $_POST['sgnl_training'] ?? array() ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	sgnl_training_store( sgnl_training_sanitize( is_array( $raw ) ? $raw : array() ) );
	wp_safe_redirect( add_query_arg( 'saved', 1, $back ) );
	exit;
} );

/**
 * Speichern und vorherigen Stand im Verlauf behalten (10 Stände).
 *
 * @param array $data Bereinigte Daten.
 */
function sgnl_training_store( $data ) {
	$old  = get_option( SGNL_TRAINING_OPTION, null );
	$hist = (array) get_option( 'sgnl_training_history', array() );
	if ( is_array( $old ) && $old !== $data ) {
		array_unshift( $hist, array( 'time' => time(), 'user' => wp_get_current_user()->display_name, 'data' => $old, 'count' => count( $old['sessions'] ?? array() ) ) );
		update_option( 'sgnl_training_history', array_slice( $hist, 0, 10 ), false );
	}
	update_option( SGNL_TRAINING_OPTION, $data, false );
}

/** Seite mit dem Block (für Link „Ansehen“) */
function sgnl_training_page_url() {
	global $wpdb;
	$id = (int) $wpdb->get_var( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'page' AND post_status = 'publish' AND post_content LIKE '%<!-- wp:sgnl/training%' ORDER BY ID ASC LIMIT 1" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	return $id ? get_permalink( $id ) : '';
}

/** Backend-Seite */
function sgnl_training_admin_page() {
	$data   = sgnl_training_get();
	$days   = sgnl_training_days();
	$groups = sgnl_training_groups( $data );
	$places = array_values( array_unique( array_filter( wp_list_pluck( $data['sessions'], 'place' ) ) ) );
	$hist   = (array) get_option( 'sgnl_training_history', array() );
	$url    = sgnl_training_page_url();
	wp_enqueue_style( 'sgnl-training-admin', SGNL_CORE_URL . 'assets/training-admin.css', array(), sgnl_core_ver( 'assets/training-admin.css' ) );
	wp_enqueue_script( 'sgnl-training-admin', SGNL_CORE_URL . 'assets/training-admin.js', array(), sgnl_core_ver( 'assets/training-admin.js' ), true );
	$row = function ( $i, $s ) use ( $days ) {
		ob_start();
		?>
		<tr class="sgnl-ta__row">
			<td class="sgnl-ta__drag" title="<?php esc_attr_e( 'Ziehen zum Verschieben', 'sgnl-core' ); ?>"><span class="dashicons dashicons-menu"></span></td>
			<td><select name="sgnl_training[sessions][<?php echo esc_attr( $i ); ?>][day]" aria-label="<?php esc_attr_e( 'Tag', 'sgnl-core' ); ?>">
				<?php foreach ( $days as $n => $l ) : ?>
					<option value="<?php echo (int) $n; ?>"<?php selected( (int) ( $s['day'] ?? 1 ), $n ); ?>><?php echo esc_html( $l ); ?></option>
				<?php endforeach; ?>
			</select></td>
			<td class="sgnl-ta__time"><input type="time" name="sgnl_training[sessions][<?php echo esc_attr( $i ); ?>][start]" value="<?php echo esc_attr( $s['start'] ?? '' ); ?>" aria-label="<?php esc_attr_e( 'Beginn', 'sgnl-core' ); ?>">
				<span>–</span><input type="time" name="sgnl_training[sessions][<?php echo esc_attr( $i ); ?>][end]" value="<?php echo esc_attr( $s['end'] ?? '' ); ?>" aria-label="<?php esc_attr_e( 'Ende', 'sgnl-core' ); ?>">
				<input type="text" class="sgnl-ta__season" name="sgnl_training[sessions][<?php echo esc_attr( $i ); ?>][season]" value="<?php echo esc_attr( $s['season'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Zusatz, z. B. Okt. – März', 'sgnl-core' ); ?>"></td>
			<td><input type="text" list="sgnl-ta-groups" name="sgnl_training[sessions][<?php echo esc_attr( $i ); ?>][group]" value="<?php echo esc_attr( $s['group'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'z. B. 1. Mannschaft', 'sgnl-core' ); ?>" aria-label="<?php esc_attr_e( 'Gruppe', 'sgnl-core' ); ?>"></td>
			<td><input type="text" list="sgnl-ta-places" name="sgnl_training[sessions][<?php echo esc_attr( $i ); ?>][place]" value="<?php echo esc_attr( $s['place'] ?? '' ); ?>" aria-label="<?php esc_attr_e( 'Ort', 'sgnl-core' ); ?>"></td>
			<td><textarea rows="2" name="sgnl_training[sessions][<?php echo esc_attr( $i ); ?>][trainers]" placeholder="<?php esc_attr_e( 'Ein Name pro Zeile', 'sgnl-core' ); ?>" aria-label="<?php esc_attr_e( 'Übungsleiter', 'sgnl-core' ); ?>"><?php echo esc_textarea( $s['trainers'] ?? '' ); ?></textarea></td>
			<td class="sgnl-ta__actions">
				<button type="button" class="button-link" data-act="copy" title="<?php esc_attr_e( 'Zeile duplizieren', 'sgnl-core' ); ?>"><span class="dashicons dashicons-admin-page"></span></button>
				<button type="button" class="button-link sgnl-ta__del" data-act="del" title="<?php esc_attr_e( 'Zeile löschen', 'sgnl-core' ); ?>"><span class="dashicons dashicons-trash"></span></button>
			</td>
		</tr>
		<?php
		return ob_get_clean();
	};
	?>
	<div class="wrap sgnl-ta">
		<h1 class="wp-heading-inline"><?php esc_html_e( 'Trainingszeiten', 'sgnl-core' ); ?></h1>
		<?php if ( $url ) : ?><a href="<?php echo esc_url( $url ); ?>" class="page-title-action" target="_blank"><?php esc_html_e( 'Auf der Website ansehen', 'sgnl-core' ); ?></a><?php endif; ?>
		<hr class="wp-header-end">
		<?php if ( isset( $_GET['saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Gespeichert – die Trainingszeiten sind auf der Website aktualisiert.', 'sgnl-core' ); ?></p></div>
		<?php elseif ( isset( $_GET['restored'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Früherer Stand wiederhergestellt.', 'sgnl-core' ); ?></p></div>
		<?php endif; ?>
		<p class="description"><?php esc_html_e( 'Jede Zeile ist eine Trainingseinheit. Die Reihenfolge innerhalb eines Tages ergibt sich aus der Uhrzeit. Leere Zeilen werden ignoriert.', 'sgnl-core' ); ?></p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="sgnl-ta-form">
			<input type="hidden" name="action" value="sgnl_training_save">
			<?php wp_nonce_field( 'sgnl_training_save' ); ?>

			<div class="sgnl-ta__head">
				<label><?php esc_html_e( 'Gültig ab', 'sgnl-core' ); ?>
					<input type="date" name="sgnl_training[valid_from]" value="<?php echo esc_attr( $data['valid_from'] ); ?>"></label>
				<label class="sgnl-ta__note"><?php esc_html_e( 'Hinweis unter dem Plan (optional)', 'sgnl-core' ); ?>
					<input type="text" name="sgnl_training[note]" value="<?php echo esc_attr( $data['note'] ); ?>" placeholder="<?php esc_attr_e( 'z. B. In den Schulferien findet kein Training statt.', 'sgnl-core' ); ?>"></label>
			</div>

			<table class="widefat sgnl-ta__table">
				<thead><tr>
					<th class="sgnl-ta__drag"></th>
					<th><?php esc_html_e( 'Tag', 'sgnl-core' ); ?></th>
					<th><?php esc_html_e( 'Zeit', 'sgnl-core' ); ?></th>
					<th><?php esc_html_e( 'Gruppe', 'sgnl-core' ); ?></th>
					<th><?php esc_html_e( 'Ort', 'sgnl-core' ); ?></th>
					<th><?php esc_html_e( 'Übungsleiter', 'sgnl-core' ); ?></th>
					<th></th>
				</tr></thead>
				<tbody id="sgnl-ta-rows">
					<?php foreach ( $data['sessions'] as $i => $s ) { echo $row( $i, $s ); } // phpcs:ignore WordPress.Security.EscapeOutput ?>
				</tbody>
			</table>
			<template id="sgnl-ta-template"><?php echo $row( '__i__', array( 'day' => 1, 'place' => $places[0] ?? '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></template>
			<datalist id="sgnl-ta-groups"><?php foreach ( array_keys( $groups ) as $g ) { echo '<option value="' . esc_attr( $g ) . '">'; } ?></datalist>
			<datalist id="sgnl-ta-places"><?php foreach ( $places as $p ) { echo '<option value="' . esc_attr( $p ) . '">'; } ?></datalist>
			<p><button type="button" class="button" id="sgnl-ta-add"><span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e( 'Trainingszeit hinzufügen', 'sgnl-core' ); ?></button></p>

			<h2><?php esc_html_e( 'Farben der Gruppen', 'sgnl-core' ); ?></h2>
			<div class="sgnl-ta__colors">
				<?php foreach ( $groups as $g => $col ) : ?>
					<label><input type="color" name="sgnl_training[groups][<?php echo esc_attr( $g ); ?>][color]" value="<?php echo esc_attr( $col ); ?>"> <?php echo esc_html( $g ); ?></label>
				<?php endforeach; ?>
			</div>
			<p class="description"><?php esc_html_e( 'Neue Gruppen bekommen automatisch eine Farbe; nach dem Speichern kannst du sie hier ändern.', 'sgnl-core' ); ?></p>

			<p class="submit"><button type="submit" class="button button-primary button-hero"><?php esc_html_e( 'Trainingszeiten speichern', 'sgnl-core' ); ?></button></p>
		</form>

		<?php if ( $hist ) : ?>
			<h2><?php esc_html_e( 'Frühere Stände', 'sgnl-core' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="sgnl-ta__history">
				<input type="hidden" name="action" value="sgnl_training_save">
				<?php wp_nonce_field( 'sgnl_training_save' ); ?>
				<ul>
					<?php foreach ( $hist as $i => $h ) : ?>
						<li><?php echo esc_html( sprintf( '%s · %s · %d Einheiten', wp_date( 'd.m.Y H:i', (int) $h['time'] ), $h['user'] ?: '–', (int) $h['count'] ) ); ?>
							<button type="submit" name="restore" value="<?php echo (int) $i; ?>" class="button button-small"><?php esc_html_e( 'Wiederherstellen', 'sgnl-core' ); ?></button></li>
					<?php endforeach; ?>
				</ul>
			</form>
		<?php endif; ?>
	</div>
	<?php
}

/* -------------------------------------------------------------------------
 * Seite "Trainingszeiten" einmalig anlegen (unter "Über uns", im Menü)
 * ---------------------------------------------------------------------- */

/**
 * Seite anlegen, falls es noch keine mit dem Block gibt.
 *
 * @return int Seiten-ID (0 = nichts angelegt)
 */
function sgnl_training_create_page() {
	if ( sgnl_training_page_url() ) {
		return 0;
	}
	$parent = get_page_by_path( 'ueber-uns' );
	$teams  = function_exists( 'sgnl_teams_page' ) ? sgnl_teams_page() : null;
	$id     = wp_insert_post( array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'Trainingszeiten',
		'post_name'    => 'trainingszeiten',
		'post_parent'  => $parent ? $parent->ID : 0,
		'menu_order'   => 5,
		'post_content' => "<!-- wp:paragraph -->\n<p>Wann und wo trainiert welche Gruppe? Wähle unten deine Gruppe aus oder schalte auf „Nach Gruppen“ um.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:sgnl/training {\"align\":\"wide\"} /-->\n",
	) );
	if ( ! $id || is_wp_error( $id ) ) {
		return 0;
	}
	if ( $teams ) {
		sgnl_events_add_menu_item( $id, $teams->ID, true );
	}
	return (int) $id;
}

add_action( 'admin_init', function () {
	if ( '1' === get_option( 'sgnl_training_page' ) || ! current_user_can( 'edit_pages' ) ) {
		return;
	}
	update_option( 'sgnl_training_page', '1' );
	if ( false === get_option( SGNL_TRAINING_OPTION, false ) ) {
		update_option( SGNL_TRAINING_OPTION, sgnl_training_defaults(), false );
	}
	$id = sgnl_training_create_page();
	if ( $id ) {
		set_transient( 'sgnl_training_notice', $id, DAY_IN_SECONDS );
	}
} );

add_action( 'admin_notices', function () {
	$id = (int) get_transient( 'sgnl_training_notice' );
	if ( ! $id || ! current_user_can( 'edit_pages' ) ) {
		return;
	}
	delete_transient( 'sgnl_training_notice' );
	printf(
		'<div class="notice notice-success is-dismissible"><p>%s <a href="%s" target="_blank">%s</a> · <a href="%s">%s</a></p></div>',
		esc_html__( 'Neue Seite „Trainingszeiten“ (Stand 19.10.2026) unter „Über uns“ angelegt.', 'sgnl-core' ),
		esc_url( get_permalink( $id ) ),
		esc_html__( 'Ansehen', 'sgnl-core' ),
		esc_url( admin_url( 'edit.php?post_type=sgnl_event&page=sgnl-training' ) ),
		esc_html__( 'Zeiten bearbeiten', 'sgnl-core' )
	);
} );
