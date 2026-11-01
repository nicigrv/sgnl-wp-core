<?php
/**
 * Eigener Cookie-Banner (ersetzt CookieYes).
 *
 * Kategorien:
 *  - notwendig: immer an (Login, Banner-Entscheidung selbst)
 *  - ext:       Externe Inhalte & Chat (eingebettete Seiten anderer Anbieter, Chatwoot)
 *
 * Externe iFrames werden automatisch durch einen Platzhalter ersetzt und erst nach
 * Zustimmung geladen. Der Chat (Plugin greylo-chatwoot) startet erst nach Zustimmung.
 *
 * @package sgnl-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Banner-Einstellungen.
 *
 * @return array
 */
function sgnl_consent_settings() {
	$imprint = get_page_by_path( 'impressum' );
	$privacy = get_privacy_policy_url();
	if ( ! $privacy ) {
		foreach ( array( 'datenschutzerklaerung', 'datenschutz' ) as $slug ) {
			$pp = get_page_by_path( $slug );
			if ( $pp && 'publish' === $pp->post_status ) {
				$privacy = get_permalink( $pp );
				break;
			}
		}
	}
	$d       = array(
		'enabled'     => 1,
		'title'       => __( 'Wir schätzen deine Privatsphäre', 'sgnl-core' ),
		'text'        => __( 'Wir verwenden nur technisch notwendige Cookies. Externe Inhalte (z. B. eingebettete Kalender, Spond, Rekorde) und unseren Chat laden wir erst, wenn du zustimmst. Deine Wahl kannst du jederzeit unten auf der Seite unter „Cookie-Einstellungen“ ändern.', 'sgnl-core' ),
		'ext_label'   => __( 'Externe Inhalte & Chat', 'sgnl-core' ),
		'ext_text'    => __( 'Eingebettete Seiten anderer Anbieter und unser Live-Chat. Dabei werden Daten wie deine IP-Adresse an den jeweiligen Anbieter übertragen.', 'sgnl-core' ),
		'privacy_url' => $privacy,
		'imprint_url' => $imprint ? get_permalink( $imprint ) : '',
	);
	$o = get_option( 'sgnl_consent', array() );
	return wp_parse_args( is_array( $o ) ? $o : array(), $d );
}

/**
 * Hat der Besucher (laut Cookie) zugestimmt? Nur für Ausnahmefälle –
 * die Ausgabe arbeitet sonst cache-sicher über JavaScript.
 *
 * @param string $cat Kategorie.
 * @return bool
 */
function sgnl_has_consent( $cat = 'ext' ) {
	$c = isset( $_COOKIE['sgnl_consent'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['sgnl_consent'] ) ) : '';
	return in_array( $cat, explode( '.', $c ), true );
}

/**
 * Externes iFrame durch Platzhalter ersetzen.
 *
 * @param string $iframe  iFrame-HTML.
 * @param string $src     Quelle.
 * @param int    $height  Höhe (px) für den Platzhalter.
 * @return string
 */
