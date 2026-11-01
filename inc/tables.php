<?php
/**
 * Tabellen-Verwaltung (ersetzt TablePress).
 *
 * - Menüpunkt "Tabellen": Tabellen anlegen und im Dashboard bearbeiten
 * - Ausgabe auf Seiten per Block "Tabelle" oder Shortcode [sgnl_tabelle id="…"]
 * - CSV-Import/-Export (Excel-kompatibel)
 *
 * Daten: Post-Meta _sgnl_table (JSON) mit Revisionen.
 *
 * @package sgnl-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const SGNL_TABLE_META = '_sgnl_table';

/* =========================================================================
 * Inhaltstyp
 * ====================================================================== */
add_action( 'init', function () {
	register_post_type( 'sgnl_table', array(
		'labels'          => array(
			'name'               => __( 'Tabellen', 'sgnl-core' ),
			'singular_name'      => __( 'Tabelle', 'sgnl-core' ),
			'add_new'            => __( 'Neue Tabelle', 'sgnl-core' ),
			'add_new_item'       => __( 'Neue Tabelle anlegen', 'sgnl-core' ),
			'edit_item'          => __( 'Tabelle bearbeiten', 'sgnl-core' ),
			'all_items'          => __( 'Alle Tabellen', 'sgnl-core' ),
			'search_items'       => __( 'Tabellen durchsuchen', 'sgnl-core' ),
			'not_found'          => __( 'Noch keine Tabellen.', 'sgnl-core' ),
			'menu_name'          => __( 'Tabellen', 'sgnl-core' ),
		),
		'public'          => false,
		'show_ui'         => true,
		'show_in_menu'    => true,
		'show_in_rest'    => true,
		'rest_base'       => 'sgnl-tabellen',
		'menu_icon'       => 'dashicons-editor-table',
		'menu_position'   => 21,
		'supports'        => array( 'title', 'revisions' ),
		'capability_type' => 'page',
		'map_meta_cap'    => true,
	) );

	register_post_meta( 'sgnl_table', SGNL_TABLE_META, array(
		'type'              => 'string',
		'single'            => true,
		'show_in_rest'      => false,
		'revisions_enabled' => true,
		'auth_callback'     => function () {
			return current_user_can( 'edit_pages' );
		},
	) );

	add_shortcode( 'sgnl_tabelle', function ( $atts ) {
		$atts = shortcode_atts( array( 'id' => 0 ), $atts, 'sgnl_tabelle' );
		return sgnl_table_render( (int) $atts['id'] );
	} );

	wp_register_script(
		'sgnl-table-block',
		SGNL_CORE_URL . 'assets/table-block.js',
		array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-data', 'wp-core-data', 'wp-server-side-render', 'wp-url' ),
		sgnl_core_ver( 'assets/table-block.js' ),
		true
	);
	wp_localize_script( 'sgnl-table-block', 'sgnlTableBlock', array(
		'editUrl' => admin_url( 'post.php?action=edit&post=' ),
		'newUrl'  => admin_url( 'post-new.php?post_type=sgnl_table' ),
		'listUrl' => admin_url( 'edit.php?post_type=sgnl_table' ),
	) );
	register_block_type( 'sgnl/table', array(
		'api_version'     => 3,
		'title'           => __( 'Tabelle', 'sgnl-core' ),
		'description'     => __( 'Zeigt eine Tabelle aus der Tabellen-Verwaltung (Dashboard → Tabellen).', 'sgnl-core' ),
		'category'        => 'text',
		'icon'            => 'editor-table',
		'keywords'        => array( 'tabelle', 'terminplan', 'archiv', 'ergebnisse' ),
		'editor_script'   => 'sgnl-table-block',
		'supports'        => array( 'align' => array( 'wide', 'full' ), 'html' => false ),
		'attributes'      => array(
			'id'    => array( 'type' => 'number', 'default' => 0 ),
			'align' => array( 'type' => 'string' ),
		),
		'render_callback' => function ( $a ) {
			return sgnl_table_render( (int) ( $a['id'] ?? 0 ), array( 'align' => $a['align'] ?? null ) );
		},
	) );
} );

