<?php
/**
 * Offizielle Ankündigungen (z. B. Einladung zur Weihnachtsfeier, Mitgliederversammlung).
 *
 * - Dashboard → Ankündigungen: je Ankündigung Titel, Text, Art („Einladung“), Datum, Uhrzeit, Ort,
 *   bis zu zwei Buttons und der Schalter „Auf der Startseite zeigen“ (optional „bis“).
 * - Block „Offizielle Ankündigungen“ zeigt alle eingeschalteten Ankündigungen als Abschnitt.
 *   Er wird einmalig auf der Startseite direkt unter den News eingefügt.
 * - Der ganze Bereich lässt sich unter Ankündigungen → Einstellungen ein- und ausschalten.
 *   Ohne eingeschaltete Ankündigung erscheint er nicht.
 *
 * @package sgnl-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Felder einer Ankündigung (Meta-Schlüssel => Standard) */
function sgnl_ann_fields() {
	return array(
		'_sgnl_ann_active'   => '0',
		'_sgnl_ann_label'    => '',
		'_sgnl_ann_date'     => '',
		'_sgnl_ann_time'     => '',
		'_sgnl_ann_place'    => '',
		'_sgnl_ann_until'    => '',
		'_sgnl_ann_btn1'     => '',
		'_sgnl_ann_btn1_url' => '',
		'_sgnl_ann_btn2'     => '',
		'_sgnl_ann_btn2_url' => '',
	);
}

/** Einstellungen des Bereichs */
function sgnl_ann_settings() {
	$s = get_option( 'sgnl_announcements', array() );
	return wp_parse_args( is_array( $s ) ? $s : array(), array(
		'enabled' => '1',
		'title'   => __( 'Offizielle Ankündigungen', 'sgnl-core' ),
		'intro'   => '',
	) );
}

add_action( 'init', function () {
	register_post_type( 'sgnl_announce', array(
		'labels'          => array(
			'name'          => __( 'Ankündigungen', 'sgnl-core' ),
			'singular_name' => __( 'Ankündigung', 'sgnl-core' ),
			'add_new'       => __( 'Neue Ankündigung', 'sgnl-core' ),
			'add_new_item'  => __( 'Neue Ankündigung anlegen', 'sgnl-core' ),
			'edit_item'     => __( 'Ankündigung bearbeiten', 'sgnl-core' ),
			'all_items'     => __( 'Alle Ankündigungen', 'sgnl-core' ),
			'menu_name'     => __( 'Ankündigungen', 'sgnl-core' ),
			'view_item'     => __( 'Ankündigung ansehen', 'sgnl-core' ),
		),
		'public'          => true,
		'publicly_queryable' => true,
		'exclude_from_search' => true,
		'has_archive'     => false,
		'rewrite'         => array( 'slug' => 'ankuendigung', 'with_front' => false ),
		'show_in_rest'    => true,
		'menu_icon'       => 'dashicons-flag',
		'menu_position'   => 25,
		'supports'        => array( 'title', 'editor', 'excerpt', 'page-attributes', 'revisions' ),
		'capability_type' => 'page',
	) );

	foreach ( sgnl_ann_fields() as $key => $default ) {
		register_post_meta( 'sgnl_announce', $key, array(
			'type'          => 'string',
			'single'        => true,
			'default'       => $default,
			'show_in_rest'  => true,
			'auth_callback' => function () {
				return current_user_can( 'edit_pages' );
			},
		) );
	}

	wp_register_style( 'sgnl-announcements', SGNL_CORE_URL . 'assets/announcements.css', array(), sgnl_core_ver( 'assets/announcements.css' ) );
	wp_register_script(
		'sgnl-announcements-block',
		SGNL_CORE_URL . 'assets/announcements-block.js',
		array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render' ),
		sgnl_core_ver( 'assets/announcements-block.js' ),
		true
	);
	wp_localize_script( 'sgnl-announcements-block', 'sgnlAnn', array(
		'list'     => admin_url( 'edit.php?post_type=sgnl_announce' ),
		'settings' => admin_url( 'edit.php?post_type=sgnl_announce&page=sgnl-announcements' ),
	) );

	register_block_type( 'sgnl/announcements', array(
		'api_version'     => 3,
		'title'           => __( 'Offizielle Ankündigungen', 'sgnl-core' ),
		'description'     => __( 'Zeigt alle eingeschalteten Ankündigungen (Dashboard → Ankündigungen).', 'sgnl-core' ),
		'category'        => 'widgets',
		'icon'            => 'flag',
		'keywords'        => array( 'ankündigung', 'einladung', 'mitgliederversammlung', 'vorstand' ),
		'editor_script'   => 'sgnl-announcements-block',
		'style'           => 'sgnl-announcements',
		'editor_style'    => 'sgnl-announcements',
		'supports'        => array( 'html' => false, 'multiple' => false ),
		'attributes'      => array(),
		'render_callback' => 'sgnl_ann_render',
	) );
} );

