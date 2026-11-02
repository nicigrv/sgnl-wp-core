<?php
/**
 * Block "Eingebettete Seite (iFrame)" – ersetzt "Advanced iFrame".
 *
 * @package sgnl-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', function () {
	wp_register_script(
		'sgnl-iframe-editor',
		SGNL_CORE_URL . 'assets/iframe-block.js',
		array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n' ),
		sgnl_core_ver( 'assets/iframe-block.js' ),
		true
	);
	register_block_type( 'sgnl/iframe', array(
		'api_version'     => 3,
		'title'           => __( 'Eingebettete Seite (iFrame)', 'sgnl-core' ),
		'category'        => 'embed',
		'icon'            => 'welcome-view-site',
		'description'     => __( 'Bindet eine externe Seite ein, z. B. Rekorde, Spond oder Umfragen.', 'sgnl-core' ),
		'editor_script'   => 'sgnl-iframe-editor',
		'supports'        => array( 'align' => array( 'wide', 'full' ), 'html' => false ),
		'attributes'      => array(
			'src'    => array( 'type' => 'string', 'default' => '' ),
			'height' => array( 'type' => 'number', 'default' => 900 ),
			'title'  => array( 'type' => 'string', 'default' => '' ),
			'align'  => array( 'type' => 'string', 'default' => 'wide' ),
		),
		'render_callback' => 'sgnl_render_iframe_block',
	) );
} );

/**
 * Ausgabe im Frontend.
 *
 * @param array $a Attribute.
 * @return string
 */
function sgnl_render_iframe_block( $a ) {
	$src = isset( $a['src'] ) ? esc_url( $a['src'] ) : '';
	if ( ! $src ) {
		return '';
	}
	$height = max( 200, min( 5000, (int) ( $a['height'] ?? 900 ) ) );
	$host   = (string) wp_parse_url( $src, PHP_URL_HOST );
	$title  = $a['title'] ?? '';
	if ( '' === $title ) {
		$title = $host;
	}
	// Seiten von der eigenen Website (z. B. /vereinsdaten/…): Höhe passt sich dem Inhalt an.
	$fit = sgnl_is_own_host( $src ) && false !== strpos( $src, '/vereinsdaten/' );
	if ( $fit ) {
		wp_enqueue_script( 'sgnl-frame-fit' );
		$title = 'SG Neptun Lampertheim' === $title || '' === $title || $title === $host ? __( 'Vereinsdatenbank', 'sgnl-core' ) : $title;
	}
	$iframe = sprintf(
		'<iframe src="%1$s" title="%2$s" style="height:%3$dpx" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" allow="fullscreen; clipboard-write"%4$s></iframe>',
		$src,
		esc_attr( $title ),
		$height,
		$fit ? ' data-sgnl-fit scrolling="no"' : ''
	);
	$align = in_array( $a['align'] ?? '', array( 'wide', 'full' ), true ) ? ' align' . $a['align'] : '';
	return '<div class="sgnl-iframe' . esc_attr( $align ) . '">' . sgnl_consent_gate_iframe( $iframe, $src, $height ) . '</div>';
}
