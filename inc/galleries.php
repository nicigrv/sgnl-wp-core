<?php
/**
 * Galerien.
 *
 * - Bilder in Galerien lassen sich immer per Klick vergrößern (Lightbox)
 * - Block "Galerie-Übersicht": zeigt die Unterseiten einer Seite als Kacheln
 *   (Titelbild + Titel + Anzahl Fotos), z. B. /galerie/ → /galerie/2026/ → /galerie/2026/dlp/
 * - Jahreszahlen als Seitenadresse (/galerie/2026/) sind möglich
 *
 * @package sgnl-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =========================================================================
 * Lightbox für alle Galeriebilder
 * ====================================================================== */
add_filter( 'render_block_data', function ( $block, $source, $parent = null ) {
	if ( 'core/image' !== ( $block['blockName'] ?? '' ) || ! $parent || 'core/gallery' !== ( $parent->name ?? '' ) ) {
		return $block;
	}
	$attrs = $block['attrs'] ?? array();
	$link  = $attrs['linkDestination'] ?? 'none';
	// Bilder mit eigenem Link oder ausdrücklich abgeschalteter Vergrößerung bleiben unverändert.
	if ( ( 'none' !== $link && '' !== $link ) || ( isset( $attrs['lightbox']['enabled'] ) && false === $attrs['lightbox']['enabled'] ) ) {
		return $block;
	}
	$block['attrs']['lightbox'] = array( 'enabled' => true );
	return $block;
}, 10, 3 );

/* =========================================================================
 * Block "Galerie-Übersicht"
 * ====================================================================== */