function sgnl_consent_gate_iframe( $iframe, $src, $height = 0 ) {
	$s = sgnl_consent_settings();
	if ( empty( $s['enabled'] ) || sgnl_is_own_host( $src ) ) {
		return $iframe;
	}
	$host = preg_replace( '/^www\./', '', (string) wp_parse_url( $src, PHP_URL_HOST ) );
	$min  = $height ? min( (int) $height, 520 ) : 320;
	ob_start();
	?>
	<div class="sgnl-gate" data-sgnl-consent="ext" style="min-height:<?php echo (int) $min; ?>px">
		<template><?php echo $iframe; // phpcs:ignore ?></template>
		<div class="sgnl-gate__box">
			<p class="sgnl-gate__title"><?php esc_html_e( 'Externer Inhalt', 'sgnl-core' ); ?></p>
			<p class="sgnl-gate__text">
				<?php
				/* translators: %s: Anbieter-Domain */
				printf( esc_html__( 'Hier wird ein Inhalt von %s angezeigt. Beim Laden werden Daten wie deine IP-Adresse an diesen Anbieter übertragen.', 'sgnl-core' ), '<strong>' . esc_html( $host ) . '</strong>' );
				?>
			</p>
			<p class="sgnl-gate__actions">
				<button type="button" class="sgnl-btn sgnl-btn--primary" data-sgnl-load><?php esc_html_e( 'Inhalt laden', 'sgnl-core' ); ?></button>
				<button type="button" class="sgnl-btn sgnl-btn--ghost" data-sgnl-allow="ext"><?php esc_html_e( 'Externe Inhalte immer erlauben', 'sgnl-core' ); ?></button>
			</p>
			<?php if ( $s['privacy_url'] ) : ?>
				<a class="sgnl-gate__link" href="<?php echo esc_url( $s['privacy_url'] ); ?>"><?php esc_html_e( 'Mehr in der Datenschutzerklärung', 'sgnl-core' ); ?></a>
			<?php endif; ?>
		</div>
	</div>
	<?php
	return trim( ob_get_clean() );
}

/**
 * Alle externen iFrames in einem HTML-Stück absichern.
 *
 * @param string $html HTML.
 * @return string
 */
function sgnl_consent_gate_html( $html ) {
	if ( false === stripos( $html, '<iframe' ) || false !== strpos( $html, 'sgnl-gate' ) ) {
		return $html;
	}
	return preg_replace_callback( '#<iframe\b[^>]*>.*?</iframe>#is', function ( $m ) {
		if ( ! preg_match( '#\ssrc=("|\')(.*?)\1#i', $m[0], $src ) ) {
			return $m[0];
		}
		$height = preg_match( '#height=("|\')?(\d+)#i', $m[0], $hm ) ? (int) $hm[2] : 0;
		return sgnl_consent_gate_iframe( $m[0], html_entity_decode( $src[2] ), $height );
	}, $html );
}

add_filter( 'render_block', function ( $html, $block ) {
	if ( is_admin() || 'sgnl/iframe' === ( $block['blockName'] ?? '' ) ) {
		return $html;
	}
	return sgnl_consent_gate_html( $html );
}, 20, 2 );

add_filter( 'the_content', function ( $html ) {
	return is_admin() ? $html : sgnl_consent_gate_html( $html );
}, 999 );

add_filter( 'widget_text_content', 'sgnl_consent_gate_html', 999 );
add_filter( 'widget_block_content', 'sgnl_consent_gate_html', 999 );

/* Chat (greylo-chatwoot) erst nach Zustimmung starten */
add_action( 'wp', function () {
	if ( ! function_exists( 'greylo_cw_render_widget' ) || is_admin() ) {
		return;
	}
	$s = sgnl_consent_settings();
	if ( empty( $s['enabled'] ) ) {
		return;
	}
	if ( false !== has_action( 'wp_footer', 'greylo_cw_render_widget' ) ) {
		remove_action( 'wp_footer', 'greylo_cw_render_widget', 100 );
		add_action( 'wp_footer', function () {
			ob_start();
			greylo_cw_render_widget();
			$html = ob_get_clean();
			echo str_replace( '<script id="greylo-chatwoot">', '<script type="text/plain" data-sgnl-consent="ext" id="greylo-chatwoot">', $html ); // phpcs:ignore
		}, 100 );
	}
} );

/* Frühe Markierung: Banner noch offen? (verhindert Flackern) */
add_action( 'wp_head', function () {
	$s = sgnl_consent_settings();
	if ( empty( $s['enabled'] ) ) {
		return;
	}
	echo "<script>(function(){if(!/(^|;\\s*)sgnl_consent=/.test(document.cookie)){document.documentElement.classList.add('sgnl-consent-pending');}})();</script>\n";
}, 2 );

