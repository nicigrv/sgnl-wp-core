<?php
/**
 * Galerien einrichten: /galerie/ → /galerie/JJJJ/ → /galerie/JJJJ/album/
 *
 * Teilt die bisherigen Seiten "Galerie JJJJ" (je Abschnitt Überschrift + Galerie)
 * in eigene Album-Seiten auf, legt Jahres- und Auswahlseite mit dem Block
 * "Galerie-Übersicht" an, leitet die alten Adressen weiter und passt das Menü an.
 * Rückgängig machbar.
 *
 * @package sgnl-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SGNL_Gallery_Setup {

	const LOG    = 'sgnl_gallery_setup';
	const BACKUP = '_sgnl_backup_gallery';

	/** Kurze Adressen für bekannte Veranstaltungen */
	protected static function slug( $title ) {
		$slug  = sanitize_title( $title );
		$short = apply_filters( 'sgnl_gallery_slugs', array(
			'drei-laender-pokal'  => 'dlp',
			'drei-lander-pokal'   => 'dlp',
			'3-laender-pokal'     => 'dlp',
			'3-lander-pokal'      => 'dlp',
			'deutsche-mannschaftsmeisterschaften' => 'dms',
		) );
		return $short[ $slug ] ?? $slug;
	}

	/**
	 * Auswahlseite /galerie/.
	 *
	 * @return WP_Post|null
	 */
	public static function root() {
		$p = get_page_by_path( 'galerie' );
		return $p && in_array( $p->post_status, array( 'publish', 'draft', 'private' ), true ) ? $p : null;
	}

	/**
	 * Inhalt in Abschnitte (Überschrift + Galerie) zerlegen.
	 *
	 * @param WP_Post $page         Seite.
	 * @param int     $default_year Jahr, wenn keines im Titel steht.
	 * @return array[] title, year, blocks, images
	 */
	protected static function sections( $page, $default_year ) {
		$out = array();
		$cur = null;
		foreach ( parse_blocks( $page->post_content ) as $b ) {
			if ( 'core/heading' === $b['blockName'] ) {
				if ( $cur ) {
					$out[] = $cur;
				}
				$cur = array( 'title' => trim( html_entity_decode( wp_strip_all_tags( $b['innerHTML'] ), ENT_QUOTES, 'UTF-8' ) ), 'blocks' => array() );
				continue;
			}
			if ( null === $b['blockName'] && '' === trim( $b['innerHTML'] ) ) {
				continue;
			}
			if ( 'core/paragraph' === $b['blockName'] && '' === trim( wp_strip_all_tags( $b['innerHTML'] ) ) ) {
				continue;
			}
			if ( ! $cur ) {
				$cur = array( 'title' => '', 'blocks' => array() );
			}
			$cur['blocks'][] = $b;
		}
		if ( $cur ) {
			$out[] = $cur;
		}

		$result = array();
		foreach ( $out as $s ) {
			$has_gallery = false;
			foreach ( $s['blocks'] as $b ) {
				if ( in_array( $b['blockName'], array( 'core/gallery', 'core/image' ), true ) ) {
					$has_gallery = true;
				}
			}
			if ( ! $has_gallery ) {
				continue;
			}
			$year  = preg_match( '/\b((?:19|20)\d\d)\b/', $s['title'], $m ) ? (int) $m[1] : $default_year;
			$title = trim( preg_replace( '/[\s\-–,]*\b' . $year . '\b[\s\-–,]*/u', ' ', $s['title'] ) );
			$title = '' !== $title ? $title : sprintf( 'Fotos %d', $year );
			$html  = '';
			foreach ( $s['blocks'] as $b ) {
				$html .= serialize_block( $b ) . "\n\n";
			}
			$result[] = array(
				'title'  => $title,
				'year'   => $year,
				'slug'   => self::slug( $title ),
				'html'   => trim( $html ),
				'images' => sgnl_albums_image_ids( $html ),
				'from'   => $page->ID,
			);
		}
		return $result;
	}

	/**
	 * Was würde passieren?
	 *
	 * @return array root, sources, years
	 */
	public static function plan() {
		$root    = self::root();
		$sources = array();
		$albums  = array();

		$pages = get_posts( array( 'post_type' => 'page', 'post_status' => 'publish', 'numberposts' => -1, 's' => 'Galerie', 'search_columns' => array( 'post_title' ) ) );
		foreach ( $pages as $p ) {
			if ( ! preg_match( '/^galerie[\s\-]*((?:19|20)\d\d)$/i', trim( $p->post_title ), $m ) ) {
				continue;
			}
			if ( $root && in_array( $root->ID, get_post_ancestors( $p ), true ) ) {
				continue; // Schon in der neuen Struktur.
			}
			if ( false === strpos( $p->post_content, 'wp:gallery' ) && false === strpos( $p->post_content, 'wp:image' ) ) {
				continue;
			}
			$sources[ $p->ID ] = $p;
			foreach ( self::sections( $p, (int) $m[1] ) as $s ) {
				$albums[] = $s;
			}
		}
		// Galerien, die schon direkt auf /galerie/ liegen.
		if ( $root && ( false !== strpos( $root->post_content, 'wp:gallery' ) || false !== strpos( $root->post_content, 'wp:image' ) ) ) {
			foreach ( self::sections( $root, (int) get_post_time( 'Y', false, $root ) ) as $s ) {
				$albums[] = $s;
			}
		}

		$years = array();
		foreach ( $albums as $a ) {
			$years[ $a['year'] ][] = $a;
		}
		krsort( $years );
		return array( 'root' => $root, 'sources' => $sources, 'years' => $years );
	}

	/**
	 * Gibt es etwas einzurichten?
	 *
	 * @return bool
	 */
	public static function pending() {
		$plan = self::plan();
		return (bool) $plan['years'];
	}

	/**
	 * Einrichten.
	 *
	 * @return array albums, years
	 */
	public static function run() {
		$plan = self::plan();
		$log  = get_option( self::LOG, array() );
		$log  = wp_parse_args( is_array( $log ) ? $log : array(), array( 'created' => array(), 'backups' => array(), 'drafted' => array(), 'menu' => array(), 'redirects' => array() ) );

		$intro = "<!-- wp:paragraph -->\n<p>" . esc_html__( 'Fotos von Wettkämpfen, Ausflügen und dem Vereinsleben – einfach ein Jahr auswählen.', 'sgnl-core' ) . "</p>\n<!-- /wp:paragraph -->\n\n";
		$index = '<!-- wp:sgnl/albums {"align":"wide"} /-->';

		// Auswahlseite.
		$root = $plan['root'];
		if ( $root ) {
			if ( ! metadata_exists( 'post', $root->ID, self::BACKUP ) ) {
				update_post_meta( $root->ID, self::BACKUP, wp_slash( $root->post_content ) );
				$log['backups'][] = $root->ID;
			}
			wp_update_post( wp_slash( array( 'ID' => $root->ID, 'post_content' => $intro . $index, 'post_status' => 'publish' ) ) );
			$root_id = $root->ID;
		} else {
			$root_id = wp_insert_post( wp_slash( array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => __( 'Galerie', 'sgnl-core' ),
				'post_name'    => 'galerie',
				'post_content' => $intro . $index,
			) ) );
			$log['created'][] = $root_id;
		}

		$n_albums = 0;
		$n_years  = 0;
		$now      = time();
		foreach ( $plan['years'] as $year => $albums ) {
			// Jahresseite.
			$year_page = get_page_by_path( 'galerie/' . $year );
			if ( ! $year_page ) {
				$year_id = wp_insert_post( wp_slash( array(
					'post_type'    => 'page',
					'post_status'  => 'publish',
					/* translators: %d: Jahr */
					'post_title'   => sprintf( __( 'Galerie %d', 'sgnl-core' ), $year ),
					'post_name'    => (string) $year,
					'post_parent'  => $root_id,
					'post_content' => $index,
				) ) );
				global $wpdb;
				$wpdb->update( $wpdb->posts, array( 'post_name' => (string) $year ), array( 'ID' => $year_id ) );
				clean_post_cache( $year_id );
				$log['created'][] = $year_id;
				$n_years++;
			} else {
				$year_id = $year_page->ID;
			}

			// Alben.
			foreach ( $albums as $i => $a ) {
				$album_id = wp_insert_post( wp_slash( array(
					'post_type'     => 'page',
					'post_status'   => 'publish',
					'post_title'    => $a['title'],
					'post_name'     => $a['slug'],
					'post_parent'   => $year_id,
					'menu_order'    => $i,
					'post_date'     => wp_date( 'Y-m-d H:i:s', $now - $i * 60 ),
					'post_content'  => $a['html'],
				) ) );
				if ( ! $album_id || is_wp_error( $album_id ) ) {
					continue;
				}
				if ( $a['images'] ) {
					set_post_thumbnail( $album_id, $a['images'][0] );
				}
				$log['created'][] = $album_id;
				$n_albums++;
			}

			// Alte Seite "Galerie JJJJ" → Weiterleitung + Menü.
			foreach ( $plan['sources'] as $src ) {
				if ( ! preg_match( '/\b' . $year . '\b/', $src->post_title ) ) {
					continue;
				}
				$old_path = wp_make_link_relative( get_permalink( $src ) );
				$new_path = wp_make_link_relative( get_permalink( $year_id ) );
				$red      = sgnl_get_redirects();
				$red[]    = array( 'from' => $old_path, 'to' => $new_path, 'code' => 301 );
				update_option( 'sgnl_redirects', $red, true );
				$log['redirects'][] = $old_path;

				foreach ( wp_get_nav_menus() as $menu ) {
					foreach ( (array) wp_get_nav_menu_items( $menu->term_id ) as $item ) {
						if ( 'page' === $item->object && (int) $item->object_id === (int) $src->ID ) {
							update_post_meta( $item->ID, '_menu_item_object_id', $year_id );
							$log['menu'][ $item->ID ] = $src->ID;
						}
					}
				}
				wp_update_post( array( 'ID' => $src->ID, 'post_status' => 'draft' ) );
				$log['drafted'][] = $src->ID;
			}
		}

		update_option( self::LOG, $log, false );
		return array( 'albums' => $n_albums, 'years' => $n_years, 'root' => $root_id );
	}

	/**
	 * Rückgängig machen.
	 *
	 * @return bool
	 */
	public static function undo() {
		$log = get_option( self::LOG );
		if ( ! is_array( $log ) ) {
			return false;
		}
		foreach ( array_reverse( $log['created'] ?? array() ) as $id ) {
			wp_trash_post( (int) $id );
		}
		foreach ( $log['backups'] ?? array() as $id ) {
			wp_update_post( wp_slash( array( 'ID' => (int) $id, 'post_content' => get_post_meta( $id, self::BACKUP, true ) ) ) );
			delete_post_meta( $id, self::BACKUP );
		}
		foreach ( $log['drafted'] ?? array() as $id ) {
			wp_update_post( array( 'ID' => (int) $id, 'post_status' => 'publish' ) );
		}
		foreach ( $log['menu'] ?? array() as $item => $old ) {
			update_post_meta( (int) $item, '_menu_item_object_id', (int) $old );
		}
		if ( ! empty( $log['redirects'] ) ) {
			$red = array_values( array_filter( sgnl_get_redirects(), function ( $r ) use ( $log ) {
				return ! in_array( $r['from'] ?? '', $log['redirects'], true );
			} ) );
			update_option( 'sgnl_redirects', $red, true );
		}
		delete_option( self::LOG );
		return true;
	}

	/**
	 * Wurde schon eingerichtet?
	 *
	 * @return bool
	 */
	public static function done() {
		return is_array( get_option( self::LOG ) );
	}
}