/* Kein Block-Editor für Tabellen – eigener Tabellen-Editor */
add_filter( 'use_block_editor_for_post_type', function ( $use, $type ) {
	return 'sgnl_table' === $type ? false : $use;
}, 10, 2 );

/* =========================================================================
 * Daten
 * ====================================================================== */

/**
 * Leere Tabelle.
 *
 * @return array
 */
function sgnl_table_default() {
	return array(
		'rows'        => array( array( '', '', '' ), array( '', '', '' ), array( '', '', '' ) ),
		'head'        => true,
		'foot'        => false,
		'hidden_rows' => array(),
		'hidden_cols' => array(),
		'show_title'  => false,
		'note'        => '',
	);
}

/**
 * Tabelle lesen.
 *
 * @param int $id Post-ID.
 * @return array|null
 */
function sgnl_table_get( $id ) {
	$post = get_post( $id );
	if ( ! $post || 'sgnl_table' !== $post->post_type ) {
		return null;
	}
	$data = json_decode( (string) get_post_meta( $id, SGNL_TABLE_META, true ), true );
	return sgnl_table_normalize( is_array( $data ) ? $data : sgnl_table_default() );
}

/**
 * Daten bereinigen und vereinheitlichen (gleich viele Spalten je Zeile).
 *
 * @param array $d Rohdaten.
 * @return array
 */
function sgnl_table_normalize( array $d ) {
	$rows = array();
	$cols = 0;
	foreach ( (array) ( $d['rows'] ?? array() ) as $r ) {
		$row = array();
		foreach ( (array) $r as $c ) {
			$row[] = wp_kses_post( trim( str_replace( array( "\r\n", "\r" ), "\n", (string) $c ) ) );
		}
		$cols   = max( $cols, count( $row ) );
		$rows[] = $row;
	}
	if ( ! $rows ) {
		$rows = sgnl_table_default()['rows'];
		$cols = 3;
	}
	$cols = max( 1, $cols );
	foreach ( $rows as &$row ) {
		$row = array_pad( $row, $cols, '' );
	}
	unset( $row );

	$flags = function ( $list, $len ) {
		$out = array();
		foreach ( (array) $list as $i ) {
			$i = (int) $i;
			if ( $i >= 0 && $i < $len ) {
				$out[] = $i;
			}
		}
		return array_values( array_unique( $out ) );
	};

	return array(
		'rows'        => $rows,
		'head'        => ! empty( $d['head'] ),
		'foot'        => ! empty( $d['foot'] ),
		'hidden_rows' => $flags( $d['hidden_rows'] ?? array(), count( $rows ) ),
		'hidden_cols' => $flags( $d['hidden_cols'] ?? array(), $cols ),
		'show_title'  => ! empty( $d['show_title'] ),
		'note'        => sanitize_textarea_field( (string) ( $d['note'] ?? '' ) ),
	);
}

/**
 * Tabelle speichern.
 *
 * @param int   $id   Post-ID.
 * @param array $data Daten.
 */
function sgnl_table_save( $id, array $data ) {
	$data = sgnl_table_normalize( $data );
	update_post_meta( $id, SGNL_TABLE_META, wp_slash( wp_json_encode( $data, JSON_UNESCAPED_UNICODE ) ) );
}

/**
 * Neue Tabelle anlegen (z. B. bei der Umstellung).
 *
 * @param string $title Name.
 * @param array  $data  Daten.
 * @return int Post-ID.
 */
function sgnl_table_create( $title, array $data ) {
	$id = wp_insert_post( array(
		'post_type'   => 'sgnl_table',
		'post_status' => 'publish',
		'post_title'  => $title,
	) );
	if ( $id && ! is_wp_error( $id ) ) {
		sgnl_table_save( $id, $data );
		return (int) $id;
	}
	return 0;
}