add_action( 'wp_enqueue_scripts', function () {
	$s = sgnl_consent_settings();
	if ( empty( $s['enabled'] ) ) {
		return;
	}
	wp_enqueue_style( 'sgnl-consent', SGNL_CORE_URL . 'assets/consent.css', array(), sgnl_core_ver( 'assets/consent.css' ) );
	wp_enqueue_script( 'sgnl-consent', SGNL_CORE_URL . 'assets/consent.js', array(), sgnl_core_ver( 'assets/consent.js' ), array( 'in_footer' => true ) );
} );

add_action( 'wp_footer', function () {
	$s = sgnl_consent_settings();
	if ( empty( $s['enabled'] ) ) {
		return;
	}
	?>
	<div class="sgnl-consent" id="sgnl-consent" role="dialog" aria-modal="false" aria-labelledby="sgnl-consent-title" aria-describedby="sgnl-consent-text" hidden>
		<p class="sgnl-consent__title" id="sgnl-consent-title"><?php echo esc_html( $s['title'] ); ?></p>
		<p class="sgnl-consent__text" id="sgnl-consent-text"><?php echo esc_html( $s['text'] ); ?></p>
		<div class="sgnl-consent__details" hidden>
			<label class="sgnl-consent__opt">
				<input type="checkbox" checked disabled>
				<span><strong><?php esc_html_e( 'Notwendig', 'sgnl-core' ); ?></strong><br><?php esc_html_e( 'Speichert deine Auswahl und hält Anmeldungen aufrecht. Immer aktiv.', 'sgnl-core' ); ?></span>
			</label>
			<label class="sgnl-consent__opt">
				<input type="checkbox" name="sgnl-ext" value="ext">
				<span><strong><?php echo esc_html( $s['ext_label'] ); ?></strong><br><?php echo esc_html( $s['ext_text'] ); ?></span>
			</label>
		</div>
		<div class="sgnl-consent__actions">
			<button type="button" class="sgnl-btn sgnl-btn--primary" data-sgnl-choice="all"><?php esc_html_e( 'Alle akzeptieren', 'sgnl-core' ); ?></button>
			<button type="button" class="sgnl-btn sgnl-btn--secondary" data-sgnl-choice="none"><?php esc_html_e( 'Nur notwendige', 'sgnl-core' ); ?></button>
			<button type="button" class="sgnl-btn sgnl-btn--text" data-sgnl-details><?php esc_html_e( 'Einstellungen', 'sgnl-core' ); ?></button>
			<button type="button" class="sgnl-btn sgnl-btn--secondary" data-sgnl-choice="save" hidden><?php esc_html_e( 'Auswahl speichern', 'sgnl-core' ); ?></button>
		</div>
		<p class="sgnl-consent__links">
			<?php if ( $s['privacy_url'] ) : ?><a href="<?php echo esc_url( $s['privacy_url'] ); ?>"><?php esc_html_e( 'Datenschutz', 'sgnl-core' ); ?></a><?php endif; ?>
			<?php if ( $s['imprint_url'] ) : ?><a href="<?php echo esc_url( $s['imprint_url'] ); ?>"><?php esc_html_e( 'Impressum', 'sgnl-core' ); ?></a><?php endif; ?>
		</p>
	</div>
	<?php
}, 5 );

/* Link "Cookie-Einstellungen" im Footer des Themes und als Shortcode */
function sgnl_consent_settings_button() {
	return '<button type="button" class="sgnl-consent-open" data-sgnl-open>' . esc_html__( 'Cookie-Einstellungen', 'sgnl-core' ) . '</button>';
}
add_action( 'sgnl_footer_bottom', function () {
	$s = sgnl_consent_settings();
	if ( ! empty( $s['enabled'] ) ) {
		echo sgnl_consent_settings_button(); // phpcs:ignore
	}
} );
add_shortcode( 'sgnl_cookie_einstellungen', 'sgnl_consent_settings_button' );

/* Einstellungen → Cookie-Banner */
add_action( 'admin_menu', function () {
	add_options_page( __( 'Cookie-Banner', 'sgnl-core' ), __( 'Cookie-Banner', 'sgnl-core' ), 'manage_options', 'sgnl-consent', 'sgnl_consent_admin_page' );
} );

