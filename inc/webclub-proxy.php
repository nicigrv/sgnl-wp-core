<?php
/**
 * Vereinsdatenbank (webclub) über die eigene Website ausliefern.
 *
 * Die öffentlichen webclub-Ansichten (Ergebnisse, Bestenliste, Rekorde, Schwimmer,
 * Meldungen) werden unter /vereinsdaten/… von sgnl.de abgerufen und durchgereicht:
 * - Besucher kommen nie mit der webclub-Domain in Berührung (kein Link, keine Anfrage)
 * - gleiche Herkunft → der Rahmen passt seine Höhe automatisch an, kein Scrollen im Rahmen
 * - keine Zustimmung zu externen Inhalten nötig (der Browser lädt nichts von webclub)
 *
 * Nur die öffentlichen Seiten und deren Datenabfragen werden weitergereicht (Freigabeliste).
 *
 * @package sgnl-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const SGNL_WC_HOST   = 'https://sgnl.webclub.app';
const SGNL_WC_CDN    = 'https://cdn.webclub.app';
const SGNL_WC_PREFIX = 'vereinsdaten';

/**
 * Öffentliche Adresse einer webclub-Seite über die eigene Website.
 *
 * @param string $url webclub-Adresse oder Pfad (z. B. "oeffentlich-ergebnisse-ex.php").
 * @return string
 */
function sgnl_wc_url( $url ) {
	$path = preg_replace( '#^https?://sgnl\.webclub\.app/?#i', '', (string) $url );
	return home_url( '/' . SGNL_WC_PREFIX . '/' . ltrim( $path, '/' ) );
}

/**
 * Ist das eine webclub-Adresse?
 *
 * @param string $url Adresse.
 * @return bool
 */
function sgnl_wc_is_webclub( $url ) {
	return (bool) preg_match( '#^https?://sgnl\.webclub\.app(/|$)#i', (string) $url );
}

/**
 * Anfrage-Pfad → Ziel bei webclub (oder null).
 *
 * @param string $path Pfad ohne Unterverzeichnis der Website.
 * @return array|null [ url, static ]
 */
function sgnl_wc_target( $path ) {
	$pages = '(oeffentlich-(?:ergebnisse|bestenliste|rekorde|schwimmer|meldungen|termine)-ex\.php)';
	$p     = SGNL_WC_PREFIX;
	if ( preg_match( '#^/' . $p . '/cdn/([A-Za-z0-9._/-]+)$#', $path, $m ) && false === strpos( $m[1], '..' ) ) {
		return array( SGNL_WC_CDN . '/' . $m[1], true );
	}
	if ( preg_match( '#^/(?:' . $p . '/)?' . $pages . '$#', $path, $m ) ) {
		return array( SGNL_WC_HOST . '/' . $m[1], false );
	}
	if ( preg_match( '#^/(?:' . $p . '/)?(ajax\.php)$#', $path, $m ) ) {
		return array( SGNL_WC_HOST . '/' . $m[1], false );
	}
	if ( preg_match( '#^/(?:' . $p . '/)?((?:include|styles)/[A-Za-z0-9._/-]+\.(?:css|js|png|gif|jpg|svg|woff2?|ttf|eot))$#', $path, $m ) && false === strpos( $m[1], '..' ) ) {
		return array( SGNL_WC_HOST . '/' . $m[1], true );
	}
	return null;
}

/**
 * Text-Antworten: webclub-Adressen auf die eigene Website umschreiben.
 *
 * @param string $body Inhalt.
 * @param string $type Content-Type.
 * @return string
 */
