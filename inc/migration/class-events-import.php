<?php
/**
 * Terminplan- und Archiv-Tabellen in "Termine" übernehmen.
 *
 * Erkennt Tabellen mit den Spalten "Datum" und "Veranstaltung", liest die Termine
 * (Spalten werden über die Überschriften zugeordnet), ersetzt die Tabellen auf den
 * Seiten durch den Block "Termine" und stellt die alten Tabellen auf Entwurf.
 * Rückgängig machbar.
 *
 * @package sgnl-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SGNL_Events_Import {

	const BACKUP = '_sgnl_backup_events';

	/**
	 * Text einer Zelle.
	 *
	 * @param string $c Zelle.
	 * @return string
	 */
	protected static function plain( $c ) {
		$t = html_entity_decode( wp_strip_all_tags( str_replace( array( '<br>', '<br/>', '<br />' ), ' ', (string) $c ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		return trim( preg_replace( '/\s+/u', ' ', $t ) );
	}

	/**
	 * Link einer Zelle.
	 *
	 * @param string $c Zelle.
	 * @return string
	 */
	protected static function href( $c ) {
		if ( preg_match( '/href\s*=\s*["\']([^"\']+)["\']/i', (string) $c, $m ) ) {
			return esc_url_raw( html_entity_decode( $m[1], ENT_QUOTES, 'UTF-8' ) );
		}
		$t = self::plain( $c );
		return preg_match( '#^https?://\S+$#i', $t ) ? esc_url_raw( $t ) : '';
	}

	/**
	 * Spalten anhand der Überschriften zuordnen.
	 *
	 * @param array $head Kopfzeile.
	 * @return array Feld => Spaltenindex
	 */
	protected static function map_columns( array $head ) {
		$map = array();
		foreach ( $head as $i => $c ) {
			$t = mb_strtolower( self::plain( $c ) );
			$f = null;
			if ( false !== strpos( $t, 'datum' ) ) {
				$f = 'date';
			} elseif ( false !== strpos( $t, 'veranstalt' ) || false !== strpos( $t, 'wettkampf' ) ) {
				$f = 'title';
			} elseif ( 'ort' === $t || 0 === strpos( $t, 'ort ' ) ) {
				$f = 'ort';
			} elseif ( false !== strpos( $t, 'bahn' ) ) {
				$f = 'bahn';
			} elseif ( false !== strpos( $t, 'mannschaft' ) ) {
				$f = 'team';
			} elseif ( false !== strpos( $t, 'ausschreib' ) ) {
				$f = 'ausschreibung';
			} elseif ( false !== strpos( $t, 'prot' ) && false !== strpos( $t, 'koll' ) ) {
				$f = 'protokoll';
			} elseif ( false !== strpos( $t, 'ergebnis' ) ) {
				$f = 'ergebnis';
			}
			if ( $f && ! isset( $map[ $f ] ) ) {
				$map[ $f ] = $i;
			}
		}
		return $map;
	}

	/**
	 * Passende Tabellen finden.
	 *
	 * @return array[] id, title, kind (upcoming|archive), year, map, table
	 */
	public static function sources() {
		$out = array();
		foreach ( get_posts( array( 'post_type' => 'sgnl_table', 'post_status' => array( 'publish', 'draft' ), 'numberposts' => -1, 'orderby' => 'ID', 'order' => 'ASC' ) ) as $p ) {
			$d = sgnl_table_get( $p->ID );
			if ( ! $d || empty( $d['rows'] ) ) {
				continue;
			}
			$map = self::map_columns( $d['rows'][0] );
			if ( ! isset( $map['date'], $map['title'] ) ) {
				continue;
			}
			if ( 'draft' === $p->post_status && ! get_post_meta( $p->ID, '_sgnl_events_prev_status', true ) ) {
				continue; // Entwürfe nur, wenn wir sie selbst umgestellt haben.
			}
			$year = preg_match( '/\b(20\d\d)\b/', $p->post_title, $m ) ? (int) $m[1] : 0;
			$kind = $year || isset( $map['protokoll'] ) || isset( $map['ergebnis'] ) ? 'archive' : 'upcoming';
			if ( 'archive' === $kind && ! $year ) {
				foreach ( sgnl_table_usage( $p->ID ) as $page ) {
					if ( preg_match( '/\b(20\d\d)\b/', $page->post_title . ' ' . $page->post_name, $m ) ) {
						$year = (int) $m[1];
						break;
					}
				}
			}
			$out[] = array(
				'id'    => $p->ID,
				'title' => $p->post_title,
				'kind'  => $kind,
				'year'  => $year,
				'map'   => $map,
				'table' => $d,
				'post'  => $p,
			);
		}
		return $out;
	}

	/**
	 * Datumsangaben einer Zelle lesen.
	 *
	 * @param string $text Text.
	 * @return array[] [Tag, Monat, Jahr|0]
	 */
	protected static function dates_in( $text ) {
		preg_match_all( '/(\d{1,2})\s*\.\s*(\d{1,2})(?:\s*\.\s*(\d{4}|\d{2})(?!\d))?/', $text, $m, PREG_SET_ORDER );
		$out = array();
		foreach ( $m as $x ) {
			$d = (int) $x[1];
			$mo = (int) $x[2];
			if ( $d < 1 || $d > 31 || $mo < 1 || $mo > 12 ) {
				continue;
			}
			$y = isset( $x[3] ) && '' !== $x[3] ? (int) $x[3] : 0;
			if ( $y && $y < 100 ) {
				$y += 2000;
			}
			$out[] = array( $d, $mo, $y );
		}
		return $out;
	}

	/**
	 * Termine einer Tabelle.
	 *
	 * @param array $src Quelle.
	 * @return array[]
	 */
	public static function parse( array $src ) {
		$rows = $src['table']['rows'];
		$map  = $src['map'];
		array_shift( $rows );

		// Jahr für den Terminplan: Jahr der letzten Änderung, ggf. Folgejahr.
		$mod       = strtotime( $src['post']->post_modified );
		$year      = $src['year'] ? $src['year'] : (int) gmdate( 'Y', $mod );
		$mod_month = (int) gmdate( 'n', $mod );
		$prev      = 0;
		$events    = array();

		foreach ( $rows as $r ) {
			$dates = self::dates_in( self::plain( $r[ $map['date'] ] ?? '' ) );
			$title = self::plain( $r[ $map['title'] ] ?? '' );
			if ( ! $dates || '' === $title ) {
				continue; // Monats-Zwischenzeile oder leer.
			}
			list( $d1, $m1, $y1 ) = $dates[0];
			if ( ! $y1 ) {
				if ( 'upcoming' === $src['kind'] ) {
					if ( ! $prev && $m1 < $mod_month - 4 ) {
						$year++;
					} elseif ( $prev && $m1 < $prev - 5 ) {
						$year++;
					}
				}
				$y1 = $year;
			}
			$prev  = $m1;
			$start = sprintf( '%04d-%02d-%02d', $y1, $m1, $d1 );
			$end   = '';
			if ( isset( $dates[1] ) ) {
				list( $d2, $m2, $y2 ) = $dates[1];
				$y2  = $y2 ? $y2 : ( $m2 < $m1 ? $y1 + 1 : $y1 );
				$end = sprintf( '%04d-%02d-%02d', $y2, $m2, $d2 );
				if ( ! checkdate( $m2, $d2, $y2 ) || $end <= $start ) {
					$end = '';
				}
			}
			if ( ! checkdate( $m1, $d1, $y1 ) ) {
				continue;
			}
			$e = array(
				'title'  => $title,
				'start'  => $start,
				'end'    => $end,
				'source' => $src['id'],
			);
			foreach ( array( 'ort', 'bahn', 'team' ) as $f ) {
				$e[ $f ] = isset( $map[ $f ] ) ? self::plain( $r[ $map[ $f ] ] ?? '' ) : '';
			}
			foreach ( array( 'ausschreibung', 'protokoll', 'ergebnis' ) as $f ) {
				$e[ $f ] = isset( $map[ $f ] ) ? self::href( $r[ $map[ $f ] ] ?? '' ) : '';
			}
			// Unter "Protokoll" verlinkte Ausschreibung richtig einsortieren.
			if ( $e['protokoll'] && false !== stripos( basename( $e['protokoll'] ), 'ausschreibung' ) ) {
				if ( ! $e['ausschreibung'] ) {
					$e['ausschreibung'] = $e['protokoll'];
				}
				$e['protokoll'] = '';
			}
			$events[] = $e;
		}
		return $events;
	}

	/**
	 * Gleiche Veranstaltung?
	 *
	 * @param array $a Termin.
	 * @param array $b Termin.
	 * @return bool
	 */
	protected static function same( array $a, array $b ) {
		if ( $a['start'] !== $b['start'] ) {
			return false;
		}
		$n = function ( $s ) {
			return preg_replace( '/[^a-z0-9äöüß]/u', '', mb_strtolower( $s ) );
		};
		if ( $a['ort'] && $b['ort'] && $n( $a['ort'] ) === $n( $b['ort'] ) ) {
			return true;
		}
		similar_text( $n( $a['title'] ), $n( $b['title'] ), $pct );
		return $pct >= 55;
	}

	/**
	 * Vorschau / Plan.
	 *
	 * @return array sources, events (zusammengeführt, mit 'existing'), pages
	 */
	public static function plan() {
		$sources = self::sources();
		// Archive zuerst, dann Terminplan (ergänzt Bahn/Mannschaft/Ausschreibung).
		usort( $sources, function ( $a, $b ) {
			return strcmp( $a['kind'], $b['kind'] ) ?: $a['id'] - $b['id'];
		} );

		$existing = array();
		foreach ( get_posts( array( 'post_type' => 'sgnl_event', 'post_status' => array( 'publish', 'draft', 'future', 'private' ), 'numberposts' => -1 ) ) as $p ) {
			$existing[] = sgnl_event_get( $p );
		}

		$events = array();
		foreach ( $sources as $src ) {
			foreach ( self::parse( $src ) as $e ) {
				$merged = false;
				foreach ( $events as &$x ) {
					// Zeilen derselben Tabelle sind immer verschiedene Termine.
					if ( $x['source'] !== $e['source'] && self::same( $x, $e ) ) {
						foreach ( $e as $k => $v ) {
							if ( '' === ( $x[ $k ] ?? '' ) && '' !== $v ) {
								$x[ $k ] = $v;
							}
						}
						$merged = true;
						break;
					}
				}
				unset( $x );
				if ( ! $merged ) {
					$e['existing'] = 0;
					foreach ( $existing as $ex ) {
						if ( self::same( $ex, $e ) ) {
							$e['existing'] = $ex['id'];
							break;
						}
					}
					$events[] = $e;
				}
			}
		}
		usort( $events, function ( $a, $b ) {
			return strcmp( $b['start'], $a['start'] );
		} );

		$pages = array();
		foreach ( $sources as $src ) {
			foreach ( sgnl_table_usage( $src['id'] ) as $page ) {
				$pages[ $page->ID ] = $page;
			}
		}
		return array( 'sources' => $sources, 'events' => $events, 'pages' => $pages );
	}

	/**
	 * Block, der eine Tabelle ersetzt.
	 *
	 * @param array $src Quelle.
	 * @return array Block
	 */
	protected static function events_block( array $src ) {
		$attrs = 'archive' === $src['kind']
			? array( 'mode' => 'archive', 'year' => (int) $src['year'], 'align' => 'wide' )
			: array( 'align' => 'wide' );
		return array(
			'blockName'    => 'sgnl/events',
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => '',
			'innerContent' => array(),
		);
	}

	/**
	 * Übernehmen.
	 *
	 * @return array created, merged, pages
	 */
	public static function run() {
		$plan    = self::plan();
		$created = 0;
		$merged  = 0;

		foreach ( $plan['events'] as $e ) {
			$id = (int) $e['existing'];
			if ( $id ) {
				$cur = sgnl_event_get( $id );
				foreach ( array_keys( sgnl_event_fields() ) as $k ) {
					if ( '' === $cur[ $k ] && '' !== ( $e[ $k ] ?? '' ) ) {
						$cur[ $k ] = $e[ $k ];
					}
				}
				sgnl_event_save_fields( $id, $cur );
				$merged++;
				continue;
			}
			$id = wp_insert_post( array(
				'post_type'   => 'sgnl_event',
				'post_status' => 'publish',
				'post_title'  => $e['title'],
			), true );
			if ( is_wp_error( $id ) ) {
				continue;
			}
			sgnl_event_save_fields( $id, $e );
			update_post_meta( $id, '_sgnl_imported', (int) $e['source'] );
			$created++;
		}

		// Seiten umstellen.
		$by_table = array();
		foreach ( $plan['sources'] as $src ) {
			$by_table[ $src['id'] ] = $src;
		}
		$pages = 0;
		foreach ( $plan['pages'] as $page ) {
			$blocks  = parse_blocks( $page->post_content );
			$changed = false;
			$out     = array();
			foreach ( $blocks as $b ) {
				if ( 'sgnl/table' === $b['blockName'] && isset( $by_table[ (int) ( $b['attrs']['id'] ?? 0 ) ] ) ) {
					$out[]   = self::events_block( $by_table[ (int) $b['attrs']['id'] ] );
					$changed = true;
					continue;
				}
				$out[] = $b;
			}
			// Alte Download-Box "Terminplan herunterladen" entfernen (PDF kommt jetzt automatisch).
			foreach ( $out as $i => $b ) {
				if ( 'core/group' !== $b['blockName'] ) {
					continue;
				}
				$html = mb_strtolower( wp_strip_all_tags( serialize_block( $b ) ) );
				if ( false !== strpos( $html, 'terminplan' ) && ( false !== strpos( $html, 'herunterladen' ) || false !== strpos( $html, 'download' ) ) ) {
					unset( $out[ $i ] );
					$prev = $i - 1;
					while ( $prev >= 0 && isset( $out[ $prev ] ) && null === $out[ $prev ]['blockName'] && '' === trim( $out[ $prev ]['innerHTML'] ) ) {
						$prev--;
					}
					if ( isset( $out[ $prev ] ) && 'core/spacer' === $out[ $prev ]['blockName'] ) {
						unset( $out[ $prev ] );
					}
					$changed = true;
				}
			}
			if ( ! $changed ) {
				continue;
			}
			if ( ! metadata_exists( 'post', $page->ID, self::BACKUP ) ) {
				update_post_meta( $page->ID, self::BACKUP, wp_slash( $page->post_content ) );
			}
			wp_update_post( wp_slash( array(
				'ID'           => $page->ID,
				'post_content' => serialize_blocks( array_values( $out ) ),
			) ) );
			$pages++;
		}

		// Alte Tabellen auf Entwurf (bleiben erhalten).
		foreach ( $plan['sources'] as $src ) {
			if ( 'publish' === $src['post']->post_status ) {
				update_post_meta( $src['id'], '_sgnl_events_prev_status', 'publish' );
				wp_update_post( array( 'ID' => $src['id'], 'post_status' => 'draft' ) );
			}
		}

		update_option( 'sgnl_events_imported', time(), false );
		delete_transient( 'sgnl_events_daily' );
		return array( 'created' => $created, 'merged' => $merged, 'pages' => $pages );
	}

	/**
	 * Rückgängig.
	 *
	 * @return int Wiederhergestellte Seiten
	 */
	public static function undo() {
		global $wpdb;
		$n = 0;
		foreach ( $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s", self::BACKUP ) ) as $id ) {
			wp_update_post( wp_slash( array(
				'ID'           => (int) $id,
				'post_content' => get_post_meta( $id, self::BACKUP, true ),
			) ) );
			delete_post_meta( $id, self::BACKUP );
			$n++;
		}
		foreach ( $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s", '_sgnl_events_prev_status' ) ) as $id ) {
			wp_update_post( array( 'ID' => (int) $id, 'post_status' => get_post_meta( $id, '_sgnl_events_prev_status', true ) ) );
			delete_post_meta( $id, '_sgnl_events_prev_status' );
		}
		foreach ( $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s", '_sgnl_imported' ) ) as $id ) {
			wp_trash_post( (int) $id );
		}
		delete_option( 'sgnl_events_imported' );
		return $n;
	}

	/**
	 * Gibt es etwas zu übernehmen?
	 *
	 * @return bool
	 */
	public static function pending() {
		if ( get_option( 'sgnl_events_imported' ) ) {
			return false;
		}
		foreach ( self::sources() as $s ) {
			if ( 'publish' === $s['post']->post_status ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Wurde schon übernommen (für "Rückgängig")?
	 *
	 * @return bool
	 */
	public static function has_backup() {
		global $wpdb;
		return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key IN (%s, %s)", self::BACKUP, '_sgnl_imported' ) );
	}
}