/* =========================================================================
 * Ausgabe
 * ====================================================================== */

/**
 * HTML einer Tabelle.
 *
 * @param int   $id   Post-ID.
 * @param array $args align.
 * @return string
 */
function sgnl_table_render( $id, array $args = array() ) {
	$post = $id ? get_post( $id ) : null;
	if ( ! $post || 'sgnl_table' !== $post->post_type || 'publish' !== $post->post_status ) {
		return current_user_can( 'edit_pages' ) ? '<p class="sgnl-table-missing">' . esc_html__( 'Tabelle nicht gefunden oder nicht veröffentlicht.', 'sgnl-core' ) . '</p>' : '';
	}
	$d    = sgnl_table_get( $id );
	$rows = $d['rows'];
	$hr   = array_flip( $d['hidden_rows'] );
	$hc   = array_flip( $d['hidden_cols'] );

	$visible = array();
	foreach ( $rows as $ri => $row ) {
		if ( isset( $hr[ $ri ] ) ) {
			continue;
		}
		$cells = array();
		foreach ( $row as $ci => $cell ) {
			if ( ! isset( $hc[ $ci ] ) ) {
				$cells[] = $cell;
			}
		}
		$visible[] = $cells;
	}
	if ( ! $visible ) {
		return '';
	}

	$cell_html = function ( $c ) {
		return nl2br( wp_kses_post( $c ), false );
	};
	$build = function ( array $row, $tag ) use ( $cell_html ) {
		// Zwischenüberschrift: nur die erste Zelle gefüllt (z. B. "Oktober")
		$filled = 0;
		foreach ( $row as $c ) {
			if ( '' !== trim( wp_strip_all_tags( (string) $c ) ) ) {
				$filled++;
			}
		}
		$group = 'td' === $tag && count( $row ) > 2 && 1 === $filled && '' !== trim( wp_strip_all_tags( (string) $row[0] ) );
		$h     = $group ? '<tr class="is-group-row">' : '<tr>';
		foreach ( $row as $c ) {
			$h .= "<{$tag}>" . $cell_html( $c ) . "</{$tag}>";
		}
		return $h . '</tr>';
	};

	$html = '<table>';
	if ( $d['head'] && count( $visible ) > 1 ) {
		$html .= '<thead>' . $build( array_shift( $visible ), 'th' ) . '</thead>';
	}
	$foot = ( $d['foot'] && count( $visible ) > 1 ) ? array_pop( $visible ) : null;
	$html .= '<tbody>';
	foreach ( $visible as $r ) {
		$html .= $build( $r, 'td' );
	}
	$html .= '</tbody>';
	if ( $foot ) {
		$html .= '<tfoot>' . $build( $foot, 'td' ) . '</tfoot>';
	}
	$html .= '</table>';

	$cols  = count( $rows[0] ) - count( $hc );
	$align = array_key_exists( 'align', $args ) && null !== $args['align'] ? $args['align'] : ( $cols > 3 ? 'wide' : '' );
	$cls   = 'wp-block-table is-style-sgnl-table sgnl-table' . ( in_array( $align, array( 'wide', 'full' ), true ) ? ' align' . $align : '' );

	$caption = '';
	if ( $d['show_title'] ) {
		$caption .= '<p class="sgnl-table__title">' . esc_html( get_the_title( $post ) ) . '</p>';
	}

	$edit = '';
	if ( current_user_can( 'edit_post', $id ) ) {
		$edit = sprintf(
			'<a class="sgnl-table__edit" href="%s">%s</a>',
			esc_url( get_edit_post_link( $id ) ),
			esc_html__( 'Tabelle bearbeiten', 'sgnl-core' )
		);
	}
	$note = $d['note'] ? '<figcaption class="wp-element-caption">' . esc_html( $d['note'] ) . '</figcaption>' : '';

	return $caption . '<figure class="' . esc_attr( $cls ) . '" data-sgnl-table="' . (int) $id . '">' . $html . $note . '</figure>' . $edit;
}

