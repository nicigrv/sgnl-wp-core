<?php
/**
 * Mini-Testhelfer (ohne PHPUnit/Composer). Läuft per `wp eval-file tests/run.php`.
 *
 * @package sgnl-core
 */

$GLOBALS['sgnl_t'] = array( 'pass' => 0, 'fail' => array(), 'section' => '' );

function t_section( $name ) {
	$GLOBALS['sgnl_t']['section'] = $name;
	echo "\n== {$name}\n";
}

function t_ok( $cond, $msg ) {
	if ( $cond ) {
		$GLOBALS['sgnl_t']['pass']++;
		echo "  ok   {$msg}\n";
	} else {
		$GLOBALS['sgnl_t']['fail'][] = $GLOBALS['sgnl_t']['section'] . ': ' . $msg;
		echo "  FAIL {$msg}\n";
	}
}

function t_eq( $actual, $expected, $msg ) {
	$ok = $actual === $expected;
	t_ok( $ok, $msg . ( $ok ? '' : ' – erwartet ' . var_export( $expected, true ) . ', erhalten ' . var_export( $actual, true ) ) );
}

function t_has( $haystack, $needle, $msg ) {
	t_ok( false !== strpos( (string) $haystack, (string) $needle ), $msg );
}

function t_not( $haystack, $needle, $msg ) {
	t_ok( false === strpos( (string) $haystack, (string) $needle ), $msg );
}

/** Termin anlegen */
function t_event( $title, array $fields, $cat = 'wettkampf' ) {
	$id = wp_insert_post( array( 'post_type' => 'sgnl_event', 'post_status' => 'publish', 'post_title' => $title ) );
	sgnl_event_save_fields( $id, $fields );
	if ( $cat ) {
		wp_set_object_terms( $id, $cat, 'sgnl_event_cat' );
	}
	return (int) $id;
}

/** Datum relativ zu heute (Zeitzone der Website) */
function t_day( $offset ) {
	return wp_date( 'Y-m-d', strtotime( ( $offset >= 0 ? '+' : '' ) . (int) $offset . ' days', current_time( 'timestamp', true ) ) );
}

/** Termine finden */
function t_find( $title ) {
	$p = get_posts( array( 'post_type' => 'sgnl_event', 'post_status' => 'any', 'title' => $title, 'numberposts' => 5 ) );
	return array_map( function ( $x ) {
		return $x->ID;
	}, $p );
}
