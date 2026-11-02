<?php
/**
 * Integrationstests für SG Neptun Funktionen (+ Theme).
 *
 * Läuft auf GitHub Actions in einer frischen WordPress-Installation:
 *   wp eval-file wp-content/plugins/sgnl-core/tests/run.php
 * Legt Testinhalte an (keine echten Daten) und prüft alle Funktionen.
 * Schreibt am Ende tests/ci/urls.txt für die HTTP- und Browser-Tests.
 *
 * @package sgnl-core
 */

require __DIR__ . '/lib.php';

/** Alle Inhalts-Streams eines PDFs entpacken */
function gzuncompress_all( $pdf ) {
	preg_match_all( '/stream\n(.*?)\nendstream/s', $pdf, $m );
	$out = '';
	foreach ( $m[1] as $s ) {
		$d    = @gzuncompress( $s ); // phpcs:ignore
		$out .= false === $d ? $s : $d;
	}
	return $out;
}
require_once SGNL_CORE_DIR . 'inc/migration/class-events-import.php';
require_once SGNL_CORE_DIR . 'inc/migration/class-gallery-setup.php';
require_once SGNL_CORE_DIR . 'inc/migration/class-db-import.php';
require_once SGNL_CORE_DIR . 'inc/class-events-pdf.php';

wp_set_current_user( 1 );
kses_remove_filters();
$urls = array();

/* =========================================================================
 * Grundlagen
 * ====================================================================== */
t_section( 'Grundlagen' );
t_ok( post_type_exists( 'sgnl_event' ), 'Inhaltstyp Termine ist registriert' );
t_ok( post_type_exists( 'sgnl_table' ), 'Inhaltstyp Tabellen ist registriert' );
t_ok( taxonomy_exists( 'sgnl_event_cat' ), 'Kategorien sind registriert' );
foreach ( array( 'wettkampf', 'veranstaltung', 'ferien', 'jugend' ) as $slug ) {
	t_ok( (bool) term_exists( $slug, 'sgnl_event_cat' ), "Standard-Kategorie {$slug} existiert" );
}
$role = get_role( 'sgnl_terminverwaltung' );
t_ok( $role && $role->has_cap( 'edit_sgnl_events' ) && ! $role->has_cap( 'edit_pages' ), 'Rolle Terminverwaltung darf Termine, aber keine Seiten bearbeiten' );
t_ok( get_role( 'administrator' )->has_cap( 'edit_others_sgnl_events' ), 'Administrator darf Termine bearbeiten' );
foreach ( array( 'sgnl/events', 'sgnl/table', 'sgnl/albums', 'sgnl/live', 'sgnl/datenbank', 'sgnl/iframe' ) as $b ) {
	t_ok( WP_Block_Type_Registry::get_instance()->is_registered( $b ), "Block {$b} ist registriert" );
}

/* =========================================================================
 * Tabellen
 * ====================================================================== */
t_section( 'Tabellen' );
$tid  = sgnl_table_create( 'Testtabelle', array( 'rows' => array( array( 'A', 'B', 'C' ), array( 'Oktober', '', '' ), array( '1', '<a href="https://example.org">x</a>', '3' ) ), 'head' => true ) );
$html = sgnl_table_render( $tid );
t_has( $html, '<thead>', 'Kopfzeile wird ausgegeben' );
t_has( $html, 'is-group-row', 'Zwischenüberschrift (nur erste Zelle) wird erkannt' );
t_has( $html, 'href="https://example.org"', 'Links in Zellen bleiben erhalten' );
$csv = sgnl_table_parse_csv( "\xEF\xBB\xBFDatum;Name\n01.02.;Test \"A\"\n" );
t_eq( count( $csv ), 2, 'CSV mit BOM und Semikolon wird gelesen' );

/* =========================================================================
 * Termine: Daten, Datum, Kategorien, mehrere am selben Tag
 * ====================================================================== */
t_section( 'Termine' );
$a = t_event( 'Test A', array( 'start' => t_day( 10 ), 'end' => t_day( 11 ), 'start_time' => '08:30', 'end_time' => '25:00', 'ort' => 'Lampertheim' ) );
$e = sgnl_event_get( $a );
t_eq( $e['start_time'], '08:30', 'Uhrzeit wird gespeichert' );
t_eq( $e['end_time'], '', 'Ungültige Uhrzeit wird verworfen' );
$b = t_event( 'Test B', array( 'start' => t_day( 10 ), 'end' => t_day( 5 ) ) );
t_eq( sgnl_event_get( $b )['end'], '', 'Ende vor Beginn wird verworfen' );
$c = t_event( 'Test C', array( 'start' => '2026-02-30' ) );
t_eq( sgnl_event_get( $c )['start'], '', 'Ungültiges Datum wird verworfen' );
t_eq( sgnl_event_date_label( array( 'start' => '2026-09-26', 'end' => '2026-09-27' ) ), '26.09. – 27.09.', 'Zeitraum im Jahr' );
t_eq( sgnl_event_date_label( array( 'start' => '2026-12-30', 'end' => '2027-01-02' ) ), '30.12.2026 – 02.01.2027', 'Zeitraum über den Jahreswechsel' );

