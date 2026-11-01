<?php
/**
 * Ablauf der Umstellung: Analyse, Ausführung, Rückgängig, Aufräumen.
 *
 * Sicherheit:
 *  - Vor jeder Änderung wird der Originalinhalt in _sgnl_backup_content gesichert.
 *  - "Rückgängig" stellt alle gesicherten Inhalte wieder her.
 *  - Daten alter Plugins werden nur beim separaten Schritt "Aufräumen" gelöscht.
 *
 * @package sgnl-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SGNL_Migration {

	const BACKUP_KEY = '_sgnl_backup_content';

	/** Plugins, deren Aufgaben jetzt Theme bzw. "SG Neptun Funktionen" übernehmen. */
	public static function replaced_plugins() {
		return array(
			'mesmerize-companion/mesmerize-companion.php' => __( 'Startseiten-Builder → normale Blöcke', 'sgnl-core' ),
			'siteorigin-panels/siteorigin-panels.php'     => __( 'Page Builder – wurde nicht genutzt', 'sgnl-core' ),
			'so-widgets-bundle/so-widgets-bundle.php'     => __( 'Boxen/Buttons → Info-Box / Buttons', 'sgnl-core' ),
			'tablepress/tablepress.php'                   => __( 'Tabellen → Dashboard → Tabellen', 'sgnl-core' ),
			'team-members/tmm.php'                        => __( 'Teams → Team-Karten', 'sgnl-core' ),
			'modula-best-grid-gallery/Modula.php'         => __( 'Galerien → Galerie-Block mit Lightbox', 'sgnl-core' ),
			'popup-maker/popup-maker.php'                 => __( 'Popups → Hinweise', 'sgnl-core' ),
			'advanced-iframe/advanced-iframe.php'         => __( 'iFrames → Block „Eingebettete Seite“', 'sgnl-core' ),
			'redirect-redirection/redirect-redirection.php' => __( 'Weiterleitungen → Werkzeuge → Weiterleitungen', 'sgnl-core' ),
			'cookie-law-info/cookie-law-info.php'         => __( 'Cookie-Banner → eigener Banner', 'sgnl-core' ),
			'disable-comments/disable-comments.php'       => __( 'Kommentare aus → eingebaut', 'sgnl-core' ),
			'host-webfonts-local/host-webfonts-local.php' => __( 'Schriften liegen jetzt im Theme', 'sgnl-core' ),
			'feed-them-social/feed-them-social.php'       => __( 'wurde nicht genutzt', 'sgnl-core' ),
			'mainwp-child/mainwp-child.php'               => __( 'nicht mehr benötigt (Verwaltung über ManageWP)', 'sgnl-core' ),
			'wpmudev-updates/update-notifications.php'    => __( 'nicht mehr benötigt (enthält auch die WPMU-DEV-Statistik)', 'sgnl-core' ),
		);
	}

	/**
	 * Zu prüfende Inhalte.
	 *
	 * @return int[]
	 */
	public static function candidate_ids() {
		global $wpdb;
		return array_map( 'intval', $wpdb->get_col(
			"SELECT ID FROM {$wpdb->posts}
			 WHERE post_type IN ('post','page','wp_block','sgnl_notice')
			   AND post_status IN ('publish','private','draft','future','pending')
			   AND ( post_content LIKE '%wp:sowb/%' OR post_content LIKE '%wp:tablepress/%' OR post_content LIKE '%[table %'
			      OR post_content LIKE '%wp:modula/%' OR post_content LIKE '%[modula%' OR post_content LIKE '%[tmm %'
			      OR post_content LIKE '%wp:ai/ai-block%' OR post_content LIKE '%[wpspeedo-team%' OR post_content LIKE '%[wpforms%'
			      OR post_content LIKE '%data-export-id=%' OR post_content LIKE '%href=\"matilo:%' )
			 ORDER BY post_type, menu_order, post_title"
		) );
	}

	/**
	 * Einen Inhalt umwandeln (ohne zu speichern).
	 *
	 * @param WP_Post $post Beitrag.
	 * @return array{content:string,log:array,warnings:array,changed:bool}
	 */
	public static function convert_post( WP_Post $post, $dry = false ) {
		$content = $post->post_content;
		$log     = array();
		$warn    = array();

		if ( SGNL_Homepage_Converter::is_builder_content( $content ) ) {
			$hc      = new SGNL_Homepage_Converter();
			$content = $hc->convert( $content );
			$log     = array_merge( $log, $hc->log );
			$warn    = array_merge( $warn, $hc->warnings );
		}

		$c       = new SGNL_Converter();
		$c->dry  = $dry;
		$content = $c->convert( $content );
		$log     = array_merge( $log, $c->log );
		$warn    = array_merge( $warn, $c->warnings );

		return array(
			'content'  => $content,
			'log'      => array_values( array_unique( $log ) ),
			'warnings' => array_values( array_unique( $warn ) ),
			'changed'  => $content !== $post->post_content,
		);
	}

	/**
	 * Analyse für die Vorschau.
	 *
	 * @return array
	 */
	public static function analyze() {
		$items = array();
		foreach ( self::candidate_ids() as $id ) {
			$p = get_post( $id );
			if ( ! $p ) {
				continue;
			}
			$r = self::convert_post( $p, true );
			if ( ! $r['changed'] && ! $r['warnings'] ) {
				continue;
			}
			$items[] = array(
				'id'       => $id,
				'title'    => get_the_title( $p ) ?: '(' . $p->post_name . ')',
				'type'     => $p->post_type,
				'status'   => $p->post_status,
				'log'      => $r['log'],
				'warnings' => $r['warnings'],
				'changed'  => $r['changed'],
			);
		}
		return $items;
	}

	/**
	 * Inhalte umstellen.
	 *
	 * @return array Protokoll.
	 */
	public static function run_content() {
		global $wpdb;
		$done = array();
		foreach ( self::candidate_ids() as $id ) {
			$p = get_post( $id );
			if ( ! $p ) {
				continue;
			}
			$r = self::convert_post( $p );
			if ( ! $r['changed'] ) {
				continue;
			}
			if ( '' === get_post_meta( $id, self::BACKUP_KEY, true ) ) {
				add_post_meta( $id, self::BACKUP_KEY, wp_slash( $p->post_content ), true );
			}
			$wpdb->update( $wpdb->posts, array( 'post_content' => $r['content'] ), array( 'ID' => $id ) );
			clean_post_cache( $id );
			$done[] = array( 'id' => $id, 'title' => get_the_title( $id ), 'log' => $r['log'], 'warnings' => $r['warnings'] );
		}
		return $done;
	}

	/**
	 * Alle übrigen TablePress-Tabellen in die Tabellen-Verwaltung übernehmen
	 * (auch solche, die gerade auf keiner Seite stehen).
	 *
	 * @return int Anzahl neu übernommener Tabellen.
	 */
	public static function run_tables() {
		$map = json_decode( (string) get_option( 'tablepress_tables', '' ), true );
		if ( empty( $map['table_post'] ) || ! function_exists( 'sgnl_table_create' ) ) {
			return 0;
		}
		$before = (int) wp_count_posts( 'sgnl_table' )->publish;
		$c      = new SGNL_Converter();
		foreach ( array_keys( $map['table_post'] ) as $table_id ) {
			$c->shortcode_block( '[table id=' . $table_id . ' /]' );
		}
		wp_cache_delete( _count_posts_cache_key( 'sgnl_table' ), 'counts' );
		return max( 0, (int) wp_count_posts( 'sgnl_table' )->publish - $before );
	}

	/**
	 * Popup Maker → Hinweise.
	 *
	 * @return int Anzahl neu angelegter Hinweise.
	 */
	public static function run_popups() {
		$popups = get_posts( array( 'post_type' => 'popup', 'post_status' => array( 'publish', 'draft', 'private' ), 'numberposts' => -1 ) );
		$n      = 0;
		foreach ( $popups as $p ) {
			$exists = get_posts( array( 'post_type' => 'sgnl_notice', 'post_status' => 'any', 'meta_key' => '_sgnl_from_popup', 'meta_value' => $p->ID, 'numberposts' => 1, 'fields' => 'ids' ) ); // phpcs:ignore
			if ( $exists ) {
				continue;
			}
			$settings = get_post_meta( $p->ID, 'popup_settings', true );
			$enabled  = (string) get_post_meta( $p->ID, 'enabled', true );
			$front    = is_array( $settings ) && false !== strpos( wp_json_encode( $settings ), 'is_front_page' );
			$title    = get_post_meta( $p->ID, 'popup_title', true );
			$new_id   = wp_insert_post( wp_slash( array(
				'post_type'    => 'sgnl_notice',
				'post_status'  => 'publish',
				'post_title'   => $title ? $title : $p->post_title,
				'post_content' => $p->post_content,
			) ) );
			if ( $new_id && ! is_wp_error( $new_id ) ) {
				update_post_meta( $new_id, '_sgnl_active', '1' === $enabled && 'publish' === $p->post_status );
				update_post_meta( $new_id, '_sgnl_scope', $front ? 'front' : 'all' );
				update_post_meta( $new_id, '_sgnl_from_popup', $p->ID );
				$n++;
			}
		}
		return $n;
	}

	/**
	 * Weiterleitungen aus "Redirect Redirection" übernehmen.
	 *
	 * @return int Anzahl.
	 */
	public static function run_redirects() {
		global $wpdb;
		$table = $wpdb->prefix . 'irrp_redirections';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return 0;
		}
		$rows     = $wpdb->get_results( "SELECT r.*, (SELECT meta_value FROM {$wpdb->prefix}irrp_redirectionmeta m WHERE m.redirect_id = r.id AND m.meta_key = 'redirect_code' LIMIT 1) AS code FROM {$table} r WHERE r.status = 1", ARRAY_A ); // phpcs:ignore
		$existing = sgnl_get_redirects();
		$have     = array();
		foreach ( $existing as $e ) {
			$have[ sgnl_normalize_path( $e['from'] ) ] = true;
		}
		$n = 0;
		foreach ( $rows as $r ) {
			$from = $r['match'] ? $r['match'] : $r['from'];
			if ( ! $from || isset( $have[ sgnl_normalize_path( $from ) ] ) ) {
				continue;
			}
			$existing[] = array(
				'from' => '/' . ltrim( (string) wp_parse_url( $from, PHP_URL_PATH ), '/' ),
				'to'   => $r['to'],
				'code' => $r['code'] ? (int) $r['code'] : 301,
			);
			$n++;
		}
		update_option( 'sgnl_redirects', $existing, true );
		return $n;
	}

	/**
	 * Rückgängig machen.
	 *
	 * @return int Anzahl wiederhergestellter Inhalte.
	 */
	public static function undo() {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s", self::BACKUP_KEY ) );
		foreach ( $rows as $r ) {
			$wpdb->update( $wpdb->posts, array( 'post_content' => $r->meta_value ), array( 'ID' => $r->post_id ) );
			delete_post_meta( $r->post_id, self::BACKUP_KEY );
			clean_post_cache( $r->post_id );
		}
		return count( $rows );
	}

	/**
	 * Anzahl gesicherter Inhalte.
	 *
	 * @return int
	 */
	public static function backup_count() {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s", self::BACKUP_KEY ) );
	}

	/* =====================================================================
	 * Aufräumen
	 * ================================================================== */

	/** Inhaltstypen gelöschter/ersetzter Plugins. */
	public static function leftover_post_types() {
		return array(
			'popup'                 => array( __( 'Popup Maker: Popups (sind als Hinweise übernommen)', 'sgnl-core' ), true ),
			'popup_theme'           => array( __( 'Popup Maker: Designs', 'sgnl-core' ), true ),
			'modula-gallery'        => array( __( 'Modula: Galerien (Bilder bleiben in der Mediathek)', 'sgnl-core' ), true ),
			'tmm'                   => array( __( 'Team Members: Teams', 'sgnl-core' ), true ),
			'tablepress_table'      => array( __( 'TablePress: Tabellen (sind in „Tabellen“ übernommen)', 'sgnl-core' ), true ),
			'wps-team-members'      => array( __( 'WPS Team (gelöschtes Plugin)', 'sgnl-core' ), true ),
			'team'                  => array( __( 'Team-Plugin (gelöscht)', 'sgnl-core' ), true ),
			'team-sc'               => array( __( 'Team-Plugin (gelöscht)', 'sgnl-core' ), true ),
			'envira'                => array( __( 'Envira-Galerien (gelöschtes Plugin)', 'sgnl-core' ), true ),
			'ml-slide'              => array( __( 'MetaSlider (gelöscht)', 'sgnl-core' ), true ),
			'ml-slider'             => array( __( 'MetaSlider (gelöscht)', 'sgnl-core' ), true ),
			'nf_sub'                => array( __( 'Ninja Forms: alte Einsendungen', 'sgnl-core' ), true ),
			'wpforms'               => array( __( 'WPForms-Formulare (gelöscht)', 'sgnl-core' ), true ),
			'fts'                   => array( __( 'Feed Them Social', 'sgnl-core' ), true ),
			'responsive_accordion'  => array( __( 'Accordion-Plugin (gelöscht)', 'sgnl-core' ), true ),
			'sc_event'              => array( __( 'Event-Plugin (gelöscht)', 'sgnl-core' ), true ),
			'event_listing'         => array( __( 'Event-Plugin (gelöscht)', 'sgnl-core' ), true ),
			'tribe_events'          => array( __( 'The Events Calendar (gelöscht)', 'sgnl-core' ), true ),
			'tribe_venue'           => array( __( 'The Events Calendar (gelöscht)', 'sgnl-core' ), true ),
			'tribe_organizer'       => array( __( 'The Events Calendar (gelöscht)', 'sgnl-core' ), true ),
			'tribe_rsvp_tickets'    => array( __( 'The Events Calendar (gelöscht)', 'sgnl-core' ), true ),
			'ppfuture_workflow'     => array( __( 'PublishPress Future (gelöscht)', 'sgnl-core' ), true ),
			'flamingo_inbound'      => array( __( 'Flamingo: gespeicherte Kontaktanfragen (personenbezogen!)', 'sgnl-core' ), false ),
			'flamingo_contact'      => array( __( 'Flamingo: gespeicherte Kontakte (personenbezogen!)', 'sgnl-core' ), false ),
		);
	}

	/** Tabellen, die bleiben (WordPress und weiter genutzte Plugins). */
	public static function keep_table_suffixes() {
		return array(
			'commentmeta', 'comments', 'links', 'options', 'postmeta', 'posts', 'term_relationships', 'term_taxonomy',
			'termmeta', 'terms', 'usermeta', 'users',
			'simple_history', 'simple_history_contexts',
			'fbv', 'fbv_attachment_folder',
			'mclean_',
			'actionscheduler_',
			'wpmm_', 'mainwp_child_', // werden nur gelöscht, wenn ausgewählt
		);
	}

	/**
	 * Tabellen alter Plugins.
	 *
	 * @return array<string, array{rows:int, default:bool}>
	 */
	public static function leftover_tables() {
		global $wpdb;
		$all  = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $wpdb->prefix ) . '%' ) );
		$keep = array( 'commentmeta', 'comments', 'links', 'options', 'postmeta', 'posts', 'term_relationships', 'term_taxonomy', 'termmeta', 'terms', 'usermeta', 'users', 'simple_history', 'simple_history_contexts', 'fbv', 'fbv_attachment_folder' );
		$keep_prefix = array( 'mclean_', 'actionscheduler_' );
		$known_old   = array( 'cky_', 'defender_', 'depicter_', 'icwp_wpsf_', 'irrp_', 'mainwp_child_', 'nextend2_', 'nf3_', 'postexpirator_', 'ppfuture_', 'pum_', 'realmedialibrary', 'sc_', 'smush_', 'snapshot_', 'tec_', 'tribe_', 'wpforms_', 'e_', 'wfls_', 'wf', 'wpmm_', 'yoast_', 'redirection_', 'aiowps_', 'ewwwio_', 'litespeed_', 'sbi_', 'cff_', 'frm_', 'gf_', 'rank_math', 'wpr_', 'mailpoet_', 'newsletter', 'ninja_', 'popularposts', 'statistics', 'wpdatatables', 'ai1ec_', 'em_', 'gmw_', 'team_', 'wps_', 'fts_', 'wc_', 'woocommerce_' );
		$out = array();
		foreach ( $all as $t ) {
			$suffix = substr( $t, strlen( $wpdb->prefix ) );
			if ( in_array( $suffix, $keep, true ) ) {
				continue;
			}
			$skip = false;
			foreach ( $keep_prefix as $kp ) {
				if ( 0 === strpos( $suffix, $kp ) ) {
					$skip = true;
				}
			}
			if ( $skip ) {
				continue;
			}
			$default = false;
			foreach ( $known_old as $k ) {
				if ( 0 === strpos( $suffix, $k ) ) {
					$default = true;
				}
			}
			$out[ $t ] = array(
				'rows'    => (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$t}`" ), // phpcs:ignore
				'default' => $default,
			);
		}
		return $out;
	}

	/** Options-Präfixe alter Plugins. */
	public static function leftover_option_prefixes() {
		return array(
			'tablepress_', 'modula', 'popmake', 'pum_', 'popup_maker', 'cookielawinfo', 'cky_', 'cookieyes', 'siteorigin_', 'so_widgets', 'sow_',
			'tmm_', 'advancediFrame', 'advanced_iframe', 'omgf_', 'fts_', 'feed_them', 'disable_comments', 'irrp_', 'redirect_redirection',
			'wpmudev_', 'wdp_un_', 'mainwp_', 'wp_defender', 'wd_', 'nextend', 'n2_', 'ninja_forms', 'nf_', 'smush', 'wp-smush', 'wpforms', 'envira',
			'metaslider', 'ml-slider', 'tribe_', 'tec_', 'depicter', 'shield', 'icwp_', 'snapshot', 'realmedialibrary', 'rml_', 'wpspeedo', 'wps_team',
			'mesmerize_companion', 'cloudpress', 'feed_them_social', 'flamingo', 'sc_', 'event_manager', 'publishpress', 'ppfuture',
		);
	}

	/**
	 * Optionen alter Plugins zählen/löschen.
	 *
	 * @param bool $delete Löschen?
	 * @return int
	 */
	public static function leftover_options( $delete = false ) {
		global $wpdb;
		$total = 0;
		foreach ( self::leftover_option_prefixes() as $p ) {
			$like = $wpdb->esc_like( $p ) . '%';
			if ( $delete ) {
				$total += (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND option_name NOT LIKE %s", $like, 'sgnl\_%' ) );
			} else {
				$total += (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s", $like ) );
			}
		}
		if ( $delete ) {
			wp_cache_flush();
		}
		return $total;
	}

	/**
	 * Inhaltstypen zählen.
	 *
	 * @return array<string,int>
	 */
	public static function leftover_post_counts() {
		global $wpdb;
		$out = array();
		foreach ( array_keys( self::leftover_post_types() ) as $type ) {
			$n = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s", $type ) );
			if ( $n ) {
				$out[ $type ] = $n;
			}
		}
		return $out;
	}

	/**
	 * Aufräumen ausführen.
	 *
	 * @param string[] $types   Inhaltstypen.
	 * @param string[] $tables  Tabellen.
	 * @param bool     $options Optionen alter Plugins löschen?
	 * @return array
	 */
	public static function cleanup( array $types, array $tables, $options ) {
		global $wpdb;
		$res     = array( 'posts' => 0, 'tables' => 0, 'options' => 0 );
		$allowed = array_keys( self::leftover_post_types() );
		foreach ( $types as $type ) {
			if ( ! in_array( $type, $allowed, true ) ) {
				continue;
			}
			$ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s", $type ) );
			foreach ( array_chunk( $ids, 200 ) as $chunk ) {
				$in = implode( ',', array_map( 'intval', $chunk ) );
				$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE post_id IN ({$in})" ); // phpcs:ignore
				$wpdb->query( "DELETE FROM {$wpdb->term_relationships} WHERE object_id IN ({$in})" ); // phpcs:ignore
				$wpdb->query( "UPDATE {$wpdb->posts} SET post_parent = 0 WHERE post_type = 'attachment' AND post_parent IN ({$in})" ); // phpcs:ignore
				$wpdb->query( "DELETE FROM {$wpdb->posts} WHERE post_parent IN ({$in}) AND post_type = 'revision'" ); // phpcs:ignore
				$res['posts'] += (int) $wpdb->query( "DELETE FROM {$wpdb->posts} WHERE ID IN ({$in})" ); // phpcs:ignore
			}
		}
		$known = array_keys( self::leftover_tables() );
		foreach ( $tables as $t ) {
			if ( in_array( $t, $known, true ) && 0 === strpos( $t, $wpdb->prefix ) ) {
				$wpdb->query( "DROP TABLE IF EXISTS `{$t}`" ); // phpcs:ignore
				$res['tables']++;
			}
		}
		if ( $options ) {
			$res['options'] = self::leftover_options( true );
		}
		wp_cache_flush();
		return $res;
	}
}