/* Kleine Styles für den Bearbeiten-Link (nur für eingeloggte Redakteure sichtbar) */
add_action( 'wp_head', function () {
	if ( ! is_user_logged_in() ) {
		return;
	}
	echo '<style>.sgnl-table__edit{display:inline-block;margin:.4rem 0 0;font-size:.85rem}.sgnl-table__title{font-weight:600;color:#0B2A5B;margin-bottom:.5rem}</style>' . "\n";
} );

/* =========================================================================
 * Editor im Dashboard
 * ====================================================================== */
add_action( 'add_meta_boxes_sgnl_table', function ( $post ) {
	add_meta_box( 'sgnl_table_editor', __( 'Tabelle', 'sgnl-core' ), 'sgnl_table_editor_box', 'sgnl_table', 'normal', 'high' );
	add_meta_box( 'sgnl_table_options', __( 'Einstellungen', 'sgnl-core' ), 'sgnl_table_options_box', 'sgnl_table', 'side', 'default' );
	add_meta_box( 'sgnl_table_io', __( 'Import / Export', 'sgnl-core' ), 'sgnl_table_io_box', 'sgnl_table', 'side', 'default' );
	add_meta_box( 'sgnl_table_usage', __( 'Einbinden', 'sgnl-core' ), 'sgnl_table_usage_box', 'sgnl_table', 'side', 'default' );
} );

/* Formular für Datei-Upload freischalten */
add_action( 'post_edit_form_tag', function ( $post ) {
	if ( $post && 'sgnl_table' === $post->post_type ) {
		echo ' enctype="multipart/form-data"';
	}
} );

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	$screen = get_current_screen();
	if ( ! $screen || 'sgnl_table' !== $screen->post_type || ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
		return;
	}
	wp_enqueue_style( 'sgnl-tables-admin', SGNL_CORE_URL . 'assets/tables-admin.css', array(), sgnl_core_ver( 'assets/tables-admin.css' ) );
	wp_enqueue_script( 'sgnl-tables-admin', SGNL_CORE_URL . 'assets/tables-admin.js', array(), sgnl_core_ver( 'assets/tables-admin.js' ), true );
} );

/**
 * Tabellen-Editor.
 *
 * @param WP_Post $post Beitrag.
 */
function sgnl_table_editor_box( $post ) {
	$d = sgnl_table_get( $post->ID );
	if ( ! $d ) {
		$d = sgnl_table_default();
	}
	wp_nonce_field( 'sgnl_table_save', 'sgnl_table_nonce' );
	?>
	<div class="sgnl-te" id="sgnl-te">
		<div class="sgnl-te__toolbar">
			<button type="button" class="button" data-cmd="row-add"><?php esc_html_e( '+ Zeile', 'sgnl-core' ); ?></button>
			<button type="button" class="button" data-cmd="col-add"><?php esc_html_e( '+ Spalte', 'sgnl-core' ); ?></button>
			<span class="sgnl-te__sep"></span>
			<button type="button" class="button" data-cmd="bold" title="<?php esc_attr_e( 'Markierten Text fett', 'sgnl-core' ); ?>"><strong>F</strong></button>
			<button type="button" class="button" data-cmd="link" title="<?php esc_attr_e( 'Markierten Text verlinken', 'sgnl-core' ); ?>"><?php esc_html_e( 'Link', 'sgnl-core' ); ?></button>
			<span class="sgnl-te__sep"></span>
			<button type="button" class="button" data-cmd="undo" title="<?php esc_attr_e( 'Letzte Änderung zurück', 'sgnl-core' ); ?>" disabled><?php esc_html_e( 'Rückgängig', 'sgnl-core' ); ?></button>
			<span class="sgnl-te__size" aria-live="polite"></span>
		</div>
		<div class="sgnl-te__scroll">
			<table class="sgnl-te__grid" aria-label="<?php esc_attr_e( 'Tabelleninhalt', 'sgnl-core' ); ?>"></table>
		</div>
		<p class="sgnl-te__help">
			<?php esc_html_e( 'Tipps: Mit Tab zur nächsten Zelle. Mehrere Zellen aus Excel kopieren und in eine Zelle einfügen – sie werden automatisch verteilt. Über die Zeilennummer bzw. den Spaltenkopf lassen sich Zeilen und Spalten einfügen, verschieben, ausblenden und löschen. Eine Zeile, in der nur die erste Zelle gefüllt ist (z. B. „Oktober“), wird auf der Website als Zwischenüberschrift hervorgehoben.', 'sgnl-core' ); ?>
		</p>
		<input type="hidden" name="sgnl_table_data" id="sgnl-table-data" value="<?php echo esc_attr( wp_json_encode( $d, JSON_UNESCAPED_UNICODE ) ); ?>">
		<noscript><p><?php esc_html_e( 'Zum Bearbeiten wird JavaScript benötigt.', 'sgnl-core' ); ?></p></noscript>
	</div>
	<?php
}