// Mehrere Termine am selben Tag und Ort
$same1 = t_event( 'Bezirksmeisterschaften Masters', array( 'start' => t_day( 40 ), 'ort' => 'Viernheim' ) );
$same2 = t_event( 'Kreismeisterschaften', array( 'start' => t_day( 40 ), 'ort' => 'Viernheim' ) );
$up    = wp_list_pluck( sgnl_events_query( 'upcoming' ), 'id' );
t_ok( in_array( $same1, $up, true ) && in_array( $same2, $up, true ), 'Zwei Termine am selben Tag und Ort erscheinen beide' );

// Ohne Unterlagen + Kategorien
$fer  = t_event( 'Herbstferien Test', array( 'start' => t_day( -20 ), 'end' => t_day( -10 ) ), 'ferien' );
$jug  = t_event( 'Jugendtreff Test', array( 'start' => t_day( -5 ) ), 'jugend' );
$wk   = t_event( 'Alter Wettkampf Test', array( 'start' => t_day( -5 ), 'protokoll' => 'https://example.org/p.pdf' ), 'wettkampf' );
$none = t_event( 'Ohne Kategorie Test', array( 'start' => t_day( -5 ) ), '' );
$year = (int) substr( t_day( -5 ), 0, 4 );
$arch = wp_list_pluck( sgnl_events_query( 'archive', $year ), 'id' );
t_ok( in_array( $wk, $arch, true ), 'Vergangener Wettkampf steht im Archiv' );
t_ok( in_array( $none, $arch, true ), 'Termin ohne Kategorie steht im Archiv' );
t_ok( ! in_array( $jug, $arch, true ), 'Jugendtermin wird nach dem Termin ausgeblendet' );
t_ok( ! in_array( $fer, $arch, true ), 'Ferien werden nach dem Termin ausgeblendet' );
$fut = t_event( 'Ferien kommend Test', array( 'start' => t_day( 20 ), 'end' => t_day( 30 ) ), 'ferien' );
t_ok( in_array( $fut, wp_list_pluck( sgnl_events_query( 'upcoming' ), 'id' ), true ), 'Kommende Ferien ohne Unterlagen stehen im Terminplan' );
t_ok( in_array( $fut, wp_list_pluck( sgnl_events_query( 'upcoming', 0, array( 'ferien' ) ), 'id' ), true ), 'Filter nach Kategorie (Ferien)' );
t_ok( ! in_array( $a, wp_list_pluck( sgnl_events_query( 'upcoming', 0, array( 'ferien' ) ), 'id' ), true ), 'Filter schließt andere Kategorien aus' );

$r = sgnl_events_render( array() );
t_has( $r, 'sgnl-events__filter', 'Kategorie-Filter wird angezeigt' );
t_has( $r, 'data-cat="ferien"', 'Termine tragen ihre Kategorie' );
t_has( $r, 'sgnl-events__tiles', 'Kachelansicht wird ausgegeben' );
t_has( $r, 'sgnl-terminplan=pdf', 'PDF-Button ist da' );
$ra = sgnl_events_render( array( 'mode' => 'archive', 'year' => $year ) );
t_not( $ra, 'Jugendtreff Test', 'Archiv-Ausgabe enthält keine Jugendtermine' );

t_section( 'Zuordnung gleicher Veranstaltungen' );
t_ok( sgnl_event_match_score( 'Bezirksmeisterschaften Masters', 'Viernheim', 'Bezirks-Masters-Meisterschaften 2026', 'Viernheim' ) >= 50, 'Bezirks-Masters wird erkannt' );
t_ok( sgnl_event_match_score( 'Kreismeisterschaften', 'Viernheim', 'Bezirks-Masters-Meisterschaften 2026', 'Viernheim' ) < 50, 'Kreismeisterschaften ≠ Bezirks-Masters (gleicher Ort)' );
t_ok( sgnl_event_match_score( 'Kreismeisterschaften', 'Viernheim', 'Kreismeisterschaften 2026 d. Kreis Bergstraße', 'Viernheim' ) >= 50, 'Kreismeisterschaften werden erkannt' );
t_ok( sgnl_event_match_score( 'Hessische (Jahrgangs-)Meisterschaften auf der Kurzbahn', 'Wiesbaden', 'Hessische Kurzbahnmeisterschaften 2026', 'Wiesbaden' ) >= 50, 'Hessische Kurzbahn wird erkannt' );

/* =========================================================================
 * Übernahme Terminplan- und Archiv-Tabellen
 * ====================================================================== */
