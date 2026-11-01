<?php
/**
 * Terminplan als PDF – kleiner eigener PDF-Schreiber (ohne Bibliothek, ohne Composer).
 *
 * A4 hochkant, Schrift Helvetica (in jedem PDF-Programm vorhanden), Umlaute über WinAnsi.
 *
 * @package sgnl-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SGNL_Events_PDF {

	const W = 595.28;
	const H = 841.89;
	const M = 40;

	/** Zeichenbreiten (1/1000 em) für Zeichen 32–255 in WinAnsi */
	const WIDTHS = array(
		'r' => array(278,278,355,556,556,889,667,191,333,333,389,584,278,333,278,278,556,556,556,556,556,556,556,556,556,556,278,278,584,584,584,556,1015,667,667,722,722,667,611,778,722,278,500,667,556,833,722,778,667,778,722,667,611,722,667,944,667,667,611,278,278,278,469,556,333,556,556,500,556,556,278,556,556,222,222,500,222,833,556,556,556,556,333,500,278,556,500,722,500,500,500,334,260,334,584,761,556,0,222,556,333,1000,556,556,333,1000,667,333,1000,0,611,0,0,222,222,333,333,350,556,1000,333,1000,500,333,944,0,500,667,278,333,556,556,556,556,260,556,333,737,370,556,584,333,737,333,400,584,333,333,333,556,537,278,333,333,365,556,834,834,834,611,667,667,667,667,667,667,1000,722,667,667,667,667,278,278,278,278,722,722,778,778,778,778,778,584,778,722,722,722,722,667,667,611,556,556,556,556,556,556,889,500,556,556,556,556,278,278,278,278,556,556,556,556,556,556,556,584,611,556,556,556,556,500,556,500),
		'b' => array(278,333,474,556,556,889,722,238,333,333,389,584,278,333,278,278,556,556,556,556,556,556,556,556,556,556,333,333,584,584,584,611,975,722,722,722,722,667,611,778,722,278,556,722,611,833,722,778,667,778,722,667,611,722,667,944,667,667,611,333,278,333,584,556,333,556,611,556,611,556,333,611,611,278,278,556,278,889,611,611,611,611,389,556,333,611,556,778,556,556,500,389,280,389,584,761,556,0,278,556,500,1000,556,556,333,1000,667,333,1000,0,611,0,0,278,278,500,500,350,556,1000,333,1000,556,333,944,0,500,667,278,333,556,556,556,556,280,556,333,737,370,556,584,333,737,333,400,584,333,333,333,611,556,278,333,333,365,556,834,834,834,611,722,722,722,722,722,722,1000,722,667,667,667,667,278,278,278,278,722,722,778,778,778,778,778,584,778,722,722,722,722,667,667,611,556,556,556,556,556,556,889,556,556,556,556,556,278,278,278,278,611,611,611,611,611,611,611,584,611,611,611,611,611,556,611,556)
	);

	/** @var array Seiten: [ 'ops' => string, 'links' => [] ] */
	protected $pages = array();
	/** @var int */
	protected $p = -1;

	/* ---------------- Grundfunktionen ---------------- */

	/**
	 * UTF-8 → WinAnsi.
	 *
	 * @param string $s Text.
	 * @return string
	 */
	public static function enc( $s ) {
		$s = str_replace( array( "\u{2009}", "\u{202F}", "\u{00A0}" ), ' ', (string) $s );
		$o = function_exists( 'iconv' ) ? @iconv( 'UTF-8', 'Windows-1252//TRANSLIT', $s ) : false; // phpcs:ignore
		if ( false === $o && function_exists( 'mb_convert_encoding' ) ) {
			$o = mb_convert_encoding( $s, 'Windows-1252', 'UTF-8' );
		}
		return false === $o ? preg_replace( '/[^\x20-\x7E]/', '?', $s ) : $o;
	}

	/**
	 * Textbreite in pt.
	 *
	 * @param string $s    WinAnsi-Text.
	 * @param bool   $bold Fett.
	 * @param float  $size Größe.
	 * @return float
	 */
	public static function tw( $s, $bold, $size ) {
		$t = self::WIDTHS[ $bold ? 'b' : 'r' ];
		$w = 0;
		$n = strlen( $s );
		for ( $i = 0; $i < $n; $i++ ) {
			$c  = ord( $s[ $i ] );
			$w += $c >= 32 ? ( $t[ $c - 32 ] ? $t[ $c - 32 ] : 556 ) : 0;
		}
		return $w * $size / 1000;
	}

	/**
	 * Zeilenumbruch.
	 *
	 * @param string $s    WinAnsi-Text.
	 * @param bool   $bold Fett.
	 * @param float  $size Größe.
	 * @param float  $max  Breite.
	 * @return string[]
	 */
	public static function wrap( $s, $bold, $size, $max ) {
		$lines = array();
		foreach ( preg_split( '/\r?\n/', trim( $s ) ) as $para ) {
			$line = '';
			foreach ( preg_split( '/ +/', $para ) as $word ) {
				$try = '' === $line ? $word : $line . ' ' . $word;
				if ( self::tw( $try, $bold, $size ) <= $max ) {
					$line = $try;
					continue;
				}
				if ( '' !== $line ) {
					$lines[] = $line;
				}
				// Sehr lange Wörter hart trennen.
				while ( self::tw( $word, $bold, $size ) > $max && strlen( $word ) > 1 ) {
					$cut = strlen( $word ) - 1;
					while ( $cut > 1 && self::tw( substr( $word, 0, $cut ), $bold, $size ) > $max ) {
						$cut--;
					}
					$lines[] = substr( $word, 0, $cut );
					$word    = substr( $word, $cut );
				}
				$line = $word;
			}
			$lines[] = $line;
		}
		return $lines;
	}

	/** Neue Seite */
	public function add_page() {
		$this->pages[] = array( 'ops' => '', 'links' => array() );
		$this->p       = count( $this->pages ) - 1;
	}

	/**
	 * Farbe "#RRGGBB" → "r g b".
	 *
	 * @param string $hex Farbe.
	 * @return string
	 */
	protected static function rgb( $hex ) {
		$hex = ltrim( $hex, '#' );
		return sprintf( '%.3F %.3F %.3F', hexdec( substr( $hex, 0, 2 ) ) / 255, hexdec( substr( $hex, 2, 2 ) ) / 255, hexdec( substr( $hex, 4, 2 ) ) / 255 );
	}

	/**
	 * Rechteck füllen (y von oben gemessen).
	 */
	public function rect( $x, $y, $w, $h, $color ) {
		$this->pages[ $this->p ]['ops'] .= sprintf( "%s rg %.2F %.2F %.2F %.2F re f\n", self::rgb( $color ), $x, self::H - $y - $h, $w, $h );
	}

	/**
	 * Linie (waagerecht).
	 */
	public function hline( $x1, $x2, $y, $color, $width = 0.5 ) {
		$this->pages[ $this->p ]['ops'] .= sprintf( "%.2F w %s RG %.2F %.2F m %.2F %.2F l S\n", $width, self::rgb( $color ), $x1, self::H - $y, $x2, self::H - $y );
	}

	/**
	 * Text (y = Grundlinie, von oben gemessen). Text bereits WinAnsi.
	 */
	public function text( $x, $y, $s, $size, $bold = false, $color = '#2F3D55' ) {
		$s = str_replace( array( '\\', '(', ')', "\r" ), array( '\\\\', '\\(', '\\)', '' ), $s );
		$this->pages[ $this->p ]['ops'] .= sprintf( "BT /%s %.2F Tf %s rg %.2F %.2F Td (%s) Tj ET\n", $bold ? 'F2' : 'F1', $size, self::rgb( $color ), $x, self::H - $y, $s );
	}

	/**
	 * Klickbarer Bereich.
	 */
	public function link( $x, $y, $w, $h, $url ) {
		$this->pages[ $this->p ]['links'][] = array( $x, self::H - $y - $h, $x + $w, self::H - $y, $url );
	}

	/**
	 * Fertiges PDF.
	 *
	 * @param string $title Titel.
	 * @return string
	 */
	public function output( $title ) {
		$objs = array();
		$n    = count( $this->pages );
		// 1 Katalog, 2 Seitenbaum, 3/4 Schriften, 5 Info, danach je Seite: Seite, Inhalt, Links.
		$next  = 6;
		$kids  = array();
		$plan  = array();
		foreach ( $this->pages as $i => $pg ) {
			$pid    = $next++;
			$cid    = $next++;
			$lids   = array();
			foreach ( $pg['links'] as $l ) {
				$lids[] = $next++;
			}
			$kids[] = $pid . ' 0 R';
			$plan[]  = array( $pid, $cid, $lids );
		}
		$objs[1] = '<< /Type /Catalog /Pages 2 0 R >>';
		$objs[2] = '<< /Type /Pages /Kids [' . implode( ' ', $kids ) . '] /Count ' . $n . ' >>';
		$objs[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
		$objs[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
		$objs[5] = '<< /Title ' . self::pdf_string( $title ) . ' /Author ' . self::pdf_string( get_bloginfo( 'name' ) ) . ' /Producer (SG Neptun Funktionen) /CreationDate (D:' . gmdate( 'YmdHis' ) . "Z) >>";
		foreach ( $this->pages as $i => $pg ) {
			list( $pid, $cid, $lids ) = $plan[ $i ];
			$annots = $lids ? ' /Annots [' . implode( ' ', array_map( function ( $id ) {
				return $id . ' 0 R';
			}, $lids ) ) . ']' : '';
			$objs[ $pid ] = sprintf( '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents %d 0 R%s >>', self::W, self::H, $cid, $annots );
			$stream       = function_exists( 'gzcompress' ) ? gzcompress( $pg['ops'] ) : $pg['ops'];
			$filter       = function_exists( 'gzcompress' ) ? ' /Filter /FlateDecode' : '';
			$objs[ $cid ] = '<< /Length ' . strlen( $stream ) . $filter . " >>\nstream\n" . $stream . "\nendstream";
			foreach ( $pg['links'] as $k => $l ) {
				$objs[ $lids[ $k ] ] = sprintf( '<< /Type /Annot /Subtype /Link /Rect [%.2F %.2F %.2F %.2F] /Border [0 0 0] /A << /S /URI /URI %s >> >>', $l[0], $l[1], $l[2], $l[3], self::pdf_string( $l[4], false ) );
			}
		}
		ksort( $objs );
		$out     = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
		$offsets = array();
		foreach ( $objs as $id => $body ) {
			$offsets[ $id ] = strlen( $out );
			$out           .= $id . " 0 obj\n" . $body . "\nendobj\n";
		}
		$xref = strlen( $out );
		$max  = max( array_keys( $objs ) );
		$out .= "xref\n0 " . ( $max + 1 ) . "\n0000000000 65535 f \n";
		for ( $i = 1; $i <= $max; $i++ ) {
			$out .= sprintf( "%010d 00000 n \n", $offsets[ $i ] ?? 0 );
		}
		$out .= "trailer\n<< /Size " . ( $max + 1 ) . " /Root 1 0 R /Info 5 0 R >>\nstartxref\n" . $xref . "\n%%EOF";
		return $out;
	}

	/**
	 * PDF-Zeichenkette.
	 *
	 * @param string $s   Text (UTF-8).
	 * @param bool   $enc Nach WinAnsi umwandeln.
	 * @return string
	 */
	protected static function pdf_string( $s, $enc = true ) {
		$s = $enc ? self::enc( $s ) : $s;
		return '(' . str_replace( array( '\\', '(', ')' ), array( '\\\\', '\\(', '\\)' ), $s ) . ')';
	}

	/* ---------------- Terminplan ---------------- */

	/**
	 * Terminplan erzeugen.
	 *
	 * @param array[] $events  Kommende Termine.
	 * @param int     $updated Stand (Zeitstempel).
	 * @return string PDF
	 */
	public static function terminplan( array $events, $updated ) {
		$pdf   = new self();
		$navy  = '#0B2A5B';
		$text  = '#2F3D55';
		$muted = '#5B6B83';
		$water = '#EEF5FA';
		$line  = '#DCE6F0';
		$blue  = '#1F44B8';
		$club  = get_bloginfo( 'name' );
		$stand = 'Stand: ' . wp_date( 'd.m.Y', $updated );

		$cols = array(
			array( 'date', 'Datum', 76 ),
			array( 'title', 'Veranstaltung', 144 ),
			array( 'ort', 'Ort', 92 ),
			array( 'bahn', 'Bahn', 42 ),
			array( 'team', 'Mannschaft', 88 ),
			array( 'ausschreibung', 'Ausschreibung', 73 ),
		);
		$x0   = self::M;
		$tw   = self::W - 2 * self::M;
		$pad  = 5;
		$fs   = 8.6;
		$lead = 11;
		$bottom = self::H - 52;
		$y    = 0;

		$table_head = function () use ( $pdf, $cols, $x0, $tw, $navy, &$y ) {
			$pdf->rect( $x0, $y, $tw, 20, $navy );
			$x = $x0;
			foreach ( $cols as $c ) {
				$pdf->text( $x + 5, $y + 13.2, self::enc( $c[1] ), 8, true, '#FFFFFF' );
				$x += $c[2];
			}
			$y += 20;
		};

		$new_page = function ( $first ) use ( $pdf, $club, $stand, $x0, $tw, $navy, $muted, &$y, $table_head ) {
			$pdf->add_page();
			if ( $first ) {
				$pdf->rect( 0, 0, self::W, 96, $navy );
				$pdf->text( $x0, 46, self::enc( 'Terminplan' ), 24, true, '#FFFFFF' );
				$pdf->text( $x0, 66, self::enc( $club ), 10.5, false, '#8FC9EC' );
				$sw = self::tw( self::enc( $stand ), false, 9 );
				$pdf->text( self::W - self::M - $sw, 66, self::enc( $stand ), 9, false, '#FFFFFF' );
				// Bahnleine
				$seg = array( '#D7263D', '#D7263D', '#1F44B8', '#8FC9EC', '#1F44B8', '#8FC9EC', '#1F44B8', '#8FC9EC', '#D7263D', '#D7263D' );
				$sw  = self::W / ( count( $seg ) * 6 );
				for ( $i = 0; $i < count( $seg ) * 6; $i++ ) {
					$pdf->rect( $i * $sw, 96, $sw * 0.82, 4, $seg[ (int) floor( $i / 6 ) ] );
				}
				$y = 124;
			} else {
				$pdf->text( $x0, 34, self::enc( $club . ' – Terminplan' ), 9, true, $navy );
				$sw = self::tw( self::enc( $stand ), false, 8 );
				$pdf->text( self::W - self::M - $sw, 34, self::enc( $stand ), 8, false, $muted );
				$pdf->hline( $x0, self::W - self::M, 42, '#DCE6F0', 0.8 );
				$y = 54;
			}
			$table_head();
		};

		$new_page( true );

		if ( ! $events ) {
			$pdf->text( $x0, $y + 24, self::enc( 'Aktuell sind keine Termine eingetragen.' ), 10, false, $muted );
		}

		$group = '';
		foreach ( $events as $e ) {
			// Zellen vorbereiten.
			$cells = array();
			$rows  = 1;
			foreach ( $cols as $c ) {
				$key  = $c[0];
				$bold = 'title' === $key;
				if ( 'date' === $key ) {
					$val = sgnl_event_date_label( $e );
				} elseif ( 'ausschreibung' === $key ) {
					$val = $e['ausschreibung'] ? 'Ausschreibung' : '';
				} elseif ( 'title' === $key && ! empty( $e['cats'] ) && ! in_array( 'wettkampf', $e['cat_slugs'], true ) ) {
					$val = $e['title'] . ' (' . $e['cats'][0]['name'] . ')';
				} else {
					$val = $e[ $key ];
				}
				$lines         = '' === $val ? array() : self::wrap( self::enc( $val ), $bold, $fs, $c[2] - 2 * $pad );
				$cells[ $key ] = array( $lines, $bold );
				$rows          = max( $rows, count( $lines ) );
			}
			$rh = $rows * $lead + 2 * $pad + 1;

			$g      = substr( $e['start'], 0, 7 );
			$need   = $rh + ( $g !== $group ? 22 : 0 );
			if ( $y + $need > $bottom ) {
				$new_page( false );
				if ( $g === $group ) {
					$group = ''; // Monat auf neuer Seite wiederholen.
				}
			}
			if ( $g !== $group ) {
				$group = $g;
				$pdf->rect( $x0, $y, $tw, 20, $water );
				$pdf->text( $x0 + $pad, $y + 13.5, self::enc( sgnl_month_name( (int) substr( $g, 5, 2 ) ) . ' ' . substr( $g, 0, 4 ) ), 9, true, $navy );
				$y += 20;
			}

			$x = $x0;
			foreach ( $cols as $c ) {
				list( $lines, $bold ) = $cells[ $c[0] ];
				$color = 'ausschreibung' === $c[0] ? $blue : ( 'date' === $c[0] ? $navy : $text );
				foreach ( $lines as $li => $l ) {
					$pdf->text( $x + $pad, $y + $pad + 8 + $li * $lead, $l, $fs, $bold || 'date' === $c[0], $color );
				}
				if ( 'ausschreibung' === $c[0] && $lines ) {
					$w = self::tw( $lines[0], false, $fs );
					$pdf->hline( $x + $pad, $x + $pad + $w, $y + $pad + 9.6, $blue, 0.5 );
					$pdf->link( $x + $pad - 2, $y + $pad - 1, $w + 4, $lead + 3, $e['ausschreibung'] );
				}
				$x += $c[2];
			}
			$y += $rh;
			$pdf->hline( $x0, $x0 + $tw, $y, $line, 0.6 );
		}

		// Fußzeilen mit Seitenzahlen.
		$total = count( $pdf->pages );
		$host  = wp_parse_url( home_url(), PHP_URL_HOST );
		foreach ( $pdf->pages as $i => $pg ) {
			$pdf->p = $i;
			$pdf->hline( $x0, self::W - self::M, self::H - 34, $line, 0.6 );
			$pdf->text( $x0, self::H - 20, self::enc( $host ), 8, false, $muted );
			$pdf->link( $x0, self::H - 30, self::tw( self::enc( $host ), false, 8 ), 12, home_url( '/' ) );
			$label = 'Seite ' . ( $i + 1 ) . ' von ' . $total;
			$pdf->text( self::W - self::M - self::tw( self::enc( $label ), false, 8 ), self::H - 20, self::enc( $label ), 8, false, $muted );
		}

		return $pdf->output( 'Terminplan – ' . $club );
	}
}