/**
 * Ist die Ankündigung heute sichtbar?
 *
 * @param int    $id    Beitrag.
 * @param string $today Datum (Y-m-d), für Tests.
 * @return bool
 */
function sgnl_ann_is_visible( $id, $today = '' ) {
	if ( 'publish' !== get_post_status( $id ) || '1' !== (string) get_post_meta( $id, '_sgnl_ann_active', true ) ) {
		return false;
	}
	$today = $today ? $today : wp_date( 'Y-m-d' );
	$until = (string) get_post_meta( $id, '_sgnl_ann_until', true );
	if ( '' === $until ) {
		// Ohne „bis“: automatisch nach dem Termin ausblenden.
		$until = (string) get_post_meta( $id, '_sgnl_ann_date', true );
	}
	return '' === $until || $until >= $today;
}

/**
 * Sichtbare Ankündigungen (Reihenfolge, dann Datum).
 *
 * @param string $today Datum (Y-m-d), für Tests.
 * @return WP_Post[]
 */
function sgnl_ann_items( $today = '' ) {
	$posts = get_posts( array(
		'post_type'      => 'sgnl_announce',
		'post_status'    => 'publish',
		'numberposts'    => 20,
		'orderby'        => array( 'menu_order' => 'ASC', 'date' => 'DESC' ),
		'meta_key'       => '_sgnl_ann_active', // phpcs:ignore WordPress.DB.SlowDBQuery
		'meta_value'     => '1', // phpcs:ignore WordPress.DB.SlowDBQuery
	) );
	return array_values( array_filter( $posts, function ( $p ) use ( $today ) {
		return sgnl_ann_is_visible( $p->ID, $today );
	} ) );
}

/**
 * Datum deutsch (z. B. „Samstag, 5. Dezember 2026“).
 *
 * @param string $ymd Datum.
 * @param string $fmt Format.
 * @return string
 */
function sgnl_ann_date( $ymd, $fmt = 'l, j. F Y' ) {
	$ts = strtotime( $ymd . ' 12:00:00' );
	if ( ! $ts ) {
		return '';
	}
	return wp_date( $fmt, $ts, new DateTimeZone( 'Europe/Berlin' ) );
}

/**
 * Kurztext der Karte: Kurzfassung oder Anfang des Textes.
 *
 * @param WP_Post $p Ankündigung.
 * @return array [HTML, gekürzt?]
 */
function sgnl_ann_summary( WP_Post $p ) {
	if ( '' !== trim( $p->post_excerpt ) ) {
		return array( wpautop( esc_html( $p->post_excerpt ) ), '' !== trim( wp_strip_all_tags( $p->post_content ) ) );
	}
	$text  = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( strip_shortcodes( do_blocks( $p->post_content ) ) ) ) );
	$short = wp_trim_words( $text, 38, '…' );
	return array( '' !== $short ? '<p>' . esc_html( $short ) . '</p>' : '', $short !== $text );
}

/**
 * Kleines Symbol.
 *
 * @param string $name calendar|clock|pin.
 * @return string
 */
function sgnl_ann_icon( $name ) {
	$paths = array(
		'calendar' => '<rect x="3.5" y="5" width="17" height="15.5" rx="2.5"/><path d="M8 3v4M16 3v4M3.5 10h17"/>',
		'clock'    => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
		'pin'      => '<path d="M12 21s-6.5-5.6-6.5-11a6.5 6.5 0 0 1 13 0c0 5.4-6.5 11-6.5 11z"/><circle cx="12" cy="10" r="2.3"/>',
	);
	return '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . ( $paths[ $name ] ?? '' ) . '</svg>';
}

/**
 * Eine Karte.
 *
 * @param WP_Post $p Ankündigung.
 * @return string
 */