t_section( 'Übernahme aus Tabellen' );
$fmt   = function ( $ymd ) {
	return substr( $ymd, 8, 2 ) . '.' . substr( $ymd, 5, 2 ) . '.';
};
$d1    = t_day( 35 );
$d2    = t_day( 41 );
$d3    = t_day( 45 );
$d4    = t_day( 50 );
$d5    = t_day( 60 );
$tp    = sgnl_table_create( 'Terminplan', array(
	'head' => true,
	'rows' => array(
		array( 'Datum', 'Veranstaltung', 'Ort', 'Bahn', 'Mannschaften', 'Ausschreibung' ),
		array( sgnl_month_name( (int) substr( $d1, 5, 2 ) ), '', '', '', '', '' ),
		array( $fmt( $d1 ), 'Sprintpokal Test', 'Worms', '25 m', '1. Mannschaft', '<a href="https://example.org/ausschreibung-sprint.pdf">Ausschreibung</a>' ),
		array( $fmt( $d2 ), 'Bezirksmeisterschaften Masters', 'Viernheim', '25 m', 'Masters', '' ),
		array( $fmt( $d2 ), 'Kreismeisterschaften', 'Viernheim', '25 m', 'Alle WK-Mannschaften', '' ),
		array( $fmt( $d3 ), 'Halloweenparty', 'Vereinsheim', '', 'Jugend', '' ),
		array( $fmt( $d4 ) . ' - ' . $fmt( $d5 ), 'Herbstferien', '', '', '', '' ),
	),
) );
$y2    = (int) substr( $d2, 0, 4 );
$ar    = sgnl_table_create( 'Archiv ' . $y2, array(
	'head' => true,
	'rows' => array(
		array( 'Datum', 'Ort', 'Veranstaltung', 'Protkoll', 'Ergebnisse' ),
		array( $fmt( $d2 ), 'Viernheim', 'Bezirks-Masters-Meisterschaften', '<a href="https://example.org/proto-bm.pdf">Protokoll</a>', '<a href="https://example.org/erg-bm.pdf">Ergebnisse</a>' ),
		array( $fmt( $d1 ), 'Woanders', 'Archivtest', '<a href="https://example.org/Ausschreibung-Archivtest.pdf">Protokoll</a>', '' ),
	),
) );
$archiv_parent = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Archiv', 'post_name' => 'archiv', 'post_content' => '<!-- wp:paragraph --><p>Archiv</p><!-- /wp:paragraph -->' ) );
$archiv_page   = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Archiv ' . $y2, 'post_name' => 'archiv-' . $y2, 'post_parent' => $archiv_parent, 'post_content' => "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">Archiv {$y2}</h2>\n<!-- /wp:heading -->\n\n<!-- wp:sgnl/table {\"id\":{$ar},\"align\":\"wide\"} /-->" ) );
$tp_page       = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Terminplan', 'post_name' => 'terminplan', 'post_content' => "<!-- wp:sgnl/table {\"id\":{$tp},\"align\":\"wide\"} /-->\n\n<!-- wp:spacer {\"height\":\"30px\"} -->\n<div style=\"height:30px\" aria-hidden=\"true\" class=\"wp-block-spacer\"></div>\n<!-- /wp:spacer -->\n\n<!-- wp:group {\"className\":\"is-style-sgnl-box\",\"layout\":{\"type\":\"flex\"}} -->\n<div class=\"wp-block-group is-style-sgnl-box\"><!-- wp:heading {\"level\":3} -->\n<h3 class=\"wp-block-heading\">Terminplan Herunterladen</h3>\n<!-- /wp:heading --></div>\n<!-- /wp:group -->" ) );

// Menü mit Archiv-Untermenü (für automatische Archivseiten und Galerie)
$menu    = wp_create_nav_menu( 'Hauptmenü Test' );
$m_arch  = wp_update_nav_menu_item( $menu, 0, array( 'menu-item-title' => 'Archiv', 'menu-item-url' => '#', 'menu-item-type' => 'custom', 'menu-item-status' => 'publish' ) );
wp_update_nav_menu_item( $menu, 0, array( 'menu-item-object-id' => $archiv_page, 'menu-item-object' => 'page', 'menu-item-type' => 'post_type', 'menu-item-parent-id' => $m_arch, 'menu-item-status' => 'publish' ) );
$m_db    = wp_update_nav_menu_item( $menu, 0, array( 'menu-item-title' => 'Online Datenbank', 'menu-item-url' => 'https://datenbank.sgnl.de/#live', 'menu-item-type' => 'custom', 'menu-item-status' => 'publish' ) );
set_theme_mod( 'nav_menu_locations', array( 'primary' => $menu ) );

$plan = SGNL_Events_Import::plan();
t_eq( count( $plan['sources'] ), 2, 'Beide Tabellen werden erkannt' );
$byname = array();
foreach ( $plan['events'] as $ev ) {
	$byname[ $ev['title'] ][] = $ev;
}
t_ok( isset( $byname['Kreismeisterschaften'] ), 'Kreismeisterschaften bleiben ein eigener Termin' );
t_eq( $byname['Sprintpokal Test'][0]['start'] ?? '', $d1, 'Jahr wird für den Terminplan richtig ergänzt' );
t_eq( $byname['Herbstferien'][0]['end'] ?? '', $d5, 'Zeitraum „von – bis“ wird gelesen' );
t_eq( $byname['Sprintpokal Test'][0]['ausschreibung'] ?? '', 'https://example.org/ausschreibung-sprint.pdf', 'Ausschreibungslink wird übernommen' );
$on_d2 = array_values( array_filter( $plan['events'], function ( $x ) use ( $d2 ) {
	return $x['start'] === $d2;
} ) );
t_eq( count( $on_d2 ), 2, 'Am ' . $d2 . ' gibt es genau zwei Termine (Archiv-Eintrag zusammengeführt, keine Dublette)' );
$bm = array_values( array_filter( $on_d2, function ( $x ) {
	return '' !== $x['protokoll'];
} ) )[0] ?? array();
t_eq( $bm['protokoll'] ?? '', 'https://example.org/proto-bm.pdf', 'Gleiche Veranstaltung aus dem Archiv wird zusammengeführt (Protokoll)' );
t_eq( $bm['bahn'] ?? '', '25 m', '… und behält die Angaben aus dem Terminplan (Bahn)' );
t_eq( $bm['team'] ?? '', 'Masters', '… mit der richtigen Mannschaft (nicht mit den Kreismeisterschaften vermischt)' );
t_eq( $byname['Archivtest'][0]['ausschreibung'] ?? '', 'https://example.org/Ausschreibung-Archivtest.pdf', 'Als Protokoll verlinkte Ausschreibung wird richtig einsortiert' );

