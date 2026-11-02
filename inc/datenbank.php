<?php
/**
 * Datenbank (früher datenbank.sgnl.de) direkt auf der Website.
 *
 * Block "Datenbank" mit Reitern:
 * - Live      → eigene Live-Wettkämpfe (aus den Terminen)
 * - Termine   → eigener Terminplan (aus den Terminen)
 * - Ergebnisse, Bestenliste, Rekorde, Schwimmer, Meldungen → Ansichten der
 *   Vereinsdatenbank (sgnl.webclub.app), eingebettet – erst nach Zustimmung
 *   zu externen Inhalten (Cookie-Banner)
 *
 * Reiter sind per Link erreichbar: /datenbank/#live, /datenbank/#ergebnisse …
 *
 * @package sgnl-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const SGNL_DB_ORIGIN = 'https://sgnl.webclub.app';

/**
 * Verfügbare Reiter.
 *
 * @return array id => [label, kind, url]
 */
function sgnl_db_tabs() {
	return apply_filters( 'sgnl_db_tabs', array(
		'live'            => array( __( 'Live', 'sgnl-core' ), 'live', '' ),
		'termine'         => array( __( 'Termine', 'sgnl-core' ), 'events', '' ),
		'ergebnisse'      => array( __( 'Ergebnisse', 'sgnl-core' ), 'iframe', SGNL_DB_ORIGIN . '/oeffentlich-ergebnisse-ex.php' ),
		'bestenliste'     => array( __( 'Bestenliste', 'sgnl-core' ), 'iframe', SGNL_DB_ORIGIN . '/oeffentlich-bestenliste-ex.php' ),
		'rekorde'         => array( __( 'Rekorde', 'sgnl-core' ), 'iframe', SGNL_DB_ORIGIN . '/oeffentlich-rekorde-ex.php' ),
		'schwimmer'       => array( __( 'Schwimmer', 'sgnl-core' ), 'iframe', SGNL_DB_ORIGIN . '/oeffentlich-schwimmer-ex.php' ),
		'meldungen'       => array( __( 'Meldungen', 'sgnl-core' ), 'iframe', SGNL_DB_ORIGIN . '/oeffentlich-meldungen-ex.php' ),
	) );
}

add_action( 'init', function () {
	wp_register_style( 'sgnl-db', SGNL_CORE_URL . 'assets/datenbank.css', array( 'sgnl-live', 'sgnl-events' ), sgnl_core_ver( 'assets/datenbank.css' ) );
	wp_register_script( 'sgnl-db', SGNL_CORE_URL . 'assets/datenbank.js', array( 'sgnl-live' ), sgnl_core_ver( 'assets/datenbank.js' ), array(
		'in_footer' => true,
		'strategy'  => 'defer',
	) );
	wp_localize_script( 'sgnl-db', 'sgnlDb', array(
		'origin'  => SGNL_DB_ORIGIN,
		'privacy' => function_exists( 'sgnl_consent_settings' ) ? (string) ( sgnl_consent_settings()['privacy_url'] ?? '' ) : '',
		'gate'    => function_exists( 'sgnl_consent_settings' ) ? ! empty( sgnl_consent_settings()['enabled'] ) : false,
	) );
	wp_register_script(
		'sgnl-db-block',
		SGNL_CORE_URL . 'assets/datenbank-block.js',
		array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components' ),
		sgnl_core_ver( 'assets/datenbank-block.js' ),
		true
	);
	$labels = array();
	foreach ( sgnl_db_tabs() as $id => $t ) {
		$labels[] = array( 'id' => $id, 'label' => $t[0], 'kind' => $t[1] );
	}
	wp_localize_script( 'sgnl-db-block', 'sgnlDbBlock', array( 'tabs' => $labels ) );

	register_block_type( 'sgnl/datenbank', array(
		'api_version'     => 3,
		'title'           => __( 'Datenbank', 'sgnl-core' ),
		'description'     => __( 'Live, Termine, Ergebnisse, Bestenliste, Rekorde … als Reiter – wie früher datenbank.sgnl.de.', 'sgnl-core' ),
		'category'        => 'widgets',
		'icon'            => 'database',
		'keywords'        => array( 'datenbank', 'ergebnisse', 'bestenliste', 'rekorde', 'live', 'webclub' ),
		'editor_script'   => 'sgnl-db-block',
		'style'           => 'sgnl-db',
		'supports'        => array( 'align' => array( 'wide', 'full' ), 'html' => false ),
		'attributes'      => array(
			'tabs'  => array( 'type' => 'string', 'default' => 'live,termine,ergebnisse,bestenliste,rekorde' ),
			'align' => array( 'type' => 'string' ),
		),
		'render_callback' => 'sgnl_db_render',
	) );
} );

