<?php
/**
 * Hinweise / Popups (ersetzt "Popup Maker").
 * Ein Hinweis wird als Fenster angezeigt, bis Besucher ihn schließen.
 *
 * @package sgnl-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', function () {
	register_post_type( 'sgnl_notice', array(
		'labels'       => array(
			'name'          => __( 'Hinweise', 'sgnl-core' ),
			'singular_name' => __( 'Hinweis', 'sgnl-core' ),
			'add_new'       => __( 'Neuer Hinweis', 'sgnl-core' ),
			'add_new_item'  => __( 'Neuen Hinweis anlegen', 'sgnl-core' ),
			'edit_item'     => __( 'Hinweis bearbeiten', 'sgnl-core' ),
			'all_items'     => __( 'Alle Hinweise', 'sgnl-core' ),
			'menu_name'     => __( 'Hinweise (Popup)', 'sgnl-core' ),
		),
		'public'       => false,
		'show_ui'      => true,
		'show_in_rest' => true,
		'menu_icon'    => 'dashicons-megaphone',
		'menu_position' => 26,
		'supports'     => array( 'title', 'editor', 'revisions' ),
		'capability_type' => 'page',
	) );

	foreach ( array(
		'_sgnl_active' => array( 'type' => 'boolean', 'default' => false ),
		'_sgnl_scope'  => array( 'type' => 'string', 'default' => 'front' ),
		'_sgnl_until'  => array( 'type' => 'string', 'default' => '' ),
	) as $key => $conf ) {
		register_post_meta( 'sgnl_notice', $key, array(
			'type'          => $conf['type'],
			'single'        => true,
			'default'       => $conf['default'],
			'show_in_rest'  => true,
			'auth_callback' => function () {
				return current_user_can( 'edit_pages' );
			},
		) );
	}
} );

/* Einstellungen-Box im Editor */
add_action( 'add_meta_boxes_sgnl_notice', function () {
	add_meta_box( 'sgnl_notice_settings', __( 'Anzeige', 'sgnl-core' ), 'sgnl_notice_box', 'sgnl_notice', 'side', 'high' );
} );

/**
 * Meta-Box.
 *
 * @param WP_Post $post Beitrag.
 */
function sgnl_notice_box( $post ) {
	wp_nonce_field( 'sgnl_notice', 'sgnl_notice_nonce' );
	$active = (bool) get_post_meta( $post->ID, '_sgnl_active', true );
	$scope  = get_post_meta( $post->ID, '_sgnl_scope', true ) ?: 'front';
	$until  = get_post_meta( $post->ID, '_sgnl_until', true );
	?>
	<p><label><input type="checkbox" name="sgnl_active" value="1" <?php checked( $active ); ?>> <strong><?php esc_html_e( 'Hinweis anzeigen', 'sgnl-core' ); ?></strong></label></p>
	<p><label for="sgnl_scope"><?php esc_html_e( 'Wo?', 'sgnl-core' ); ?></label><br>
		<select name="sgnl_scope" id="sgnl_scope">
			<option value="front" <?php selected( $scope, 'front' ); ?>><?php esc_html_e( 'Nur auf der Startseite', 'sgnl-core' ); ?></option>
			<option value="all" <?php selected( $scope, 'all' ); ?>><?php esc_html_e( 'Auf allen Seiten', 'sgnl-core' ); ?></option>
		</select></p>
	<p><label for="sgnl_until"><?php esc_html_e( 'Anzeigen bis (optional)', 'sgnl-core' ); ?></label><br>
		<input type="date" name="sgnl_until" id="sgnl_until" value="<?php echo esc_attr( $until ); ?>"></p>
	<p class="description"><?php esc_html_e( 'Besucher sehen den Hinweis, bis sie ihn schließen. Nach einer Änderung am Text erscheint er erneut.', 'sgnl-core' ); ?></p>
	<?php
}

