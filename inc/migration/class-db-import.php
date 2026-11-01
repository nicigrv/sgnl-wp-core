<?php
/**
 * Datenbank (datenbank.sgnl.de) in die Website übernehmen.
 *
 * 1. Wettkämpfe + Nachrichten aus dem phpMyAdmin-Export (.sql) in die Termine
 *    übernehmen (passende Termine werden ergänzt, fehlende angelegt).
 * 2. Seite /datenbank/ (+ optional passwortgeschützte Trainer-Seite) anlegen,
 *    Menüpunkte und Links von datenbank.sgnl.de auf die neue Seite umstellen.
 *
 * @package sgnl-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SGNL_DB_Import {

	const LOG    = 'sgnl_db_setup';
	const BACKUP = '_sgnl_backup_db';

	/* ---------------------------------------------------------------------
	 * SQL lesen
	 * ------------------------------------------------------------------ */

	/**
	 * Zeilen aus INSERT-Anweisungen einer Tabelle.
	 *
	 * @param string $sql   SQL-Export.
	 * @param string $table Tabelle.
	 * @return array[]
	 */
	public static function rows( $sql, $table ) {
		$out = array();
		$re  = '/INSERT\s+INTO\s+`?' . preg_quote( $table, '/' ) . '`?\s*\(([^)]*)\)\s*VALUES\s*/i';
		$off = 0;
		while ( preg_match( $re, $sql, $m, PREG_OFFSET_CAPTURE, $off ) ) {
			$cols = array_map( function ( $c ) {
				return trim( $c, " `\t\r\n" );
			}, explode( ',', $m[1][0] ) );
			$i    = $m[0][1] + strlen( $m[0][0] );
			$n    = strlen( $sql );
			while ( $i < $n ) {
				while ( $i < $n && ctype_space( $sql[ $i ] ) ) {
					$i++;
				}
				if ( $i >= $n || '(' !== $sql[ $i ] ) {
					break;
				}
				$i++;
				$vals = array();
				while ( $i < $n ) {
					while ( $i < $n && ctype_space( $sql[ $i ] ) ) {
						$i++;
					}
					if ( "'" === $sql[ $i ] || '"' === $sql[ $i ] ) {
						$q   = $sql[ $i++ ];
						$val = '';
						while ( $i < $n ) {
							$c = $sql[ $i ];
							if ( '\\' === $c && $i + 1 < $n ) {
								$nx   = $sql[ $i + 1 ];
								$map  = array( 'n' => "\n", 'r' => "\r", 't' => "\t", '0' => "\0", 'Z' => "\x1a" );
								$val .= $map[ $nx ] ?? $nx;
								$i   += 2;
								continue;
							}
							if ( $c === $q ) {
								if ( $i + 1 < $n && $sql[ $i + 1 ] === $q ) {
									$val .= $q;
									$i   += 2;
									continue;
								}
								$i++;
								break;
							}
							$val .= $c;
							$i++;
						}
						$vals[] = $val;
					} else {
						$start = $i;
						while ( $i < $n && ',' !== $sql[ $i ] && ')' !== $sql[ $i ] ) {
							$i++;
						}
						$tok    = trim( substr( $sql, $start, $i - $start ) );
						$vals[] = 'NULL' === strtoupper( $tok ) ? null : $tok;
					}
					while ( $i < $n && ctype_space( $sql[ $i ] ) ) {
						$i++;
					}
					if ( ',' === $sql[ $i ] ) {
						$i++;
						continue;
					}
					if ( ')' === $sql[ $i ] ) {
						$i++;
						break;
					}
				}
				if ( count( $vals ) === count( $cols ) ) {
					$out[] = array_combine( $cols, $vals );
				}
				while ( $i < $n && ctype_space( $sql[ $i ] ) ) {
					$i++;
				}
				if ( $i < $n && ',' === $sql[ $i ] ) {
					$i++;
					continue;
				}
				break;
			}
			$off = $i;
		}
		return $out;
	}

	/**
	 * Wettkämpfe + Nachrichten aus dem Export.
	 *
	 * @param string $sql SQL.
	 * @return array[] Wettkämpfe (mit 'nachrichten')
	 */
	public static function parse( $sql ) {
		$wk  = self::rows( $sql, 'wettkaempfe' );
		$msg = self::rows( $sql, 'wettkampf_nachrichten' );
		$tz  = wp_timezone();
		$out = array();
		foreach ( $wk as $r ) {
			$s = (int) $r['start_ts'];
			$e = (int) ( $r['end_ts'] ?? $s );
			if ( ! $s || '' === trim( (string) $r['name'] ) ) {
				continue;
			}
			$ds  = ( new DateTimeImmutable( '@' . $s ) )->setTimezone( $tz );
			$de  = ( new DateTimeImmutable( '@' . max( $s, $e ) ) )->setTimezone( $tz );
			$row = array(
				'wk_id'           => (int) $r['id'],
				'title'           => trim( (string) $r['name'] ),
				'ort'             => trim( (string) ( $r['ort'] ?? '' ) ),
				'start'           => $ds->format( 'Y-m-d' ),
				'end'             => $de->format( 'Y-m-d' ) !== $ds->format( 'Y-m-d' ) ? $de->format( 'Y-m-d' ) : '',
				'start_time'      => '00:00' === $ds->format( 'H:i' ) ? '' : $ds->format( 'H:i' ),
				'end_time'        => in_array( $de->format( 'H:i' ), array( '00:00', '23:59' ), true ) ? '' : $de->format( 'H:i' ),
				'meldeuebersicht' => (string) ( $r['meldeuebersicht_url'] ?? '' ),
				'meldeergebnis'   => (string) ( $r['meldeergebnis_url'] ?? '' ),
				'livetiming'      => (string) ( $r['livetiming_url'] ?? '' ),
				'protokoll'       => (string) ( $r['protokoll_url'] ?? '' ),
				'nachrichten'     => array(),
			);
			foreach ( $msg as $m ) {
				if ( (int) $m['wettkampf_id'] === $row['wk_id'] ) {
					$row['nachrichten'][] = array(
						'id'    => 'wk' . (int) $m['id'],
						'text'  => (string) $m['nachricht'],
						'autor' => (string) $m['autor_name'],
						'zeit'  => (int) $m['created_at'],
					);
				}
			}
			$out[] = $row;
		}
		return $out;
	}

	/**
	 * Termine den Wettkämpfen zuordnen (jeder Termin höchstens einmal).
	 *
	 * @param array[] $rows Wettkämpfe.
	 * @return int[] wk_id => Termin-ID (0 = neu anlegen)
	 */
	public static function assign( array $rows ) {
		$result = array();
		$pairs  = array();
		foreach ( $rows as $row ) {
			$result[ $row['wk_id'] ] = 0;
			$by_id = get_posts( array( 'post_type' => 'sgnl_event', 'post_status' => 'any', 'meta_key' => '_sgnl_wk_id', 'meta_value' => (string) $row['wk_id'], 'fields' => 'ids', 'numberposts' => 1 ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
			if ( $by_id ) {
				$pairs[] = array( 1000, $row['wk_id'], (int) $by_id[0] );
				continue;
			}
			$cands = get_posts( array( 'post_type' => 'sgnl_event', 'post_status' => array( 'publish', 'draft', 'future', 'private' ), 'meta_key' => '_sgnl_start', 'meta_value' => $row['start'], 'numberposts' => 20 ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
			foreach ( $cands as $p ) {
				$e   = sgnl_event_get( $p );
				$pct = sgnl_event_match_score( $e['title'], $e['ort'], $row['title'], $row['ort'] );
				if ( $pct >= 50 ) {
					$pairs[] = array( $pct, $row['wk_id'], $p->ID );
				}
			}
		}
		usort( $pairs, function ( $a, $b ) {
			return $b[0] <=> $a[0];
		} );
		$taken = array();
		foreach ( $pairs as $pr ) {
			if ( $result[ $pr[1] ] || isset( $taken[ $pr[2] ] ) ) {
				continue;
			}
			$result[ $pr[1] ] = $pr[2];
			$taken[ $pr[2] ]  = true;
		}
		return $result;
	}

	/**
	 * Alle Termine an diesem Tag (zur Auswahl in der Vorschau).
	 *
	 * @param string $ymd Datum.
	 * @return WP_Post[]
	 */
	public static function candidates( $ymd ) {
		return get_posts( array( 'post_type' => 'sgnl_event', 'post_status' => array( 'publish', 'draft', 'future', 'private' ), 'meta_key' => '_sgnl_start', 'meta_value' => $ymd, 'numberposts' => 30, 'orderby' => 'title', 'order' => 'ASC' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	}

	/**
	 * Übernehmen.
	 *
	 * @param array[] $rows Wettkämpfe.
	 * @return array created, merged, messages
	 */
	public static function run( array $rows, array $manual = array() ) {
		$created = 0;
		$merged  = 0;
		$msgs    = 0;
		$map     = array_replace( self::assign( $rows ), $manual );
		foreach ( $rows as $row ) {
			$id = $map[ $row['wk_id'] ];
			if ( $id ) {
				$cur = sgnl_event_get( $id );
				foreach ( array( 'end', 'start_time', 'end_time', 'ort', 'meldeuebersicht', 'meldeergebnis', 'livetiming', 'protokoll' ) as $k ) {
					if ( '' === (string) $cur[ $k ] && '' !== (string) $row[ $k ] ) {
						$cur[ $k ] = $row[ $k ];
					}
				}
				sgnl_event_save_fields( $id, $cur );
				$merged++;
			} else {
				$id = wp_insert_post( array( 'post_type' => 'sgnl_event', 'post_status' => 'publish', 'post_title' => $row['title'] ), true );
				if ( is_wp_error( $id ) ) {
					continue;
				}
				sgnl_event_save_fields( $id, $row );
				$created++;
			}
			update_post_meta( $id, '_sgnl_wk_id', (string) $row['wk_id'] );
			if ( ! wp_get_object_terms( $id, 'sgnl_event_cat', array( 'fields' => 'ids' ) ) ) {
				wp_set_object_terms( $id, 'wettkampf', 'sgnl_event_cat' );
			}
			if ( $row['nachrichten'] ) {
				$have = get_post_meta( $id, '_sgnl_messages', true );
				$have = is_array( $have ) ? $have : array();
				$ids  = wp_list_pluck( $have, 'id' );
				foreach ( $row['nachrichten'] as $m ) {
					if ( ! in_array( $m['id'], $ids, true ) ) {
						$have[] = $m;
						$msgs++;
					}
				}
				update_post_meta( $id, '_sgnl_messages', $have );
			}
		}
		update_option( 'sgnl_db_imported', time(), false );
		return array( 'created' => $created, 'merged' => $merged, 'messages' => $msgs );
	}

	/* ---------------------------------------------------------------------
	 * Seiten, Menü, Links
	 * ------------------------------------------------------------------ */

	/**
	 * Wurde schon eingerichtet?
	 *
	 * @return bool
	 */
	public static function done() {
		return is_array( get_option( self::LOG ) );
	}

	/**
	 * Was zeigt noch auf datenbank.sgnl.de?
	 *
	 * @return array menu (Items), posts (IDs)
	 */
	public static function references() {
		global $wpdb;
		$menu = array();
		foreach ( wp_get_nav_menus() as $m ) {
			foreach ( (array) wp_get_nav_menu_items( $m->term_id ) as $item ) {
				if ( 'custom' === $item->type && false !== stripos( $item->url, 'datenbank.sgnl.de' ) ) {
					$menu[] = $item;
				}
			}
		}
		$posts = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_status IN ('publish','draft','private','future') AND post_type NOT IN ('revision','nav_menu_item') AND post_content LIKE '%datenbank.sgnl.de%'" );
		return array( 'menu' => $menu, 'posts' => array_map( 'intval', $posts ) );
	}

	/**
	 * Alte Adresse → neue Seite (mit Reiter).
	 *
	 * @param string $html    Inhalt/URL.
	 * @param string $db_url  /datenbank/.
	 * @param string $tr_url  Trainer-Seite (oder '').
	 * @return string
	 */
	public static function rewrite( $html, $db_url, $tr_url ) {
		if ( $tr_url ) {
			$html = preg_replace( '#https?://datenbank\.sgnl\.de/trainer(?:\.html)?/?#i', $tr_url, $html );
		}
		return preg_replace_callback( '#https?://datenbank\.sgnl\.de(?:/index\.html)?/?(\#[A-Za-z0-9_-]+)?(?=["\'\s<]|$)#i', function ( $m ) use ( $db_url ) {
			return $db_url . ( $m[1] ?? '' );
		}, $html );
	}

	/**
	 * Einrichten.
	 *
	 * @param string $trainer_pw Passwort für die Trainer-Seite ('' = keine Trainer-Seite).
	 * @return array page, trainer, menu, posts
	 */
	public static function setup( $trainer_pw ) {
		$log = array( 'created' => array(), 'backups' => array(), 'menu' => array() );

		$page = get_page_by_path( 'datenbank' );
		if ( ! $page ) {
			$id = wp_insert_post( wp_slash( array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => __( 'Datenbank', 'sgnl-core' ),
				'post_name'    => 'datenbank',
				'post_content' => '<!-- wp:sgnl/datenbank {"align":"wide"} /-->',
			) ) );
			$log['created'][] = $id;
		} else {
			$id = $page->ID;
			if ( false === strpos( $page->post_content, 'wp:sgnl/datenbank' ) ) {
				update_post_meta( $id, self::BACKUP, wp_slash( $page->post_content ) );
				$log['backups'][] = $id;
				wp_update_post( wp_slash( array( 'ID' => $id, 'post_content' => $page->post_content . "\n\n" . '<!-- wp:sgnl/datenbank {"align":"wide"} /-->' ) ) );
			}
		}
		$db_url = get_permalink( $id );

		$tr_id = 0;
		if ( '' !== $trainer_pw ) {
			$tr = get_page_by_path( 'datenbank/trainer' );
			if ( $tr ) {
				$tr_id = $tr->ID;
				wp_update_post( array( 'ID' => $tr_id, 'post_password' => $trainer_pw ) );
			} else {
				$tr_id = wp_insert_post( wp_slash( array(
					'post_type'     => 'page',
					'post_status'   => 'publish',
					'post_title'    => __( 'Trainerbereich', 'sgnl-core' ),
					'post_name'     => 'trainer',
					'post_parent'   => $id,
					'post_password' => $trainer_pw,
					'post_content'  => '<!-- wp:sgnl/datenbank {"tabs":"schwimmer,meldungen","align":"wide"} /-->',
				) ) );
				$log['created'][] = $tr_id;
			}
		}
		$tr_url = $tr_id ? get_permalink( $tr_id ) : '';

		$refs = self::references();
		foreach ( $refs['menu'] as $item ) {
			$new = self::rewrite( $item->url, $db_url, $tr_url );
			if ( $new !== $item->url ) {
				$log['menu'][ $item->ID ] = $item->url;
				update_post_meta( $item->ID, '_menu_item_url', esc_url_raw( $new ) );
			}
		}
		$n_posts = 0;
		foreach ( $refs['posts'] as $pid ) {
			$p   = get_post( $pid );
			$new = self::rewrite( $p->post_content, $db_url, $tr_url );
			if ( $new === $p->post_content ) {
				continue;
			}
			if ( ! metadata_exists( 'post', $pid, self::BACKUP ) ) {
				update_post_meta( $pid, self::BACKUP, wp_slash( $p->post_content ) );
				$log['backups'][] = $pid;
			}
			wp_update_post( wp_slash( array( 'ID' => $pid, 'post_content' => $new ) ) );
			$n_posts++;
		}
		update_option( self::LOG, $log, false );
		return array( 'page' => $id, 'trainer' => $tr_id, 'menu' => count( $log['menu'] ), 'posts' => $n_posts );
	}

	/**
	 * Rückgängig.
	 */
	public static function undo() {
		$log = get_option( self::LOG );
		if ( ! is_array( $log ) ) {
			return;
		}
		foreach ( $log['menu'] as $item => $url ) {
			update_post_meta( (int) $item, '_menu_item_url', esc_url_raw( $url ) );
		}
		foreach ( array_unique( $log['backups'] ) as $pid ) {
			wp_update_post( wp_slash( array( 'ID' => (int) $pid, 'post_content' => get_post_meta( $pid, self::BACKUP, true ) ) ) );
			delete_post_meta( $pid, self::BACKUP );
		}
		foreach ( array_reverse( $log['created'] ) as $pid ) {
			wp_trash_post( (int) $pid );
		}
		delete_option( self::LOG );
	}
}
