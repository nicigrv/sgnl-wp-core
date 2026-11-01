<?php
/**
 * Wandelt die Startseite aus dem Mesmerize-Companion-Builder in Blöcke um.
 *
 * Jede Builder-Sektion wird zu einem Abschnitt (Gruppe in voller Breite).
 * Erkannt werden: News, Spalten mit Text/Buttons, Text+Bild, Kennzahlen,
 * Logo-Leisten und der Kontakt-Bereich mit Formular. Unbekannte Sektionen
 * werden als HTML-Block übernommen und im Protokoll gemeldet.
 *
 * @package sgnl-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SGNL_Homepage_Converter {

	/** @var string[] */
	public $log = array();

	/** @var string[] */
	public $warnings = array();

	/**
	 * Ist das Builder-Inhalt?
	 *
	 * @param string $content Inhalt.
	 * @return bool
	 */
	public static function is_builder_content( $content ) {
		return false !== strpos( $content, 'data-export-id=' ) && ! has_blocks( $content );
	}

	/**
	 * Umwandeln.
	 *
	 * @param string $html Builder-HTML.
	 * @return string
	 */
	public function convert( $html ) {
		$this->log      = array();
		$this->warnings = array();

		$doc = new DOMDocument();
		libxml_use_internal_errors( true );
		$doc->loadHTML( '<?xml encoding="utf-8"?><div id="sgnl-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();
		$xp = new DOMXPath( $doc );

		$sections = $xp->query( '//*[@id="sgnl-root"]/*[@data-export-id]' );
		$out      = array();
		foreach ( $sections as $sec ) {
			$out[] = $this->section( $sec, $xp );
		}
		$this->log[] = sprintf( __( 'Startseite: %d Abschnitte in Blöcke umgewandelt', 'sgnl-core' ), count( $sections ) );
		return implode( "\n\n", array_filter( $out ) );
	}

	/* ------------------------------------------------------------------ */

	protected function cls( DOMElement $el ) {
		return ' ' . $el->getAttribute( 'class' ) . ' ';
	}

	protected function has_class( DOMElement $el, $c ) {
		return false !== strpos( $this->cls( $el ), ' ' . $c . ' ' );
	}

	protected function inner_html( DOMNode $n ) {
		$h = '';
		foreach ( $n->childNodes as $c ) {
			$h .= $n->ownerDocument->saveHTML( $c );
		}
		return $this->clean_inline( $h );
	}

	/**
	 * Inline-HTML säubern (nur einfache Auszeichnungen behalten).
	 *
	 * @param string $h HTML.
	 * @return string
	 */
	protected function clean_inline( $h ) {
		$h = wp_kses( $h, array(
			'a'      => array( 'href' => true, 'target' => true, 'rel' => true ),
			'br'     => array(),
			'strong' => array(),
			'b'      => array(),
			'em'     => array(),
			'i'      => array(),
		) );
		$h = preg_replace( '/(&nbsp;|\x{00A0}|\s)+$/u', '', $h );
		$h = preg_replace( '/^(&nbsp;|\x{00A0}|\s)+/u', '', $h );
		return trim( $h );
	}

	/**
	 * Hintergrund der Sektion → Farbe aus der Palette.
	 *
	 * @param DOMElement $sec Sektion.
	 * @return string Slug oder ''.
	 */
	protected function bg( DOMElement $sec ) {
		$style = $sec->getAttribute( 'style' );
		if ( preg_match( '/background-color:\s*(#f5fafd|rgb\(\s*245,\s*250,\s*253\s*\))/i', $style ) ) {
			return 'water';
		}
		return '';
	}

	/**
	 * Abschnitt (Gruppe in voller Breite).
	 *
	 * @param string $inner  Innere Blöcke.
	 * @param string $bg     Farbe.
	 * @param string $anchor ID.
	 * @return string
	 */
	protected function wrap( $inner, $bg, $anchor ) {
		$attrs   = array( 'align' => 'full' );
		$classes = array( 'alignfull' );
		if ( $bg ) {
			$attrs['backgroundColor'] = $bg;
			$classes[]                = 'has-' . $bg . '-background-color';
			$classes[]                = 'has-background';
		}
		$attrs['layout'] = array( 'type' => 'constrained' );
		return SGNL_Converter::group( $inner, $attrs, $classes, 'section', $anchor );
	}

	protected function title( DOMElement $sec, DOMXPath $xp, $align = 'center' ) {
		$h = $xp->query( './/*[contains(concat(" ", normalize-space(@class), " "), " section-title-col ")]//h2', $sec );
		if ( ! $h->length ) {
			return '';
		}
		$text = $this->clean_inline( $this->inner_html( $h->item( 0 ) ) );
		if ( '' === $text ) {
			return '';
		}
		return SGNL_Converter::heading( $text, 2, array( 'className' => 'is-style-sgnl-lane', 'align' => $align ) );
	}

	/**
	 * Link korrigieren.
	 *
	 * @param string $url Link.
	 * @return string
	 */
	protected function fix_url( $url ) {
		$url = trim( html_entity_decode( $url ) );
		$url = preg_replace( '/^matilo:/i', 'mailto:', $url );
		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		$url  = str_replace( 'https://' . $host . '/' . $host . '/', 'https://' . $host . '/', $url );
		if ( false !== strpos( $url, '[mesmerize_blog_link]' ) ) {
			$posts_page = (int) get_option( 'page_for_posts' );
			$url        = $posts_page ? get_permalink( $posts_page ) : home_url( '/' );
		}
		return $url;
	}

	/**
	 * Builder-Button → Button-Daten.
	 *
	 * @param DOMElement $a Link.
	 * @return array
	 */
	protected function button_data( DOMElement $a ) {
		$url      = $this->fix_url( $a->getAttribute( 'href' ) );
		$external = 0 === strpos( $url, 'http' ) && ! sgnl_is_own_host( $url );
		return array(
			'text'       => esc_html( trim( $a->textContent ) ),
			'url'        => $url,
			'style'      => 0 === strpos( $url, 'mailto:' ) ? 'sgnl-mail' : '',
			'new_window' => $external && '_blank' === $a->getAttribute( 'target' ),
		);
	}

	/**
	 * Elemente einer Spalte in Blöcke umwandeln.
	 *
	 * @param DOMElement $col     Spalte.
	 * @param string     $align   Textausrichtung.
	 * @param bool       $lane    Überschrift h2 mit Bahnleine?
	 * @return string[]
	 */
	protected function column_blocks( DOMElement $col, $align = '', $lane = true ) {
		$blocks  = array();
		$buttons = array();
		$flush   = function () use ( &$blocks, &$buttons, $align ) {
			if ( $buttons ) {
				$blocks[] = SGNL_Converter::buttons( $buttons, 'center' === $align ? 'center' : '' );
				$buttons  = array();
			}
		};
		$walker = function ( DOMNode $node ) use ( &$walker, &$blocks, &$buttons, $flush, $align, $lane ) {
			foreach ( $node->childNodes as $c ) {
				if ( ! $c instanceof DOMElement ) {
					continue;
				}
				$tag = strtolower( $c->tagName );
				if ( in_array( $tag, array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ), true ) ) {
					$flush();
					$text = $this->clean_inline( $this->inner_html( $c ) );
					if ( '' === $text ) {
						continue;
					}
					$level = (int) substr( $tag, 1 );
					if ( 5 === $level || 6 === $level ) {
						// Kleine Überzeile -> Absatz.
						$blocks[] = SGNL_Converter::paragraph( '<strong>' . $text . '</strong>', array( 'align' => $align ) );
						continue;
					}
					$opt = array( 'align' => $align );
					if ( 2 === $level && $lane ) {
						$opt['className'] = 'is-style-sgnl-lane';
					}
					$blocks[] = SGNL_Converter::heading( $text, max( 2, $level ), $opt );
				} elseif ( 'p' === $tag ) {
					$flush();
					$text = $this->clean_inline( $this->inner_html( $c ) );
					if ( '' !== $text ) {
						$blocks[] = SGNL_Converter::paragraph( $text, array( 'align' => $align ) );
					}
				} elseif ( 'a' === $tag && ( $this->has_class( $c, 'button' ) ) ) {
					$buttons[] = $this->button_data( $c );
				} elseif ( 'img' === $tag ) {
					$flush();
					$src      = $c->getAttribute( 'src' );
					$id       = attachment_url_to_postid( $src );
					$blocks[] = SGNL_Converter::image( array(
						'id'        => $id,
						'size'      => $id ? 'large' : '',
						'url'       => $src,
						'alt'       => $c->getAttribute( 'alt' ),
						'className' => $this->has_class( $c, 'shadow-large' ) ? 'is-style-sgnl-photo' : '',
					) );
				} elseif ( 'i' === $tag ) {
					continue; // Font-Awesome-Icons entfallen.
				} else {
					$walker( $c );
				}
			}
		};
		$walker( $col );
		$flush();
		return $blocks;
	}

	/**
	 * Spalten in einer Zeile finden.
	 *
	 * @param DOMElement $sec Sektion.
	 * @param DOMXPath   $xp  XPath.
	 * @return DOMElement[]
	 */
	protected function columns( DOMElement $sec, DOMXPath $xp ) {
		$rows = $xp->query( './/*[contains(concat(" ", normalize-space(@class), " "), " row ")][not(.//*[contains(@class,"section-title-col")])][not(contains(@class,"section-title-col"))]', $sec );
		foreach ( $rows as $row ) {
			$cols = array();
			foreach ( $row->childNodes as $c ) {
				if ( $c instanceof DOMElement && preg_match( '/(^|\s)col(-|\s|$)/', $c->getAttribute( 'class' ) ) ) {
					$cols[] = $c;
				}
			}
			if ( $cols ) {
				return $cols;
			}
		}
		return array();
	}

	/* ------------------------------------------------------------------ */

	protected function section( DOMElement $sec, DOMXPath $xp ) {
		$type   = $sec->getAttribute( 'data-export-id' );
		$cat    = $sec->getAttribute( 'data-category' );
		$anchor = sanitize_title( $sec->getAttribute( 'id' ) );
		$bg     = $this->bg( $sec );

		if ( 'latest_news' === $cat || 0 === strpos( $type, 'blog-section' ) ) {
			return $this->news( $sec, $xp, $bg, $anchor );
		}
		if ( 'counters' === $cat ) {
			return $this->counters( $sec, $xp, $bg, $anchor );
		}
		if ( 'clients' === $cat ) {
			return $this->logos( $sec, $xp, $anchor );
		}
		if ( 'contact' === $cat ) {
			return $this->contact( $sec, $xp, $anchor );
		}

		$cols = $this->columns( $sec, $xp );
		if ( ! $cols ) {
			$this->warnings[] = sprintf( __( 'Startseiten-Abschnitt „%s“ nicht erkannt – als HTML übernommen', 'sgnl-core' ), $sec->getAttribute( 'data-label' ) );
			return $this->wrap( SGNL_Converter::block( 'core/html', array(), "\n" . $sec->ownerDocument->saveHTML( $sec ) . "\n" ), $bg, $anchor );
		}

		$title = $this->title( $sec, $xp );

		// Text + Bild nebeneinander (zwei Spalten, eine davon mit Bild).
		$with_img = 0;
		foreach ( $cols as $c ) {
			if ( $c->getElementsByTagName( 'img' )->length ) {
				$with_img++;
			}
		}
		$is_media_text = 2 === count( $cols ) && 1 === $with_img;

		if ( $is_media_text ) {
			$col_html = array();
			foreach ( $cols as $c ) {
				$is_img = $c->getElementsByTagName( 'img' )->length > 0 && ! $c->getElementsByTagName( 'p' )->length;
				$inner  = $this->column_blocks( $c, '', true );
				$col_html[] = SGNL_Converter::block(
					'core/column',
					array( 'verticalAlignment' => 'center' ),
					"\n<div class=\"wp-block-column is-vertically-aligned-center\">" . implode( "\n\n", $inner ) . "</div>\n"
				);
				unset( $is_img );
			}
			$columns = SGNL_Converter::block(
				'core/columns',
				array( 'verticalAlignment' => 'center', 'align' => 'wide' ),
				"\n<div class=\"wp-block-columns alignwide are-vertically-aligned-center\">" . implode( "\n\n", $col_html ) . "</div>\n"
			);
			return $this->wrap( ( $title ? $title . "\n\n" : '' ) . $columns, $bg, $anchor );
		}

		// Mehrere Spalten (Karten), z. B. "Aktuelles" oder Sponsor.
		$cards = array();
		foreach ( $cols as $c ) {
			$inner = $this->column_blocks( $c, 'center', false );
			if ( ! $inner ) {
				continue;
			}
			$only_image = 1 === count( $inner ) && 0 === strpos( $inner[0], '<!-- wp:image' );
			if ( $only_image ) {
				$cards[] = $inner[0];
			} else {
				$cards[] = SGNL_Converter::group( implode( "\n\n", $inner ), array( 'className' => 'is-style-sgnl-card' ), array( 'is-style-sgnl-card' ) );
			}
		}
		if ( 1 === count( $cards ) ) {
			$grid = SGNL_Converter::group( $cards[0], array( 'layout' => array( 'type' => 'constrained', 'contentSize' => '520px' ) ) );
		} else {
			$grid = SGNL_Converter::group(
				implode( "\n\n", $cards ),
				array( 'align' => 'wide', 'className' => 'sgnl-cards', 'layout' => array( 'type' => 'grid', 'minimumColumnWidth' => '18rem' ) ),
				array( 'alignwide', 'sgnl-cards' )
			);
		}
		return $this->wrap( ( $title ? $title . "\n\n" : '' ) . $grid, $bg, $anchor );
	}

	protected function news( DOMElement $sec, DOMXPath $xp, $bg, $anchor ) {
		$title = $this->title( $sec, $xp );
		$cols  = 4;
		$query = SGNL_Converter::block(
			'core/query',
			array(
				'queryId'   => 1,
				'query'     => array(
					'perPage'  => 4,
					'pages'    => 0,
					'offset'   => 0,
					'postType' => 'post',
					'order'    => 'desc',
					'orderBy'  => 'date',
					'author'   => '',
					'search'   => '',
					'exclude'  => array(),
					'sticky'   => '',
					'inherit'  => false,
				),
				'align'     => 'wide',
				'className' => 'sgnl-news',
			),
			"\n<div class=\"wp-block-query alignwide sgnl-news\">"
			. SGNL_Converter::block( 'core/post-template', array( 'layout' => array( 'type' => 'grid', 'columnCount' => $cols ) ),
				"\n" . SGNL_Converter::block( 'core/post-featured-image', array( 'isLink' => true, 'aspectRatio' => '4/3' ), '' )
				. "\n\n" . SGNL_Converter::block( 'core/post-terms', array( 'term' => 'category' ), '' )
				. "\n\n" . SGNL_Converter::block( 'core/post-title', array( 'level' => 3, 'isLink' => true ), '' )
				. "\n\n" . SGNL_Converter::block( 'core/post-excerpt', array( 'moreText' => 'Weiterlesen', 'excerptLength' => 22 ), '' )
				. "\n"
			)
			. "</div>\n"
		);

		$buttons = array();
		foreach ( $xp->query( './/a[contains(@class,"button")]', $sec ) as $a ) {
			$buttons[] = $this->button_data( $a );
		}
		$inner = ( $title ? $title . "\n\n" : '' ) . $query;
		if ( $buttons ) {
			$inner .= "\n\n" . SGNL_Converter::buttons( $buttons, 'center' );
		}
		$this->log[] = __( 'Startseite: News-Bereich → Abfrage-Block (letzte 4 Beiträge)', 'sgnl-core' );
		return $this->wrap( $inner, $bg, $anchor );
	}

	/**
	 * Groß-/Kleinschreibung von Beschriftungen reparieren, die vorher per CSS
	 * in Großbuchstaben angezeigt wurden (z. B. "Trainingsstunden im jahr").
	 *
	 * @param string $t Text.
	 * @return string
	 */
	protected function fix_case( $t ) {
		$t     = trim( $t );
		$nouns = array( 'jahr', 'jahre', 'lampertheim', 'wettkämpfe', 'wettkampf', 'meldungen', 'schwimmer', 'trainingsstunden', 'mitglieder', 'stunden', 'woche', 'monat' );
		$words = preg_split( '/(\s+)/u', $t, -1, PREG_SPLIT_DELIM_CAPTURE );
		foreach ( $words as $i => $w ) {
			$lw = mb_strtolower( $w );
			if ( 0 === $i || in_array( preg_replace( '/[^\p{L}]/u', '', $lw ), $nouns, true ) ) {
				$words[ $i ] = mb_strtoupper( mb_substr( $w, 0, 1 ) ) . mb_substr( $w, 1 );
			}
		}
		return implode( '', $words );
	}

	protected function counters( DOMElement $sec, DOMXPath $xp, $bg, $anchor ) {
		$title = $this->title( $sec, $xp );
		$items = array();
		foreach ( $xp->query( './/*[@data-countup]', $sec ) as $num ) {
			$max    = (int) $num->getAttribute( 'data-max' );
			$value  = $num->getAttribute( 'data-prefix' ) . number_format_i18n( $max ) . $num->getAttribute( 'data-suffix' );
			$label  = '';
			$next   = $num->nextSibling;
			while ( $next && ! ( $next instanceof DOMElement ) ) {
				$next = $next->nextSibling;
			}
			if ( $next ) {
				$label = $this->fix_case( $next->textContent );
			}
			$inner   = SGNL_Converter::paragraph( esc_html( $value ), array( 'align' => 'center', 'className' => 'is-style-sgnl-stat' ) );
			$inner  .= "\n\n" . SGNL_Converter::paragraph( esc_html( $label ), array( 'align' => 'center' ) );
			$items[] = SGNL_Converter::group( $inner );
		}
		$grid = SGNL_Converter::group(
			implode( "\n\n", $items ),
			array( 'align' => 'wide', 'className' => 'sgnl-stats', 'layout' => array( 'type' => 'grid', 'minimumColumnWidth' => '13rem' ) ),
			array( 'alignwide', 'sgnl-stats' )
		);
		$this->log[] = __( 'Startseite: Kennzahlen → Kennzahl-Absätze (zählen beim Scrollen hoch)', 'sgnl-core' );
		return $this->wrap( ( $title ? $title . "\n\n" : '' ) . $grid, $bg, $anchor );
	}

	protected function logos( DOMElement $sec, DOMXPath $xp, $anchor ) {
		$title  = $this->title( $sec, $xp );
		$images = array();
		foreach ( $xp->query( './/img', $sec ) as $img ) {
			$a    = $img->parentNode instanceof DOMElement && 'a' === strtolower( $img->parentNode->tagName ) ? $img->parentNode : null;
			$href = $a ? $this->fix_url( $a->getAttribute( 'href' ) ) : '';
			$host = $href ? preg_replace( '/^www\./', '', (string) wp_parse_url( $href, PHP_URL_HOST ) ) : '';
			$src  = $img->getAttribute( 'src' );
			$images[] = SGNL_Converter::image( array(
				'url'        => $src,
				'alt'        => $host ? $host : '',
				'href'       => $href,
				'new_window' => (bool) $href,
				'className'  => 'sgnl-logo',
			) );
		}
		$grid = SGNL_Converter::group(
			implode( "\n\n", $images ),
			array( 'align' => 'wide', 'className' => 'sgnl-logos', 'layout' => array( 'type' => 'grid', 'minimumColumnWidth' => '9rem' ) ),
			array( 'alignwide', 'sgnl-logos' )
		);
		$this->log[] = sprintf( __( 'Startseite: %d Logos → Logo-Raster', 'sgnl-core' ), count( $images ) );
		return $this->wrap( ( $title ? $title . "\n\n" : '' ) . $grid, '', $anchor );
	}

	protected function contact( DOMElement $sec, DOMXPath $xp, $anchor ) {
		$title = $this->title( $sec, $xp );
		$bg    = '';
		if ( preg_match( '/background-image:\s*url\((["\']?)(.*?)\1\)/i', html_entity_decode( $sec->getAttribute( 'style' ) ), $m ) ) {
			$bg = trim( $m[2], "\"' " );
		}
		// Formular-Shortcode aus dem Builder-Attribut lesen.
		$form = '';
		$raw  = $sec->ownerDocument->saveHTML( $sec );
		for ( $i = 0; $i < 6; $i++ ) {
			$dec = html_entity_decode( $raw, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			if ( $dec === $raw ) {
				break;
			}
			$raw = $dec;
		}
		if ( preg_match( '/\[?(contact-form-7\s+id="?([^"\s]+)"?(?:\s+title="([^"]*)")?)/', $raw, $fm ) ) {
			$form = '[contact-form-7 id="' . $fm[2] . '"' . ( ! empty( $fm[3] ) ? ' title="' . $fm[3] . '"' : '' ) . ']';
		}
		$inner = '';
		if ( $title ) {
			$inner .= $title . "\n\n";
		}
		if ( $form ) {
			$inner .= SGNL_Converter::group(
				SGNL_Converter::block( 'core/shortcode', array(), "\n" . $form . "\n" ),
				array( 'className' => 'sgnl-form-card', 'layout' => array( 'type' => 'constrained' ) ),
				array( 'sgnl-form-card' )
			);
		} else {
			$this->warnings[] = __( 'Startseite: Kontaktformular nicht gefunden – bitte Formular-Block einfügen', 'sgnl-core' );
		}

		if ( ! $bg ) {
			return $this->wrap( $inner, 'water', $anchor );
		}

		$bg_id = attachment_url_to_postid( $bg );
		$attrs = array( 'url' => $bg );
		if ( $bg_id ) {
			$attrs['id'] = $bg_id;
		}
		$attrs = array_merge( $attrs, array(
			'dimRatio'           => 80,
			'overlayColor'       => 'navy-deep',
			'isUserOverlayColor' => true,
			'align'              => 'full',
			'layout'             => array( 'type' => 'constrained', 'contentSize' => '760px' ),
		) );
		$img_cls = $bg_id ? 'wp-block-cover__image-background wp-image-' . $bg_id : 'wp-block-cover__image-background';
		$id_attr = $anchor ? ' id="' . esc_attr( $anchor ) . '"' : '';
		$this->log[] = __( 'Startseite: Kontakt-Bereich → Cover-Block mit Formular', 'sgnl-core' );
		return SGNL_Converter::block(
			'core/cover',
			$attrs,
			"\n<div{$id_attr} class=\"wp-block-cover alignfull\"><img class=\"{$img_cls}\" alt=\"\" src=\"" . esc_url( $bg ) . "\" data-object-fit=\"cover\"/><span aria-hidden=\"true\" class=\"wp-block-cover__background has-navy-deep-background-color has-background-dim-80 has-background-dim\"></span><div class=\"wp-block-cover__inner-container\">{$inner}</div></div>\n"
		);
	}
}