add_action( 'save_post_sgnl_notice', function ( $post_id ) {
	if ( ! isset( $_POST['sgnl_notice_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['sgnl_notice_nonce'] ), 'sgnl_notice' ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	update_post_meta( $post_id, '_sgnl_active', ! empty( $_POST['sgnl_active'] ) );
	update_post_meta( $post_id, '_sgnl_scope', 'all' === ( $_POST['sgnl_scope'] ?? '' ) ? 'all' : 'front' );
	$until = sanitize_text_field( wp_unslash( $_POST['sgnl_until'] ?? '' ) );
	update_post_meta( $post_id, '_sgnl_until', preg_match( '/^\d{4}-\d{2}-\d{2}$/', $until ) ? $until : '' );
} );

/* Spalten in der Übersicht */
add_filter( 'manage_sgnl_notice_posts_columns', function ( $cols ) {
	$new = array();
	foreach ( $cols as $k => $v ) {
		$new[ $k ] = $v;
		if ( 'title' === $k ) {
			$new['sgnl_active'] = __( 'Status', 'sgnl-core' );
			$new['sgnl_scope']  = __( 'Wo', 'sgnl-core' );
		}
	}
	return $new;
} );
add_action( 'manage_sgnl_notice_posts_custom_column', function ( $col, $id ) {
	if ( 'sgnl_active' === $col ) {
		$until = get_post_meta( $id, '_sgnl_until', true );
		if ( get_post_meta( $id, '_sgnl_active', true ) && ( ! $until || $until >= current_time( 'Y-m-d' ) ) ) {
			echo '<span style="color:#007017;font-weight:600">' . esc_html__( 'Wird angezeigt', 'sgnl-core' ) . '</span>';
			if ( $until ) {
				/* translators: %s: Datum */
				echo '<br><small>' . esc_html( sprintf( __( 'bis %s', 'sgnl-core' ), date_i18n( get_option( 'date_format' ), strtotime( $until ) ) ) ) . '</small>';
			}
		} else {
			echo '<span style="color:#646970">' . esc_html__( 'Aus', 'sgnl-core' ) . '</span>';
		}
	}
	if ( 'sgnl_scope' === $col ) {
		echo 'all' === get_post_meta( $id, '_sgnl_scope', true ) ? esc_html__( 'Alle Seiten', 'sgnl-core' ) : esc_html__( 'Startseite', 'sgnl-core' );
	}
}, 10, 2 );

/**
 * Aktiven Hinweis für die aktuelle Seite finden.
 *
 * @return WP_Post|null
 */
function sgnl_current_notice() {
	if ( is_admin() || is_feed() || is_embed() ) {
		return null;
	}
	$q = new WP_Query( array(
		'post_type'      => 'sgnl_notice',
		'post_status'    => 'publish',
		'posts_per_page' => 10,
		'orderby'        => 'modified',
		'order'          => 'DESC',
		'no_found_rows'  => true,
		'meta_query'     => array( array( 'key' => '_sgnl_active', 'value' => '1' ) ), // phpcs:ignore
	) );
	$today = current_time( 'Y-m-d' );
	foreach ( $q->posts as $p ) {
		$until = get_post_meta( $p->ID, '_sgnl_until', true );
		if ( $until && $until < $today ) {
			continue;
		}
		$scope = get_post_meta( $p->ID, '_sgnl_scope', true ) ?: 'front';
		if ( 'front' === $scope && ! is_front_page() ) {
			continue;
		}
		return $p;
	}
	return null;
}

add_action( 'wp_footer', function () {
	$p = sgnl_current_notice();
	if ( ! $p ) {
		return;
	}
	$key = 'n' . $p->ID . '-' . strtotime( $p->post_modified_gmt );
	// Inhalt wie einen Beitrag rendern (Blöcke, Shortcodes).
	$content = apply_filters( 'the_content', $p->post_content ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals
	?>
	<dialog class="sgnl-notice" id="sgnl-notice" data-key="<?php echo esc_attr( $key ); ?>" aria-labelledby="sgnl-notice-title">
		<div class="sgnl-notice__inner">
			<button type="button" class="sgnl-notice__close" data-sgnl-close aria-label="<?php esc_attr_e( 'Hinweis schließen', 'sgnl-core' ); ?>">
				<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
			</button>
			<h2 class="sgnl-notice__title" id="sgnl-notice-title"><?php echo esc_html( get_the_title( $p ) ); ?></h2>
			<div class="sgnl-notice__content"><?php echo $content; // phpcs:ignore ?></div>
			<p class="sgnl-notice__actions"><button type="button" class="sgnl-notice__ok" data-sgnl-close><?php esc_html_e( 'Verstanden', 'sgnl-core' ); ?></button></p>
		</div>
	</dialog>
	<script>
	(function () {
		var d = document.getElementById('sgnl-notice');
		if (!d || typeof d.showModal !== 'function') { return; }
		var k = 'sgnl_notice_' + d.getAttribute('data-key');
		try { if (localStorage.getItem(k)) { return; } } catch (e) {}
		function close() { try { localStorage.setItem(k, '1'); } catch (e) {} d.close(); }
		d.querySelectorAll('[data-sgnl-close]').forEach(function (b) { b.addEventListener('click', close); });
		d.addEventListener('cancel', function () { try { localStorage.setItem(k, '1'); } catch (e) {} });
		d.addEventListener('click', function (e) { if (e.target === d) { close(); } });
		// Erst nach dem Cookie-Banner zeigen
		function show() { if (!d.open) { d.showModal(); } }
		if (document.documentElement.classList.contains('sgnl-consent-pending')) {
			document.addEventListener('sgnl:consent', function () { setTimeout(show, 300); }, { once: true });
		} else { setTimeout(show, 700); }
	})();
	</script>
	<?php
}, 30 );
