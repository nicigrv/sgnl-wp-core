-- Testdaten im Format des phpMyAdmin-Exports der alten Datenbank (datenbank.sgnl.de).
-- Platzhalter __D1__ usw. werden vom Test durch echte Zeitstempel ersetzt.
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;

CREATE TABLE `wettkaempfe` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `ort` varchar(255) DEFAULT NULL,
  `start_ts` int UNSIGNED NOT NULL,
  `end_ts` int UNSIGNED NOT NULL,
  `meldeuebersicht_url` varchar(1000) DEFAULT NULL,
  `meldeergebnis_url` varchar(1000) DEFAULT NULL,
  `livetiming_url` varchar(1000) DEFAULT NULL,
  `protokoll_url` varchar(1000) DEFAULT NULL,
  `created_at` int UNSIGNED NOT NULL,
  `updated_at` int UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `wettkaempfe` (`id`, `name`, `ort`, `start_ts`, `end_ts`, `meldeuebersicht_url`, `meldeergebnis_url`, `livetiming_url`, `protokoll_url`, `created_at`, `updated_at`) VALUES
(1, 'Bezirks-Masters-Meisterschaften Test', 'Teststadt', __S1__, __E1__, 'https://example.org/meldungen-1.pdf', 'https://example.org/meldeergebnis-1.pdf', NULL, NULL, 1, 1),
(2, 'Kreismeisterschaften Test d. Kreis Bergstraße', 'Teststadt', __S1__, __E2__, NULL, 'https://example.org/meldeergebnis-2.pdf', 'https://example.org/live/#home', NULL, 1, 1),
(3, 'Laufender Wettkampf \"Lange Strecken\"', 'Rüsselsheim', __S3__, __E3__, NULL, NULL, 'https://example.org/live-3', NULL, 1, 1),
(4, 'Brandneuer Wettkampf mit O\'Brien', NULL, __S4__, __E4__, NULL, NULL, NULL, NULL, 1, 1);

CREATE TABLE `wettkampf_nachrichten` (
  `id` int UNSIGNED NOT NULL,
  `wettkampf_id` int UNSIGNED NOT NULL,
  `nachricht` varchar(1000) NOT NULL,
  `autor_name` varchar(255) NOT NULL,
  `autor_email` varchar(255) DEFAULT NULL,
  `created_at` int UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `wettkampf_nachrichten` (`id`, `wettkampf_id`, `nachricht`, `autor_name`, `autor_email`, `created_at`) VALUES
(1, 3, '**Einschwimmen** erst ab 8:30 Uhr\nTreffpunkt: Eingang (Halle 2)', 'Test Trainerin', NULL, 1700000000),
(2, 3, 'Zweite Info mit ''Apostroph''', 'Test Trainer', NULL, 1700000100);
COMMIT;
