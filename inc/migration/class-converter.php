<?php
/**
 * Wandelt Inhalte alter Plugins in normale WordPress-Blöcke um.
 *
 * - SiteOrigin-Widgets (CTA, Button)   → Gruppe "Info-Box" / Buttons
 * - TablePress                         → Tabellen-Verwaltung + Block "Tabelle"
 * - Modula                             → Galerie-Block mit Lightbox
 * - Team Members ([tmm])               → Team-Karten (Gruppen)
 * - Advanced iFrame (ai/ai-block)      → Block "Eingebettete Seite"
 * - Shortcodes gelöschter Plugins      → entfernt (wird protokolliert)
 *
 * Das Markup entspricht exakt dem, was der Block-Editor selbst speichert,
 * damit die Blöcke im Editor ohne Fehlermeldung öffnen.
 *
 * @package sgnl-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SGNL_Converter {

	/** @var string[] Protokoll der aktuellen Umwandlung */
	public $log = array();

	/** @var string[] Hinweise, die manuell geprüft werden sollten */
	public $warnings = array();

	/** @var bool Nur Vorschau – nichts anlegen */
	public $dry = false;

	/* =====================================================================
	 * Einstieg
	 * ================================================================== */

	/**
	 * Inhalt umwandeln.
	 *
	 * @param string $content Beitragsinhalt.
	 * @return string
	 */
	public function convert( $content ) {
		$this->log      = array();
		$this->warnings = array();

		if ( ! has_blocks( $content ) ) {
			$out = $this->convert_classic( $content );
		} else {
			$out = $this->serialize_list( parse_blocks( $content ) );
		}

		return $this->fix_links( $out );
	}

	/**
	 * Bekannte Tippfehler in Links korrigieren.
	 *
	 * @param string $html Inhalt.
	 * @return string
	 */
	public function fix_links( $html ) {
		$fixed = str_replace( 'href="matilo:', 'href="mailto:', $html );
		$host  = wp_parse_url( home_url(), PHP_URL_HOST );
		$fixed = str_replace( 'https://' . $host . '/' . $host . '/', 'https://' . $host . '/', $fixed );
		if ( $fixed !== $html ) {
			$this->log[] = __( 'Fehlerhafte Links korrigiert (mailto/doppelte Domain)', 'sgnl-core' );
		}
		return $fixed;
	}

	/* =====================================================================
	 * Block-Baum durchlaufen und wieder zusammensetzen
	 * ================================================================== */

	/**
	 * @param array $blocks Geparste Blöcke.
	 * @return string
	 */
	protected function serialize_list( array $blocks ) {
		$out = '';
		foreach ( $blocks as $b ) {
			$out .= $this->serialize_one( $b );
		}
		return $out;
	}

	/**
	 * @param array $b Block.
	 * @return string
	 */
	protected function serialize_one( array $b ) {
		$replacement = $this->transform( $b );
		if ( null !== $replacement ) {
			return $replacement;
		}

		if ( empty( $b['blockName'] ) ) {
			// Freier HTML-/Klassik-Text zwischen Blöcken.
			return $this->convert_classic( $b['innerHTML'] ?? '', true );
		}

		$content = '';
		$i       = 0;
		foreach ( $b['innerContent'] as $chunk ) {
			if ( is_string( $chunk ) ) {
				$content .= $chunk;
			} else {
				$content .= $this->serialize_one( $b['innerBlocks'][ $i++ ] );
			}
		}
		return get_comment_delimited_block_content( $b['blockName'], $b['attrs'], $content );
	}

	/**
	 * Einen Block ersetzen – oder null, wenn er bleibt.
	 *
	 * @param array $b Block.
	 * @return string|null
	 */
	protected function transform( array $b ) {
		$name  = $b['blockName'] ?? '';
		$attrs = $b['attrs'] ?? array();

		switch ( $name ) {
			case 'sowb/siteorigin-widget-cta-widget':
				return $this->so_cta( $attrs['widgetData'] ?? array(), $attrs );

			case 'sowb/siteorigin-widget-button-widget':
				return $this->so_button( $attrs['widgetData'] ?? array(), $attrs );

			case 'sowb/widget-block':
				$class = $attrs['widgetClass'] ?? '';
				if ( 'SiteOrigin_Widget_Button_Widget' === $class ) {
					return $this->so_button( $attrs['widgetData'] ?? array(), $attrs );
				}
				if ( 'SiteOrigin_Widget_Cta_Widget' === $class ) {
					return $this->so_cta( $attrs['widgetData'] ?? array(), $attrs );
				}
				$this->warnings[] = sprintf( __( 'Unbekanntes SiteOrigin-Widget (%s) – bitte manuell prüfen', 'sgnl-core' ), $class );
				return null;

			case 'tablepress/table':
				return $this->tablepress( $attrs['id'] ?? '' );

			case 'modula/gallery':
				return $this->modula( (int) ( $attrs['id'] ?? 0 ) );

			case 'ai/ai-block':
				return $this->iframe( $attrs );

			case 'core/shortcode':
				return $this->shortcode_block( trim( $b['innerHTML'] ?? '' ) );
		}

		if ( 0 === strpos( $name, 'sowb/' ) ) {
			$this->warnings[] = sprintf( __( 'SiteOrigin-Block %s nicht umgewandelt – bitte manuell prüfen', 'sgnl-core' ), $name );
		}
		return null;
	}

	/**
	 * Klassischer Inhalt ohne Blöcke: nur alleinstehende Shortcodes ersetzen.
	 *
	 * @param string $html     Inhalt.
	 * @param bool   $fragment Teil eines Block-Inhalts?
	 * @return string
	 */
	protected function convert_classic( $html, $fragment = false ) {
		$self = $this;
		return preg_replace_callback(
			'/^[ \t]*(?:<p>)?\s*(\[(?:modula|table|tmm|wpspeedo-team|wpforms)\b[^\]]*\])\s*(?:<\/p>)?[ \t]*$/m',
			function ( $m ) use ( $self, $fragment ) {
				$r = $self->shortcode_block( $m[1] );
				if ( null === $r ) {
					return $m[0];
				}
				if ( ! $fragment ) {
					$self->warnings[] = __( 'Klassischer Inhalt (ohne Blöcke) – Ergebnis bitte im Editor kontrollieren', 'sgnl-core' );
				}
				return "\n" . $r . "\n";
			},
			$html
		);
	}

	/**
	 * Shortcode-Block umwandeln.
	 *
	 * @param string $text Inhalt des Shortcode-Blocks.
	 * @return string|null
	 */
	public function shortcode_block( $text ) {
		if ( ! preg_match( '/^\[([a-z0-9_-]+)([^\]]*)\]$/i', trim( $text ), $m ) ) {
			return null;
		}
		$tag  = strtolower( $m[1] );
		$atts = shortcode_parse_atts( trim( rtrim( $m[2], '/' ) ) );
		$atts = is_array( $atts ) ? $atts : array();

		switch ( $tag ) {
			case 'modula':
				return $this->modula( (int) ( $atts['id'] ?? 0 ) );
			case 'table':
				return $this->tablepress( $atts['id'] ?? '' );
			case 'tmm':
				return $this->tmm( $atts['name'] ?? '' );
			case 'wpspeedo-team':
			case 'wpforms':
				if ( ! shortcode_exists( $tag ) ) {
					$this->log[] = sprintf( __( 'Shortcode [%s] entfernt (Plugin nicht mehr vorhanden, zeigte nichts an)', 'sgnl-core' ), $tag );
					return '';
				}
				return null;
		}
		return null;
	}

	/* =====================================================================
	 * Markup-Bausteine (exakt wie vom Editor gespeichert)
	 * ================================================================== */

	/**
	 * Block-Kommentar.
	 *
	 * @param string $name    Name ohne core/.
	 * @param array  $attrs   Attribute.
	 * @param string $content Inhalt (leer = selbstschließend).
	 * @return string
	 */
	public static function block( $name, array $attrs, $content ) {
		return get_comment_delimited_block_content( $name, $attrs, $content );
	}

	public static function heading( $text_html, $level = 2, array $opt = array() ) {
		$attrs   = array();
		$classes = array( 'wp-block-heading' );
		if ( 2 !== $level ) {
			$attrs['level'] = $level;
		}
		if ( ! empty( $opt['className'] ) ) {
			$attrs['className'] = $opt['className'];
		}
		if ( ! empty( $opt['align'] ) ) {
			$attrs['style'] = array( 'typography' => array( 'textAlign' => $opt['align'] ) );
			$classes[]      = 'has-text-align-' . $opt['align'];
		}
		if ( ! empty( $opt['className'] ) ) {
			$classes[] = $opt['className'];
		}
		$id = ! empty( $opt['anchor'] ) ? ' id="' . esc_attr( $opt['anchor'] ) . '"' : '';
		return self::block( 'core/heading', $attrs, "\n<h{$level}{$id} class=\"" . implode( ' ', $classes ) . "\">{$text_html}</h{$level}>\n" );
	}

	public static function paragraph( $html, array $opt = array() ) {
		$attrs   = array();
		$classes = array();
		if ( ! empty( $opt['className'] ) ) {
			$attrs['className'] = $opt['className'];
		}
		if ( ! empty( $opt['align'] ) ) {
			$attrs['style'] = array( 'typography' => array( 'textAlign' => $opt['align'] ) );
			$classes[]      = 'has-text-align-' . $opt['align'];
		}
		if ( ! empty( $opt['className'] ) ) {
			$classes[] = $opt['className'];
		}
		$cls = $classes ? ' class="' . implode( ' ', $classes ) . '"' : '';
		return self::block( 'core/paragraph', $attrs, "\n<p{$cls}>{$html}</p>\n" );
	}

	/**
	 * Buttons-Gruppe.
	 *
	 * @param array  $buttons Liste aus [text, url, style, new_window].
	 * @param string $justify left|center|right|space-between.
	 * @return string
	 */
	public static function buttons( array $buttons, $justify = '' ) {
		$inner = array();
		foreach ( $buttons as $btn ) {
			$attrs = array();
			$cls   = 'wp-block-button';
			if ( ! empty( $btn['style'] ) ) {
				$attrs['className'] = 'is-style-' . $btn['style'];
				$cls               .= ' is-style-' . $btn['style'];
			}
			$target = ! empty( $btn['new_window'] ) ? ' target="_blank" rel="noreferrer noopener"' : '';
			$inner[] = self::block( 'core/button', $attrs, "\n<div class=\"{$cls}\"><a class=\"wp-block-button__link wp-element-button\" href=\"" . esc_url( $btn['url'], array( 'http', 'https', 'mailto', 'tel' ) ) . "\"{$target}>" . $btn['text'] . "</a></div>\n" );
		}
		$attrs = array();
		if ( $justify && 'left' !== $justify ) {
			$attrs['layout'] = array( 'type' => 'flex', 'justifyContent' => $justify );
		}
		return self::block( 'core/buttons', $attrs, "\n<div class=\"wp-block-buttons\">" . implode( "\n\n", $inner ) . "</div>\n" );
	}

	/**
	 * Einzelbild.
	 *
	 * @param array $o id, url, alt, size, className, href, new_window, linkDestination.
	 * @return string
	 */
	public static function image( array $o ) {
		$attrs = array();
		$fig   = array( 'wp-block-image' );
		$id    = (int) ( $o['id'] ?? 0 );
		$size  = $o['size'] ?? '';
		$src   = $o['url'] ?? '';

		if ( $id ) {
			$attrs['id'] = $id;
			if ( $size ) {
				$attrs['sizeSlug'] = $size;
				$fig[]             = 'size-' . $size;
				$s                 = wp_get_attachment_image_src( $id, $size );
				if ( $s ) {
					$src = $s[0];
				}
			}
		}
		if ( ! empty( $o['href'] ) ) {
			$attrs['linkDestination'] = 'custom';
		} elseif ( $id && $size ) {
			$attrs['linkDestination'] = 'none';
		}
		if ( ! empty( $o['className'] ) ) {
			$attrs['className'] = $o['className'];
			$fig[]              = $o['className'];
		}
		$img_cls = $id ? ' class="wp-image-' . $id . '"' : '';
		$img     = '<img src="' . esc_url( $src ) . '" alt="' . esc_attr( $o['alt'] ?? '' ) . '"' . $img_cls . '/>';
		if ( ! empty( $o['href'] ) ) {
			$t   = ! empty( $o['new_window'] ) ? ' target="_blank" rel="noreferrer noopener"' : '';
			$img = '<a href="' . esc_url( $o['href'] ) . '"' . $t . '>' . $img . '</a>';
		}
		return self::block( 'core/image', $attrs, "\n<figure class=\"" . implode( ' ', $fig ) . "\">{$img}</figure>\n" );
	}

	/**
	 * Gruppe.
	 *
	 * @param string $inner   Innere Blöcke.
	 * @param array  $attrs   Block-Attribute.
	 * @param array  $classes Zusätzliche Klassen im HTML (Reihenfolge wie Editor).
	 * @param string $tag     div|section.
	 * @param string $anchor  ID.
	 * @return string
	 */
	public static function group( $inner, array $attrs = array(), array $classes = array(), $tag = 'div', $anchor = '' ) {
		if ( 'div' !== $tag ) {
			$attrs = array_merge( array( 'tagName' => $tag ), $attrs );
		}
		$id = $anchor ? ' id="' . esc_attr( $anchor ) . '"' : '';
		return self::block( 'core/group', $attrs, "\n<{$tag}{$id} class=\"" . implode( ' ', array_merge( array( 'wp-block-group' ), $classes ) ) . "\">{$inner}</{$tag}>\n" );
	}

	/**
	 * Info-Box (ersetzt SiteOrigin-CTA).
	 *
	 * @param string $title    Titel.
	 * @param string $sub      Untertitel.
	 * @param array  $buttons  Buttons.
	 * @return string
	 */
	public static function box( $title, $sub, array $buttons ) {
		$text = self::heading( $title, 3 );
		if ( '' !== trim( $sub ) ) {
			$text .= "\n\n" . self::paragraph( $sub );
		}
		$left  = self::group(
			$text,
			array(
				'style'  => array( 'spacing' => array( 'blockGap' => '0.2rem' ) ),
				'layout' => array( 'type' => 'flex', 'orientation' => 'vertical', 'flexWrap' => 'nowrap' ),
			)
		);
		$inner = $left;
		if ( $buttons ) {
			$inner .= "\n\n" . self::buttons( $buttons );
		}
		return self::group(
			$inner,
			array(
				'className' => 'is-style-sgnl-box',
				'layout'    => array( 'type' => 'flex', 'flexWrap' => 'nowrap', 'justifyContent' => 'space-between', 'verticalAlignment' => 'center' ),
			),
			array( 'is-style-sgnl-box' )
		);
	}

	/* =====================================================================
	 * SiteOrigin
	 * ================================================================== */

	/**
	 * Button-Stil anhand des alten Icons.
	 *
	 * @param string $icon Icon-Kennung.
	 * @param string $url  Link.
	 * @return string
	 */
	protected function style_from_icon( $icon, $url ) {
		$icon = strtolower( (string) $icon );
		if ( false !== strpos( $icon, 'envelope' ) || 0 === strpos( $url, 'mailto:' ) ) {
			return 'sgnl-mail';
		}
		if ( preg_match( '/file|download|pdf/', $icon ) || preg_match( '/\.(pdf|docx?|xlsx?|zip)(\?|$)/i', $url ) ) {
			return 'sgnl-download';
		}
		if ( preg_match( '/folder|link|globe|external|arrow/', $icon ) ) {
			return 'sgnl-link';
		}
		return '';
	}

	/**
	 * SiteOrigin-Link auflösen ("post: 1459 (Galerie)").
	 *
	 * @param string $url   Link.
	 * @param array  $attrs Block-Attribute (enthält gerendertes HTML).
	 * @return string
	 */
	protected function so_url( $url, array $attrs ) {
		$url = trim( (string) $url );
		if ( preg_match( '/^post:\s*(\d+)/', $url, $m ) ) {
			$link = get_permalink( (int) $m[1] );
			if ( $link ) {
				return $link;
			}
		}
		if ( '' === $url && ! empty( $attrs['html'] ) && preg_match( '/href="([^"]+)"/', $attrs['html'], $m ) ) {
			return html_entity_decode( $m[1] );
		}
		return $url;
	}

	protected function so_cta( $d, array $attrs ) {
		$d      = is_array( $d ) ? $d : array();
		$title  = esc_html( wp_strip_all_tags( $d['title'] ?? '' ) );
		$sub    = esc_html( wp_strip_all_tags( $d['sub_title'] ?? '' ) );
		$btn    = $d['button'] ?? array();
		$url    = $this->so_url( $btn['url'] ?? '', $attrs );
		$text   = esc_html( trim( (string) ( $btn['text'] ?? '' ) ) );
		$btns   = array();
		if ( $url && '' !== $text ) {
			$btns[] = array(
				'text'       => $text,
				'url'        => $url,
				'style'      => $this->style_from_icon( $btn['button_icon']['icon_selected'] ?? '', $url ),
				'new_window' => ! empty( $btn['new_window'] ),
			);
		}
		$this->log[] = __( 'SiteOrigin-Box → Info-Box', 'sgnl-core' );
		return self::box( $title, $sub, $btns );
	}

	protected function so_button( $d, array $attrs ) {
		$d    = is_array( $d ) ? $d : array();
		$url  = $this->so_url( $d['url'] ?? '', $attrs );
		$text = esc_html( trim( (string) ( $d['text'] ?? '' ) ) );
		if ( '' === $text ) {
			$this->warnings[] = __( 'SiteOrigin-Button ohne Text entfernt', 'sgnl-core' );
			return '';
		}
		if ( ! $url ) {
			$url              = '#';
			$this->warnings[] = sprintf( __( 'Button „%s“ hatte schon vorher keinen Link – bitte Link ergänzen', 'sgnl-core' ), $text );
		}
		$align = $d['design']['align'] ?? 'left';
		$map   = array( 'center' => 'center', 'right' => 'right', 'justify' => 'center' );
		$this->log[] = __( 'SiteOrigin-Button → Button', 'sgnl-core' );
		return self::buttons(
			array(
				array(
					'text'       => $text,
					'url'        => $url,
					'style'      => $this->style_from_icon( $d['button_icon']['icon_selected'] ?? '', $url ),
					'new_window' => ! empty( $d['new_window'] ),
				),
			),
			$map[ $align ] ?? ''
		);
	}

	/* =====================================================================
	 * TablePress
	 * ================================================================== */

	protected function tablepress( $table_id ) {
		$table_id = trim( (string) $table_id );
		$map      = json_decode( (string) get_option( 'tablepress_tables', '' ), true );
		$post_id  = isset( $map['table_post'][ $table_id ] ) ? (int) $map['table_post'][ $table_id ] : 0;
		$post     = $post_id ? get_post( $post_id ) : null;

		// Schon übernommen? (z. B. zweite Seite mit derselben Tabelle oder erneute Umstellung)
		$existing = get_posts( array(
			'post_type'   => 'sgnl_table',
			'post_status' => 'any',
			'meta_key'    => '_sgnl_from_tablepress', // phpcs:ignore
			'meta_value'  => $table_id, // phpcs:ignore
			'numberposts' => 1,
			'fields'      => 'ids',
		) );

		if ( ! $existing && ( ! $post || 'tablepress_table' !== $post->post_type ) ) {
			$this->warnings[] = sprintf( __( 'TablePress-Tabelle %s nicht gefunden', 'sgnl-core' ), $table_id );
			return null;
		}

		$new_id = $existing ? (int) $existing[0] : 0;
		$title  = $post ? $post->post_title : get_the_title( $new_id );
		$cols   = 0;

		if ( ! $new_id ) {
			$data = json_decode( $post->post_content, true );
			if ( ! is_array( $data ) || ! $data ) {
				$this->warnings[] = sprintf( __( 'TablePress-Tabelle %s ist leer', 'sgnl-core' ), $table_id );
				return null;
			}
			$opt = json_decode( (string) get_post_meta( $post_id, '_tablepress_table_options', true ), true );
			$vis = json_decode( (string) get_post_meta( $post_id, '_tablepress_table_visibility', true ), true );

			$hidden_rows = array();
			foreach ( (array) ( $vis['rows'] ?? array() ) as $i => $v ) {
				if ( 0 === (int) $v ) {
					$hidden_rows[] = $i;
				}
			}
			$hidden_cols = array();
			foreach ( (array) ( $vis['columns'] ?? array() ) as $i => $v ) {
				if ( 0 === (int) $v ) {
					$hidden_cols[] = $i;
				}
			}
			foreach ( $data as $row ) {
				foreach ( (array) $row as $cell ) {
					if ( 0 === strpos( (string) $cell, '=' ) ) {
						$this->warnings[] = sprintf( __( 'TablePress-Tabelle „%s“ enthält Formeln – Werte bitte prüfen', 'sgnl-core' ), $title );
						break 2;
					}
				}
			}
			$cols = isset( $data[0] ) ? count( $data[0] ) - count( $hidden_cols ) : 0;

			// Kopfzeile und Zwischenüberschriften (nur erste Zelle gefüllt) werden auf der Website
			// automatisch fett – das <strong> drumherum macht das Bearbeiten nur unübersichtlich.
			$unwrap = function ( $c ) {
				$c = trim( (string) $c );
				return preg_match( '#^<strong>([^<]*)</strong>$#i', $c, $m ) ? $m[1] : $c;
			};
			foreach ( $data as $ri => $row ) {
				$filled = count( array_filter( array_map( 'trim', array_map( 'strval', (array) $row ) ), 'strlen' ) );
				$is_group = 1 === $filled && '' !== trim( (string) $row[0] ) && count( $row ) > 2;
				if ( ( 0 === $ri && ! empty( $opt['table_head'] ) ) || $is_group ) {
					$data[ $ri ] = array_map( $unwrap, (array) $row );
				}
			}

			if ( $this->dry ) {
				$this->log[] = sprintf( __( 'TablePress „%s“ → Tabellen-Verwaltung (Block „Tabelle“)', 'sgnl-core' ), $title );
				return '<!-- wp:sgnl/table {"id":0} /-->';
			}

			$new_id = sgnl_table_create( $title, array(
				'rows'        => $data,
				'head'        => ! empty( $opt['table_head'] ),
				'foot'        => ! empty( $opt['table_foot'] ),
				'hidden_rows' => $hidden_rows,
				'hidden_cols' => $hidden_cols,
				'show_title'  => ! empty( $opt['print_name'] ),
				'note'        => ! empty( $opt['print_description'] ) ? $post->post_excerpt : '',
			) );
			if ( ! $new_id ) {
				$this->warnings[] = sprintf( __( 'Tabelle „%s“ konnte nicht angelegt werden', 'sgnl-core' ), $title );
				return null;
			}
			update_post_meta( $new_id, '_sgnl_from_tablepress', $table_id );
		} else {
			$d    = function_exists( 'sgnl_table_get' ) ? sgnl_table_get( $new_id ) : null;
			$cols = $d ? count( $d['rows'][0] ) - count( $d['hidden_cols'] ) : 0;
		}

		$attrs = array( 'id' => $new_id );
		if ( $cols > 3 ) {
			$attrs['align'] = 'wide';
		}
		$this->log[] = sprintf( __( 'TablePress „%s“ → Tabellen-Verwaltung (Block „Tabelle“)', 'sgnl-core' ), $title );
		return self::block( 'sgnl/table', $attrs, '' );
	}

	/* =====================================================================
	 * Modula
	 * ================================================================== */

	protected function modula( $gallery_id ) {
		$post = $gallery_id ? get_post( $gallery_id ) : null;
		if ( ! $post || 'modula-gallery' !== $post->post_type ) {
			$this->warnings[] = sprintf( __( 'Modula-Galerie %d nicht gefunden', 'sgnl-core' ), $gallery_id );
			return null;
		}
		$images   = get_post_meta( $gallery_id, 'modula-images', true );
		$settings = get_post_meta( $gallery_id, 'modula-settings', true );
		$images   = is_array( $images ) ? $images : array();

		$size  = 'medium_large';
		$inner = array();
		foreach ( $images as $img ) {
			$id = (int) ( $img['id'] ?? 0 );
			if ( ! $id || ! wp_attachment_is_image( $id ) ) {
				continue;
			}
			$src = wp_get_attachment_image_src( $id, $size );
			$use = $size;
			if ( ! $src || empty( $src[3] ) ) {
				// Kein eigenes Zwischenformat vorhanden.
				$src = wp_get_attachment_image_src( $id, 'large' );
				$use = 'large';
			}
			if ( ! $src ) {
				continue;
			}
			$alt     = trim( (string) ( $img['alt'] ?? '' ) );
			$alt     = '' !== $alt ? $alt : (string) get_post_meta( $id, '_wp_attachment_image_alt', true );
			$attrs   = array(
				'lightbox'        => array( 'enabled' => true ),
				'id'              => $id,
				'sizeSlug'        => $use,
				'linkDestination' => 'none',
			);
			$caption = trim( (string) ( $img['description'] ?? '' ) );
			$cap     = '' !== $caption ? '<figcaption class="wp-element-caption">' . esc_html( $caption ) . '</figcaption>' : '';
			$inner[] = self::block( 'core/image', $attrs, "\n<figure class=\"wp-block-image size-{$use}\"><img src=\"" . esc_url( $src[0] ) . '" alt="' . esc_attr( $alt ) . "\" class=\"wp-image-{$id}\"/>{$cap}</figure>\n" );
		}
		if ( ! $inner ) {
			$this->warnings[] = sprintf( __( 'Modula-Galerie „%s“ enthält keine Bilder', 'sgnl-core' ), $post->post_title );
			return '';
		}

		$cols  = (int) ( $settings['grid_columns'] ?? 0 );
		$cols  = $cols >= 2 && $cols <= 6 ? $cols : 4;
		$attrs = array(
			'columns' => $cols,
			'linkTo'  => 'lightbox',
		);
		if ( 'large' !== $size ) {
			$attrs['sizeSlug'] = $size;
		}
		$attrs['align'] = 'wide';

		$this->log[] = sprintf( __( 'Modula „%1$s“ → Galerie mit %2$d Bildern', 'sgnl-core' ), $post->post_title, count( $inner ) );
		return self::block(
			'core/gallery',
			$attrs,
			"\n<figure class=\"wp-block-gallery alignwide has-nested-images columns-{$cols} is-cropped\">" . implode( "\n\n", $inner ) . "</figure>\n"
		);
	}

	/* =====================================================================
	 * Team Members
	 * ================================================================== */

	protected function tmm( $slug ) {
		$posts = get_posts( array(
			'post_type'   => 'tmm',
			'name'        => sanitize_title( $slug ),
			'post_status' => 'any',
			'numberposts' => 1,
		) );
		if ( ! $posts ) {
			$this->warnings[] = sprintf( __( 'Team „%s“ nicht gefunden', 'sgnl-core' ), $slug );
			return null;
		}
		$team    = $posts[0];
		$members = get_post_meta( $team->ID, '_tmm_head', true );
		$members = is_array( $members ) ? $members : array();
		$cards   = array();

		foreach ( $members as $m ) {
			$name = trim( ( $m['_tmm_firstname'] ?? '' ) . ' ' . ( $m['_tmm_lastname'] ?? '' ) );
			if ( '' === $name ) {
				continue;
			}
			$inner = array();
			$photo = trim( (string) ( $m['_tmm_photo'] ?? '' ) );
			if ( $photo ) {
				$aid     = attachment_url_to_postid( $photo );
				$inner[] = self::image( array(
					'id'        => $aid,
					'size'      => $aid ? 'medium' : '',
					'url'       => $photo,
					'alt'       => $name,
					'className' => 'is-style-sgnl-portrait',
				) );
			}
			$inner[] = self::heading( esc_html( $name ), 3 );
			if ( '' !== trim( (string) ( $m['_tmm_job'] ?? '' ) ) ) {
				$inner[] = self::paragraph( esc_html( trim( $m['_tmm_job'] ) ) );
			}
			if ( '' !== trim( wp_strip_all_tags( (string) ( $m['_tmm_desc'] ?? '' ) ) ) ) {
				// Beschreibung kann bereits Absätze enthalten -> in Zeilenumbrüche wandeln.
				$desc = trim( (string) $m['_tmm_desc'] );
				$desc = preg_replace( '#</p>\s*<p[^>]*>#i', '<br><br>', $desc );
				$desc = preg_replace( '#</?p[^>]*>#i', '', $desc );
				$desc = preg_replace( '#\r?\n#', '<br>', trim( $desc ) );
				$inner[] = self::paragraph( wp_kses( $desc, array( 'a' => array( 'href' => true, 'target' => true, 'rel' => true ), 'br' => array(), 'strong' => array(), 'em' => array(), 'b' => array(), 'i' => array() ) ) );
			}
			$btns = array();
			for ( $i = 1; $i <= 3; $i++ ) {
				$type = $m[ '_tmm_sc_type' . $i ] ?? 'nada';
				$url  = trim( (string) ( $m[ '_tmm_sc_url' . $i ] ?? '' ) );
				if ( 'nada' === $type || '' === $url ) {
					continue;
				}
				if ( 'email' === $type ) {
					$btns[] = array( 'text' => esc_html__( 'E-Mail', 'sgnl-core' ), 'url' => 0 === strpos( $url, 'mailto:' ) ? $url : 'mailto:' . $url, 'style' => 'sgnl-mail' );
				} elseif ( 'phone' === $type ) {
					$btns[] = array( 'text' => esc_html__( 'Anrufen', 'sgnl-core' ), 'url' => 'tel:' . preg_replace( '/[^0-9+]/', '', $url ), 'style' => '' );
				} else {
					$label  = trim( (string) ( $m[ '_tmm_sc_title' . $i ] ?? '' ) );
					$btns[] = array( 'text' => esc_html( $label ? $label : ucfirst( $type ) ), 'url' => $url, 'style' => 'sgnl-link', 'new_window' => true );
				}
			}
			if ( $btns ) {
				$inner[] = self::buttons( $btns, 'center' );
			}
			$cards[] = self::group( implode( "\n\n", $inner ) );
		}

		if ( ! $cards ) {
			return '';
		}
		$this->log[] = sprintf( __( 'Team „%1$s“ → %2$d Team-Karten', 'sgnl-core' ), $team->post_title, count( $cards ) );
		return self::group(
			implode( "\n\n", $cards ),
			array(
				'align'     => 'wide',
				'className' => 'sgnl-team',
				'layout'    => array( 'type' => 'grid', 'minimumColumnWidth' => '15rem' ),
			),
			array( 'alignwide', 'sgnl-team' )
		);
	}

	/* =====================================================================
	 * Advanced iFrame
	 * ================================================================== */

	protected function iframe( array $attrs ) {
		$src = trim( (string) ( $attrs['src'] ?? '' ) );
		if ( '' === $src ) {
			return '';
		}
		$height = (int) preg_replace( '/\D/', '', (string) ( $attrs['height'] ?? '' ) );
		$this->log[] = __( 'Advanced iFrame → Block „Eingebettete Seite“', 'sgnl-core' );
		return self::block( 'sgnl/iframe', array(
			'src'    => esc_url_raw( $src ),
			'height' => $height >= 200 ? $height : 1000,
		), '' );
	}
}