/**
 * Block ausgeben.
 *
 * @param array $a Attribute.
 * @return string
 */
function sgnl_db_render( $a ) {
	$all  = sgnl_db_tabs();
	$want = array_filter( array_map( 'trim', explode( ',', (string) ( $a['tabs'] ?? '' ) ) ) );
	$tabs = array();
	foreach ( $want as $id ) {
		if ( isset( $all[ $id ] ) ) {
			$tabs[ $id ] = $all[ $id ];
		}
	}
	if ( ! $tabs ) {
		return '';
	}
	wp_enqueue_style( 'sgnl-db' );
	wp_enqueue_script( 'sgnl-db' );

	$align = ! empty( $a['align'] ) ? $a['align'] : 'wide';
	$uid   = 'sgnl-db-' . wp_unique_id();
	$h     = '<div class="sgnl-db align' . esc_attr( $align ) . '" id="' . esc_attr( $uid ) . '">';

	// Reiter.
	$h .= '<div class="sgnl-db__tabs"><div class="sgnl-db__tablist" role="tablist" aria-label="' . esc_attr__( 'Datenbank', 'sgnl-core' ) . '">';
	$first = true;
	foreach ( $tabs as $id => $t ) {
		$h    .= '<button type="button" role="tab" class="sgnl-db__tab" id="' . esc_attr( $uid . '-tab-' . $id ) . '" data-tab="' . esc_attr( $id ) . '" aria-controls="' . esc_attr( $uid . '-' . $id ) . '" aria-selected="' . ( $first ? 'true' : 'false' ) . '"' . ( $first ? '' : ' tabindex="-1"' ) . '>' . esc_html( $t[0] ) . '</button>';
		$first = false;
	}
	$h .= '<span class="sgnl-db__rope" aria-hidden="true"></span></div></div>';

	// Rahmen mit Leiste.
	$h .= '<div class="sgnl-db__frame">';
	$h .= '<div class="sgnl-db__bar"><span class="sgnl-db__label" aria-live="polite"></span>'
		. '<button type="button" class="sgnl-db__act" data-act="share">' . sgnl_events_icon( 'ext' ) . '<span>' . esc_html__( 'Link teilen', 'sgnl-core' ) . '</span></button>'
		. '<button type="button" class="sgnl-db__act" data-act="reload"><svg class="sgnl-ico" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 11a8 8 0 1 0-2.3 5.7M20 4v7h-7"/></svg><span>' . esc_html__( 'Neu laden', 'sgnl-core' ) . '</span></button>'
		. '<a class="sgnl-db__act" data-act="open" href="#" target="_blank" rel="noopener" hidden><svg class="sgnl-ico" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 4h6v6M10 14 20 4M20 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1h5"/></svg><span>' . esc_html__( 'Neues Fenster', 'sgnl-core' ) . '</span></a>'
		. '<button type="button" class="sgnl-db__act" data-act="full"><svg class="sgnl-ico" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5"/></svg><span>' . esc_html__( 'Vollbild', 'sgnl-core' ) . '</span></button>'
		. '</div>';

	$first = true;
	foreach ( $tabs as $id => $t ) {
		$attrs = ' class="sgnl-db__panel sgnl-db__panel--' . esc_attr( $t[1] ) . '" id="' . esc_attr( $uid . '-' . $id ) . '" role="tabpanel" aria-labelledby="' . esc_attr( $uid . '-tab-' . $id ) . '" data-tab="' . esc_attr( $id ) . '" data-label="' . esc_attr( $t[0] ) . '"' . ( $first ? '' : ' hidden' );
		if ( 'iframe' === $t[1] ) {
			$host = wp_parse_url( $t[2], PHP_URL_HOST );
			$h   .= '<div' . $attrs . ' data-src="' . esc_url( $t[2] ) . '"><div class="sgnl-db__body">'
				. '<div class="sgnl-db__state" data-role="loading" hidden><div class="sgnl-db__lanes" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i></div><strong>' . esc_html( sprintf( /* translators: %s: Reiter */ __( '%s werden geladen', 'sgnl-core' ), $t[0] ) ) . '</strong></div>'
				. '<div class="sgnl-db__state" data-role="error" hidden><strong>' . esc_html__( 'Daten konnten nicht geladen werden', 'sgnl-core' ) . '</strong><p>' . esc_html__( 'Die Verbindung zur Vereinsdatenbank hat zu lange gedauert. Prüf deine Internetverbindung und versuch es noch einmal.', 'sgnl-core' ) . '</p><button type="button" class="sgnl-db__act" data-act="retry">' . esc_html__( 'Erneut versuchen', 'sgnl-core' ) . '</button></div>'
				. '<div class="sgnl-db__state sgnl-db__gate" data-role="gate" hidden><strong>' . esc_html__( 'Externer Inhalt', 'sgnl-core' ) . '</strong><p>'
				/* translators: %s: Domain */
				. sprintf( esc_html__( 'Die Daten kommen aus der Vereinsdatenbank auf %s. Beim Laden werden Daten wie deine IP-Adresse an diesen Anbieter übertragen.', 'sgnl-core' ), '<strong>' . esc_html( $host ) . '</strong>' )
				. '</p><p class="sgnl-db__gate-actions"><button type="button" class="sgnl-btn sgnl-btn--primary" data-act="load-once">' . esc_html__( 'Inhalt laden', 'sgnl-core' ) . '</button> <button type="button" class="sgnl-btn sgnl-btn--ghost" data-sgnl-allow="ext">' . esc_html__( 'Externe Inhalte immer erlauben', 'sgnl-core' ) . '</button></p></div>'
				. '</div></div>';
		} elseif ( 'live' === $t[1] ) {
			$h .= '<div' . $attrs . '><div class="sgnl-db__flow">' . sgnl_live_render() . '</div></div>';
		} else {
			$h .= '<div' . $attrs . '><div class="sgnl-db__flow">' . sgnl_events_render( array( 'align' => 'none' ) ) . '</div></div>';
		}
		$first = false;
	}
	$h .= '</div></div>';
	return $h;
}