/**
 * Einstellungen.
 *
 * @param WP_Post $post Beitrag.
 */
function sgnl_table_options_box( $post ) {
	$d = sgnl_table_get( $post->ID ) ?: sgnl_table_default();
	?>
	<p><label><input type="checkbox" id="sgnl-opt-head" <?php checked( $d['head'] ); ?>> <?php esc_html_e( 'Erste Zeile ist Kopfzeile', 'sgnl-core' ); ?></label></p>
	<p><label><input type="checkbox" id="sgnl-opt-foot" <?php checked( $d['foot'] ); ?>> <?php esc_html_e( 'Letzte Zeile ist Fußzeile', 'sgnl-core' ); ?></label></p>
	<p><label><input type="checkbox" id="sgnl-opt-title" <?php checked( $d['show_title'] ); ?>> <?php esc_html_e( 'Namen über der Tabelle anzeigen', 'sgnl-core' ); ?></label></p>
	<p><label for="sgnl-opt-note"><?php esc_html_e( 'Hinweis unter der Tabelle (optional)', 'sgnl-core' ); ?></label>
		<textarea id="sgnl-opt-note" rows="2" class="widefat"><?php echo esc_textarea( $d['note'] ); ?></textarea></p>
	<?php
}

/**
 * Import/Export.
 *
 * @param WP_Post $post Beitrag.
 */
function sgnl_table_io_box( $post ) {
	?>
	<p><label for="sgnl-csv"><?php esc_html_e( 'CSV-Datei importieren (ersetzt den Inhalt beim Speichern):', 'sgnl-core' ); ?></label><br>
		<input type="file" name="sgnl_csv" id="sgnl-csv" accept=".csv,text/csv,text/plain"></p>
	<p class="description"><?php esc_html_e( 'In Excel: Datei → Speichern unter → „CSV (Trennzeichen-getrennt)“.', 'sgnl-core' ); ?></p>
	<?php if ( 'auto-draft' !== $post->post_status ) : ?>
		<p><a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=sgnl_table_csv&id=' . $post->ID ), 'sgnl_table_csv_' . $post->ID ) ); ?>"><?php esc_html_e( 'Als CSV herunterladen', 'sgnl-core' ); ?></a></p>
	<?php endif; ?>
	<?php
}

/**
 * Einbinden + Verwendung.
 *
 * @param WP_Post $post Beitrag.
 */