$res = SGNL_Events_Import::run();
t_ok( $res['created'] >= 6, 'Termine werden angelegt (' . $res['created'] . ')' );
$tp_content = get_post( $tp_page )->post_content;
t_has( $tp_content, '<!-- wp:sgnl/events {"align":"wide"} /-->', 'Terminplan-Seite zeigt den Block Termine' );
t_not( $tp_content, 'wp:sgnl/table', 'Tabelle ist von der Terminplan-Seite entfernt' );
t_not( $tp_content, 'Terminplan Herunterladen', 'Alte Download-Box ist entfernt' );
t_not( $tp_content, 'wp:spacer', 'Abstand vor der Download-Box ist entfernt' );
t_has( get_post( $archiv_page )->post_content, '"mode":"archive","year":' . $y2, 'Archivseite zeigt das Archiv des Jahres' );
t_eq( get_post_status( $tp ), 'draft', 'Alte Tabelle bleibt als Entwurf erhalten' );
$hw = t_find( 'Halloweenparty' );
t_eq( $hw ? wp_get_object_terms( $hw[0], 'sgnl_event_cat', array( 'fields' => 'slugs' ) ) : array(), array( 'jugend' ), 'Halloweenparty → Jugendveranstaltungen' );
$hf = t_find( 'Herbstferien' );
t_eq( $hf ? wp_get_object_terms( $hf[0], 'sgnl_event_cat', array( 'fields' => 'slugs' ) ) : array(), array( 'ferien' ), 'Herbstferien → Ferien' );
t_ok( ! SGNL_Events_Import::pending(), 'Nichts mehr zu übernehmen' );

SGNL_Events_Import::undo();
t_has( get_post( $tp_page )->post_content, 'wp:sgnl/table', 'Rückgängig: Tabelle ist wieder da' );
t_eq( get_post_status( $tp ), 'publish', 'Rückgängig: Tabelle wieder veröffentlicht' );
t_eq( count( t_find( 'Halloweenparty' ) ), 0, 'Rückgängig: übernommene Termine im Papierkorb' );
SGNL_Events_Import::run();
t_eq( count( get_posts( array( 'post_type' => 'sgnl_event', 'title' => 'Halloweenparty', 'post_status' => 'publish' ) ) ), 1, 'Erneut übernommen ohne Dubletten' );

/* =========================================================================
 * Galerien
 * ====================================================================== */
t_section( 'Galerien' );
$imgs = array();
for ( $i = 1; $i <= 3; $i++ ) {
	$att = wp_insert_attachment( array( 'post_mime_type' => 'image/jpeg', 'post_title' => "Bild {$i}", 'post_status' => 'inherit' ), "test-{$i}.jpg" );
	wp_update_attachment_metadata( $att, array( 'width' => 1200, 'height' => 800, 'file' => "test-{$i}.jpg", 'sizes' => array() ) );
	$imgs[] = $att;
}
$gal = function ( array $ids ) {
	$h = "<!-- wp:gallery {\"linkTo\":\"none\"} -->\n<figure class=\"wp-block-gallery has-nested-images columns-default is-cropped\">";
	foreach ( $ids as $id ) {
		$url = wp_get_attachment_url( $id );
		$h  .= "<!-- wp:image {\"id\":{$id},\"sizeSlug\":\"large\",\"linkDestination\":\"none\"} -->\n<figure class=\"wp-block-image size-large\"><img src=\"{$url}\" alt=\"\" class=\"wp-image-{$id}\"/></figure>\n<!-- /wp:image -->\n\n";
	}
	return $h . "</figure>\n<!-- /wp:gallery -->";
};
$y0       = (int) wp_date( 'Y' ) - 1;
$gal_page = wp_insert_post( array(
	'post_type'    => 'page',
	'post_status'  => 'publish',
	'post_title'   => 'Galerie ' . $y0,
	'post_name'    => 'galerie-' . $y0,
	'post_parent'  => $archiv_parent,
	'post_content' => "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">Drei-Länder-Pokal {$y0}</h2>\n<!-- /wp:heading -->\n\n" . $gal( array( $imgs[0], $imgs[1] ) ) . "\n\n<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">Vereinsmeisterschaften</h2>\n<!-- /wp:heading -->\n\n" . $gal( array( $imgs[2] ) ),
) );
wp_update_nav_menu_item( $menu, 0, array( 'menu-item-object-id' => $gal_page, 'menu-item-object' => 'page', 'menu-item-type' => 'post_type', 'menu-item-parent-id' => $m_arch, 'menu-item-status' => 'publish' ) );
$old_gal_url = wp_make_link_relative( get_permalink( $gal_page ) );