function sgnl_wc_rewrite( $body, $type ) {
	$base = untrailingslashit( home_url( '/' . SGNL_WC_PREFIX ) );
	$body = str_ireplace(
		array( 'https://cdn.webclub.app', 'http://cdn.webclub.app', '//cdn.webclub.app', 'https://sgnl.webclub.app', 'http://sgnl.webclub.app' ),
		array( $base . '/cdn', $base . '/cdn', $base . '/cdn', $base, $base ),
		$body
	);
	if ( false !== stripos( $type, 'html' ) && false !== stripos( $body, '</head>' ) ) {
		// Anmeldung/Wartung ausblenden, Seite an die Vereinsseite angleichen.
		$css  = '<style id="sgnl-wc">html,body{background:#fff!important}body{padding:0!important;margin:0!important}'
			. 'section.container{width:auto!important;max-width:none!important;padding:16px 20px 24px!important}'
			. '#dlg_login,#butLOGIN,#butPWDRESET,nav,.navbar,footer,.footer{display:none!important}'
			. 'h1{font-size:1.5rem!important;margin-top:.2rem!important;color:#0B2A5B}h2{font-size:1.15rem!important;color:#0B2A5B}'
			. '</style>';
		$body = preg_replace( '#</head>#i', $css . '</head>', $body, 1 );
	}
	return $body;
}

/**
 * Cache-Ordner für statische Dateien.
 *
 * @return string
 */
function sgnl_wc_cache_dir() {
	$up = wp_upload_dir( null, false );
	return trailingslashit( $up['basedir'] ) . 'sgnl-vereinsdaten-cache';
}

add_action( 'init', 'sgnl_wc_maybe_proxy', 1 );

/**
 * Anfragen an /vereinsdaten/… (und die von dort ausgelösten Pfade) weiterreichen.
 */