function sgnl_ann_card( WP_Post $p ) {
	$m = array();
	foreach ( array_keys( sgnl_ann_fields() ) as $k ) {
		$m[ $k ] = trim( (string) get_post_meta( $p->ID, $k, true ) );
	}
	$date = $m['_sgnl_ann_date'];
	$ts   = $date ? strtotime( $date . ' 12:00:00' ) : 0;

	$h = '<article class="sgnl-ann__card">';
	$h .= '<div class="sgnl-ann__head">';
	if ( $ts ) {
		$h .= '<time class="sgnl-ann__tile" datetime="' . esc_attr( $date ) . '"><span class="sgnl-ann__day">' . esc_html( sgnl_ann_date( $date, 'j' ) ) . '</span><span class="sgnl-ann__month">' . esc_html( rtrim( sgnl_ann_date( $date, 'M' ), '.' ) ) . '</span></time>';
	}
	$h .= '<div class="sgnl-ann__titles">';
	if ( '' !== $m['_sgnl_ann_label'] ) {
		$h .= '<p class="sgnl-ann__label">' . esc_html( $m['_sgnl_ann_label'] ) . '</p>';
	}
	$h .= '<h3 class="sgnl-ann__title">' . esc_html( get_the_title( $p ) ) . '</h3></div></div>';

	$meta = array();
	if ( $ts ) {
		$meta[] = '<li>' . sgnl_ann_icon( 'calendar' ) . '<span>' . esc_html( sgnl_ann_date( $date ) ) . '</span></li>';
	}
	if ( '' !== $m['_sgnl_ann_time'] ) {
		$t      = $m['_sgnl_ann_time'];
		$meta[] = '<li>' . sgnl_ann_icon( 'clock' ) . '<span>' . esc_html( preg_match( '/^\d{1,2}:\d{2}$/', $t ) ? $t . ' Uhr' : $t ) . '</span></li>';
	}
	if ( '' !== $m['_sgnl_ann_place'] ) {
		$meta[] = '<li>' . sgnl_ann_icon( 'pin' ) . '<span>' . esc_html( $m['_sgnl_ann_place'] ) . '</span></li>';
	}
	if ( $meta ) {
		$h .= '<ul class="sgnl-ann__meta">' . implode( '', $meta ) . '</ul>';
	}

	list( $summary, $more ) = sgnl_ann_summary( $p );
	$h .= '<div class="sgnl-ann__text">' . $summary . '</div>';

	$btns = array();
	foreach ( array( '1', '2' ) as $n ) {
		$label = $m[ '_sgnl_ann_btn' . $n ];
		$url   = $m[ '_sgnl_ann_btn' . $n . '_url' ];
		if ( '' === $label || '' === $url ) {
			continue;
		}
		$ext    = 0 === strpos( $url, 'http' ) && function_exists( 'sgnl_is_own_host' ) && ! sgnl_is_own_host( $url );
		$pdf    = (bool) preg_match( '/\.pdf(\?|#|$)/i', $url );
		$btns[] = '<a class="sgnl-ann__btn' . ( '1' === $n ? ' is-primary' : '' ) . '" href="' . esc_url( $url ) . '"' . ( $ext || $pdf ? ' target="_blank" rel="noopener"' : '' ) . '>' . esc_html( $label ) . ( $pdf ? ' <span class="sgnl-ann__pdf">PDF</span>' : '' ) . '</a>';
	}
	if ( $more ) {
		$btns[] = '<a class="sgnl-ann__btn is-link" href="' . esc_url( get_permalink( $p ) ) . '">' . esc_html__( 'Alle Infos', 'sgnl-core' ) . ' <span aria-hidden="true">→</span></a>';
	}
	if ( $btns ) {
		$h .= '<p class="sgnl-ann__actions">' . implode( '', $btns ) . '</p>';
	}
	return $h . '</article>';
}

/**
 * Block ausgeben.
 *
 * @return string
 */
function sgnl_ann_render() {
	$s       = sgnl_ann_settings();
	$items   = sgnl_ann_items();
	$preview = defined( 'REST_REQUEST' ) && REST_REQUEST;

	if ( '1' !== (string) $s['enabled'] || ! $items ) {
		if ( ! $preview ) {
			return '';
		}
		$why = '1' !== (string) $s['enabled']
			? __( 'Der Bereich ist ausgeschaltet (Ankündigungen → Einstellungen). Auf der Website erscheint hier nichts.', 'sgnl-core' )
			: __( 'Gerade ist keine Ankündigung eingeschaltet. Auf der Website erscheint hier nichts.', 'sgnl-core' );
		return '<div class="sgnl-ann-empty"><strong>' . esc_html( $s['title'] ) . '</strong><br>' . esc_html( $why ) . '</div>';
	}

	$cards = '';
	foreach ( $items as $p ) {
		$cards .= sgnl_ann_card( $p );
	}
	$count = count( $items );
	$html  = '<section class="wp-block-group alignfull has-global-padding is-layout-constrained wp-block-group-is-layout-constrained sgnl-ann" id="ankuendigungen" aria-labelledby="sgnl-ann-title">';
	$html .= '<h2 class="wp-block-heading has-text-align-center is-style-sgnl-lane" id="sgnl-ann-title">' . esc_html( $s['title'] ) . '</h2>';
	if ( '' !== trim( (string) $s['intro'] ) ) {
		$html .= '<p class="has-text-align-center sgnl-ann__intro">' . esc_html( $s['intro'] ) . '</p>';
	}
	$html .= '<div class="alignwide sgnl-ann__grid is-count-' . min( 3, $count ) . '">' . $cards . '</div>';
	return $html . '</section>';
}

/* =========================================================================
 * Verwaltung
 * ====================================================================== */

add_action( 'add_meta_boxes_sgnl_announce', function () {
	add_meta_box( 'sgnl_ann_box', __( 'Anzeige auf der Startseite', 'sgnl-core' ), 'sgnl_ann_box', 'sgnl_announce', 'side', 'high' );
	add_meta_box( 'sgnl_ann_details', __( 'Termin und Buttons', 'sgnl-core' ), 'sgnl_ann_details_box', 'sgnl_announce', 'normal', 'high' );
} );

