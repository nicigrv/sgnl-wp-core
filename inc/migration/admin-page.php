<?php
/**
 * Werkzeuge → SG Neptun Umstellung.
 *
 * @package sgnl-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', function () {
	add_management_page(
		__( 'SG Neptun Umstellung', 'sgnl-core' ),
		__( 'SG Neptun Umstellung', 'sgnl-core' ),
		'manage_options',
		'sgnl-migration',
		'sgnl_migration_page'
	);
} );

/* Hinweis auf dem Dashboard, solange noch nicht umgestellt wurde */
add_action( 'admin_notices', function () {
	if ( ! current_user_can( 'manage_options' ) || get_option( 'sgnl_migration_done' ) ) {
		return;
	}
	$screen = get_current_screen();
	if ( $screen && 'tools_page_sgnl-migration' === $screen->id ) {
		return;
	}
	printf(
		'<div class="notice notice-info"><p><strong>%s</strong> %s <a href="%s">%s</a></p></div>',
		esc_html__( 'SG Neptun Funktionen:', 'sgnl-core' ),
		esc_html__( 'Die Inhalte alter Plugins können jetzt in normale Blöcke umgestellt werden.', 'sgnl-core' ),
		esc_url( admin_url( 'tools.php?page=sgnl-migration' ) ),
		esc_html__( 'Zur Umstellung', 'sgnl-core' )
	);
} );

/**
 * Seite ausgeben.
 */