// Lightbox für Galeriebilder ohne Link
$out = do_blocks( $gal( array( $imgs[0] ) ) );
t_ok( false !== strpos( $out, 'wp-lightbox-container' ) || false !== strpos( $out, 'data-wp-interactive' ), 'Galeriebilder lassen sich per Klick vergrößern' );

$gp = SGNL_Gallery_Setup::plan();
t_eq( array_keys( $gp['years'] ), array( $y0 ), 'Jahr der Galerie wird erkannt' );
t_eq( wp_list_pluck( $gp['years'][ $y0 ], 'slug' ), array( 'dlp', 'vereinsmeisterschaften' ), 'Alben + kurze Adresse /dlp/' );
SGNL_Gallery_Setup::run();
$root  = get_page_by_path( 'galerie' );
$yp    = get_page_by_path( 'galerie/' . $y0 );
$dlp   = get_page_by_path( 'galerie/' . $y0 . '/dlp' );
t_ok( $root && $yp && $dlp, 'Seiten /galerie/, /galerie/' . $y0 . '/ und /dlp/ existieren' );
t_eq( $yp ? $yp->post_name : '', (string) $y0, 'Jahresseite hat die Jahreszahl als Adresse' );
t_eq( $dlp ? (int) get_post_thumbnail_id( $dlp ) : 0, $imgs[0], 'Titelbild = erstes Foto' );
t_eq( get_post_status( $gal_page ), 'draft', 'Alte Galerie-Seite ist Entwurf' );
t_ok( in_array( $old_gal_url, wp_list_pluck( sgnl_get_redirects(), 'from' ), true ), 'Alte Adresse leitet weiter' );
$tiles = sgnl_albums_render( $root->ID );
t_has( $tiles, (string) $y0, 'Auswahlseite zeigt das Jahr als Kachel' );
t_has( $tiles, '3 Fotos', 'Kachel zählt die Fotos' );
t_has( sgnl_albums_render( $yp->ID ), 'Drei-Länder-Pokal', 'Jahresseite zeigt die Alben' );
$urls[] = '/galerie/';
$urls[] = '/galerie/' . $y0 . '/';
$urls[] = '/galerie/' . $y0 . '/dlp/';
file_put_contents( __DIR__ . '/ci/redirect.txt', $old_gal_url . ' /galerie/' . $y0 . '/' );

/* =========================================================================
 * Übernahme der alten Datenbank (SQL-Export)
 * ====================================================================== */
t_section( 'Datenbank-Übernahme' );
$day7 = t_day( 7 );
$bm7  = t_event( 'Bezirksmeisterschaften Masters', array( 'start' => $day7, 'ort' => 'Teststadt' ) );
$km7  = t_event( 'Kreismeisterschaften', array( 'start' => $day7, 'ort' => 'Teststadt' ) );
$sql  = strtr( file_get_contents( __DIR__ . '/fixtures/wettkaempfe.sql' ), array(
	'__S1__' => sgnl_event_ts( $day7, '08:00' ),
	'__E1__' => sgnl_event_ts( $day7, '12:00' ),
	'__E2__' => sgnl_event_ts( $day7, '17:00' ),
	'__S3__' => time() - HOUR_IN_SECONDS,
	'__E3__' => time() + HOUR_IN_SECONDS,
	'__S4__' => sgnl_event_ts( t_day( 30 ), '09:00' ),
	'__E4__' => sgnl_event_ts( t_day( 30 ), '18:00' ),
) );
$rows = SGNL_DB_Import::parse( $sql );
t_eq( count( $rows ), 4, 'Vier Wettkämpfe gelesen' );
t_eq( $rows[2]['title'] ?? '', 'Laufender Wettkampf "Lange Strecken"', 'Anführungszeichen im Namen' );
t_eq( $rows[3]['title'] ?? '', "Brandneuer Wettkampf mit O'Brien", 'Apostroph im Namen' );
t_eq( $rows[3]['ort'] ?? 'x', '', 'NULL wird leer' );
t_eq( count( $rows[2]['nachrichten'] ?? array() ), 2, 'Nachrichten werden zugeordnet' );
t_ok( false !== strpos( $rows[2]['nachrichten'][0]['text'] ?? '', "\n" ), 'Zeilenumbruch in Nachricht' );
t_eq( $rows[0]['start_time'] ?? '', '08:00', 'Uhrzeit aus dem Zeitstempel' );
$map = SGNL_DB_Import::assign( $rows );
t_eq( $map[1], $bm7, 'Bezirks-Masters → vorhandener Termin' );
t_eq( $map[2], $km7, 'Kreismeisterschaften → vorhandener Termin (gleicher Tag und Ort)' );
t_eq( $map[4], 0, 'Unbekannter Wettkampf → neu' );
$res = SGNL_DB_Import::run( $rows );
t_eq( $res['merged'] + $res['created'], 4, 'Alle Wettkämpfe übernommen' );
$e = sgnl_event_get( $bm7 );
t_eq( $e['meldeergebnis'], 'https://example.org/meldeergebnis-1.pdf', 'Meldeergebnis ergänzt' );
t_eq( $e['end_time'], '12:00', 'Uhrzeit Ende ergänzt' );
$run_id = t_find( 'Laufender Wettkampf "Lange Strecken"' );
t_ok( (bool) $run_id, 'Neuer Wettkampf angelegt' );
t_eq( $run_id ? wp_get_object_terms( $run_id[0], 'sgnl_event_cat', array( 'fields' => 'slugs' ) ) : array(), array( 'wettkampf' ), 'Neuer Wettkampf hat Kategorie Wettkämpfe' );
SGNL_DB_Import::run( $rows );
t_eq( count( sgnl_event_messages( $run_id[0] ) ), 2, 'Erneutes Einlesen erzeugt keine doppelten Nachrichten' );