/* =========================================================================
 * Alte Subdomain datenbank.<domain> → /datenbank/
 * Zeigt die Subdomain (z. B. bei IONOS) auf denselben Ordner wie die Website,
 * leitet WordPress alle alten Adressen hierher um. Der Reiter (#live …)
 * bleibt dabei erhalten – Browser übernehmen ihn bei Weiterleitungen.
 * ====================================================================== */
add_action( 'init', function () {
	$host = strtolower( preg_replace( '/:\d+$/', '', (string) ( $_SERVER['HTTP_HOST'] ?? '' ) ) );
	$own  = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
	if ( '' === $host || $host === $own || 0 !== strpos( $host, 'datenbank.' ) ) {
		return;
	}
	$path = strtolower( (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH ) );
	$db   = get_page_by_path( 'datenbank' );
	$base = $db ? get_permalink( $db ) : home_url( '/datenbank/' );
	if ( preg_match( '#^/trainer(\.html)?/?$#', $path ) ) {
		$tr  = get_page_by_path( 'datenbank/trainer' );
		$to  = $tr ? get_permalink( $tr ) : $base;
	} elseif ( 0 === strpos( $path, '/admin' ) ) {
		$to = admin_url( 'edit.php?post_type=sgnl_event' );
	} elseif ( 0 === strpos( $path, '/wettkaempfe.php' ) ) {
		$to = rest_url( 'sgnl/v1/live' );
	} else {
		$to = $base;
	}
	nocache_headers();
	wp_redirect( $to, 301, 'SG Neptun' ); // phpcs:ignore WordPress.Security.SafeRedirect
	exit;
}, 0 );