function sgnl_migration_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	@set_time_limit( 300 ); // phpcs:ignore
	$messages = array();
	$results  = null;

	if ( isset( $_POST['sgnl_action'] ) && check_admin_referer( 'sgnl_migration' ) ) {
		$action = sanitize_key( $_POST['sgnl_action'] );

		if ( 'run' === $action ) {
			if ( empty( $_POST['sgnl_backup_ok'] ) ) {
				$messages[] = array( 'error', __( 'Bitte bestätige zuerst, dass ein Backup existiert.', 'sgnl-core' ) );
			} else {
				$results   = SGNL_Migration::run_content();
				$tables    = SGNL_Migration::run_tables();
				$popups    = SGNL_Migration::run_popups();
				$redirects = SGNL_Migration::run_redirects();
				update_option( 'sgnl_migration_done', time() );
				$messages[] = array( 'success', sprintf(
					/* translators: 1: Inhalte, 2: Hinweise, 3: Weiterleitungen */
					__( 'Fertig: %1$d Inhalte umgestellt, %4$d weitere Tabellen übernommen, %2$d Popups als Hinweise übernommen, %3$d Weiterleitungen übernommen.', 'sgnl-core' ),
					count( $results ), $popups, $redirects, $tables
				) );
			}
		}
		if ( 'undo' === $action ) {
			$n          = SGNL_Migration::undo();
			$messages[] = array( 'success', sprintf( __( '%d Inhalte auf den Stand vor der Umstellung zurückgesetzt.', 'sgnl-core' ), $n ) );
			delete_option( 'sgnl_migration_done' );
		}
		if ( 'deactivate' === $action ) {
			$list = array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['sgnl_plugins'] ?? array() ) );
			$list = array_intersect( $list, array_keys( SGNL_Migration::replaced_plugins() ) );
			deactivate_plugins( $list );
			$messages[] = array( 'success', sprintf( __( '%d Plugins deaktiviert. Bitte die Seite jetzt durchklicken – danach können sie unter Plugins gelöscht werden.', 'sgnl-core' ), count( $list ) ) );
		}
		if ( 'events_run' === $action ) {
			$r          = SGNL_Events_Import::run();
			$messages[] = array( 'success', sprintf( __( 'Termine übernommen: %1$d neu angelegt, %2$d vorhandene ergänzt, %3$d Seiten umgestellt.', 'sgnl-core' ), $r['created'], $r['merged'], $r['pages'] ) );
		}
		if ( 'events_undo' === $action ) {
			$n          = SGNL_Events_Import::undo();
			$messages[] = array( 'success', sprintf( __( 'Termin-Übernahme rückgängig gemacht (%d Seiten wiederhergestellt, übernommene Termine im Papierkorb).', 'sgnl-core' ), $n ) );
		}
		if ( 'gallery_run' === $action ) {
			$r          = SGNL_Gallery_Setup::run();
			$messages[] = array( 'success', sprintf( __( 'Galerien eingerichtet: %1$d Jahre, %2$d Alben.', 'sgnl-core' ), $r['years'], $r['albums'] ) );
		}
		if ( 'gallery_undo' === $action ) {
			SGNL_Gallery_Setup::undo();
			$messages[] = array( 'success', __( 'Galerie-Einrichtung rückgängig gemacht.', 'sgnl-core' ) );
		}
		if ( 'cleanup' === $action ) {
			if ( empty( $_POST['sgnl_cleanup_ok'] ) ) {
				$messages[] = array( 'error', __( 'Bitte bestätige das Aufräumen.', 'sgnl-core' ) );
			} else {
				$types   = array_map( 'sanitize_key', (array) wp_unslash( $_POST['sgnl_types'] ?? array() ) );
				$tables  = array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['sgnl_tables'] ?? array() ) );
				$res     = SGNL_Migration::cleanup( $types, $tables, ! empty( $_POST['sgnl_options'] ) );
				$messages[] = array( 'success', sprintf( __( 'Aufgeräumt: %1$d Einträge, %2$d Tabellen, %3$d Einstellungen gelöscht.', 'sgnl-core' ), $res['posts'], $res['tables'], $res['options'] ) );
			}
		}
	}

	$analysis = SGNL_Migration::analyze();
	$backups  = SGNL_Migration::backup_count();
	$theme    = wp_get_theme();
	?>
	<div class="wrap sgnl-mig">
		<style>
			.sgnl-mig .card { max-width: 980px; padding: 1.2rem 1.5rem; }
			.sgnl-mig h2 { margin-top: .2rem; }
			.sgnl-mig table.widefat td, .sgnl-mig table.widefat th { vertical-align: top; }
			.sgnl-mig ul.sgnl-log { margin: 0; }
			.sgnl-mig ul.sgnl-log li { margin: 0 0 .2rem; }
			.sgnl-mig .warn { color: #996800; }
			.sgnl-mig .ok { color: #007017; font-weight: 600; }
			.sgnl-mig .step { display: inline-grid; place-items: center; width: 28px; height: 28px; border-radius: 99px; background: #1F44B8; color: #fff; font-weight: 600; margin-right: .4rem; }
		</style>
		<h1><?php esc_html_e( 'SG Neptun Umstellung', 'sgnl-core' ); ?></h1>
		<?php foreach ( $messages as $m ) : ?>
			<div class="notice notice-<?php echo esc_attr( $m[0] ); ?>"><p><?php echo esc_html( $m[1] ); ?></p></div>
		<?php endforeach; ?>

		<?php if ( $results ) : ?>
			<div class="card">
				<h2><?php esc_html_e( 'Umgestellt', 'sgnl-core' ); ?></h2>
				<table class="widefat striped"><tbody>
				<?php foreach ( $results as $r ) : ?>
					<tr><td><a href="<?php echo esc_url( get_permalink( $r['id'] ) ); ?>" target="_blank"><?php echo esc_html( $r['title'] ); ?></a> · <a href="<?php echo esc_url( get_edit_post_link( $r['id'] ) ); ?>"><?php esc_html_e( 'Bearbeiten', 'sgnl-core' ); ?></a></td>
					<td><ul class="sgnl-log"><?php foreach ( $r['log'] as $l ) { echo '<li>' . esc_html( $l ) . '</li>'; } foreach ( $r['warnings'] as $w ) { echo '<li class="warn">⚠ ' . esc_html( $w ) . '</li>'; } ?></ul></td></tr>
				<?php endforeach; ?>
				</tbody></table>
			</div>
		<?php endif; ?>

		<div class="card">
			<h2><span class="step">1</span><?php esc_html_e( 'Theme', 'sgnl-core' ); ?></h2>
			<p>
				<?php
				printf(
					/* translators: 1: Theme, 2: Version */
					esc_html__( 'Aktives Theme: %1$s (Version %2$s)', 'sgnl-core' ),
					'<strong>' . esc_html( $theme->get( 'Name' ) ) . '</strong>',
					esc_html( $theme->get( 'Version' ) )
				);
				if ( $theme->parent() ) {
					echo ' – <span class="warn">' . esc_html__( 'noch als Child-Theme von Mesmerize aktiv. Bitte zuerst Theme-Version 2 hochladen.', 'sgnl-core' ) . '</span>';
				} elseif ( version_compare( $theme->get( 'Version' ), '2.0.0', '>=' ) ) {
					echo ' – <span class="ok">' . esc_html__( 'passt', 'sgnl-core' ) . '</span>';
				}
				?>
			</p>
		</div>

		<div class="card">
			<h2><span class="step">2</span><?php esc_html_e( 'Inhalte umstellen', 'sgnl-core' ); ?></h2>
			<?php if ( ! $analysis ) : ?>
				<p class="ok"><?php esc_html_e( 'Keine Inhalte mehr, die von alten Plugins abhängen.', 'sgnl-core' ); ?></p>
			<?php else : ?>
				<p><?php esc_html_e( 'Diese Inhalte werden in normale Blöcke umgewandelt. Der Originalinhalt wird gesichert und kann unten wiederhergestellt werden.', 'sgnl-core' ); ?></p>
				<table class="widefat striped">
					<thead><tr><th><?php esc_html_e( 'Inhalt', 'sgnl-core' ); ?></th><th><?php esc_html_e( 'Was passiert', 'sgnl-core' ); ?></th></tr></thead>
					<tbody>
					<?php foreach ( $analysis as $a ) : ?>
						<tr>
							<td><a href="<?php echo esc_url( get_permalink( $a['id'] ) ); ?>" target="_blank"><?php echo esc_html( $a['title'] ); ?></a><br><small><?php echo esc_html( $a['type'] . ' · ' . $a['status'] ); ?></small></td>
							<td><ul class="sgnl-log">
								<?php foreach ( $a['log'] as $l ) : ?><li><?php echo esc_html( $l ); ?></li><?php endforeach; ?>
								<?php foreach ( $a['warnings'] as $w ) : ?><li class="warn">⚠ <?php echo esc_html( $w ); ?></li><?php endforeach; ?>
							</ul></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<form method="post" style="margin-top:1rem">
					<?php wp_nonce_field( 'sgnl_migration' ); ?>
					<p><label><input type="checkbox" name="sgnl_backup_ok" value="1"> <?php esc_html_e( 'Ich habe ein aktuelles Backup von Dateien und Datenbank.', 'sgnl-core' ); ?></label></p>
					<p><?php esc_html_e( 'Zusätzlich werden alle TablePress-Tabellen in „Tabellen“ übernommen (auch ungenutzte), Popups (Popup Maker) als „Hinweise“ angelegt und Weiterleitungen aus „Redirect Redirection“ übertragen.', 'sgnl-core' ); ?></p>
					<button class="button button-primary button-hero" name="sgnl_action" value="run"><?php esc_html_e( 'Jetzt umstellen', 'sgnl-core' ); ?></button>
				</form>
			<?php endif; ?>
			<?php if ( $backups ) : ?>
				<form method="post" style="margin-top:1rem" onsubmit="return confirm('<?php echo esc_js( __( 'Alle umgestellten Inhalte auf den alten Stand zurücksetzen?', 'sgnl-core' ) ); ?>');">
					<?php wp_nonce_field( 'sgnl_migration' ); ?>
					<p><?php printf( esc_html__( '%d Inhalte sind gesichert.', 'sgnl-core' ), (int) $backups ); ?>
					<button class="button" name="sgnl_action" value="undo"><?php esc_html_e( 'Umstellung rückgängig machen', 'sgnl-core' ); ?></button></p>
				</form>
			<?php endif; ?>
		</div>

		<div class="card">
			<h2><span class="step">3</span><?php esc_html_e( 'Alte Plugins deaktivieren', 'sgnl-core' ); ?></h2>
			<p><?php esc_html_e( 'Diese Plugins werden nicht mehr gebraucht. Erst deaktivieren, Seite prüfen, dann unter „Plugins“ löschen.', 'sgnl-core' ); ?></p>
			<form method="post">
				<?php wp_nonce_field( 'sgnl_migration' ); ?>
				<table class="widefat striped"><tbody>
				<?php
				if ( ! function_exists( 'get_plugins' ) ) {
					require_once ABSPATH . 'wp-admin/includes/plugin.php';
				}
				$installed = get_plugins();
				$any       = false;
				foreach ( SGNL_Migration::replaced_plugins() as $file => $why ) :
					if ( ! isset( $installed[ $file ] ) ) {
						continue;
					}
					$active = is_plugin_active( $file );
					$any    = $any || $active;
					?>
					<tr>
						<td style="width:28px"><input type="checkbox" name="sgnl_plugins[]" value="<?php echo esc_attr( $file ); ?>" <?php checked( $active ); ?> <?php disabled( ! $active ); ?>></td>
						<td><strong><?php echo esc_html( $installed[ $file ]['Name'] ); ?></strong><br><small><?php echo esc_html( $why ); ?></small></td>
						<td><?php echo $active ? esc_html__( 'aktiv', 'sgnl-core' ) : '<span class="ok">' . esc_html__( 'deaktiviert', 'sgnl-core' ) . '</span>'; ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody></table>
				<?php if ( $any ) : ?>
					<p><button class="button button-primary" name="sgnl_action" value="deactivate"><?php esc_html_e( 'Ausgewählte deaktivieren', 'sgnl-core' ); ?></button></p>
				<?php endif; ?>
			</form>
		</div>

		<?php sgnl_events_import_card(); ?>
		<?php sgnl_gallery_setup_card(); ?>

		<div class="card">
			<h2><span class="step">7</span><?php esc_html_e( 'Aufräumen (optional)', 'sgnl-core' ); ?></h2>
			<p><?php esc_html_e( 'Löscht Daten alter und bereits entfernter Plugins aus der Datenbank. Erst ausführen, wenn die umgestellte Seite geprüft ist. Kann nicht rückgängig gemacht werden – nur über das Backup.', 'sgnl-core' ); ?></p>
			<?php
			$counts  = SGNL_Migration::leftover_post_counts();
			$labels  = SGNL_Migration::leftover_post_types();
			$tables  = SGNL_Migration::leftover_tables();
			$options = SGNL_Migration::leftover_options();
			?>
			<form method="post">
				<?php wp_nonce_field( 'sgnl_migration' ); ?>
				<?php if ( $counts ) : ?>
					<h3><?php esc_html_e( 'Inhalte', 'sgnl-core' ); ?></h3>
					<?php foreach ( $counts as $type => $n ) : ?>
						<label style="display:block;margin:.2rem 0"><input type="checkbox" name="sgnl_types[]" value="<?php echo esc_attr( $type ); ?>" <?php checked( $labels[ $type ][1] ); ?>> <?php echo esc_html( $labels[ $type ][0] . ' (' . $n . ')' ); ?></label>
					<?php endforeach; ?>
				<?php endif; ?>
				<?php if ( $tables ) : ?>
					<h3><?php esc_html_e( 'Datenbank-Tabellen', 'sgnl-core' ); ?></h3>
					<div style="columns:2">
					<?php foreach ( $tables as $t => $info ) : ?>
						<label style="display:block;margin:.15rem 0"><input type="checkbox" name="sgnl_tables[]" value="<?php echo esc_attr( $t ); ?>" <?php checked( $info['default'] ); ?>> <code><?php echo esc_html( $t ); ?></code> <small>(<?php echo (int) $info['rows']; ?>)</small></label>
					<?php endforeach; ?>
					</div>
					<p class="description"><?php esc_html_e( 'Nicht angehakt sind Tabellen, die keinem bekannten alten Plugin zugeordnet werden konnten.', 'sgnl-core' ); ?></p>
				<?php endif; ?>
				<?php if ( $options ) : ?>
					<h3><?php esc_html_e( 'Einstellungen', 'sgnl-core' ); ?></h3>
					<label><input type="checkbox" name="sgnl_options" value="1" checked> <?php printf( esc_html__( '%d gespeicherte Einstellungen alter Plugins löschen', 'sgnl-core' ), (int) $options ); ?></label>
				<?php endif; ?>
				<?php if ( $counts || $tables || $options ) : ?>
					<p style="margin-top:1rem"><label><input type="checkbox" name="sgnl_cleanup_ok" value="1"> <?php esc_html_e( 'Ich habe die Seite geprüft und ein Backup.', 'sgnl-core' ); ?></label></p>
					<p><button class="button" name="sgnl_action" value="cleanup"><?php esc_html_e( 'Ausgewähltes löschen', 'sgnl-core' ); ?></button></p>
				<?php else : ?>
					<p class="ok"><?php esc_html_e( 'Nichts mehr aufzuräumen.', 'sgnl-core' ); ?></p>
				<?php endif; ?>
			</form>
		</div>
	</div>
	<?php
}


/* Hinweis: Termine/Galerien können eingerichtet werden */
add_action( 'admin_notices', function () {
	if ( ! current_user_can( 'manage_options' ) || ! get_option( 'sgnl_migration_done' ) ) {
		return;
	}
	$screen = get_current_screen();
	if ( ! $screen || ! in_array( $screen->id, array( 'dashboard', 'edit-sgnl_event', 'edit-page', 'plugins' ), true ) ) {
		return;
	}
	$todo = array();
	if ( SGNL_Events_Import::pending() ) {
		$todo[] = __( 'Terminplan und Archiv-Tabellen in die neue Termin-Verwaltung übernehmen', 'sgnl-core' );
	}
	if ( ! SGNL_Gallery_Setup::done() && SGNL_Gallery_Setup::pending() ) {
		$todo[] = __( 'Galerien als Kacheln einrichten (/galerie/)', 'sgnl-core' );
	}
	if ( ! $todo ) {
		return;
	}
	printf(
		'<div class="notice notice-info"><p><strong>%s</strong> %s – <a href="%s">%s</a></p></div>',
		esc_html__( 'SG Neptun Funktionen:', 'sgnl-core' ),
		esc_html( implode( ' · ', $todo ) ),
		esc_url( admin_url( 'tools.php?page=sgnl-migration#sgnl-neu' ) ),
		esc_html__( 'Jetzt ansehen', 'sgnl-core' )
	);
} );

/**
 * Karte "Termine übernehmen".
 */
function sgnl_events_import_card() {
	$plan    = SGNL_Events_Import::plan();
	$pending = SGNL_Events_Import::pending();
	$today   = sgnl_events_today();
	?>
	<div class="card" id="sgnl-neu">
		<h2><span class="step">5</span><?php esc_html_e( 'Termine übernehmen', 'sgnl-core' ); ?></h2>
		<?php if ( ! $pending ) : ?>
			<p class="ok"><?php esc_html_e( 'Erledigt – Termine werden unter „Termine“ gepflegt.', 'sgnl-core' ); ?></p>
		<?php else : ?>
			<p><?php esc_html_e( 'Die Tabellen „Terminplan“ und „Archiv JJJJ“ werden in einzelne Termine umgewandelt. Auf den Seiten erscheint danach der Block „Termine“ (Liste/Kacheln), der Terminplan als PDF entsteht automatisch. Die alten Tabellen bleiben als Entwurf erhalten.', 'sgnl-core' ); ?></p>
			<p><strong><?php esc_html_e( 'Gefundene Tabellen:', 'sgnl-core' ); ?></strong>
				<?php
				echo esc_html( implode( ', ', array_map( function ( $s ) {
					return $s['title'] . ( 'archive' === $s['kind'] ? ' (Archiv ' . $s['year'] . ')' : ' (Terminplan)' );
				}, $plan['sources'] ) ) );
				?>
			</p>
			<p><strong><?php esc_html_e( 'Seiten, die umgestellt werden:', 'sgnl-core' ); ?></strong>
				<?php echo esc_html( implode( ', ', array_map( 'get_the_title', $plan['pages'] ) ) ); ?></p>
			<details>
				<summary><?php printf( esc_html__( 'Vorschau: %d Termine', 'sgnl-core' ), count( $plan['events'] ) ); ?></summary>
				<table class="widefat striped" style="margin-top:.6rem">
					<thead><tr><th><?php esc_html_e( 'Datum', 'sgnl-core' ); ?></th><th><?php esc_html_e( 'Veranstaltung', 'sgnl-core' ); ?></th><th><?php esc_html_e( 'Ort', 'sgnl-core' ); ?></th><th><?php esc_html_e( 'Bahn / Mannschaft', 'sgnl-core' ); ?></th><th>A / E / P</th><th><?php esc_html_e( 'Erscheint in', 'sgnl-core' ); ?></th></tr></thead>
					<tbody>
					<?php foreach ( $plan['events'] as $e ) : ?>
						<tr>
							<td><?php echo esc_html( sgnl_event_date_label( $e, true ) ); ?></td>
							<td><?php echo esc_html( $e['title'] ); ?><?php echo $e['existing'] ? ' <em>(' . esc_html__( 'schon vorhanden – wird ergänzt', 'sgnl-core' ) . ')</em>' : ''; ?></td>
							<td><?php echo esc_html( $e['ort'] ); ?></td>
							<td><?php echo esc_html( trim( $e['bahn'] . ' · ' . $e['team'], ' ·' ) ); ?></td>
							<td><?php echo esc_html( ( $e['ausschreibung'] ? 'A' : '–' ) . ' ' . ( $e['ergebnis'] ? 'E' : '–' ) . ' ' . ( $e['protokoll'] ? 'P' : '–' ) ); ?></td>
							<td><?php echo esc_html( ( $e['end'] ? $e['end'] : $e['start'] ) < $today ? 'Archiv ' . substr( $e['start'], 0, 4 ) : 'Terminplan' ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</details>
			<form method="post" style="margin-top:1rem">
				<?php wp_nonce_field( 'sgnl_migration' ); ?>
				<button class="button button-primary" name="sgnl_action" value="events_run"><?php esc_html_e( 'Termine übernehmen', 'sgnl-core' ); ?></button>
			</form>
		<?php endif; ?>
		<?php if ( SGNL_Events_Import::has_backup() ) : ?>
			<form method="post" style="margin-top:.8rem" onsubmit="return confirm('<?php echo esc_js( __( 'Übernahme rückgängig machen? Übernommene Termine kommen in den Papierkorb, die Seiten zeigen wieder die Tabellen.', 'sgnl-core' ) ); ?>');">
				<?php wp_nonce_field( 'sgnl_migration' ); ?>
				<button class="button" name="sgnl_action" value="events_undo"><?php esc_html_e( 'Termin-Übernahme rückgängig machen', 'sgnl-core' ); ?></button>
			</form>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Karte "Galerien einrichten".
 */
function sgnl_gallery_setup_card() {
	$done = SGNL_Gallery_Setup::done();
	$plan = SGNL_Gallery_Setup::plan();
	?>
	<div class="card">
		<h2><span class="step">6</span><?php esc_html_e( 'Galerien als Kacheln', 'sgnl-core' ); ?></h2>
		<p><?php esc_html_e( 'Aufbau: /galerie/ (Auswahl der Jahre) → /galerie/2026/ (Alben als Kacheln) → /galerie/2026/dlp/ (Fotos, per Klick vergrößerbar). Die Kacheln entstehen automatisch aus den Unterseiten; Titelbild = Beitragsbild der Seite.', 'sgnl-core' ); ?></p>
		<?php if ( $plan['years'] ) : ?>
			<p><?php esc_html_e( 'Die bisherigen Seiten werden je Überschrift + Galerie in eigene Alben aufgeteilt. Alte Adressen leiten weiter, Menüpunkte zeigen auf die neuen Jahresseiten.', 'sgnl-core' ); ?></p>
			<table class="widefat striped"><tbody>
				<tr><td><strong>/galerie/</strong></td><td><?php echo $plan['root'] ? esc_html__( 'vorhandene Seite wird zur Auswahlseite (bisheriger Inhalt wird gesichert und als Album übernommen)', 'sgnl-core' ) : esc_html__( 'wird angelegt', 'sgnl-core' ); ?></td></tr>
				<?php foreach ( $plan['years'] as $year => $albums ) : ?>
					<tr><td><strong>/galerie/<?php echo (int) $year; ?>/</strong></td><td><ul class="sgnl-log">
						<?php foreach ( $albums as $a ) : ?>
							<li><?php echo esc_html( $a['title'] . ' → /galerie/' . $year . '/' . $a['slug'] . '/ (' . count( $a['images'] ) . ' Fotos)' ); ?></li>
						<?php endforeach; ?>
					</ul></td></tr>
				<?php endforeach; ?>
			</tbody></table>
			<form method="post" style="margin-top:1rem">
				<?php wp_nonce_field( 'sgnl_migration' ); ?>
				<button class="button button-primary" name="sgnl_action" value="gallery_run"><?php esc_html_e( 'Galerien einrichten', 'sgnl-core' ); ?></button>
			</form>
		<?php elseif ( $done ) : ?>
			<p class="ok"><?php esc_html_e( 'Eingerichtet. Neues Album: Seite anlegen, als übergeordnete Seite das Jahr wählen (z. B. „Galerie 2026“), Galerie-Block einfügen, Beitragsbild setzen.', 'sgnl-core' ); ?></p>
		<?php else : ?>
			<p><?php esc_html_e( 'Keine alten Galerie-Seiten gefunden. Neue Struktur: Seite „Galerie“ mit dem Block „Galerie-Übersicht“, darunter Jahresseiten (ebenfalls mit dem Block) und darunter die Alben.', 'sgnl-core' ); ?></p>
		<?php endif; ?>
		<?php if ( $done ) : ?>
			<form method="post" style="margin-top:.8rem" onsubmit="return confirm('<?php echo esc_js( __( 'Galerie-Einrichtung rückgängig machen?', 'sgnl-core' ) ); ?>');">
				<?php wp_nonce_field( 'sgnl_migration' ); ?>
				<button class="button" name="sgnl_action" value="gallery_undo"><?php esc_html_e( 'Galerie-Einrichtung rückgängig machen', 'sgnl-core' ); ?></button>
			</form>
		<?php endif; ?>
	</div>
	<?php
}