function sgnl_table_usage_box( $post ) {
	if ( 'auto-draft' === $post->post_status ) {
		echo '<p>' . esc_html__( 'Nach dem ersten Speichern erscheint hier, wie die Tabelle eingebunden wird.', 'sgnl-core' ) . '</p>';
		return;
	}
	echo '<p>' . esc_html__( 'Im Seiten-Editor den Block „Tabelle“ einfügen und diese Tabelle auswählen – oder den Shortcode verwenden:', 'sgnl-core' ) . '</p>';
	echo '<p><input type="text" readonly class="widefat code" onfocus="this.select()" value="' . esc_attr( '[sgnl_tabelle id="' . $post->ID . '"]' ) . '"></p>';
	$uses = sgnl_table_usage( $post->ID );
	if ( $uses ) {
		echo '<p><strong>' . esc_html__( 'Verwendet auf:', 'sgnl-core' ) . '</strong></p><ul>';
		foreach ( $uses as $p ) {
			printf( '<li><a href="%s" target="_blank">%s</a></li>', esc_url( get_permalink( $p ) ), esc_html( get_the_title( $p ) ) );
		}
		echo '</ul>';
	}
}

/**
 * Wo wird die Tabelle verwendet?
 *
 * @param int $id Tabellen-ID.
 * @return WP_Post[]
 */
function sgnl_table_usage( $id ) {
	global $wpdb;
	$ids = $wpdb->get_col( $wpdb->prepare(
		"SELECT ID FROM {$wpdb->posts} WHERE post_status IN ('publish','private','draft','future') AND post_type NOT IN ('revision','sgnl_table')
		 AND ( post_content LIKE %s OR post_content LIKE %s OR post_content LIKE %s )",
		'%' . $wpdb->esc_like( '<!-- wp:sgnl/table {"id":' . (int) $id . ',' ) . '%',
		'%' . $wpdb->esc_like( '<!-- wp:sgnl/table {"id":' . (int) $id . '}' ) . '%',
		'%' . $wpdb->esc_like( '[sgnl_tabelle id="' . (int) $id . '"' ) . '%'
	) );
	return array_filter( array_map( 'get_post', $ids ) );
}