/**
 * Admin-Seite.
 */
function sgnl_consent_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( isset( $_POST['sgnl_consent_save'] ) && check_admin_referer( 'sgnl_consent' ) ) {
		$in = wp_unslash( $_POST ); // phpcs:ignore
		update_option( 'sgnl_consent', array(
			'enabled'     => empty( $in['enabled'] ) ? 0 : 1,
			'title'       => sanitize_text_field( $in['title'] ?? '' ),
			'text'        => sanitize_textarea_field( $in['text'] ?? '' ),
			'ext_label'   => sanitize_text_field( $in['ext_label'] ?? '' ),
			'ext_text'    => sanitize_textarea_field( $in['ext_text'] ?? '' ),
			'privacy_url' => esc_url_raw( $in['privacy_url'] ?? '' ),
			'imprint_url' => esc_url_raw( $in['imprint_url'] ?? '' ),
		) );
		echo '<div class="notice notice-success"><p>' . esc_html__( 'Gespeichert.', 'sgnl-core' ) . '</p></div>';
	}
	$s = sgnl_consent_settings();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Cookie-Banner', 'sgnl-core' ); ?></h1>
		<p><?php esc_html_e( 'Externe Inhalte (iFrames anderer Anbieter) und der Chat werden erst nach Zustimmung geladen. Inhalte von sgnl.de und Subdomains gelten als eigene Inhalte.', 'sgnl-core' ); ?></p>
		<form method="post">
			<?php wp_nonce_field( 'sgnl_consent' ); ?>
			<table class="form-table" role="presentation">
				<tr><th><?php esc_html_e( 'Banner aktiv', 'sgnl-core' ); ?></th><td><label><input type="checkbox" name="enabled" value="1" <?php checked( ! empty( $s['enabled'] ) ); ?>> <?php esc_html_e( 'Banner anzeigen und externe Inhalte blockieren', 'sgnl-core' ); ?></label></td></tr>
				<tr><th><label for="t"><?php esc_html_e( 'Überschrift', 'sgnl-core' ); ?></label></th><td><input id="t" class="regular-text" name="title" value="<?php echo esc_attr( $s['title'] ); ?>"></td></tr>
				<tr><th><label for="x"><?php esc_html_e( 'Text', 'sgnl-core' ); ?></label></th><td><textarea id="x" class="large-text" rows="4" name="text"><?php echo esc_textarea( $s['text'] ); ?></textarea></td></tr>
				<tr><th><label for="el"><?php esc_html_e( 'Kategorie „Extern“: Name', 'sgnl-core' ); ?></label></th><td><input id="el" class="regular-text" name="ext_label" value="<?php echo esc_attr( $s['ext_label'] ); ?>"></td></tr>
				<tr><th><label for="et"><?php esc_html_e( 'Kategorie „Extern“: Beschreibung', 'sgnl-core' ); ?></label></th><td><textarea id="et" class="large-text" rows="3" name="ext_text"><?php echo esc_textarea( $s['ext_text'] ); ?></textarea></td></tr>
				<tr><th><label for="pu"><?php esc_html_e( 'Link Datenschutzerklärung', 'sgnl-core' ); ?></label></th><td><input id="pu" class="regular-text" type="url" name="privacy_url" value="<?php echo esc_attr( $s['privacy_url'] ); ?>"></td></tr>
				<tr><th><label for="iu"><?php esc_html_e( 'Link Impressum', 'sgnl-core' ); ?></label></th><td><input id="iu" class="regular-text" type="url" name="imprint_url" value="<?php echo esc_attr( $s['imprint_url'] ); ?>"></td></tr>
			</table>
			<?php submit_button( __( 'Speichern', 'sgnl-core' ), 'primary', 'sgnl_consent_save' ); ?>
		</form>
	</div>
	<?php
}