// Seite /datenbank/ einrichten
$post_with_link = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Startseite', 'post_name' => 'startseite', 'post_content' => "<!-- wp:group {\"tagName\":\"section\",\"align\":\"full\",\"backgroundColor\":\"water\",\"layout\":{\"type\":\"constrained\"}} -->\n<section class=\"wp-block-group alignfull has-water-background-color has-background\"><!-- wp:paragraph -->\n<p><a href=\"https://datenbank.sgnl.de/index.html/#live\">Live</a></p>\n<!-- /wp:paragraph --></section>\n<!-- /wp:group -->\n\n<!-- wp:group {\"align\":\"wide\",\"className\":\"sgnl-cards\",\"layout\":{\"type\":\"grid\",\"minimumColumnWidth\":\"18rem\"}} -->\n<div class=\"wp-block-group alignwide sgnl-cards\"><!-- wp:group {\"className\":\"is-style-sgnl-card\"} -->\n<div class=\"wp-block-group is-style-sgnl-card\"><!-- wp:heading {\"level\":3} -->\n<h3 class=\"wp-block-heading\">Terminplan</h3>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>Alle Wettkämpfe und Termine.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:buttons -->\n<div class=\"wp-block-buttons\"><!-- wp:button -->\n<div class=\"wp-block-button\"><a class=\"wp-block-button__link wp-element-button\" href=\"/terminplan/\">Zum Terminplan</a></div>\n<!-- /wp:button --></div>\n<!-- /wp:buttons --></div>\n<!-- /wp:group -->\n\n<!-- wp:group {\"className\":\"is-style-sgnl-card\"} -->\n<div class=\"wp-block-group is-style-sgnl-card\"><!-- wp:heading {\"level\":3} -->\n<h3 class=\"wp-block-heading\">Training</h3>\n<!-- /wp:heading --></div>\n<!-- /wp:group --></div>\n<!-- /wp:group -->" ) );
update_option( 'show_on_front', 'page' );
update_option( 'page_on_front', $post_with_link );
$su = SGNL_DB_Import::setup( 'trainer-test' );
$db = get_page_by_path( 'datenbank' );
t_ok( (bool) $db, 'Seite /datenbank/ angelegt' );
t_eq( get_post( $su['trainer'] )->post_password, 'trainer-test', 'Trainerbereich ist passwortgeschützt' );
t_has( get_post_meta( $m_db, '_menu_item_url', true ), '/datenbank/#live', 'Menüpunkt zeigt auf /datenbank/#live' );
t_has( get_post( $post_with_link )->post_content, '/datenbank/#live', 'Link im Inhalt umgestellt' );
$dbh = sgnl_db_render( array( 'tabs' => 'live,termine,ergebnisse,bestenliste,rekorde' ) );
foreach ( array( 'live', 'termine', 'ergebnisse', 'bestenliste', 'rekorde' ) as $tab ) {
	t_has( $dbh, 'data-tab="' . $tab . '"', "Reiter {$tab}" );
}
t_not( $dbh, 'oeffentlich-termine-ex.php', 'Kein webclub-Terminplan' );
t_not( $dbh, 'webclub', 'Keine webclub-Adresse auf der Seite' );
t_not( $dbh, 'data-act="open"', 'Kein Link „Neues Fenster“' );
t_has( $dbh, 'data-src="' . home_url( '/vereinsdaten/oeffentlich-ergebnisse-ex.php' ) . '"', 'Ergebnisse kommen über die eigene Website' );
t_not( $dbh, 'data-role="gate"', 'Keine Zustimmung nötig (nichts Externes)' );