function sgnl_ann_box( $post ) {
	wp_nonce_field( 'sgnl_ann_save', 'sgnl_ann_nonce' );
	$active = '1' === (string) get_post_meta( $post->ID, '_sgnl_ann_active', true );
	$until  = (string) get_post_meta( $post->ID, '_sgnl_ann_until', true );
	echo '<p><label><input type="checkbox" name="sgnl_ann[_sgnl_ann_active]" value="1"' . checked( $active, true, false ) . '> <strong>' . esc_html__( 'Auf der Startseite zeigen', 'sgnl-core' ) . '</strong></label></p>';
	echo '<p><label for="sgnl-ann-until">' . esc_html__( 'Zeigen bis einschließlich', 'sgnl-core' ) . '</label><br><input type="date" id="sgnl-ann-until" name="sgnl_ann[_sgnl_ann_until]" value="' . esc_attr( $until ) . '" style="width:100%"></p>';
	echo '<p class="description">' . esc_html__( 'Leer = bis zum Tag des Termins. Danach verschwindet die Ankündigung von selbst.', 'sgnl-core' ) . '</p>';
	echo '<p class="description">' . esc_html__( 'Reihenfolge: Feld „Reihenfolge“ (kleinere Zahl zuerst).', 'sgnl-core' ) . '</p>';
}

