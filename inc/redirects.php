<?php
/**
 * Weiterleitungen (ersetzt "Redirect Redirection").
 * Verwaltung: Werkzeuge → Weiterleitungen.
 *
 * @package sgnl-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Weiterleitungen lesen.
 *
 * @return array<int, array{from:string,to:string,code:int}>
 */
function sgnl_get_redirects() {
	$r = get_option( 'sgnl_redirects', array() );
	return is_array( $r ) ? $r : array();
}

/**
 * Pfad normalisieren: klein, ohne Query, ohne Slash am Ende.
 *
 * @param string $path Pfad oder URL.
 * @return string
 */
function sgnl_normalize_path( $path ) {
	$p = (string) wp_parse_url( $path, PHP_URL_PATH );
	$p = '/' . trim( rawurldecode( $p ), '/' );
	return strtolower( $p );
}

add_action( 'parse_request', function () {
	if ( is_admin() || ! in_array( $_SERVER['REQUEST_METHOD'] ?? 'GET', array( 'GET', 'HEAD' ), true ) ) {
		return;
	}
	$redirects = sgnl_get_redirects();
	if ( ! $redirects ) {
		return;
	}
	$req = sgnl_normalize_path( $_SERVER['REQUEST_URI'] ?? '/' );
	// Unterverzeichnis-Installationen berücksichtigen.
	$base = sgnl_normalize_path( home_url( '/' ) );
	if ( '/' !== $base && 0 === strpos( $req, $base ) ) {
		$req = '/' . ltrim( substr( $req, strlen( $base ) ), '/' );
	}
	foreach ( $redirects as $r ) {
		if ( empty( $r['from'] ) || empty( $r['to'] ) ) {
			continue;
		}
		if ( sgnl_normalize_path( $r['from'] ) === $req ) {
			$to = $r['to'];
			if ( 0 === strpos( $to, '/' ) ) {
				$to = home_url( $to );
			}
			wp_redirect( $to, in_array( (int) $r['code'], array( 301, 302, 307, 308 ), true ) ? (int) $r['code'] : 301, 'SG Neptun' ); // phpcs:ignore WordPress.Security.SafeRedirect
			exit;
		}
	}
}, 1 );

add_action( 'admin_menu', function () {
	add_management_page(
		__( 'Weiterleitungen', 'sgnl-core' ),
		__( 'Weiterleitungen', 'sgnl-core' ),
		'manage_options',
		'sgnl-redirects',
		'sgnl_redirects_page'
	);
} );

/**
 * Admin-Seite.
 */
function sgnl_redirects_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$msg = '';
	if ( isset( $_POST['sgnl_redirects'] ) && check_admin_referer( 'sgnl_redirects' ) ) {
		$lines = preg_split( '/\r\n|\r|\n/', wp_unslash( $_POST['sgnl_redirects'] ) ); // phpcs:ignore
		$out   = array();
		foreach ( $lines as $line ) {
			$parts = preg_split( '/\s+/', trim( $line ) );
			if ( count( $parts ) < 2 || '' === $parts[0] || 0 === strpos( $parts[0], '#' ) ) {
				continue;
			}
			$out[] = array(
				'from' => sanitize_text_field( $parts[0] ),
				'to'   => esc_url_raw( $parts[1] ),
				'code' => isset( $parts[2] ) ? (int) $parts[2] : 301,
			);
		}
		update_option( 'sgnl_redirects', $out, true );
		$msg = sprintf( _n( '%d Weiterleitung gespeichert.', '%d Weiterleitungen gespeichert.', count( $out ), 'sgnl-core' ), count( $out ) );
	}
	$text = '';
	foreach ( sgnl_get_redirects() as $r ) {
		$text .= $r['from'] . '  ' . $r['to'] . ( 301 !== (int) $r['code'] ? '  ' . (int) $r['code'] : '' ) . "\n";
	}
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Weiterleitungen', 'sgnl-core' ); ?></h1>
		<?php if ( $msg ) : ?><div class="notice notice-success"><p><?php echo esc_html( $msg ); ?></p></div><?php endif; ?>
		<p><?php esc_html_e( 'Eine Weiterleitung pro Zeile: alter Pfad, Leerzeichen, Ziel. Optional dahinter der Code (301 dauerhaft, 302 vorübergehend). Groß-/Kleinschreibung, Schrägstrich am Ende und Parameter werden ignoriert.', 'sgnl-core' ); ?></p>
		<p><code>/kraulkurs  https://sgnl-portal.de/event.php?id=12  302</code></p>
		<form method="post">
			<?php wp_nonce_field( 'sgnl_redirects' ); ?>
			<textarea name="sgnl_redirects" rows="14" class="large-text code" spellcheck="false"><?php echo esc_textarea( $text ); ?></textarea>
			<?php submit_button( __( 'Speichern', 'sgnl-core' ) ); ?>
		</form>
	</div>
	<?php
}