add_action( 'init', function () {
	wp_register_style( 'sgnl-albums', SGNL_CORE_URL . 'assets/albums.css', array(), sgnl_core_ver( 'assets/albums.css' ) );
	wp_register_script(
		'sgnl-albums-block',
		SGNL_CORE_URL . 'assets/albums-block.js',
		array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render', 'wp-data', 'wp-core-data' ),
		sgnl_core_ver( 'assets/albums-block.js' ),
		true
	);
	wp_localize_script( 'sgnl-albums-block', 'sgnlAlbumsBlock', array(
		'newUrl' => admin_url( 'post-new.php?post_type=page' ),
	) );
	register_block_type( 'sgnl/albums', array(
		'api_version'     => 3,
		'title'           => __( 'Galerie-Übersicht', 'sgnl-core' ),
		'description'     => __( 'Zeigt die Unterseiten als Kacheln mit Titelbild – z. B. alle Jahre oder alle Alben eines Jahres.', 'sgnl-core' ),
		'category'        => 'media',
		'icon'            => 'images-alt2',
		'keywords'        => array( 'galerie', 'alben', 'fotos', 'bilder', 'kacheln' ),
		'editor_script'   => 'sgnl-albums-block',
		'style'           => 'sgnl-albums',
		'supports'        => array( 'align' => array( 'wide', 'full' ), 'html' => false ),
		'uses_context'    => array( 'postId' ),
		'attributes'      => array(
			'parent' => array( 'type' => 'number', 'default' => 0 ),
			'align'  => array( 'type' => 'string' ),
		),
		'render_callback' => function ( $a, $content, $block ) {
			$parent = (int) ( $a['parent'] ?? 0 );
			if ( ! $parent ) {
				$parent = (int) ( $block->context['postId'] ?? 0 );
			}
			if ( ! $parent ) {
				$parent = (int) get_queried_object_id();
			}
			if ( ! $parent && isset( $_GET['post_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				$parent = absint( $_GET['post_id'] ); // Vorschau im Editor.
			}
			return sgnl_albums_render( $parent, $a['align'] ?? null );
		},
	) );
} );

/**
 * Unterseiten (veröffentlicht), sinnvoll sortiert.
 *
 * @param int $parent Seite.
 * @return WP_Post[]
 */
function sgnl_albums_children( $parent ) {
	$kids = get_posts( array(
		'post_type'      => 'page',
		'post_status'    => 'publish',
		'post_parent'    => (int) $parent,
		'posts_per_page' => -1,
		'orderby'        => array( 'menu_order' => 'ASC', 'date' => 'DESC' ),
		'no_found_rows'  => true,
	) );
	// Lauter Jahre? Dann neuestes Jahr zuerst.
	$years = array_filter( $kids, function ( $p ) {
		return (bool) preg_match( '/\b(19|20)\d\d\b/', $p->post_title );
	} );
	if ( $kids && count( $years ) === count( $kids ) ) {
		usort( $kids, function ( $a, $b ) {
			preg_match( '/\b((?:19|20)\d\d)\b/', $a->post_title, $ma );
			preg_match( '/\b((?:19|20)\d\d)\b/', $b->post_title, $mb );
			return (int) $mb[1] - (int) $ma[1] ?: $a->menu_order - $b->menu_order;
		} );
	}
	return $kids;
}

/**
 * Bild-IDs in einem Inhalt.
 *
 * @param string $content Inhalt.
 * @return int[]
 */
function sgnl_albums_image_ids( $content ) {
	preg_match_all( '/wp-image-(\d+)/', (string) $content, $m );
	return array_values( array_unique( array_map( 'intval', $m[1] ) ) );
}

/**
 * Titelbild, Fotoanzahl und Albenanzahl einer Seite.
 *
 * @param WP_Post $page  Seite.
 * @param int     $depth Tiefe (für Jahres-Seiten mit Alben).
 * @return array cover, photos, albums
 */
function sgnl_albums_info( $page, $depth = 0 ) {
	$ids    = sgnl_albums_image_ids( $page->post_content );
	$cover  = (int) get_post_thumbnail_id( $page );
	$photos = count( $ids );
	$albums = 0;
	if ( ! $cover && $ids ) {
		$cover = $ids[0];
	}
	if ( $depth < 2 ) {
		foreach ( sgnl_albums_children( $page->ID ) as $kid ) {
			$k = sgnl_albums_info( $kid, $depth + 1 );
			$albums++;
			$photos += $k['photos'];
			if ( ! $cover && $k['cover'] ) {
				$cover = $k['cover'];
			}
		}
	}
	return array( 'cover' => $cover, 'photos' => $photos, 'albums' => $albums );
}

/**
 * Kacheln ausgeben.
 *
 * @param int         $parent Elternseite.
 * @param string|null $align  Ausrichtung.
 * @return string
 */
function sgnl_albums_render( $parent, $align = null ) {
	wp_enqueue_style( 'sgnl-albums' );
	$kids  = $parent ? sgnl_albums_children( $parent ) : array();
	$align = $align ? $align : 'wide';
	$cls   = 'sgnl-albums' . ( in_array( $align, array( 'wide', 'full' ), true ) ? ' align' . $align : '' );

	if ( ! $kids ) {
		return current_user_can( 'edit_pages' )
			? '<p class="sgnl-albums__empty">' . esc_html__( 'Noch keine Unterseiten. Lege Unterseiten an (Seite → „Übergeordnete Seite“ = diese Seite) – sie erscheinen hier automatisch als Kacheln.', 'sgnl-core' ) . '</p>'
			: '';
	}

	$parent_title = get_the_title( $parent );
	$h            = '<div class="' . esc_attr( $cls ) . '">';
	foreach ( $kids as $i => $kid ) {
		$info  = sgnl_albums_info( $kid );
		$title = get_the_title( $kid );
		// "Galerie 2026" unter "Galerie" → "2026"; "DLP 2026" unter "2026" → "DLP".
		$short = trim( preg_replace( '/^\s*(Galerie|Fotos|Bilder)\s+/iu', '', $title ) );
		if ( preg_match( '/\b((?:19|20)\d\d)\b/', $parent_title, $py ) && $short !== $py[1] ) {
			$short = trim( preg_replace( '/[\s\-–]*\b' . $py[1] . '\b[\s\-–]*/u', ' ', $short ) );
		}
		$short = '' !== $short ? $short : $title;

		$meta = array();
		if ( $info['albums'] ) {
			/* translators: %d: Anzahl */
			$meta[] = sprintf( _n( '%d Album', '%d Alben', $info['albums'], 'sgnl-core' ), $info['albums'] );
		}
		if ( $info['photos'] ) {
			/* translators: %d: Anzahl */
			$meta[] = sprintf( _n( '%d Foto', '%d Fotos', $info['photos'], 'sgnl-core' ), $info['photos'] );
		}

		$img = $info['cover'] ? wp_get_attachment_image( $info['cover'], 'medium_large', false, array(
			'class'   => 'sgnl-album__img',
			'alt'     => '',
			'loading' => $i < 3 ? 'eager' : 'lazy',
			'sizes'   => '(max-width: 640px) 100vw, (max-width: 1100px) 50vw, 420px',
		) ) : '';

		$h .= '<a class="sgnl-album' . ( $info['albums'] ? ' is-collection' : '' ) . ( $img ? '' : ' no-image' ) . '" href="' . esc_url( get_permalink( $kid ) ) . '">'
			. '<span class="sgnl-album__media">' . ( $img ? $img : '<span class="sgnl-album__placeholder" aria-hidden="true"></span>' ) . '</span>'
			. '<span class="sgnl-album__body"><span class="sgnl-album__title">' . esc_html( $short ) . '</span>'
			. ( $meta ? '<span class="sgnl-album__meta">' . esc_html( implode( ' · ', $meta ) ) . '</span>' : '' )
			. '</span><span class="sgnl-album__go" aria-hidden="true"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg></span></a>';
	}
	return $h . '</div>';
}

/* =========================================================================
 * Jahreszahlen als Unterseiten-Adresse: /galerie/2026/
 * WordPress würde "2026" sonst als Seitenzahl von /galerie/ lesen bzw. beim
 * Speichern "2026-2" daraus machen.
 * ====================================================================== */
add_filter( 'wp_unique_post_slug', function ( $slug, $post_id, $status, $type, $parent, $original ) {
	if ( 'page' !== $type || ! $parent || ! preg_match( '/^(19|20)\d\d$/', (string) $original ) || $slug === $original ) {
		return $slug;
	}
	global $wpdb;
	$taken = $wpdb->get_var( $wpdb->prepare(
		"SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_type = 'page' AND post_parent = %d AND ID != %d AND post_status NOT IN ('trash','auto-draft') LIMIT 1",
		$original, $parent, $post_id
	) );
	return $taken ? $slug : $original;
}, 10, 6 );

add_filter( 'request', function ( $vars ) {
	if ( empty( $vars['pagename'] ) || empty( $vars['page'] ) ) {
		return $vars;
	}
	$path = trim( $vars['pagename'], '/' ) . '/' . trim( (string) $vars['page'], '/' );
	$page = get_page_by_path( $path );
	if ( $page ) {
		$vars['pagename'] = $path;
		unset( $vars['page'] );
	}
	return $vars;
} );