function sgnl_ann_details_box( $post ) {
	$v = function ( $k ) use ( $post ) {
		return esc_attr( (string) get_post_meta( $post->ID, $k, true ) );
	};
	$row = function ( $label, $html ) {
		return '<tr><th scope="row" style="width:170px">' . $label . '</th><td>' . $html . '</td></tr>';
	};
	echo '<table class="form-table" role="presentation"><tbody>';
	echo $row( esc_html__( 'Art', 'sgnl-core' ), '<input type="text" name="sgnl_ann[_sgnl_ann_label]" value="' . $v( '_sgnl_ann_label' ) . '" class="regular-text" list="sgnl-ann-labels" placeholder="z. B. Einladung"><datalist id="sgnl-ann-labels"><option value="Einladung"><option value="Mitteilung des Vorstands"><option value="Wichtig"><option value="Termin"></datalist>' ); // phpcs:ignore WordPress.Security.EscapeOutput
	echo $row( esc_html__( 'Datum', 'sgnl-core' ), '<input type="date" name="sgnl_ann[_sgnl_ann_date]" value="' . $v( '_sgnl_ann_date' ) . '">' ); // phpcs:ignore WordPress.Security.EscapeOutput
	echo $row( esc_html__( 'Uhrzeit', 'sgnl-core' ), '<input type="text" name="sgnl_ann[_sgnl_ann_time]" value="' . $v( '_sgnl_ann_time' ) . '" placeholder="15:00" style="width:9em"> <span class="description">' . esc_html__( '„15:00“ wird zu „15:00 Uhr“', 'sgnl-core' ) . '</span>' ); // phpcs:ignore WordPress.Security.EscapeOutput
	echo $row( esc_html__( 'Ort', 'sgnl-core' ), '<input type="text" name="sgnl_ann[_sgnl_ann_place]" value="' . $v( '_sgnl_ann_place' ) . '" class="large-text">' ); // phpcs:ignore WordPress.Security.EscapeOutput
	foreach ( array( '1' => __( 'Button 1 (blau)', 'sgnl-core' ), '2' => __( 'Button 2', 'sgnl-core' ) ) as $n => $label ) {
		echo $row( esc_html( $label ), '<input type="text" name="sgnl_ann[_sgnl_ann_btn' . $n . ']" value="' . $v( '_sgnl_ann_btn' . $n ) . '" placeholder="' . esc_attr__( 'Beschriftung', 'sgnl-core' ) . '" style="width:16em"> <input type="text" name="sgnl_ann[_sgnl_ann_btn' . $n . '_url]" value="' . $v( '_sgnl_ann_btn' . $n . '_url' ) . '" placeholder="https://… oder Link zu einer PDF aus der Mediathek" class="regular-text" style="width:min(30em,100%)">' ); // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '</tbody></table>';
	echo '<p class="description">' . esc_html__( 'Auf der Karte stehen die „Textauszug“-Zeilen (falls ausgefüllt), sonst der Anfang des Textes. Ist der Text länger, erscheint „Alle Infos“ und führt zur ganzen Ankündigung.', 'sgnl-core' ) . '</p>';
}

add_action( 'save_post_sgnl_announce', function ( $post_id ) {
	if ( ! isset( $_POST['sgnl_ann_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['sgnl_ann_nonce'] ), 'sgnl_ann_save' ) ) {
		return;
	}
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$in = isset( $_POST['sgnl_ann'] ) && is_array( $_POST['sgnl_ann'] ) ? wp_unslash( $_POST['sgnl_ann'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	sgnl_ann_save_fields( $post_id, $in );
} );

/**
 * Felder speichern (auch für Tests und das Anlegen der ersten Ankündigungen).
 *
 * @param int   $post_id Ankündigung.
 * @param array $in      Werte.
 */
function sgnl_ann_save_fields( $post_id, array $in ) {
	foreach ( array_keys( sgnl_ann_fields() ) as $k ) {
		$val = isset( $in[ $k ] ) ? (string) $in[ $k ] : '';
		if ( '_sgnl_ann_active' === $k ) {
			$val = '1' === $val ? '1' : '0';
		} elseif ( in_array( $k, array( '_sgnl_ann_date', '_sgnl_ann_until' ), true ) ) {
			$val = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $val ) ? $val : '';
		} elseif ( substr( $k, -4 ) === '_url' ) {
			$val = esc_url_raw( trim( $val ) );
		} else {
			$val = sanitize_text_field( $val );
		}
		update_post_meta( $post_id, $k, $val );
	}
}

/* Spalten in der Liste */
add_filter( 'manage_sgnl_announce_posts_columns', function ( $cols ) {
	$new = array();
	foreach ( $cols as $k => $v ) {
		$new[ $k ] = $v;
		if ( 'title' === $k ) {
			$new['sgnl_ann_when']  = __( 'Termin', 'sgnl-core' );
			$new['sgnl_ann_shown'] = __( 'Startseite', 'sgnl-core' );
		}
	}
	unset( $new['date'] );
	return $new;
} );

add_action( 'manage_sgnl_announce_posts_custom_column', function ( $col, $id ) {
	if ( 'sgnl_ann_when' === $col ) {
		$d = (string) get_post_meta( $id, '_sgnl_ann_date', true );
		echo esc_html( $d ? sgnl_ann_date( $d, 'D, d.m.Y' ) : '–' );
		$t = (string) get_post_meta( $id, '_sgnl_ann_time', true );
		echo $t ? ' · ' . esc_html( $t ) : '';
	}
	if ( 'sgnl_ann_shown' === $col ) {
		$on   = '1' === (string) get_post_meta( $id, '_sgnl_ann_active', true );
		$vis  = sgnl_ann_is_visible( $id );
		$url  = wp_nonce_url( admin_url( 'admin-post.php?action=sgnl_ann_toggle&id=' . (int) $id ), 'sgnl_ann_toggle_' . $id );
		$text = $vis ? __( 'wird gezeigt', 'sgnl-core' ) : ( $on ? __( 'abgelaufen', 'sgnl-core' ) : __( 'aus', 'sgnl-core' ) );
		$col  = $vis ? '#00a32a' : '#787c82';
		printf( '<span style="color:%s;font-weight:600">● %s</span><br><a href="%s">%s</a>', esc_attr( $col ), esc_html( $text ), esc_url( $url ), esc_html( $on ? __( 'Ausschalten', 'sgnl-core' ) : __( 'Einschalten', 'sgnl-core' ) ) );
	}
}, 10, 2 );

add_action( 'admin_post_sgnl_ann_toggle', function () {
	$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
	check_admin_referer( 'sgnl_ann_toggle_' . $id );
	if ( ! $id || ! current_user_can( 'edit_post', $id ) ) {
		wp_die( esc_html__( 'Keine Berechtigung.', 'sgnl-core' ) );
	}
	$on = '1' === (string) get_post_meta( $id, '_sgnl_ann_active', true );
	update_post_meta( $id, '_sgnl_ann_active', $on ? '0' : '1' );
	wp_safe_redirect( admin_url( 'edit.php?post_type=sgnl_announce' ) );
	exit;
} );

/* Einstellungen des Bereichs */
add_action( 'admin_menu', function () {
	add_submenu_page( 'edit.php?post_type=sgnl_announce', __( 'Einstellungen', 'sgnl-core' ), __( 'Einstellungen', 'sgnl-core' ), 'edit_pages', 'sgnl-announcements', 'sgnl_ann_settings_page' );
} );

function sgnl_ann_settings_page() {
	if ( isset( $_POST['sgnl_ann_settings'] ) && check_admin_referer( 'sgnl_ann_settings' ) ) {
		$in = wp_unslash( $_POST['sgnl_ann_settings'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		update_option( 'sgnl_announcements', array(
			'enabled' => ! empty( $in['enabled'] ) ? '1' : '0',
			'title'   => sanitize_text_field( $in['title'] ?? '' ) ?: __( 'Offizielle Ankündigungen', 'sgnl-core' ),
			'intro'   => sanitize_text_field( $in['intro'] ?? '' ),
		) );
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Gespeichert.', 'sgnl-core' ) . '</p></div>';
	}
	if ( isset( $_POST['sgnl_ann_insert'] ) && check_admin_referer( 'sgnl_ann_settings' ) ) {
		$done = sgnl_ann_insert_front();
		echo '<div class="notice notice-' . ( $done ? 'success' : 'info' ) . '"><p>' . esc_html( $done ? __( 'Der Bereich steht jetzt auf der Startseite unter den News.', 'sgnl-core' ) : __( 'Der Bereich ist schon auf der Startseite (oder es gibt keine statische Startseite).', 'sgnl-core' ) ) . '</p></div>';
	}
	$s      = sgnl_ann_settings();
	$front  = (int) get_option( 'page_on_front' );
	$placed = $front && false !== strpos( (string) get_post_field( 'post_content', $front ), 'wp:sgnl/announcements' );
	$count  = count( sgnl_ann_items() );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Offizielle Ankündigungen – Einstellungen', 'sgnl-core' ); ?></h1>
		<form method="post">
			<?php wp_nonce_field( 'sgnl_ann_settings' ); ?>
			<table class="form-table" role="presentation"><tbody>
				<tr><th scope="row"><?php esc_html_e( 'Bereich', 'sgnl-core' ); ?></th><td><label><input type="checkbox" name="sgnl_ann_settings[enabled]" value="1" <?php checked( '1', $s['enabled'] ); ?>> <?php esc_html_e( 'Auf der Startseite anzeigen (wenn mindestens eine Ankündigung eingeschaltet ist)', 'sgnl-core' ); ?></label>
					<p class="description"><?php echo esc_html( sprintf( /* translators: %d: Anzahl */ _n( 'Gerade eingeschaltet: %d Ankündigung.', 'Gerade eingeschaltet: %d Ankündigungen.', $count, 'sgnl-core' ), $count ) ); ?></p></td></tr>
				<tr><th scope="row"><label for="sgnl-ann-title"><?php esc_html_e( 'Überschrift', 'sgnl-core' ); ?></label></th><td><input type="text" id="sgnl-ann-title" name="sgnl_ann_settings[title]" value="<?php echo esc_attr( $s['title'] ); ?>" class="regular-text"></td></tr>
				<tr><th scope="row"><label for="sgnl-ann-intro"><?php esc_html_e( 'Einleitung (optional)', 'sgnl-core' ); ?></label></th><td><input type="text" id="sgnl-ann-intro" name="sgnl_ann_settings[intro]" value="<?php echo esc_attr( $s['intro'] ); ?>" class="large-text"></td></tr>
			</tbody></table>
			<?php submit_button( __( 'Speichern', 'sgnl-core' ) ); ?>
		</form>
		<h2><?php esc_html_e( 'Platz auf der Startseite', 'sgnl-core' ); ?></h2>
		<?php if ( $placed ) : ?>
			<p><?php esc_html_e( 'Der Block „Offizielle Ankündigungen“ steht auf der Startseite. Verschieben oder entfernen lässt er sich im Editor der Startseite.', 'sgnl-core' ); ?> <a href="<?php echo esc_url( get_edit_post_link( $front ) ); ?>"><?php esc_html_e( 'Startseite bearbeiten', 'sgnl-core' ); ?></a></p>
		<?php else : ?>
			<form method="post"><?php wp_nonce_field( 'sgnl_ann_settings' ); ?><p><?php esc_html_e( 'Der Block steht nicht auf der Startseite.', 'sgnl-core' ); ?></p><?php submit_button( __( 'Unter den News einfügen', 'sgnl-core' ), 'secondary', 'sgnl_ann_insert', false ); ?></form>
		<?php endif; ?>
	</div>
	<?php
}

/* =========================================================================
 * Einbau auf der Startseite (einmalig, direkt unter den News)
 * ====================================================================== */

/**
 * Index des News-Abschnitts in den Blöcken der Startseite.
 *
 * @param array $blocks Blöcke.
 * @return int|null
 */
function sgnl_ann_news_index( array $blocks ) {
	foreach ( $blocks as $i => $b ) {
		if ( empty( $b['blockName'] ) || 'sgnl/announcements' === $b['blockName'] ) {
			continue;
		}
		$ser = serialize_block( $b );
		if ( false !== strpos( $ser, 'sgnl-news' ) || false !== strpos( $ser, '<!-- wp:query' ) || 'news' === ( $b['attrs']['anchor'] ?? '' ) || false !== strpos( $ser, 'id="news"' ) ) {
			return $i;
		}
	}
	return null;
}

/**
 * Block auf der Startseite direkt unter den News platzieren.
 * Steht er schon auf der Seite, wird er nur verschoben, wenn er noch über den News steht
 * (so wie ihn Version 1.10.0 eingefügt hat). Von Hand an eine andere Stelle gesetzt, bleibt er dort.
 *
 * @return bool Geändert?
 */
function sgnl_ann_insert_front() {
	$front = (int) get_option( 'page_on_front' );
	if ( ! $front || 'page' !== get_option( 'show_on_front' ) ) {
		return false;
	}
	$content = (string) get_post_field( 'post_content', $front );
	if ( ! has_blocks( $content ) ) {
		return false;
	}
	$blocks = parse_blocks( $content );
	$news   = sgnl_ann_news_index( $blocks );
	$have   = null;
	foreach ( $blocks as $i => $b ) {
		if ( 'sgnl/announcements' === $b['blockName'] ) {
			$have = $i;
			break;
		}
	}
	if ( null !== $have ) {
		if ( null === $news || $have > $news ) {
			return false; // Steht schon unter den News oder an einer selbst gewählten Stelle.
		}
		// Aus der alten Position nehmen (samt Leerzeile danach).
		$len = isset( $blocks[ $have + 1 ] ) && empty( $blocks[ $have + 1 ]['blockName'] ) && '' === trim( $blocks[ $have + 1 ]['innerHTML'] ) ? 2 : 1;
		array_splice( $blocks, $have, $len );
		$news = sgnl_ann_news_index( $blocks );
	}
	$new = array(
		'blockName'    => 'sgnl/announcements',
		'attrs'        => array(),
		'innerBlocks'  => array(),
		'innerHTML'    => '',
		'innerContent' => array(),
	);
	$sep = array( 'blockName' => null, 'attrs' => array(), 'innerBlocks' => array(), 'innerHTML' => "\n\n", 'innerContent' => array( "\n\n" ) );
	if ( null === $news ) {
		array_splice( $blocks, 0, 0, array( $new, $sep ) );
	} else {
		array_splice( $blocks, $news + 1, 0, array( $sep, $new ) );
	}
	wp_update_post( wp_slash( array( 'ID' => $front, 'post_content' => serialize_blocks( $blocks ) ) ) );
	return true;
}

/* Erste Ankündigungen (Weihnachtsfeier + außerordentliche Mitgliederversammlung 2026) */
function sgnl_ann_seed() {
	$einladung = 'https://sgnl.de/wp-content/uploads/Einladung_Weihnachtsfeier_aoMV_2026.pdf';
	$anmeldung = 'https://sgnl-portal.de/event.php?id=20';
	$items     = array(
		array(
			'title'   => 'Einladung zur Weihnachtsfeier',
			'order'   => 1,
			'excerpt' => 'Wir lassen das Jahr gemeinsam ausklingen: mit Kuchen- und Fingerfood-Buffet, Nikolaus, Tombola und den Ehrungen der Vereinsmeisterschaften und Jubilare. Bitte meldet euch bis zum 28. November an.',
			'content' => "<!-- wp:paragraph -->\n<p>Liebe Mitglieder, liebe Eltern,</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>das Jahr neigt sich dem Ende zu, und wir möchten es mit euch in gemütlicher Runde ausklingen lassen. Wir laden euch herzlich zu unserer Weihnachtsfeier ein!</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>Freut euch auf einen festlich geschmückten Nachmittag mit Kuchen- und Fingerfood-Buffet, Musik und erster weihnachtlicher Stimmung:</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:list -->\n<ul class=\"wp-block-list\"><!-- wp:list-item -->\n<li>Der Nikolaus kommt vorbei und hat für alle Schwimmer*innen eine Überraschung dabei.</li>\n<!-- /wp:list-item -->\n\n<!-- wp:list-item -->\n<li>Bei unserer Tombola warten tolle Gewinne und der Erlös kommt unserer Jugend zugute.</li>\n<!-- /wp:list-item -->\n\n<!-- wp:list-item -->\n<li>Wir ehren die Teilnehmer*innen der Vereinsmeisterschaften und unsere Jubilare.</li>\n<!-- /wp:list-item --></ul>\n<!-- /wp:list -->\n\n<!-- wp:paragraph -->\n<p>Fürs Buffet brauchen wir eure Unterstützung: Wir freuen uns über süße und herzhafte Leckereien.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>Bitte meldet euch bis zum <strong>28. November 2026</strong> über <a href=\"{$anmeldung}\">sgnl-portal.de</a> an.</p>\n<!-- /wp:paragraph -->",
			'fields'  => array( '_sgnl_ann_label' => 'Einladung', '_sgnl_ann_btn1' => 'Jetzt anmelden', '_sgnl_ann_btn1_url' => $anmeldung, '_sgnl_ann_btn2' => 'Einladung', '_sgnl_ann_btn2_url' => $einladung ),
		),
		array(
			'title'   => 'Außerordentliche Mitgliederversammlung',
			'order'   => 2,
			'excerpt' => 'Zu Beginn der Weihnachtsfeier laden wir alle Mitglieder zur außerordentlichen Mitgliederversammlung ein. Einziges Thema ist die Änderung von § 3 Abs. 2 der Satzung (Vereinssymbol): Künftig soll der Gesamtvorstand das Logo per Beschluss festlegen.',
			'content' => "<!-- wp:paragraph -->\n<p>Zu Beginn der Weihnachtsfeier um 15:00 Uhr findet eine außerordentliche Mitgliederversammlung statt, zu der wir alle Mitglieder herzlich einladen.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading {\"level\":3} -->\n<h3 class=\"wp-block-heading\">Tagesordnung</h3>\n<!-- /wp:heading -->\n\n<!-- wp:list {\"ordered\":true} -->\n<ol class=\"wp-block-list\"><!-- wp:list-item -->\n<li>Begrüßung durch den Vereinsvorstand und Feststellung der ordnungsgemäßen Einberufung sowie der Beschlussfähigkeit</li>\n<!-- /wp:list-item -->\n\n<!-- wp:list-item -->\n<li>Bestimmung einer Protokollführerin / eines Protokollführers und Genehmigung der Tagesordnung</li>\n<!-- /wp:list-item -->\n\n<!-- wp:list-item -->\n<li>Änderung von § 3 Abs. (2) der Satzung (Vereinssymbol): Vorstellung und Aussprache</li>\n<!-- /wp:list-item -->\n\n<!-- wp:list-item -->\n<li>Beschlussfassung über die Satzungsänderung</li>\n<!-- /wp:list-item -->\n\n<!-- wp:list-item -->\n<li>Verschiedenes</li>\n<!-- /wp:list-item --></ol>\n<!-- /wp:list -->\n\n<!-- wp:paragraph -->\n<p>Die bisherige und die neue Fassung findet ihr in der Einladung auf Seite 2. Für den Beschluss ist eine Mehrheit von drei Vierteln der abgegebenen gültigen Stimmen nötig. Antragsteller ist der Gesamtvorstand.</p>\n<!-- /wp:paragraph -->",
			'fields'  => array( '_sgnl_ann_label' => 'Mitteilung des Vorstands', '_sgnl_ann_btn1' => 'Einladung & Satzungsänderung', '_sgnl_ann_btn1_url' => $einladung ),
		),
	);
	foreach ( $items as $it ) {
		$id = wp_insert_post( wp_slash( array(
			'post_type'    => 'sgnl_announce',
			'post_status'  => 'publish',
			'post_title'   => $it['title'],
			'post_excerpt' => $it['excerpt'],
			'post_content' => $it['content'],
			'menu_order'   => $it['order'],
		) ) );
		if ( $id && ! is_wp_error( $id ) ) {
			sgnl_ann_save_fields( $id, array_merge( array(
				'_sgnl_ann_active' => '1',
				'_sgnl_ann_date'   => '2026-12-05',
				'_sgnl_ann_time'   => '15:00',
				'_sgnl_ann_place'  => 'Mensa der Alfred-Delp-Schule, Carl-Lepper-Straße 5, Lampertheim',
			), $it['fields'] ) );
		}
	}
}

add_action( 'admin_init', function () {
	if ( '1' === get_option( 'sgnl_announcements_setup' ) || ! current_user_can( 'edit_pages' ) ) {
		return;
	}
	update_option( 'sgnl_announcements_setup', '1' );
	if ( ! get_posts( array( 'post_type' => 'sgnl_announce', 'post_status' => 'any', 'numberposts' => 1 ) ) && wp_date( 'Y-m-d' ) <= '2026-12-05' ) {
		sgnl_ann_seed();
	}
	if ( sgnl_ann_insert_front() ) {
		set_transient( 'sgnl_ann_notice', 1, DAY_IN_SECONDS );
	}
	update_option( 'sgnl_announcements_pos', 'unter-news' );
	flush_rewrite_rules( false );
} );

/* Ab 1.10.1 stehen die Ankündigungen unter den News: einmalig verschieben */
add_action( 'admin_init', function () {
	if ( 'unter-news' === get_option( 'sgnl_announcements_pos' ) || ! current_user_can( 'edit_pages' ) ) {
		return;
	}
	update_option( 'sgnl_announcements_pos', 'unter-news' );
	sgnl_ann_insert_front();
}, 20 );

add_action( 'admin_notices', function () {
	if ( ! get_transient( 'sgnl_ann_notice' ) || ! current_user_can( 'edit_pages' ) ) {
		return;
	}
	delete_transient( 'sgnl_ann_notice' );
	printf(
		'<div class="notice notice-success is-dismissible"><p>%s <a href="%s">%s</a> · <a href="%s" target="_blank">%s</a></p></div>',
		esc_html__( 'Neu: „Offizielle Ankündigungen“ stehen auf der Startseite direkt unter den News.', 'sgnl-core' ),
		esc_url( admin_url( 'edit.php?post_type=sgnl_announce' ) ),
		esc_html__( 'Ankündigungen verwalten', 'sgnl-core' ),
		esc_url( home_url( '/#ankuendigungen' ) ),
		esc_html__( 'Ansehen', 'sgnl-core' )
	);
} );

/* Einzelansicht: Termin, Ort und Buttons über dem Text */
add_filter( 'the_content', function ( $content ) {
	if ( ! is_singular( 'sgnl_announce' ) || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}
	wp_enqueue_style( 'sgnl-announcements' );
	$p    = get_post();
	$card = sgnl_ann_card( $p );
	// Auf der Einzelseite: ohne Kurztext und ohne „Alle Infos“, der ganze Text folgt darunter.
	$card = preg_replace( '#<div class="sgnl-ann__text">.*?</div>#s', '', $card );
	$card = preg_replace( '#<a class="sgnl-ann__btn is-link"[^>]*>.*?</a>#s', '', $card );
	$card = str_replace( '<p class="sgnl-ann__actions"></p>', '', $card );
	return '<div class="sgnl-ann sgnl-ann--single">' . str_replace( 'sgnl-ann__card', 'sgnl-ann__card is-single', $card ) . '</div>' . $content;
}, 5 );