t_section( 'Vereinsdaten über die eigene Website' );
t_eq( sgnl_wc_target( '/vereinsdaten/oeffentlich-ergebnisse-ex.php' ), array( 'https://sgnl.webclub.app/oeffentlich-ergebnisse-ex.php', false ), 'Seite wird weitergereicht' );
t_eq( sgnl_wc_target( '/vereinsdaten/ajax.php' ), array( 'https://sgnl.webclub.app/ajax.php', false ), 'Datenabfrage wird weitergereicht' );
t_eq( sgnl_wc_target( '/oeffentlich-schwimmer-ex.php' ), array( 'https://sgnl.webclub.app/oeffentlich-schwimmer-ex.php', false ), 'Sprung zur Schwimmerseite funktioniert' );
t_eq( sgnl_wc_target( '/vereinsdaten/cdn/v1.79/js/jquery.min.js' ), array( 'https://cdn.webclub.app/v1.79/js/jquery.min.js', true ), 'Skripte/Bilder kommen über die eigene Website' );
t_eq( sgnl_wc_target( '/vereinsdaten/intern-mitglieder.php' ), null, 'Interne webclub-Seiten werden nicht weitergereicht' );
t_eq( sgnl_wc_target( '/vereinsdaten/cdn/../etc/passwd' ), null, 'Kein Ausbrechen aus dem Pfad' );
t_eq( sgnl_wc_target( '/wp-login.php' ), null, 'Normale Seiten bleiben unberührt' );
$rw = sgnl_wc_rewrite( '<html><head><link href="https://cdn.webclub.app/v1/css/a.css"></head><body><a href="https://sgnl.webclub.app/x.php">x</a></body></html>', 'text/html; charset=utf-8' );
t_not( $rw, 'webclub', 'Alle webclub-Adressen werden umgeschrieben' );
t_has( $rw, home_url( '/vereinsdaten/cdn/v1/css/a.css' ), 'CDN-Adresse zeigt auf die eigene Website' );
t_has( $rw, 'id="sgnl-wc"', 'Eigene Gestaltung wird eingefügt' );
$ifr = render_block( array( 'blockName' => 'sgnl/iframe', 'attrs' => array( 'src' => 'https://sgnl.webclub.app/oeffentlich-rekorde-ex.php', 'height' => 900 ), 'innerBlocks' => array(), 'innerHTML' => '', 'innerContent' => array() ) );
t_has( $ifr, home_url( '/vereinsdaten/oeffentlich-rekorde-ex.php' ), 'Block „Eingebettete Seite“ mit webclub-Adresse → eigene Website' );
t_has( $ifr, 'data-sgnl-fit', '… mit automatischer Höhe' );
t_not( $ifr, 'sgnl-gate', '… ohne Zustimmungs-Hinweis' );
$urls[] = '/datenbank/';
$urls[] = '/datenbank/trainer/';

/* =========================================================================
 * Startseite: nächste Termine in der Kachel "Terminplan"
 * ====================================================================== */
t_section( 'Startseite: nächste Termine' );
t_ok( sgnl_events_home_insert(), 'Block wird in die Kachel „Terminplan“ eingesetzt' );
$home = get_post( $post_with_link )->post_content;
t_has( $home, '<!-- wp:sgnl/next-events {"showLink":false} /-->', '… ohne doppelten Link (Kachel hat schon einen Button)' );
t_ok( strpos( $home, 'wp:sgnl/next-events' ) < strpos( $home, 'Zum Terminplan</a>' ) && strpos( $home, 'wp:sgnl/next-events' ) > strpos( $home, 'Alle Wettkämpfe' ), '… zwischen Text und Button' );
t_eq( substr_count( $home, 'wp:sgnl/next-events' ), 1, '… nur in der Terminplan-Kachel' );
t_ok( ! sgnl_events_home_insert(), 'Kein zweites Einsetzen' );
t_eq( count( parse_blocks( $home ) ), count( parse_blocks( get_post_meta( $post_with_link, '_sgnl_backup_home', true ) ) ), 'Aufbau der Startseite bleibt erhalten' );
$next = sgnl_next_events_render( array( 'count' => 3 ) );
t_eq( substr_count( $next, 'sgnl-next__item' ), 3, 'Genau 3 Termine' );
$first = sgnl_events_query( 'upcoming' )[0];
t_has( $next, esc_html( $first['title'] ), 'Beginnt mit dem nächsten Termin' );
t_has( $next, 'Zum Terminplan', 'Link zum Terminplan' );
t_has( $next, wp_make_link_relative( sgnl_events_plan_url() ) === '/terminplan/' ? '/terminplan/' : 'terminplan', 'Link zeigt auf die Terminplan-Seite' );

/* =========================================================================
 * Live
 * ====================================================================== */
