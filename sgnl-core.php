<?php
/**
 * Plugin Name:       SG Neptun Funktionen
 * Plugin URI:        https://sgnl.de/
 * Description:       Vereinsfunktionen der SG Neptun: Termine (Terminplan, Archiv, PDF), Datenbank mit Live-Wettkämpfen, Galerie-Übersicht, Tabellen-Verwaltung, Cookie-Banner, Hinweise (Popups), Weiterleitungen, iFrame-Block, Kommentare aus, Umstellung von alten Plugins. Ersetzt TablePress, CookieYes, Popup Maker, Redirect Redirection, Advanced iFrame und Disable Comments.
 * Version:           1.7.0
 * Requires at least: 6.6
 * Tested up to:      7.1
 * Requires PHP:      7.4
 * Author:            greylo.de
 * Author URI:        https://greylo.de/
 * License:           GPL-3.0-or-later
 * Text Domain:       sgnl-core
 * Update URI:        https://github.com/nicigrv/sgnl-wp-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SGNL_CORE_VERSION', '1.7.0' );
define( 'SGNL_CORE_FILE', __FILE__ );
define( 'SGNL_CORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'SGNL_CORE_URL', plugin_dir_url( __FILE__ ) );

require SGNL_CORE_DIR . 'inc/helpers.php';
require SGNL_CORE_DIR . 'inc/class-github-updater.php';

// Updates direkt aus GitHub (Dashboard → Aktualisierungen).
new SGNL_Core_GitHub_Updater( array(
	'type'  => 'plugin',
	'repo'  => 'nicigrv/sgnl-wp-core',
	'slug'  => dirname( plugin_basename( __FILE__ ) ),
	'file'  => plugin_basename( __FILE__ ),
	'asset' => 'sgnl-core.zip',
	'name'  => 'SG Neptun Funktionen',
) );
require SGNL_CORE_DIR . 'inc/comments-off.php';
require SGNL_CORE_DIR . 'inc/redirects.php';
require SGNL_CORE_DIR . 'inc/notices.php';
require SGNL_CORE_DIR . 'inc/iframe-block.php';
require SGNL_CORE_DIR . 'inc/tables.php';
require SGNL_CORE_DIR . 'inc/consent.php';
require SGNL_CORE_DIR . 'inc/events.php';
require SGNL_CORE_DIR . 'inc/galleries.php';
require SGNL_CORE_DIR . 'inc/live.php';
require SGNL_CORE_DIR . 'inc/webclub-proxy.php';
require SGNL_CORE_DIR . 'inc/datenbank.php';

if ( is_admin() ) {
	require SGNL_CORE_DIR . 'inc/migration/class-converter.php';
	require SGNL_CORE_DIR . 'inc/migration/class-homepage-converter.php';
	require SGNL_CORE_DIR . 'inc/migration/class-migration.php';
	require SGNL_CORE_DIR . 'inc/migration/class-events-import.php';
	require SGNL_CORE_DIR . 'inc/migration/class-gallery-setup.php';
	require SGNL_CORE_DIR . 'inc/migration/class-db-import.php';
	require SGNL_CORE_DIR . 'inc/migration/admin-page.php';
}