/* Speichern */
add_action( 'save_post_sgnl_table', function ( $post_id, $post ) {
	if ( wp_is_post_revision( $post_id ) || ! isset( $_POST['sgnl_table_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['sgnl_table_nonce'] ), 'sgnl_table_save' ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$data = json_decode( wp_unslash( $_POST['sgnl_table_data'] ?? '' ), true ); // phpcs:ignore
	$data = is_array( $data ) ? $data : sgnl_table_default();

	// CSV-Import ersetzt den Inhalt.
	if ( ! empty( $_FILES['sgnl_csv']['tmp_name'] ) && is_uploaded_file( $_FILES['sgnl_csv']['tmp_name'] ) ) { // phpcs:ignore
		$rows = sgnl_table_parse_csv( file_get_contents( $_FILES['sgnl_csv']['tmp_name'] ) ); // phpcs:ignore
		if ( $rows ) {
			$data['rows']        = $rows;
			$data['hidden_rows'] = array();
			$data['hidden_cols'] = array();
			set_transient( 'sgnl_table_notice_' . get_current_user_id(), sprintf( __( 'CSV importiert: %1$d Zeilen, %2$d Spalten.', 'sgnl-core' ), count( $rows ), count( $rows[0] ) ), 60 );
		}
	}
	sgnl_table_save( $post_id, $data );
}, 10, 2 );

add_action( 'admin_notices', function () {
	$k   = 'sgnl_table_notice_' . get_current_user_id();
	$msg = get_transient( $k );
	if ( $msg ) {
		delete_transient( $k );
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
	}
} );

/**
 * CSV einlesen (Trennzeichen ; , oder Tab automatisch, UTF-8 oder Windows-1252).
 *
 * @param string $raw Dateiinhalt.
 * @return array
 */
function sgnl_table_parse_csv( $raw ) {
	$raw = (string) $raw;
	if ( 0 === strpos( $raw, "\xEF\xBB\xBF" ) ) {
		$raw = substr( $raw, 3 );
	} elseif ( ! mb_check_encoding( $raw, 'UTF-8' ) ) {
		$raw = mb_convert_encoding( $raw, 'UTF-8', 'Windows-1252' );
	}
	$first = strtok( $raw, "\n" );
	$delim = ';';
	$best  = -1;
	foreach ( array( ';', ',', "\t" ) as $c ) {
		$n = substr_count( (string) $first, $c );
		if ( $n > $best ) {
			$best  = $n;
			$delim = $c;
		}
	}
	$fh = fopen( 'php://temp', 'r+' );
	fwrite( $fh, $raw );
	rewind( $fh );
	$rows = array();
	while ( ( $r = fgetcsv( $fh, 0, $delim, '"', '' ) ) !== false ) {
		if ( array( null ) === $r ) {
			continue;
		}
		$rows[] = array_map( function ( $c ) {
			return wp_kses_post( (string) $c );
		}, $r );
	}
	fclose( $fh );
	return $rows;
}

/* CSV-Export */
add_action( 'admin_post_sgnl_table_csv', function () {
	$id = (int) ( $_GET['id'] ?? 0 );
	if ( ! $id || ! current_user_can( 'edit_post', $id ) || ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ?? '' ), 'sgnl_table_csv_' . $id ) ) {
		wp_die( esc_html__( 'Keine Berechtigung.', 'sgnl-core' ) );
	}
	$d    = sgnl_table_get( $id );
	$name = sanitize_file_name( get_the_title( $id ) ?: 'tabelle' ) . '.csv';
	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="' . $name . '"' );
	$out = fopen( 'php://output', 'w' );
	fwrite( $out, "\xEF\xBB\xBF" ); // Excel erkennt so UTF-8.
	foreach ( $d['rows'] as $row ) {
		fputcsv( $out, $row, ';', '"', '' );
	}
	fclose( $out );
	exit;
} );

/* =========================================================================
 * Übersicht
 * ====================================================================== */
add_filter( 'manage_sgnl_table_posts_columns', function ( $cols ) {
	$new = array();
	foreach ( $cols as $k => $v ) {
		$new[ $k ] = $v;
		if ( 'title' === $k ) {
			$new['sgnl_size']  = __( 'Größe', 'sgnl-core' );
			$new['sgnl_usage'] = __( 'Verwendet auf', 'sgnl-core' );
			$new['sgnl_code']  = __( 'Shortcode', 'sgnl-core' );
		}
	}
	return $new;
} );

add_action( 'manage_sgnl_table_posts_custom_column', function ( $col, $id ) {
	if ( 'sgnl_size' === $col ) {
		$d = sgnl_table_get( $id );
		/* translators: 1: Zeilen, 2: Spalten */
		printf( esc_html__( '%1$d × %2$d', 'sgnl-core' ), count( $d['rows'] ), count( $d['rows'][0] ) );
	}
	if ( 'sgnl_usage' === $col ) {
		$links = array();
		foreach ( sgnl_table_usage( $id ) as $p ) {
			$links[] = '<a href="' . esc_url( get_permalink( $p ) ) . '" target="_blank">' . esc_html( get_the_title( $p ) ) . '</a>';
		}
		echo $links ? implode( ', ', $links ) : '<span style="color:#646970">–</span>'; // phpcs:ignore
	}
	if ( 'sgnl_code' === $col ) {
		echo '<code>[sgnl_tabelle id="' . (int) $id . '"]</code>';
	}
}, 10, 2 );

/* Revision der Tabellendaten wiederherstellen (WordPress kann Meta-Revisionen ab 6.4) */
add_filter( 'wp_post_revision_meta_keys', function ( $keys, $post_type ) {
	if ( 'sgnl_table' === $post_type && ! in_array( SGNL_TABLE_META, $keys, true ) ) {
		$keys[] = SGNL_TABLE_META;
	}
	return $keys;
}, 10, 2 );

/* Revision auch speichern, wenn sich nur die Tabelle geändert hat */
add_filter( 'wp_save_post_revision_post_has_changed', function ( $changed, $last, $post ) {
	if ( 'sgnl_table' !== $post->post_type || $changed ) {
		return $changed;
	}
	return get_post_meta( $last->ID, SGNL_TABLE_META, true ) !== get_post_meta( $post->ID, SGNL_TABLE_META, true );
}, 10, 3 );
