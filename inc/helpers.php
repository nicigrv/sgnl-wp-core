<?php
/**
 * Hilfsfunktionen.
 *
 * @package sgnl-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ist ein Host "unser eigener" (sgnl.de und Subdomains)?
 *
 * @param string $url URL.
 * @return bool
 */
function sgnl_is_own_host( $url ) {
	$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
	if ( '' === $host ) {
		return true;
	}
	$own = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
	$own = preg_replace( '/^www\./', '', $own );
	$list = apply_filters( 'sgnl_own_hosts', array( $own ) );
	foreach ( $list as $o ) {
		if ( $host === $o || substr( $host, -strlen( '.' . $o ) ) === '.' . $o ) {
			return true;
		}
	}
	return false;
}

/**
 * Asset-Version anhand Dateizeit.
 *
 * @param string $rel Pfad relativ zum Plugin.
 * @return string
 */
function sgnl_core_ver( $rel ) {
	$f = SGNL_CORE_DIR . $rel;
	return file_exists( $f ) ? (string) filemtime( $f ) : SGNL_CORE_VERSION;
}