function sgnl_wc_maybe_proxy() {
	if ( ! apply_filters( 'sgnl_wc_proxy', true ) || is_admin() || wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
		return;
	}
	$path = (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH );
	$home = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
	if ( '/' !== $home && 0 === strpos( $path, rtrim( $home, '/' ) ) ) {
		$path = substr( $path, strlen( rtrim( $home, '/' ) ) );
	}
	$target = sgnl_wc_target( $path );
	if ( ! $target ) {
		return;
	}
	list( $url, $static ) = $target;
	$method = strtoupper( $_SERVER['REQUEST_METHOD'] ?? 'GET' );
	if ( ! in_array( $method, array( 'GET', 'HEAD', 'POST' ), true ) || ( $static && 'POST' === $method ) ) {
		status_header( 405 );
		exit;
	}
	if ( ! empty( $_SERVER['QUERY_STRING'] ) ) {
		$url .= '?' . $_SERVER['QUERY_STRING']; // phpcs:ignore
	}

	// Datenabfragen: nur öffentliche Funktionen.
	$body = null;
	if ( 'POST' === $method ) {
		$func    = (string) wp_unslash( $_POST['func'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
		$allowed = apply_filters( 'sgnl_wc_allowed_funcs', '#^(oeff/[a-z0-9_]+|std/userdata)$#i' );
		if ( 0 !== strpos( $url, SGNL_WC_HOST . '/ajax.php' ) || ! preg_match( $allowed, $func ) ) {
			status_header( 403 );
			header( 'Content-Type: application/json' );
			echo '{"error":true,"rtc":-2}';
			exit;
		}
		$body = file_get_contents( 'php://input' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}

	// Statische Dateien zwischenspeichern (7 Tage).
	$cache = '';
	if ( $static ) {
		$cache = sgnl_wc_cache_dir() . '/' . md5( $url );
		if ( is_readable( $cache ) && is_readable( $cache . '.type' ) && filemtime( $cache ) > time() - WEEK_IN_SECONDS ) {
			header( 'Content-Type: ' . trim( (string) file_get_contents( $cache . '.type' ) ) ); // phpcs:ignore
			header( 'Cache-Control: public, max-age=604800' );
			header( 'X-Robots-Tag: noindex' );
			readfile( $cache ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			exit;
		}
	}

	// Cookies von webclub (z. B. Sitzung) durchreichen – keine WordPress-Cookies.
	$cookies = array();
	foreach ( $_COOKIE as $k => $v ) {
		if ( preg_match( '/^(wordpress|wp-|wp_|comment_|sgnl_)/i', $k ) || ! is_string( $v ) ) {
			continue;
		}
		$cookies[] = $k . '=' . rawurlencode( wp_unslash( $v ) );
	}
	$headers = array(
		'User-Agent'      => 'Mozilla/5.0 (compatible; SG-Neptun-Website)',
		'Accept'          => $_SERVER['HTTP_ACCEPT'] ?? '*/*', // phpcs:ignore
		'Accept-Language' => 'de-DE,de;q=0.9',
	);
	if ( $cookies ) {
		$headers['Cookie'] = implode( '; ', $cookies );
	}
	if ( null !== $body ) {
		$headers['Content-Type']     = $_SERVER['CONTENT_TYPE'] ?? 'application/x-www-form-urlencoded'; // phpcs:ignore
		$headers['X-Requested-With'] = 'XMLHttpRequest';
	}

	$res = wp_remote_request( $url, array(
		'method'      => 'HEAD' === $method ? 'GET' : $method,
		'headers'     => $headers,
		'body'        => $body,
		'timeout'     => 25,
		'redirection' => 0,
	) );
	if ( is_wp_error( $res ) ) {
		status_header( 502 );
		header( 'Content-Type: text/html; charset=utf-8' );
		echo '<!doctype html><meta charset="utf-8"><p style="font-family:sans-serif;padding:2rem;color:#0B2A5B">Die Vereinsdatenbank ist gerade nicht erreichbar. Bitte später erneut versuchen.</p>';
		exit;
	}

	$code = (int) wp_remote_retrieve_response_code( $res );
	$type = (string) wp_remote_retrieve_header( $res, 'content-type' );
	$out  = (string) wp_remote_retrieve_body( $res );
	$text = (bool) preg_match( '#text/|javascript|json|xml#i', $type );
	if ( $text ) {
		$out = sgnl_wc_rewrite( $out, $type );
	}

	status_header( $code );
	if ( $type ) {
		header( 'Content-Type: ' . $type );
	}
	header( 'X-Robots-Tag: noindex' );
	$loc = wp_remote_retrieve_header( $res, 'location' );
	if ( $loc ) {
		header( 'Location: ' . ( sgnl_wc_is_webclub( $loc ) ? sgnl_wc_url( $loc ) : $loc ) );
	}
	foreach ( (array) wp_remote_retrieve_header( $res, 'set-cookie' ) as $sc ) {
		if ( ! $sc ) {
			continue;
		}
		$sc = preg_replace( '/;\s*domain=[^;]*/i', '', $sc );
		$sc = preg_replace( '/;\s*path=[^;]*/i', '', $sc ) . '; Path=/';
		header( 'Set-Cookie: ' . $sc, false );
	}
	if ( $static && 200 === $code ) {
		header( 'Cache-Control: public, max-age=604800' );
		wp_mkdir_p( sgnl_wc_cache_dir() );
		@file_put_contents( $cache, $out ); // phpcs:ignore
		@file_put_contents( $cache . '.type', $type ); // phpcs:ignore
	} else {
		nocache_headers();
	}
	if ( 'HEAD' !== $method ) {
		echo $out; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	exit;
}

/* Eingebettete webclub-Seiten (Block "Eingebettete Seite") ebenfalls über die eigene Website */
add_filter( 'render_block_data', function ( $block ) {
	if ( 'sgnl/iframe' === ( $block['blockName'] ?? '' ) && sgnl_wc_is_webclub( $block['attrs']['src'] ?? '' ) && apply_filters( 'sgnl_wc_proxy', true ) ) {
		$block['attrs']['src']      = sgnl_wc_url( $block['attrs']['src'] );
		$block['attrs']['sgnl_fit'] = true;
	}
	return $block;
} );
