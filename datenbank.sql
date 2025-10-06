-- --------------------------------------------------------
-- Host:                         w01e3b67.kasserver.com
-- Server-Version:               10.6.22-MariaDB-0ubuntu0.22.04.1-log - Ubuntu 22.04
-- Server-Betriebssystem:        debian-linux-gnu
-- HeidiSQL Version:             12.4.0.6659
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

-- Exportiere Struktur von Tabelle d044f149.activity_log
CREATE TABLE IF NOT EXISTS `activity_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `username` varchar(100) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `details` text DEFAULT NULL,
  `marker_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `marker_id` (`marker_id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_action` (`action`),
  CONSTRAINT `activity_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `activity_log_ibfk_2` FOREIGN KEY (`marker_id`) REFERENCES `markers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Exportiere Daten aus Tabelle d044f149.activity_log: ~22 rows (ungefähr)
INSERT INTO `activity_log` (`id`, `user_id`, `username`, `action`, `details`, `marker_id`, `ip_address`, `user_agent`, `created_at`) VALUES
	(1, 1, 'admin', 'custom_field_created', 'Feld \'bla bla bla\' erstellt', NULL, '109.43.49.192', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 OPR/122.0.0.0 (Edition std-1)', '2025-10-04 20:14:02'),
	(2, 1, 'admin', 'login', 'Benutzer angemeldet', NULL, '109.43.49.192', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 OPR/122.0.0.0 (Edition std-1)', '2025-10-04 20:19:42'),
	(3, 1, 'admin', 'marker_updated', 'Marker \'fghfdg\' aktualisiert', 8, '109.43.49.192', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 OPR/122.0.0.0 (Edition std-1)', '2025-10-04 20:26:47'),
	(4, 1, 'admin', 'marker_updated', 'Marker \'fghfdg\' aktualisiert', 8, '109.43.49.192', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 OPR/122.0.0.0 (Edition std-1)', '2025-10-04 20:26:47'),
	(5, 1, 'admin', 'login', 'Benutzer angemeldet', NULL, '109.43.49.192', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', '2025-10-04 20:49:06'),
	(6, 1, 'admin', 'user_created', 'Benutzer \'gfdhfghsdf\' erstellt', NULL, '109.43.49.192', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', '2025-10-04 23:22:18'),
	(7, 1, 'admin', 'login', 'Benutzer angemeldet', NULL, '109.43.49.192', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-10-05 09:00:56'),
	(8, 1, 'admin', 'login', 'Benutzer angemeldet', NULL, '109.43.49.192', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-10-05 09:01:08'),
	(9, 1, 'admin', 'login', 'Benutzer angemeldet', NULL, '109.43.49.192', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-10-05 09:02:09'),
	(10, 1, 'admin', 'login', 'Benutzer angemeldet', NULL, '109.43.49.192', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-10-05 09:02:37'),
	(11, 1, 'admin', 'login', 'Benutzer angemeldet', NULL, '109.43.49.192', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-10-05 09:07:17'),
	(12, 1, 'admin', 'login', 'Benutzer angemeldet', NULL, '109.43.49.192', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-10-05 09:43:53'),
	(13, 1, 'admin', 'login', 'Benutzer angemeldet', NULL, '109.43.49.192', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-10-05 09:44:13'),
	(14, 1, 'admin', 'login', 'Benutzer angemeldet', NULL, '109.43.49.192', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-10-05 09:59:54'),
	(15, 1, 'admin', 'login', 'Benutzer angemeldet', NULL, '109.43.49.192', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-10-05 11:02:35'),
	(16, 1, 'admin', 'login', 'Benutzer angemeldet', NULL, '109.43.49.192', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-10-05 14:11:26'),
	(17, 1, 'admin', 'marker_deleted_soft', 'Marker \'fghfdg\' in Papierkorb verschoben', 8, '109.43.49.192', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-10-05 14:15:26'),
	(18, 1, 'admin', 'marker_restored', 'Marker aus Papierkorb wiederhergestellt', 8, '109.43.49.192', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-10-05 14:15:33'),
	(19, 1, 'admin', 'login', 'Benutzer angemeldet', NULL, '109.43.49.192', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.0 Mobile/15E148 Safari/604.1 OPT/6.1.2', '2025-10-05 16:22:09'),
	(20, 1, 'admin', 'login', 'Benutzer angemeldet', NULL, '109.43.49.192', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.0 Mobile/15E148 Safari/604.1 OPT/6.1.2', '2025-10-05 16:23:17'),
	(21, 1, 'admin', 'login', 'Benutzer angemeldet', NULL, '109.43.49.192', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-10-05 19:59:46'),
	(22, 1, 'admin', 'bug_report', 'Bug gemeldet: retger', NULL, '109.43.49.192', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-10-05 20:00:09'),
	(23, 1, 'admin', 'bug_report', 'Bug gemeldet: fdghdfhgdfghgf', NULL, '109.43.49.192', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-10-05 20:03:28'),
	(24, 1, 'admin', 'login', 'Benutzer angemeldet', NULL, '109.43.49.192', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-10-05 20:29:01'),
	(25, 1, 'admin', 'login', 'Benutzer angemeldet', NULL, '109.43.49.192', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', '2025-10-05 20:57:19');

-- Exportiere Struktur von Tabelle d044f149.bug_admin_users
CREATE TABLE IF NOT EXISTS `bug_admin_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Exportiere Daten aus Tabelle d044f149.bug_admin_users: ~1 rows (ungefähr)
INSERT INTO `bug_admin_users` (`id`, `username`, `password`, `email`, `full_name`, `is_active`, `created_at`, `last_login`) VALUES
	(1, 'admin', '$2y$12$Mg55kXgUWMhmqWw0Z129lej77LzE2aVLZuxBu0dH22676pn4PxxHi', 'doofwiescheisse@outlook.de', 'Administrator', 1, '2025-10-05 12:36:37', '2025-10-05 17:51:51');

-- Exportiere Struktur von Tabelle d044f149.bug_comments
CREATE TABLE IF NOT EXISTS `bug_comments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bug_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `comment` text NOT NULL,
  `is_internal` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `bug_id` (`bug_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `bug_comments_ibfk_1` FOREIGN KEY (`bug_id`) REFERENCES `bug_reports` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bug_comments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `bug_admin_users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Exportiere Daten aus Tabelle d044f149.bug_comments: ~0 rows (ungefähr)

-- Exportiere Struktur von Tabelle d044f149.bug_reports
CREATE TABLE IF NOT EXISTS `bug_reports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `page_url` varchar(500) DEFAULT NULL,
  `browser_info` text DEFAULT NULL,
  `screenshot_path` varchar(500) DEFAULT NULL,
  `status` enum('offen','in_bearbeitung','erledigt') DEFAULT 'offen',
  `priority` enum('niedrig','mittel','hoch','kritisch') DEFAULT 'mittel',
  `reported_by` int(11) DEFAULT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `archived_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Exportiere Daten aus Tabelle d044f149.bug_reports: ~1 rows (ungefähr)
INSERT INTO `bug_reports` (`id`, `title`, `description`, `email`, `phone`, `page_url`, `browser_info`, `screenshot_path`, `status`, `priority`, `reported_by`, `assigned_to`, `created_at`, `updated_at`, `archived_at`, `notes`) VALUES
	(1, 'retger', 'asdgtfagfsadfgdfarsfaewsfdasdfdgsad', 'dfhsdrfg@dsfgdsf.de', '', 'https://test.projekt-z.eu/index.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', NULL, 'erledigt', 'niedrig', 1, NULL, '2025-10-05 18:00:09', '2025-10-05 18:03:03', '2025-10-05 18:03:03', NULL),
	(2, 'fdghdfhgdfghgf', 'fdsghdrfghgsretdfhgbsdfghgbsdfgeradsf', 'fdgjhdfghgfds@dffhgdsf.de', '', 'https://test.projekt-z.eu/index.php', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', NULL, 'offen', 'mittel', 1, NULL, '2025-10-05 18:03:28', '2025-10-05 18:03:28', NULL, NULL);

-- Exportiere Struktur von Tabelle d044f149.categories
CREATE TABLE IF NOT EXISTS `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `icon` varchar(255) DEFAULT NULL COMMENT 'FontAwesome Icon-Klasse',
  `color` varchar(7) DEFAULT '#007bff' COMMENT 'Hex-Farbcode für die Kategorie',
  `description` text DEFAULT NULL,
  `is_system` tinyint(1) DEFAULT 0 COMMENT 'System-Kategorie kann nicht gelöscht werden',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Exportiere Daten aus Tabelle d044f149.categories: ~5 rows (ungefähr)
INSERT INTO `categories` (`id`, `name`, `icon`, `color`, `description`, `is_system`, `created_at`, `updated_at`) VALUES
	(1, 'Generator', 'fa-bolt', '#ffc107', 'Stromerzeugungsgeräte', 1, '2025-10-02 20:09:42', '2025-10-02 20:09:42'),
	(2, 'Baumaschine', 'fa-truck', '#fd7e14', 'Baumaschinen und schweres Gerät', 1, '2025-10-02 20:09:42', '2025-10-02 20:09:42'),
	(3, 'Werkzeug', 'fa-wrench', '#6c757d', 'Handwerkzeuge und Elektrowerkzeuge', 1, '2025-10-02 20:09:42', '2025-10-02 20:09:42'),
	(4, 'Fahrzeug', 'fa-car', '#007bff', 'Fahrzeuge und Transportmittel', 1, '2025-10-02 20:09:42', '2025-10-02 20:09:42'),
	(5, 'Lager', 'fa-warehouse', '#28a745', 'Lagerflächen und Container', 1, '2025-10-02 20:09:42', '2025-10-02 20:09:42');

-- Exportiere Struktur von Tabelle d044f149.checklist_completions
CREATE TABLE IF NOT EXISTS `checklist_completions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `marker_id` int(11) NOT NULL,
  `template_id` int(11) NOT NULL,
  `completed_by` int(11) DEFAULT NULL,
  `completion_date` datetime DEFAULT current_timestamp(),
  `results` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`results`)),
  `pdf_path` varchar(500) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `marker_id` (`marker_id`),
  KEY `template_id` (`template_id`),
  KEY `completed_by` (`completed_by`),
  CONSTRAINT `checklist_completions_ibfk_1` FOREIGN KEY (`marker_id`) REFERENCES `markers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `checklist_completions_ibfk_2` FOREIGN KEY (`template_id`) REFERENCES `checklist_templates` (`id`) ON DELETE CASCADE,
  CONSTRAINT `checklist_completions_ibfk_3` FOREIGN KEY (`completed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Exportiere Daten aus Tabelle d044f149.checklist_completions: ~0 rows (ungefähr)

-- Exportiere Struktur von Tabelle d044f149.checklist_items
CREATE TABLE IF NOT EXISTS `checklist_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `checklist_id` int(11) NOT NULL,
  `item_text` varchar(255) NOT NULL,
  `item_order` int(11) DEFAULT 0,
  `is_required` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `checklist_id` (`checklist_id`),
  CONSTRAINT `checklist_items_ibfk_1` FOREIGN KEY (`checklist_id`) REFERENCES `maintenance_checklists` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Exportiere Daten aus Tabelle d044f149.checklist_items: ~0 rows (ungefähr)

-- Exportiere Struktur von Tabelle d044f149.checklist_item_results
CREATE TABLE IF NOT EXISTS `checklist_item_results` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `result_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `is_checked` tinyint(1) DEFAULT 0,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `result_id` (`result_id`),
  KEY `item_id` (`item_id`),
  CONSTRAINT `checklist_item_results_ibfk_1` FOREIGN KEY (`result_id`) REFERENCES `maintenance_checklist_results` (`id`) ON DELETE CASCADE,
  CONSTRAINT `checklist_item_results_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `checklist_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Exportiere Daten aus Tabelle d044f149.checklist_item_results: ~0 rows (ungefähr)

-- Exportiere Struktur von Tabelle d044f149.checklist_templates
CREATE TABLE IF NOT EXISTS `checklist_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL,
  `items` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`items`)),
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `checklist_templates_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Exportiere Daten aus Tabelle d044f149.checklist_templates: ~0 rows (ungefähr)

-- Exportiere Struktur von Tabelle d044f149.checkout_history
CREATE TABLE IF NOT EXISTS `checkout_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `marker_id` int(11) NOT NULL,
  `checked_out_by` varchar(100) NOT NULL,
  `checked_out_by_email` varchar(100) DEFAULT NULL,
  `checked_out_by_phone` varchar(50) DEFAULT NULL,
  `checkout_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `expected_return_date` date DEFAULT NULL,
  `checkin_date` timestamp NULL DEFAULT NULL,
  `checkin_notes` text DEFAULT NULL,
  `checkout_notes` text DEFAULT NULL,
  `qr_scanned` tinyint(1) DEFAULT 1,
  `user_id` int(11) DEFAULT NULL,
  `status` enum('active','returned','overdue') DEFAULT 'active',
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_marker` (`marker_id`),
  KEY `idx_checkout_date` (`checkout_date`),
  CONSTRAINT `checkout_history_ibfk_1` FOREIGN KEY (`marker_id`) REFERENCES `markers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `checkout_history_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Exportiere Daten aus Tabelle d044f149.checkout_history: ~0 rows (ungefähr)

-- Exportiere Struktur von Tabelle d044f149.custom_fields
CREATE TABLE IF NOT EXISTS `custom_fields` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `field_name` varchar(100) NOT NULL,
  `field_label` varchar(100) NOT NULL,
  `field_type` enum('text','textarea','number','date') DEFAULT 'text',
  `required` tinyint(1) DEFAULT 0,
  `display_order` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `custom_fields_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Exportiere Daten aus Tabelle d044f149.custom_fields: ~0 rows (ungefähr)
INSERT INTO `custom_fields` (`id`, `field_name`, `field_label`, `field_type`, `required`, `display_order`, `created_at`, `created_by`) VALUES
	(1, 'test', 'bla bla bla', 'text', 1, 0, '2025-10-04 20:14:02', 1);

-- Exportiere Struktur von Tabelle d044f149.document_versions
CREATE TABLE IF NOT EXISTS `document_versions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `document_id` int(11) NOT NULL,
  `version_number` int(11) NOT NULL DEFAULT 1,
  `document_path` varchar(255) NOT NULL,
  `file_size` bigint(20) DEFAULT NULL,
  `uploaded_by` int(11) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `change_description` text DEFAULT NULL,
  `is_current` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `uploaded_by` (`uploaded_by`),
  KEY `idx_document` (`document_id`),
  KEY `idx_current` (`is_current`),
  CONSTRAINT `document_versions_ibfk_1` FOREIGN KEY (`document_id`) REFERENCES `marker_documents` (`id`) ON DELETE CASCADE,
  CONSTRAINT `document_versions_ibfk_2` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Exportiere Daten aus Tabelle d044f149.document_versions: ~0 rows (ungefähr)

-- Exportiere Struktur von Tabelle d044f149.email_log
CREATE TABLE IF NOT EXISTS `email_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `marker_id` int(11) NOT NULL,
  `email_type` varchar(50) NOT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `marker_id` (`marker_id`),
  CONSTRAINT `email_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `email_log_ibfk_2` FOREIGN KEY (`marker_id`) REFERENCES `markers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Exportiere Daten aus Tabelle d044f149.email_log: ~0 rows (ungefähr)

-- Exportiere Struktur von Tabelle d044f149.inspection_schedules
CREATE TABLE IF NOT EXISTS `inspection_schedules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `marker_id` int(11) NOT NULL,
  `inspection_type` enum('TÜV','UVV','DGUV','Sicherheitsprüfung','Sonstiges') NOT NULL,
  `inspection_interval_months` int(11) NOT NULL DEFAULT 12,
  `last_inspection` date DEFAULT NULL,
  `next_inspection` date DEFAULT NULL,
  `inspection_authority` varchar(100) DEFAULT NULL,
  `certificate_number` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `marker_id` (`marker_id`),
  KEY `idx_next_inspection` (`next_inspection`),
  CONSTRAINT `inspection_schedules_ibfk_1` FOREIGN KEY (`marker_id`) REFERENCES `markers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Exportiere Daten aus Tabelle d044f149.inspection_schedules: ~0 rows (ungefähr)

-- Exportiere Struktur von Tabelle d044f149.maintenance_checklists
CREATE TABLE IF NOT EXISTS `maintenance_checklists` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `maintenance_checklists_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Exportiere Daten aus Tabelle d044f149.maintenance_checklists: ~0 rows (ungefähr)

-- Exportiere Struktur von Tabelle d044f149.maintenance_checklist_results
CREATE TABLE IF NOT EXISTS `maintenance_checklist_results` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `maintenance_id` int(11) NOT NULL,
  `checklist_id` int(11) NOT NULL,
  `completed_at` datetime DEFAULT current_timestamp(),
  `completed_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `maintenance_id` (`maintenance_id`),
  KEY `checklist_id` (`checklist_id`),
  KEY `completed_by` (`completed_by`),
  CONSTRAINT `maintenance_checklist_results_ibfk_1` FOREIGN KEY (`maintenance_id`) REFERENCES `maintenance_history` (`id`) ON DELETE CASCADE,
  CONSTRAINT `maintenance_checklist_results_ibfk_2` FOREIGN KEY (`checklist_id`) REFERENCES `maintenance_checklists` (`id`) ON DELETE CASCADE,
  CONSTRAINT `maintenance_checklist_results_ibfk_3` FOREIGN KEY (`completed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Exportiere Daten aus Tabelle d044f149.maintenance_checklist_results: ~0 rows (ungefähr)

-- Exportiere Struktur von Tabelle d044f149.maintenance_history
CREATE TABLE IF NOT EXISTS `maintenance_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `marker_id` int(11) NOT NULL,
  `maintenance_date` date NOT NULL,
  `description` text DEFAULT NULL,
  `performed_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `marker_id` (`marker_id`),
  KEY `performed_by` (`performed_by`),
  CONSTRAINT `maintenance_history_ibfk_1` FOREIGN KEY (`marker_id`) REFERENCES `markers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `maintenance_history_ibfk_2` FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Exportiere Daten aus Tabelle d044f149.maintenance_history: ~0 rows (ungefähr)
INSERT INTO `maintenance_history` (`id`, `marker_id`, `maintenance_date`, `description`, `performed_by`, `created_at`) VALUES
	(1, 8, '2025-10-04', 'fdghdfg', 1, '2025-10-04 17:54:50');

-- Exportiere Struktur von Tabelle d044f149.maintenance_notifications
CREATE TABLE IF NOT EXISTS `maintenance_notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sent_at` datetime NOT NULL,
  `devices_count` int(11) NOT NULL COMMENT 'Anzahl der Geräte mit fälliger Wartung',
  `users_notified` int(11) NOT NULL COMMENT 'Anzahl der benachrichtigten Benutzer',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_sent_at` (`sent_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Protokoll der Wartungsbenachrichtigungen';

-- Exportiere Daten aus Tabelle d044f149.maintenance_notifications: ~0 rows (ungefähr)

-- Exportiere Struktur von Tabelle d044f149.markers
CREATE TABLE IF NOT EXISTS `markers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `rfid_chip` varchar(100) NOT NULL,
  `name` varchar(255) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `serial_number` varchar(100) DEFAULT NULL,
  `is_storage` tinyint(1) DEFAULT 0,
  `rental_status` enum('verfuegbar','vermietet','wartung') DEFAULT 'verfuegbar',
  `operating_hours` decimal(10,2) DEFAULT 0.00,
  `fuel_level` int(11) DEFAULT 0,
  `maintenance_interval_months` int(11) DEFAULT 6,
  `last_maintenance` date DEFAULT NULL,
  `next_maintenance` date DEFAULT NULL,
  `maintenance_required` tinyint(1) DEFAULT 0,
  `latitude` decimal(10,8) NOT NULL,
  `longitude` decimal(11,8) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_multi_device` tinyint(1) DEFAULT 0 COMMENT 'Mehrere Geräte an einem Standort',
  `public_token` varchar(64) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `rfid_chip` (`rfid_chip`),
  UNIQUE KEY `public_token` (`public_token`),
  KEY `created_by` (`created_by`),
  KEY `idx_deleted_at` (`deleted_at`),
  FULLTEXT KEY `ft_search` (`name`,`category`,`serial_number`,`rfid_chip`),
  CONSTRAINT `markers_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Exportiere Daten aus Tabelle d044f149.markers: ~1 rows (ungefähr)
INSERT INTO `markers` (`id`, `rfid_chip`, `name`, `category`, `serial_number`, `is_storage`, `rental_status`, `operating_hours`, `fuel_level`, `maintenance_interval_months`, `last_maintenance`, `next_maintenance`, `maintenance_required`, `latitude`, `longitude`, `created_by`, `created_at`, `updated_at`, `is_multi_device`, `public_token`, `deleted_at`, `deleted_by`) VALUES
	(8, 'RFID00001234', 'fghfdg', 'Generator', '5476456', 0, 'verfuegbar', 0.00, 100, 6, '2025-10-04', '2026-04-04', 0, 49.99458447, 9.07221526, 1, '2025-10-03 09:17:37', '2025-10-05 12:15:33', 0, '6ca2370277c0eae65874fae17a56d7ff5863863eaecebaad8d6d3f2f1085c09f', NULL, NULL);

-- Exportiere Struktur von Tabelle d044f149.marker_comments
CREATE TABLE IF NOT EXISTS `marker_comments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `marker_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `username` varchar(100) DEFAULT NULL,
  `comment` text NOT NULL,
  `is_important` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `idx_marker_created` (`marker_id`,`created_at`),
  CONSTRAINT `marker_comments_ibfk_1` FOREIGN KEY (`marker_id`) REFERENCES `markers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `marker_comments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Exportiere Daten aus Tabelle d044f149.marker_comments: ~0 rows (ungefähr)

-- Exportiere Struktur von Tabelle d044f149.marker_custom_values
CREATE TABLE IF NOT EXISTS `marker_custom_values` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `marker_id` int(11) NOT NULL,
  `field_id` int(11) NOT NULL,
  `field_value` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_marker_field` (`marker_id`,`field_id`),
  KEY `field_id` (`field_id`),
  FULLTEXT KEY `ft_custom_search` (`field_value`),
  CONSTRAINT `marker_custom_values_ibfk_1` FOREIGN KEY (`marker_id`) REFERENCES `markers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `marker_custom_values_ibfk_2` FOREIGN KEY (`field_id`) REFERENCES `custom_fields` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Exportiere Daten aus Tabelle d044f149.marker_custom_values: ~1 rows (ungefähr)
INSERT INTO `marker_custom_values` (`id`, `marker_id`, `field_id`, `field_value`) VALUES
	(2, 8, 1, 'dfghfgdh');

-- Exportiere Struktur von Tabelle d044f149.marker_documents
CREATE TABLE IF NOT EXISTS `marker_documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `marker_id` int(11) NOT NULL,
  `document_name` varchar(255) NOT NULL,
  `document_path` varchar(500) NOT NULL,
  `file_size` int(11) DEFAULT NULL,
  `uploaded_by` int(11) DEFAULT NULL,
  `uploaded_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `marker_id` (`marker_id`),
  KEY `uploaded_by` (`uploaded_by`),
  CONSTRAINT `marker_documents_ibfk_1` FOREIGN KEY (`marker_id`) REFERENCES `markers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `marker_documents_ibfk_2` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Exportiere Daten aus Tabelle d044f149.marker_documents: ~0 rows (ungefähr)

-- Exportiere Struktur von Tabelle d044f149.marker_images
CREATE TABLE IF NOT EXISTS `marker_images` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `marker_id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `marker_id` (`marker_id`),
  CONSTRAINT `marker_images_ibfk_1` FOREIGN KEY (`marker_id`) REFERENCES `markers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Exportiere Daten aus Tabelle d044f149.marker_images: ~0 rows (ungefähr)

-- Exportiere Struktur von Tabelle d044f149.marker_serial_numbers
CREATE TABLE IF NOT EXISTS `marker_serial_numbers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `marker_id` int(11) NOT NULL,
  `serial_number` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_marker_id` (`marker_id`),
  CONSTRAINT `marker_serial_numbers_ibfk_1` FOREIGN KEY (`marker_id`) REFERENCES `markers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Exportiere Daten aus Tabelle d044f149.marker_serial_numbers: ~0 rows (ungefähr)

-- Exportiere Struktur von Tabelle d044f149.patchnotes
CREATE TABLE IF NOT EXISTS `patchnotes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `version` varchar(20) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `release_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exportiere Daten aus Tabelle d044f149.patchnotes: ~7 rows (ungefähr)
INSERT INTO `patchnotes` (`id`, `version`, `title`, `description`, `release_date`, `created_at`, `is_active`) VALUES
	(1, '1.0.0', 'Erstes Release', 'Willkommen zum RFID Marker System! Features:\n- Marker-Verwaltung auf Karte\n- Wartungsverwaltung\n- Multi-Device Support', '2025-10-04', '2025-10-04 09:40:04', 1),
	(3, '1.0.1', 'Erweiterte Suche', 'Features der erweiterten Suche:\r\n✅ Globale Textsuche - Durchsucht Name, RFID, Seriennummer und Kategorie\r\n✅ Filter nach Kategorie - Alle vorhandenen Kategorien\r\n✅ Filter nach Status - Verfügbar, Vermietet, Wartung, Lagergerät, Mehrgerät\r\n✅ Wartungsstatus-Filter - Überfällig, Fällig (30 Tage), OK\r\n✅ Datumsbereich - Suche nach Erstellungsdatum\r\n✅ Kraftstoff-Filter - Min/Max Bereich\r\n✅ Flexible Sortierung - Nach verschiedenen Kriterien, auf-/absteigend\r\n✅ Gespeicherte Suchen - Häufig verwendete Suchen speichern\r\n✅ Verwendungsstatistik - Zeigt wie oft gespeicherte Suchen verwendet wurden\r\n✅ Responsive Design - Funktioniert auf Desktop, Tablet und Mobile\r\n✅ Dark Mode Support - Vollständig kompatibel mit deinem Dark Mode\r\n✅ Direkte Actions - Details anzeigen, Bearbeiten, Auf Karte zeigen', '2025-10-05', '2025-10-05 08:56:32', 1),
	(4, '1.0.2', 'QR-Code Checkout/Checkin System', 'Baut auf vorhandenem QR-System auf', '2025-10-05', '2025-10-05 08:58:16', 1),
	(5, '1.0.3', 'Foto-Upload vom Handy', 'Lade Fotos direkt über die Kamera hoch', '2025-10-05', '2025-10-05 08:58:46', 1),
	(6, '1.0.4', 'Gelöschte Items wiederherstellen', 'Soft-Delete-System', '2025-10-05', '2025-10-05 08:58:59', 1),
	(7, '1.0.5', 'Prüffristen (TÜV, UVV, DGUV)', 'Custom Fields erweitern', '2025-10-05', '2025-10-05 09:00:26', 1),
	(8, '1.0.6', 'Änderungen an der Navigationsbar', 'RFID Scannen wird nur noch auf Handy angezeigt\r\nErneut Scannen wird nur noch auf Handy angezeigt', '2025-10-05', '2025-10-05 09:01:37', 1);

-- Exportiere Struktur von Tabelle d044f149.permissions
CREATE TABLE IF NOT EXISTS `permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `permission_key` varchar(50) NOT NULL,
  `display_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permission_key` (`permission_key`)
) ENGINE=InnoDB AUTO_INCREMENT=91 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Exportiere Daten aus Tabelle d044f149.permissions: ~58 rows (ungefähr)
INSERT INTO `permissions` (`id`, `permission_key`, `display_name`, `description`, `category`) VALUES
	(1, 'markers_view', 'Marker ansehen', 'Kann Marker und deren Details ansehen', 'Marker'),
	(2, 'markers_create', 'Marker erstellen', 'Kann neue Marker erstellen', 'Marker'),
	(3, 'markers_edit', 'Marker bearbeiten', 'Kann bestehende Marker bearbeiten', 'Marker'),
	(4, 'markers_delete', 'Marker löschen', 'Kann Marker löschen', 'Marker'),
	(5, 'markers_change_status', 'Status ändern', 'Kann Mietstatus von Geräten ändern', 'Marker'),
	(6, 'markers_update_position', 'Position aktualisieren', 'Kann GPS-Position aktualisieren', 'Marker'),
	(7, 'maintenance_add', 'Wartung durchführen', 'Kann Wartungen durchführen und eintragen', 'Wartung'),
	(8, 'maintenance_view_history', 'Wartungshistorie ansehen', 'Kann Wartungshistorie ansehen', 'Wartung'),
	(9, 'users_manage', 'Benutzer verwalten', 'Kann Benutzer erstellen, bearbeiten und löschen', 'Administration'),
	(10, 'roles_manage', 'Rollen verwalten', 'Kann Rollen und Berechtigungen verwalten', 'Administration'),
	(11, 'settings_manage', 'Systemeinstellungen', 'Kann Systemeinstellungen ändern', 'Administration'),
	(23, 'markers_view_all', 'Alle Marker anzeigen', 'Kann alle Marker im System sehen', 'Marker'),
	(24, 'markers_view_own', 'Eigene Marker anzeigen', 'Kann nur selbst erstellte Marker sehen', 'Marker'),
	(25, 'markers_export', 'Marker exportieren', 'Kann Markerdaten exportieren (CSV/Excel)', 'Marker'),
	(26, 'markers_import', 'Marker importieren', 'Kann Markerdaten importieren', 'Marker'),
	(27, 'maintenance_view_all', 'Alle Wartungen anzeigen', 'Kann Wartungshistorie aller Geräte sehen', 'Wartung'),
	(28, 'maintenance_edit', 'Wartungen bearbeiten', 'Kann Wartungseinträge bearbeiten', 'Wartung'),
	(29, 'maintenance_delete', 'Wartung löschen', 'Kann Wartungseinträge löschen', 'Wartung'),
	(30, 'reports_view', 'Berichte ansehen', 'Kann Systemberichte und Statistiken ansehen', 'Berichte'),
	(31, 'reports_create', 'Berichte erstellen', 'Kann eigene Berichte erstellen', 'Berichte'),
	(32, 'reports_export', 'Berichte exportieren', 'Kann Berichte exportieren', 'Berichte'),
	(33, 'dashboard_access', 'Dashboard Zugriff', 'Kann das Dashboard sehen', 'System'),
	(34, 'map_view', 'Karte ansehen', 'Kann die Kartenansicht nutzen', 'System'),
	(35, 'search_advanced', 'Erweiterte Suche', 'Kann erweiterte Suchfunktionen nutzen', 'System'),
	(36, 'notifications_manage', 'Benachrichtigungen verwalten', 'Kann eigene Benachrichtigungseinstellungen verwalten', 'System'),
	(37, 'system_logs_view', 'Systemlogs ansehen', 'Kann Systemlogs und Aktivitäten einsehen', 'Administration'),
	(38, 'system_backup', 'System-Backup', 'Kann System-Backups erstellen', 'Administration'),
	(39, 'categories_manage', 'Kategorien verwalten', 'Kann Kategorien erstellen und bearbeiten', 'Administration'),
	(40, 'rfid_scan', 'RFID Scannen', 'Kann RFID-Chips scannen', 'Marker'),
	(41, 'location_update', 'Standort aktualisieren', 'Kann GPS-Positionen von Markern aktualisieren', 'Marker'),
	(42, 'images_manage', 'Bilder verwalten', 'Kann Bilder hochladen und löschen', 'Marker'),
	(43, 'dashboard_view', 'Dashboard anzeigen', 'Zugriff auf das Statistik-Dashboard', 'Dashboard'),
	(44, 'dashboard_export', 'Dashboard exportieren', 'Dashboard-Daten als PDF/Excel exportieren', 'Dashboard'),
	(45, 'markers_bulk_edit', 'Massen-Bearbeitung', 'Mehrere Marker gleichzeitig bearbeiten', 'Marker'),
	(46, 'maintenance_reports', 'Wartungsberichte', 'Wartungsberichte erstellen und exportieren', 'Wartung'),
	(47, 'status_override', 'Status überschreiben', 'Automatische Status-Änderungen überschreiben', 'Status'),
	(48, 'status_history', 'Status-Historie anzeigen', 'Verlauf aller Status-Änderungen einsehen', 'Status'),
	(49, 'images_upload', 'Bilder hochladen', 'Bilder zu Markern hochladen', 'Medien'),
	(50, 'images_delete', 'Bilder löschen', 'Bilder von Markern entfernen', 'Medien'),
	(51, 'documents_upload', 'Dokumente hochladen', 'Dokumente zu Markern hochladen', 'Medien'),
	(52, 'documents_delete', 'Dokumente löschen', 'Dokumente von Markern entfernen', 'Medien'),
	(53, 'reports_generate', 'Berichte erstellen', 'Systemweite Berichte generieren', 'Berichte'),
	(54, 'reports_schedule', 'Berichte planen', 'Automatische Berichterstellung planen', 'Berichte'),
	(55, 'logs_view', 'System-Logs anzeigen', 'System- und Audit-Logs einsehen', 'System'),
	(56, 'logs_export', 'Logs exportieren', 'Log-Dateien exportieren', 'System'),
	(57, 'notifications_send', 'Benachrichtigungen senden', 'Manuelle Benachrichtigungen versenden', 'Benachrichtigungen'),
	(67, 'custom_fields_manage', 'Custom Fields verwalten', 'Eigene Felder erstellen und bearbeiten', 'System'),
	(68, 'activity_log_view', 'Aktivitätsprotokoll ansehen', 'Zugriff auf das Aktivitätsprotokoll', 'System'),
	(71, 'checklists_manage', 'Checklisten verwalten', 'Wartungs-Checklisten erstellen und bearbeiten', 'Wartung'),
	(72, 'checklists_use', 'Checklisten verwenden', 'Checklisten bei Wartungen ausfüllen', 'Wartung'),
	(73, 'comments_add', 'Kommentare schreiben', 'Kommentare zu Markern hinzufügen', 'Marker'),
	(74, 'comments_delete', 'Kommentare löschen', 'Eigene und fremde Kommentare löschen', 'Marker'),
	(75, 'bulk_operations', 'Bulk-Operationen', 'Mehrere Marker gleichzeitig bearbeiten', 'Marker'),
	(76, 'advanced_search', 'Erweiterte Suche', 'Zugriff auf erweiterte Suchfunktionen', 'System'),
	(77, 'statistics_view', 'Statistiken ansehen', 'Nutzungsstatistiken einsehen', 'System'),
	(78, 'settings_dark_mode', 'Dark Mode', 'Dark Mode verwenden', 'System'),
	(80, 'checklists_complete', 'Checklisten ausfüllen', 'Checklisten für Marker ausfüllen', 'Checklisten'),
	(81, 'checklists_view', 'Checklisten ansehen', 'Ausgefüllte Checklisten ansehen', 'Checklisten'),
	(82, 'comments_edit', 'Kommentare bearbeiten', 'Eigene Kommentare bearbeiten', 'Marker'),
	(90, 'add_patchnotes', 'Patchnotes erstellen', 'Benutzer kann webseiten Patchnotes erstellen', 'Benachrichtigungen');

-- Exportiere Struktur von Tabelle d044f149.remember_tokens
CREATE TABLE IF NOT EXISTS `remember_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_token` (`token`),
  KEY `idx_user_token` (`user_id`,`token`),
  KEY `idx_expires` (`expires_at`),
  CONSTRAINT `remember_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Exportiere Daten aus Tabelle d044f149.remember_tokens: ~0 rows (ungefähr)

-- Exportiere Struktur von Tabelle d044f149.roles
CREATE TABLE IF NOT EXISTS `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_name` varchar(50) NOT NULL,
  `display_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `is_system` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `role_name` (`role_name`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Exportiere Daten aus Tabelle d044f149.roles: ~3 rows (ungefähr)
INSERT INTO `roles` (`id`, `role_name`, `display_name`, `description`, `is_system`, `created_at`, `updated_at`) VALUES
	(1, 'admin', 'Administrator', 'Volle Systemrechte - kann alles', 1, '2025-10-02 19:07:41', '2025-10-02 19:07:41'),
	(2, 'user', 'Benutzer', 'Standard-Benutzer - kann Marker verwalten', 1, '2025-10-02 19:07:41', '2025-10-02 19:07:41'),
	(3, 'viewer', 'Betrachter', 'Nur Lesezugriff', 1, '2025-10-02 19:07:41', '2025-10-02 19:07:41');

-- Exportiere Struktur von Tabelle d044f149.role_permissions
CREATE TABLE IF NOT EXISTS `role_permissions` (
  `role_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL,
  PRIMARY KEY (`role_id`,`permission_id`),
  KEY `permission_id` (`permission_id`),
  CONSTRAINT `role_permissions_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_permissions_ibfk_2` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Exportiere Daten aus Tabelle d044f149.role_permissions: ~87 rows (ungefähr)
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES
	(1, 1),
	(1, 2),
	(1, 3),
	(1, 4),
	(1, 5),
	(1, 6),
	(1, 7),
	(1, 8),
	(1, 9),
	(1, 10),
	(1, 11),
	(1, 23),
	(1, 24),
	(1, 25),
	(1, 26),
	(1, 27),
	(1, 28),
	(1, 29),
	(1, 30),
	(1, 31),
	(1, 32),
	(1, 33),
	(1, 34),
	(1, 35),
	(1, 36),
	(1, 37),
	(1, 38),
	(1, 39),
	(1, 40),
	(1, 41),
	(1, 42),
	(1, 43),
	(1, 44),
	(1, 45),
	(1, 46),
	(1, 47),
	(1, 48),
	(1, 49),
	(1, 50),
	(1, 51),
	(1, 52),
	(1, 53),
	(1, 54),
	(1, 55),
	(1, 56),
	(1, 57),
	(1, 67),
	(1, 68),
	(1, 71),
	(1, 72),
	(1, 73),
	(1, 74),
	(1, 75),
	(1, 76),
	(1, 77),
	(1, 78),
	(1, 80),
	(1, 81),
	(1, 82),
	(1, 90),
	(2, 1),
	(2, 2),
	(2, 3),
	(2, 5),
	(2, 6),
	(2, 7),
	(2, 8),
	(2, 23),
	(2, 27),
	(2, 33),
	(2, 34),
	(2, 35),
	(2, 36),
	(2, 40),
	(2, 41),
	(2, 42),
	(2, 43),
	(2, 48),
	(2, 49),
	(3, 1),
	(3, 8),
	(3, 23),
	(3, 27),
	(3, 33),
	(3, 34),
	(3, 43),
	(3, 48);

-- Exportiere Struktur von Tabelle d044f149.saved_filters
CREATE TABLE IF NOT EXISTS `saved_filters` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `filter_name` varchar(100) NOT NULL,
  `filter_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`filter_data`)),
  `is_default` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `saved_filters_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Exportiere Daten aus Tabelle d044f149.saved_filters: ~0 rows (ungefähr)

-- Exportiere Struktur von Tabelle d044f149.saved_searches
CREATE TABLE IF NOT EXISTS `saved_searches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `search_name` varchar(100) NOT NULL,
  `search_params` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_used` timestamp NULL DEFAULT NULL,
  `use_count` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `saved_searches_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Exportiere Daten aus Tabelle d044f149.saved_searches: ~0 rows (ungefähr)

-- Exportiere Struktur von Tabelle d044f149.system_settings
CREATE TABLE IF NOT EXISTS `system_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=609 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Exportiere Daten aus Tabelle d044f149.system_settings: ~23 rows (ungefähr)
INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `updated_at`) VALUES
	(1, 'map_default_lat', '49.994502', '2025-10-05 08:45:46'),
	(2, 'map_default_lng', '9.0721707', '2025-10-05 08:45:46'),
	(3, 'map_default_zoom', '19', '2025-10-04 05:16:24'),
	(4, 'marker_size', 'small', '2025-10-04 05:16:24'),
	(5, 'marker_pulse', '0', '2025-10-03 18:33:32'),
	(6, 'marker_hover_scale', '1', '2025-10-04 05:16:24'),
	(19, 'email_from', '', '2025-10-03 07:05:58'),
	(20, 'email_from_name', 'RFID System', '2025-10-03 07:05:58'),
	(21, 'email_enabled', '0', '2025-10-03 07:05:58'),
	(22, 'maintenance_check_days_before', '7', '2025-10-02 19:42:44'),
	(30, 'storage_marker_color', '#28a745', '2025-10-03 06:56:38'),
	(31, 'show_legend', '1', '2025-10-03 06:56:38'),
	(32, 'show_notifications', '1', '2025-10-03 06:56:38'),
	(33, 'auto_save_interval', '5', '2025-10-03 06:56:38'),
	(70, 'show_map_legend', '1', '2025-10-04 05:01:51'),
	(71, 'show_system_messages', '0', '2025-10-04 04:46:56'),
	(76, 'system_name', 'RFID Marker System', '2025-10-03 09:11:19'),
	(597, 'bug_report_email', 'doofwiescheisse@outlook.de', '2025-10-05 18:02:50'),
	(598, 'bug_report_enabled', '1', '2025-10-05 12:24:07'),
	(599, 'footer_copyright', '© 2025 RFID Marker System', '2025-10-05 12:24:07'),
	(600, 'footer_company', 'Ihr Firmenname', '2025-10-05 12:24:07'),
	(601, 'impressum_url', '/impressum.php', '2025-10-05 12:24:07'),
	(602, 'datenschutz_url', '/datenschutz.php', '2025-10-05 12:24:07');

-- Exportiere Struktur von Tabelle d044f149.usage_statistics
CREATE TABLE IF NOT EXISTS `usage_statistics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action_type` varchar(50) DEFAULT NULL,
  `page` varchar(100) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_date` (`user_id`,`created_at`),
  KEY `idx_action` (`action_type`),
  CONSTRAINT `usage_statistics_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Exportiere Daten aus Tabelle d044f149.usage_statistics: ~19 rows (ungefähr)
INSERT INTO `usage_statistics` (`id`, `user_id`, `action_type`, `page`, `created_at`) VALUES
	(1, 1, 'checklist_complete', 'complete_checklist.php', '2025-10-04 20:56:40'),
	(2, 1, 'checklists_admin_view', 'checklists_admin.php', '2025-10-04 20:57:49'),
	(3, 1, 'checklists_admin_view', 'checklists_admin.php', '2025-10-04 21:00:38'),
	(4, 1, 'setup_2fa', 'setup_2fa.php', '2025-10-04 21:23:29'),
	(5, 1, 'reports_view', 'reports.php', '2025-10-04 21:33:56'),
	(6, 1, 'profile_view', 'profile.php', '2025-10-04 23:02:30'),
	(7, 1, 'profile_view', 'profile.php', '2025-10-04 23:04:31'),
	(8, 1, 'profile_view', 'profile.php', '2025-10-04 23:04:32'),
	(9, 1, 'profile_view', 'profile.php', '2025-10-04 23:04:56'),
	(10, 1, 'profile_view', 'profile.php', '2025-10-04 23:05:19'),
	(11, 1, 'profile_view', 'profile.php', '2025-10-04 23:06:16'),
	(12, 1, 'edit_user', 'edit_user.php', '2025-10-04 23:06:21'),
	(13, 1, 'edit_user', 'edit_user.php', '2025-10-04 23:11:40'),
	(14, 1, 'advanced_search', 'advanced_search.php', '2025-10-05 10:20:53'),
	(15, 1, 'advanced_search', 'advanced_search.php', '2025-10-05 10:23:31'),
	(16, 1, 'advanced_search', 'advanced_search.php', '2025-10-05 10:23:33'),
	(17, 1, 'advanced_search', 'advanced_search.php', '2025-10-05 10:43:48'),
	(18, 1, 'checklists_admin_view', 'checklists_admin.php', '2025-10-05 11:05:05'),
	(19, 1, 'advanced_search', 'advanced_search.php', '2025-10-05 16:22:15');

-- Exportiere Struktur von Tabelle d044f149.users
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `maintenance_notification` tinyint(1) DEFAULT 0,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','user','viewer') DEFAULT 'user',
  `role_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  `receive_maintenance_emails` tinyint(1) DEFAULT 0 COMMENT 'Erhält E-Mails bei fälliger Wartung',
  `require_2fa` tinyint(1) DEFAULT 0,
  `has_2fa_enabled` tinyint(1) DEFAULT 0,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `role_id` (`role_id`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Exportiere Daten aus Tabelle d044f149.users: ~1 rows (ungefähr)
INSERT INTO `users` (`id`, `username`, `email`, `maintenance_notification`, `password`, `role`, `role_id`, `created_at`, `last_login`, `receive_maintenance_emails`, `require_2fa`, `has_2fa_enabled`, `first_name`, `last_name`, `phone`) VALUES
	(1, 'admin', 'admin@example.com', 0, '$2y$12$kbsJR1eK2YkyjDdyo55D1OT6MTnu7HW0MaswzobSbQGhivr2z7zju', 'admin', 1, '2025-10-02 10:45:19', '2025-10-05 18:57:19', 1, 0, 0, 'Frank', 'Schwind', '123456789');

-- Exportiere Struktur von Tabelle d044f149.user_2fa
CREATE TABLE IF NOT EXISTS `user_2fa` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `secret` varchar(32) NOT NULL,
  `is_enabled` tinyint(1) DEFAULT 0,
  `backup_codes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  CONSTRAINT `user_2fa_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Exportiere Daten aus Tabelle d044f149.user_2fa: ~0 rows (ungefähr)

-- Exportiere Struktur von Tabelle d044f149.user_preferences
CREATE TABLE IF NOT EXISTS `user_preferences` (
  `user_id` int(11) NOT NULL,
  `dark_mode` tinyint(1) DEFAULT 0,
  `language` varchar(10) DEFAULT 'de',
  `notifications_enabled` tinyint(1) DEFAULT 1,
  `preferences` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`preferences`)),
  PRIMARY KEY (`user_id`),
  CONSTRAINT `user_preferences_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Exportiere Daten aus Tabelle d044f149.user_preferences: ~0 rows (ungefähr)
INSERT INTO `user_preferences` (`user_id`, `dark_mode`, `language`, `notifications_enabled`, `preferences`) VALUES
	(1, 0, 'de', 1, NULL);

-- Exportiere Struktur von Tabelle d044f149.user_settings
CREATE TABLE IF NOT EXISTS `user_settings` (
  `user_id` int(11) NOT NULL,
  `dark_mode` tinyint(1) DEFAULT 0,
  `items_per_page` int(11) DEFAULT 25,
  `default_map_view` varchar(20) DEFAULT 'standard',
  `notifications_enabled` tinyint(1) DEFAULT 1,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`user_id`),
  CONSTRAINT `user_settings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Exportiere Daten aus Tabelle d044f149.user_settings: ~0 rows (ungefähr)

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