t_section( 'Live' );
$ferien_now = t_event( 'Ferien jetzt Test', array( 'start' => t_day( -1 ), 'end' => t_day( 1 ) ), 'ferien' );
$wk_now     = t_event( 'Wettkampf heute ohne Links', array( 'start' => t_day( 0 ) ), 'wettkampf' );
$old_link   = t_event( 'Vor drei Tagen mit Link', array( 'start' => t_day( -4 ), 'livetiming' => 'https://example.org/l' ), 'wettkampf' );
$res        = rest_do_request( new WP_REST_Request( 'GET', '/sgnl/v1/live' ) );
t_eq( $res->get_status(), 200, 'REST-Endpunkt antwortet' );
$names = wp_list_pluck( $res->get_data()['events'], 'name' );
t_ok( in_array( 'Laufender Wettkampf "Lange Strecken"', $names, true ), 'Laufender Wettkampf erscheint (Name ohne HTML-Zeichen)' );
t_ok( in_array( 'Wettkampf heute ohne Links', $names, true ), 'Laufender Wettkampf ohne Links erscheint' );
t_ok( in_array( 'Bezirksmeisterschaften Masters', $names, true ), 'Kommender Wettkampf mit Meldeergebnis erscheint' );
t_ok( ! in_array( "Brandneuer Wettkampf mit O'Brien", $names, true ), 'Kommender Wettkampf ohne Links erscheint nicht' );
t_ok( ! in_array( 'Ferien jetzt Test', $names, true ), 'Laufende Ferien erscheinen nicht als Live' );
t_ok( ! in_array( 'Vor drei Tagen mit Link', $names, true ), 'Älter als 48 Stunden erscheint nicht' );
$lr = sgnl_live_render();
t_has( $lr, 'live-card--now', 'Laufender Wettkampf ist als „Live jetzt“ markiert' );
t_has( $lr, 'Wichtige Info', 'Nachricht wird angezeigt' );

/* =========================================================================
 * PDF
 * ====================================================================== */
t_section( 'PDF' );
$many = sgnl_events_query( 'upcoming' );
for ( $i = 0; $i < 70; $i++ ) {
	$many[] = array_merge( $many[0], array( 'title' => "Füllwettkampf {$i} mit Umlauten äöüß und einem sehr langen Namen", 'start' => t_day( 100 + $i * 3 ), 'end' => '', 'ausschreibung' => $i % 2 ? 'https://example.org/a.pdf' : '' ) );
}
$pdf = SGNL_Events_PDF::terminplan( $many, time() );
t_eq( substr( $pdf, 0, 8 ), '%PDF-1.4', 'PDF-Kopf' );
t_eq( substr( $pdf, -5 ), '%%EOF', 'PDF-Ende' );
t_ok( preg_match_all( '#/Type /Page /Parent#', $pdf ) > 1, 'Mehrere Seiten' );
t_ok( substr_count( $pdf, '/S /URI' ) >= 35, 'Ausschreibungen sind verlinkt' );
t_ok( false === strpos( gzuncompress_all( $pdf ), '&#' ), 'Keine HTML-Zeichen (&#…) im PDF' );
file_put_contents( __DIR__ . '/ci/test.pdf', $pdf );

/* =========================================================================
 * Archivseiten automatisch
 * ====================================================================== */
t_section( 'Archivseite für ein neues Jahr' );
$yy = (int) wp_date( 'Y' ) - 3;
t_event( 'Sehr alter Wettkampf', array( 'start' => $yy . '-06-01' ), 'wettkampf' );
$created = sgnl_events_maybe_create_archive_pages();
t_ok( in_array( $yy, $created, true ), "Seite Archiv {$yy} wird angelegt" );
$pages = sgnl_events_archive_pages();
t_ok( isset( $pages[ $yy ] ) && (int) get_post( $pages[ $yy ] )->post_parent === $archiv_parent, '… unter der Seite Archiv' );
$in_menu = false;
foreach ( wp_get_nav_menu_items( $menu ) as $it ) {
	$in_menu = $in_menu || ( 'page' === $it->object && (int) $it->object_id === (int) ( $pages[ $yy ] ?? 0 ) );
}
t_ok( $in_menu, '… und im Menü' );
t_eq( sgnl_events_maybe_create_archive_pages(), array(), 'Keine doppelte Archivseite' );

/* =========================================================================
 * Theme
 * ====================================================================== */
t_section( 'Theme' );
if ( function_exists( 'sgnl_first_section_color' ) ) {
	$GLOBALS['wp_query']     = new WP_Query( array( 'page_id' => $post_with_link ) );
	$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
	t_eq( sgnl_first_section_color(), 'var(--wp--preset--color--water)', 'Welle übernimmt die Farbe des ersten Abschnitts' );
	ob_start();
	sgnl_wave();
	t_has( ob_get_clean(), 'fill:var(--wp--preset--color--water)', 'Welle wird eingefärbt' );
	wp_reset_query();
} else {
	t_ok( false, 'Theme SG Neptun ist aktiv' );
}

/* =========================================================================
 * Ergebnis
 * ====================================================================== */
$urls = array_merge( array( '/', '/terminplan/', '/archiv/archiv-' . $y2 . '/', '/wp-json/sgnl/v1/live', '/?sgnl-terminplan=pdf' ), $urls );
file_put_contents( __DIR__ . '/ci/urls.txt', implode( "\n", $urls ) . "\n" );
$ids = array( $tp_page, $archiv_page, $root->ID, $yp->ID, $dlp->ID, $db->ID, $post_with_link );
file_put_contents( __DIR__ . '/ci/editor-ids.txt', implode( ' ', $ids ) . "\n" );

$t = $GLOBALS['sgnl_t'];
echo "\n" . $t['pass'] . ' Prüfungen bestanden, ' . count( $t['fail'] ) . " fehlgeschlagen.\n";
if ( $t['fail'] ) {
	echo "\nFehlgeschlagen:\n - " . implode( "\n - ", $t['fail'] ) . "\n";
	exit( 1 );
}
