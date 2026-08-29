-- Struktura pouze pro synteticke localhost demo; neobsahuje zadne radky aplikacnich dat.
SET FOREIGN_KEY_CHECKS=0;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `aa_treneri` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `jmeno` varchar(100) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `heslo` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `account_person_claim_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `request_id` bigint(20) unsigned NOT NULL,
  `actor_type` varchar(24) NOT NULL,
  `actor_id` int(11) NOT NULL,
  `action` varchar(24) NOT NULL,
  `from_status` varchar(24) DEFAULT NULL,
  `to_status` varchar(24) NOT NULL,
  `note` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_account_claim_event` (`request_id`,`created_at`),
  CONSTRAINT `fk_account_claim_event_request` FOREIGN KEY (`request_id`) REFERENCES `account_person_claim_requests` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `account_person_claim_requests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `account_id` int(11) NOT NULL,
  `requested_role` varchar(24) NOT NULL,
  `request_kind` varchar(32) NOT NULL DEFAULT 'person_link',
  `contract_version` varchar(64) DEFAULT NULL,
  `claimed_jmeno` varchar(100) NOT NULL,
  `claimed_prijmeni` varchar(100) NOT NULL,
  `claimed_narozeni` date NOT NULL,
  `requester_message` text NOT NULL,
  `status` varchar(24) NOT NULL DEFAULT 'pending',
  `active_fingerprint` char(64) DEFAULT NULL,
  `matched_sportovec_id` int(11) DEFAULT NULL,
  `decided_by_trainer_id` int(11) DEFAULT NULL,
  `decision_note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `decided_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_account_claim_pending` (`account_id`,`active_fingerprint`),
  KEY `idx_account_claim_status` (`status`,`created_at`),
  KEY `idx_account_claim_account` (`account_id`,`created_at`),
  KEY `fk_account_claim_person` (`matched_sportovec_id`),
  KEY `fk_account_claim_decider` (`decided_by_trainer_id`),
  KEY `idx_account_claim_kind_status` (`request_kind`,`status`,`created_at`),
  CONSTRAINT `fk_account_claim_account` FOREIGN KEY (`account_id`) REFERENCES `verejni_uzivatele` (`id`),
  CONSTRAINT `fk_account_claim_decider` FOREIGN KEY (`decided_by_trainer_id`) REFERENCES `treneri` (`id`),
  CONSTRAINT `fk_account_claim_person` FOREIGN KEY (`matched_sportovec_id`) REFERENCES `sportovci` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `account_person_role_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `relation_id` bigint(20) unsigned NOT NULL,
  `actor_trainer_id` int(11) NOT NULL,
  `action` varchar(24) NOT NULL,
  `from_status` varchar(24) DEFAULT NULL,
  `to_status` varchar(24) NOT NULL,
  `relation_role` varchar(24) NOT NULL,
  `note` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_account_role_event_relation` (`relation_id`,`created_at`),
  KEY `fk_account_role_event_actor` (`actor_trainer_id`),
  CONSTRAINT `fk_account_role_event_actor` FOREIGN KEY (`actor_trainer_id`) REFERENCES `treneri` (`id`),
  CONSTRAINT `fk_account_role_event_relation` FOREIGN KEY (`relation_id`) REFERENCES `account_person_roles` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `account_person_roles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `account_id` int(11) NOT NULL,
  `sportovec_id` int(11) NOT NULL,
  `relation_role` varchar(24) NOT NULL,
  `status` varchar(24) NOT NULL,
  `source` varchar(24) NOT NULL DEFAULT 'admin',
  `valid_from` datetime NOT NULL,
  `valid_to` datetime DEFAULT NULL,
  `created_by_trainer_id` int(11) NOT NULL,
  `approved_by_trainer_id` int(11) DEFAULT NULL,
  `decision_note` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_account_person` (`account_id`,`sportovec_id`),
  KEY `idx_account_person_active` (`account_id`,`status`,`valid_to`),
  KEY `idx_person_account_active` (`sportovec_id`,`status`,`valid_to`),
  KEY `fk_account_person_created_by` (`created_by_trainer_id`),
  KEY `fk_account_person_approved_by` (`approved_by_trainer_id`),
  CONSTRAINT `fk_account_person_account` FOREIGN KEY (`account_id`) REFERENCES `verejni_uzivatele` (`id`),
  CONSTRAINT `fk_account_person_approved_by` FOREIGN KEY (`approved_by_trainer_id`) REFERENCES `treneri` (`id`),
  CONSTRAINT `fk_account_person_created_by` FOREIGN KEY (`created_by_trainer_id`) REFERENCES `treneri` (`id`),
  CONSTRAINT `fk_account_person_sportovec` FOREIGN KEY (`sportovec_id`) REFERENCES `sportovci` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `athlete_private_files` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `request_id` bigint(20) unsigned NOT NULL,
  `sportovec_id` int(11) DEFAULT NULL,
  `file_kind` varchar(32) NOT NULL,
  `storage_key` varchar(128) NOT NULL,
  `sha256` binary(32) NOT NULL,
  `byte_size` bigint(20) unsigned NOT NULL,
  `mime_type` varchar(64) NOT NULL,
  `width_px` int(10) unsigned NOT NULL,
  `height_px` int(10) unsigned NOT NULL,
  `status` varchar(24) NOT NULL DEFAULT 'active',
  `consent_snapshot_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `replaced_at` datetime DEFAULT NULL,
  `erased_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_athlete_private_storage_key` (`storage_key`),
  KEY `idx_athlete_private_request_status` (`request_id`,`status`,`id`),
  KEY `idx_athlete_private_person_status` (`sportovec_id`,`status`,`id`),
  KEY `idx_athlete_private_consent` (`consent_snapshot_id`),
  CONSTRAINT `fk_athlete_private_consent` FOREIGN KEY (`consent_snapshot_id`) REFERENCES `athlete_registration_consent_snapshots` (`id`),
  CONSTRAINT `fk_athlete_private_person` FOREIGN KEY (`sportovec_id`) REFERENCES `sportovci` (`id`),
  CONSTRAINT `fk_athlete_private_request` FOREIGN KEY (`request_id`) REFERENCES `account_person_claim_requests` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `athlete_registration_consent_snapshots` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `request_id` bigint(20) unsigned NOT NULL,
  `purpose` varchar(64) NOT NULL,
  `term_version_id` bigint(20) unsigned DEFAULT NULL,
  `terms_version` varchar(64) NOT NULL,
  `text_snapshot` text NOT NULL,
  `accepted` tinyint(1) NOT NULL,
  `accepted_by_account_id` int(11) NOT NULL,
  `accepted_at` datetime NOT NULL,
  `withdrawn_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_athlete_registration_consent` (`request_id`,`purpose`),
  KEY `idx_athlete_registration_consent_term` (`term_version_id`),
  KEY `idx_athlete_registration_consent_account` (`accepted_by_account_id`),
  CONSTRAINT `fk_athlete_registration_consent_account` FOREIGN KEY (`accepted_by_account_id`) REFERENCES `verejni_uzivatele` (`id`),
  CONSTRAINT `fk_athlete_registration_consent_request` FOREIGN KEY (`request_id`) REFERENCES `account_person_claim_requests` (`id`),
  CONSTRAINT `fk_athlete_registration_consent_term` FOREIGN KEY (`term_version_id`) REFERENCES `club_event_term_versions` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `athlete_registration_request_details` (
  `request_id` bigint(20) unsigned NOT NULL,
  `submitted_related_sportovec_id` int(11) DEFAULT NULL,
  `has_czech_birth_number` tinyint(1) NOT NULL DEFAULT 1,
  `contact_email_snapshot` varchar(255) NOT NULL,
  `contact_phone` varchar(50) NOT NULL,
  `citizenship_country_code` char(2) NOT NULL DEFAULT 'CZ',
  `address_street` varchar(200) NOT NULL,
  `address_house_number` varchar(20) NOT NULL,
  `address_orientation_number` varchar(20) DEFAULT NULL,
  `address_city` varchar(100) NOT NULL,
  `address_postcode` varchar(10) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`request_id`),
  KEY `idx_athlete_registration_related_person` (`submitted_related_sportovec_id`),
  CONSTRAINT `fk_athlete_registration_detail_person` FOREIGN KEY (`submitted_related_sportovec_id`) REFERENCES `sportovci` (`id`),
  CONSTRAINT `fk_athlete_registration_detail_request` FOREIGN KEY (`request_id`) REFERENCES `account_person_claim_requests` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `auth_login_limits` (
  `scope` varchar(64) NOT NULL,
  `key_hash` char(64) NOT NULL,
  `window_started_at` bigint(20) unsigned NOT NULL,
  `attempts` int(10) unsigned NOT NULL DEFAULT 0,
  `blocked_until` bigint(20) unsigned NOT NULL DEFAULT 0,
  `updated_at` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`scope`,`key_hash`),
  KEY `idx_auth_login_limits_blocked` (`blocked_until`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `child_access_accounts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sportovec_id` int(11) NOT NULL,
  `login_name` varchar(120) NOT NULL,
  `login_key` varchar(120) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `session_version` int(10) unsigned NOT NULL DEFAULT 1,
  `last_login_at` datetime DEFAULT NULL,
  `password_changed_at` datetime NOT NULL,
  `created_by_trainer_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_child_access_sportovec` (`sportovec_id`),
  UNIQUE KEY `uq_child_access_login_key` (`login_key`),
  KEY `idx_child_access_active` (`active`,`id`),
  KEY `fk_child_access_creator` (`created_by_trainer_id`),
  CONSTRAINT `fk_child_access_creator` FOREIGN KEY (`created_by_trainer_id`) REFERENCES `treneri` (`id`),
  CONSTRAINT `fk_child_access_sportovec` FOREIGN KEY (`sportovec_id`) REFERENCES `sportovci` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `child_access_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `access_account_id` bigint(20) unsigned NOT NULL,
  `actor_type` varchar(24) NOT NULL,
  `actor_id` bigint(20) DEFAULT NULL,
  `action` varchar(32) NOT NULL,
  `note` varchar(1000) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_child_access_event_account` (`access_account_id`,`id`),
  CONSTRAINT `fk_child_access_event_account` FOREIGN KEY (`access_account_id`) REFERENCES `child_access_accounts` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `club_event_admin_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `event_id` bigint(20) unsigned NOT NULL,
  `actor_trainer_id` int(11) NOT NULL,
  `action` varchar(32) NOT NULL,
  `subject_type` varchar(24) NOT NULL,
  `subject_id` bigint(20) unsigned DEFAULT NULL,
  `note` text NOT NULL,
  `payload_json` longtext NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_club_admin_event` (`event_id`,`created_at`),
  KEY `fk_club_admin_event_actor` (`actor_trainer_id`),
  CONSTRAINT `fk_club_admin_event_actor` FOREIGN KEY (`actor_trainer_id`) REFERENCES `treneri` (`id`),
  CONSTRAINT `fk_club_admin_event_event` FOREIGN KEY (`event_id`) REFERENCES `club_events` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `club_event_cart_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `cart_id` bigint(20) unsigned NOT NULL,
  `event_id` bigint(20) unsigned NOT NULL,
  `variant_id` bigint(20) unsigned NOT NULL,
  `beneficiary_sportovec_id` int(11) NOT NULL,
  `consent_version` varchar(64) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_event_cart_person` (`cart_id`,`event_id`,`beneficiary_sportovec_id`),
  KEY `fk_event_cart_event` (`event_id`),
  KEY `fk_event_cart_variant` (`variant_id`),
  KEY `fk_event_cart_person` (`beneficiary_sportovec_id`),
  CONSTRAINT `fk_event_cart_cart` FOREIGN KEY (`cart_id`) REFERENCES `shop_carts` (`id`),
  CONSTRAINT `fk_event_cart_event` FOREIGN KEY (`event_id`) REFERENCES `club_events` (`id`),
  CONSTRAINT `fk_event_cart_person` FOREIGN KEY (`beneficiary_sportovec_id`) REFERENCES `sportovci` (`id`),
  CONSTRAINT `fk_event_cart_variant` FOREIGN KEY (`variant_id`) REFERENCES `shop_variants` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `club_event_links` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `event_id` bigint(20) unsigned NOT NULL,
  `link_type` varchar(24) NOT NULL,
  `label` varchar(255) NOT NULL,
  `url` varchar(2048) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_by_trainer_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_club_event_links_creator` (`created_by_trainer_id`),
  KEY `idx_club_event_links_event` (`event_id`,`sort_order`,`id`),
  CONSTRAINT `fk_club_event_links_creator` FOREIGN KEY (`created_by_trainer_id`) REFERENCES `treneri` (`id`),
  CONSTRAINT `fk_club_event_links_event` FOREIGN KEY (`event_id`) REFERENCES `club_events` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `club_event_notification_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `notification_id` bigint(20) unsigned NOT NULL,
  `actor_trainer_id` int(11) NOT NULL,
  `action` varchar(64) NOT NULL,
  `from_status` varchar(32) NOT NULL,
  `attempts_before` tinyint(3) unsigned NOT NULL,
  `reason` varchar(1000) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_club_notification_event_notification` (`notification_id`,`id`),
  KEY `fk_club_notification_event_actor` (`actor_trainer_id`),
  CONSTRAINT `fk_club_notification_event_actor` FOREIGN KEY (`actor_trainer_id`) REFERENCES `treneri` (`id`),
  CONSTRAINT `fk_club_notification_event_notification` FOREIGN KEY (`notification_id`) REFERENCES `club_event_notifications` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `club_event_notifications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `registration_id` bigint(20) unsigned DEFAULT NULL,
  `registration_event_id` bigint(20) unsigned DEFAULT NULL,
  `order_id` bigint(20) unsigned DEFAULT NULL,
  `notification_type` varchar(64) NOT NULL,
  `recipient_email` varchar(254) NOT NULL,
  `recipient_name` varchar(255) NOT NULL,
  `subject_plain` varchar(255) NOT NULL,
  `body_plain` text NOT NULL,
  `status` enum('pending','processing','sent','failed') NOT NULL DEFAULT 'pending',
  `attempts` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `available_at` datetime NOT NULL DEFAULT current_timestamp(),
  `claimed_at` datetime DEFAULT NULL,
  `claim_token` char(32) DEFAULT NULL,
  `sent_at` datetime DEFAULT NULL,
  `last_error` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_club_event_notification` (`registration_event_id`,`notification_type`),
  UNIQUE KEY `uq_shop_payment_notification` (`order_id`,`notification_type`),
  KEY `idx_club_event_notification_queue` (`status`,`available_at`,`id`),
  KEY `fk_club_event_notification_registration` (`registration_id`),
  KEY `idx_club_notification_order` (`order_id`,`id`),
  CONSTRAINT `fk_club_event_notification_event` FOREIGN KEY (`registration_event_id`) REFERENCES `club_event_registration_events` (`id`),
  CONSTRAINT `fk_club_event_notification_registration` FOREIGN KEY (`registration_id`) REFERENCES `club_event_registrations` (`id`),
  CONSTRAINT `fk_club_notification_order` FOREIGN KEY (`order_id`) REFERENCES `shop_orders` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `club_event_order_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint(20) unsigned NOT NULL,
  `registration_id` bigint(20) unsigned NOT NULL,
  `event_id` bigint(20) unsigned NOT NULL,
  `variant_id` bigint(20) unsigned NOT NULL,
  `beneficiary_sportovec_id` int(11) NOT NULL,
  `event_name_snapshot` varchar(255) NOT NULL,
  `sku_snapshot` varchar(191) NOT NULL,
  `consent_version_snapshot` varchar(64) NOT NULL,
  `consent_text_snapshot` text NOT NULL,
  `cancellation_policy_snapshot` text NOT NULL,
  `cancellation_deadline_snapshot` datetime NOT NULL,
  `eligibility_team_ids_snapshot` longtext NOT NULL,
  `quantity` int(10) unsigned NOT NULL DEFAULT 1,
  `unit_amount_minor` bigint(20) unsigned NOT NULL,
  `line_amount_minor` bigint(20) unsigned NOT NULL,
  `currency` char(3) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_event_order_registration` (`registration_id`),
  UNIQUE KEY `uq_event_order_person` (`order_id`,`event_id`,`beneficiary_sportovec_id`),
  KEY `fk_event_order_event` (`event_id`),
  KEY `fk_event_order_variant` (`variant_id`),
  KEY `fk_event_order_person` (`beneficiary_sportovec_id`),
  CONSTRAINT `fk_event_order_event` FOREIGN KEY (`event_id`) REFERENCES `club_events` (`id`),
  CONSTRAINT `fk_event_order_order` FOREIGN KEY (`order_id`) REFERENCES `shop_orders` (`id`),
  CONSTRAINT `fk_event_order_person` FOREIGN KEY (`beneficiary_sportovec_id`) REFERENCES `sportovci` (`id`),
  CONSTRAINT `fk_event_order_registration` FOREIGN KEY (`registration_id`) REFERENCES `club_event_registrations` (`id`),
  CONSTRAINT `fk_event_order_variant` FOREIGN KEY (`variant_id`) REFERENCES `shop_variants` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `club_event_people` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `event_id` bigint(20) unsigned NOT NULL,
  `person_role` varchar(24) NOT NULL,
  `trainer_id` int(11) DEFAULT NULL,
  `external_name` varchar(255) DEFAULT NULL,
  `external_contact` varchar(255) DEFAULT NULL,
  `visible_to_members` tinyint(1) NOT NULL DEFAULT 0,
  `note` varchar(1000) NOT NULL DEFAULT '',
  `created_by_trainer_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_club_event_people_trainer` (`trainer_id`),
  KEY `fk_club_event_people_creator` (`created_by_trainer_id`),
  KEY `idx_club_event_people_event` (`event_id`,`id`),
  CONSTRAINT `fk_club_event_people_creator` FOREIGN KEY (`created_by_trainer_id`) REFERENCES `treneri` (`id`),
  CONSTRAINT `fk_club_event_people_event` FOREIGN KEY (`event_id`) REFERENCES `club_events` (`id`),
  CONSTRAINT `fk_club_event_people_trainer` FOREIGN KEY (`trainer_id`) REFERENCES `treneri` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `club_event_planned_participants` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `event_id` bigint(20) unsigned NOT NULL,
  `sportovec_id` int(11) NOT NULL,
  `account_id` int(11) DEFAULT NULL,
  `status` varchar(24) NOT NULL DEFAULT 'planned',
  `registration_id` bigint(20) unsigned DEFAULT NULL,
  `charge_id` bigint(20) unsigned DEFAULT NULL,
  `created_by_trainer_id` int(11) DEFAULT NULL,
  `confirmed_by_trainer_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `confirmed_at` datetime DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_club_planned_participant` (`event_id`,`sportovec_id`),
  KEY `fk_club_planned_person` (`sportovec_id`),
  KEY `fk_club_planned_account` (`account_id`),
  KEY `fk_club_planned_registration` (`registration_id`),
  KEY `fk_club_planned_charge` (`charge_id`),
  KEY `fk_club_planned_creator` (`created_by_trainer_id`),
  KEY `fk_club_planned_confirmer` (`confirmed_by_trainer_id`),
  KEY `idx_club_planned_status` (`event_id`,`status`,`id`),
  CONSTRAINT `fk_club_planned_account` FOREIGN KEY (`account_id`) REFERENCES `verejni_uzivatele` (`id`),
  CONSTRAINT `fk_club_planned_charge` FOREIGN KEY (`charge_id`) REFERENCES `club_member_charges` (`id`),
  CONSTRAINT `fk_club_planned_confirmer` FOREIGN KEY (`confirmed_by_trainer_id`) REFERENCES `treneri` (`id`),
  CONSTRAINT `fk_club_planned_creator` FOREIGN KEY (`created_by_trainer_id`) REFERENCES `treneri` (`id`),
  CONSTRAINT `fk_club_planned_event` FOREIGN KEY (`event_id`) REFERENCES `club_events` (`id`),
  CONSTRAINT `fk_club_planned_person` FOREIGN KEY (`sportovec_id`) REFERENCES `sportovci` (`id`),
  CONSTRAINT `fk_club_planned_registration` FOREIGN KEY (`registration_id`) REFERENCES `club_event_registrations` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `club_event_registration_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `registration_id` bigint(20) unsigned NOT NULL,
  `actor_type` varchar(24) NOT NULL,
  `actor_id` int(11) DEFAULT NULL,
  `action` varchar(32) NOT NULL,
  `from_status` varchar(24) DEFAULT NULL,
  `to_status` varchar(24) NOT NULL,
  `note` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_club_registration_event_history` (`registration_id`,`created_at`),
  CONSTRAINT `fk_club_registration_history` FOREIGN KEY (`registration_id`) REFERENCES `club_event_registrations` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `club_event_registrations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `event_id` bigint(20) unsigned NOT NULL,
  `account_id` int(11) NOT NULL,
  `sportovec_id` int(11) NOT NULL,
  `relation_role_snapshot` varchar(24) NOT NULL,
  `status` varchar(24) NOT NULL DEFAULT 'confirmed',
  `registered_at` datetime NOT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `cancellation_note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `consent_version_snapshot` varchar(64) DEFAULT NULL,
  `consent_text_snapshot` text DEFAULT NULL,
  `consented_at` datetime DEFAULT NULL,
  `cancellation_policy_snapshot` text DEFAULT NULL,
  `cancellation_deadline_snapshot` datetime DEFAULT NULL,
  `waitlisted_at` datetime DEFAULT NULL,
  `promoted_at` datetime DEFAULT NULL,
  `eligibility_team_ids_snapshot` longtext DEFAULT NULL,
  `eligibility_reason_snapshot` varchar(1000) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_club_registration_person` (`event_id`,`sportovec_id`),
  KEY `idx_club_registration_capacity` (`event_id`,`status`),
  KEY `idx_club_registration_account` (`account_id`,`status`),
  KEY `fk_club_registration_person` (`sportovec_id`),
  KEY `idx_club_registration_waitlist` (`event_id`,`status`,`waitlisted_at`,`id`),
  CONSTRAINT `fk_club_registration_account` FOREIGN KEY (`account_id`) REFERENCES `verejni_uzivatele` (`id`),
  CONSTRAINT `fk_club_registration_event` FOREIGN KEY (`event_id`) REFERENCES `club_events` (`id`),
  CONSTRAINT `fk_club_registration_person` FOREIGN KEY (`sportovec_id`) REFERENCES `sportovci` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `club_event_roster_targets` (
  `event_id` bigint(20) unsigned NOT NULL,
  `team_id` bigint(20) unsigned NOT NULL,
  `actor_trainer_id` int(11) NOT NULL,
  `decision_note` varchar(1000) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`event_id`,`team_id`),
  KEY `idx_club_event_roster_team` (`team_id`,`event_id`),
  KEY `fk_club_event_roster_actor` (`actor_trainer_id`),
  CONSTRAINT `fk_club_event_roster_actor` FOREIGN KEY (`actor_trainer_id`) REFERENCES `treneri` (`id`),
  CONSTRAINT `fk_club_event_roster_event` FOREIGN KEY (`event_id`) REFERENCES `club_events` (`id`),
  CONSTRAINT `fk_club_event_roster_team` FOREIGN KEY (`team_id`) REFERENCES `club_teams` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `club_event_sessions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `event_id` bigint(20) unsigned NOT NULL,
  `starts_at` datetime NOT NULL,
  `ends_at` datetime NOT NULL,
  `location` varchar(255) NOT NULL,
  `capacity_override` int(10) unsigned DEFAULT NULL,
  `status` varchar(24) NOT NULL DEFAULT 'scheduled',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_club_session_event_time` (`event_id`,`starts_at`),
  CONSTRAINT `fk_club_session_event` FOREIGN KEY (`event_id`) REFERENCES `club_events` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `club_event_term_versions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `scope_type` varchar(32) NOT NULL,
  `scope_key` varchar(128) NOT NULL,
  `consent_purpose` varchar(64) NOT NULL,
  `event_id` bigint(20) unsigned DEFAULT NULL,
  `terms_version` varchar(64) NOT NULL,
  `consent_text_plain` text NOT NULL,
  `cancellation_policy_plain` text DEFAULT NULL,
  `cancellation_deadline_at` datetime DEFAULT NULL,
  `actor_trainer_id` int(11) DEFAULT NULL,
  `actor_type` varchar(24) NOT NULL DEFAULT 'trainer',
  `actor_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` varchar(16) NOT NULL DEFAULT 'active',
  `archived_at` datetime DEFAULT NULL,
  `archived_by_trainer_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_terms_scope_version` (`scope_type`,`scope_key`,`consent_purpose`,`terms_version`),
  UNIQUE KEY `uq_club_event_terms_version` (`event_id`,`terms_version`),
  KEY `fk_club_terms_actor` (`actor_trainer_id`),
  KEY `idx_terms_scope_current` (`scope_type`,`scope_key`,`consent_purpose`,`status`,`id`),
  KEY `fk_terms_archiver` (`archived_by_trainer_id`),
  CONSTRAINT `fk_club_terms_actor` FOREIGN KEY (`actor_trainer_id`) REFERENCES `treneri` (`id`),
  CONSTRAINT `fk_club_terms_event` FOREIGN KEY (`event_id`) REFERENCES `club_events` (`id`),
  CONSTRAINT `fk_terms_archiver` FOREIGN KEY (`archived_by_trainer_id`) REFERENCES `treneri` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `club_event_vehicle_reservations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `event_id` bigint(20) unsigned NOT NULL,
  `vehicle_id` int(11) NOT NULL,
  `starts_at` datetime NOT NULL,
  `ends_at` datetime NOT NULL,
  `driver_trainer_id` int(11) DEFAULT NULL,
  `driver_name` varchar(255) DEFAULT NULL,
  `note` varchar(1000) NOT NULL DEFAULT '',
  `conflict_acknowledged` tinyint(1) NOT NULL DEFAULT 0,
  `conflict_note` varchar(1000) DEFAULT NULL,
  `status` varchar(24) NOT NULL DEFAULT 'active',
  `created_by_trainer_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_club_vehicle_driver` (`driver_trainer_id`),
  KEY `fk_club_vehicle_creator` (`created_by_trainer_id`),
  KEY `idx_club_vehicle_time` (`vehicle_id`,`status`,`starts_at`,`ends_at`),
  KEY `idx_club_vehicle_event` (`event_id`,`status`,`id`),
  CONSTRAINT `fk_club_vehicle_creator` FOREIGN KEY (`created_by_trainer_id`) REFERENCES `treneri` (`id`),
  CONSTRAINT `fk_club_vehicle_driver` FOREIGN KEY (`driver_trainer_id`) REFERENCES `treneri` (`id`),
  CONSTRAINT `fk_club_vehicle_event` FOREIGN KEY (`event_id`) REFERENCES `club_events` (`id`),
  CONSTRAINT `fk_club_vehicle_vehicle` FOREIGN KEY (`vehicle_id`) REFERENCES `ucto_vozidla` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `club_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(64) NOT NULL,
  `event_type` varchar(24) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description_plain` text NOT NULL,
  `audience_label` varchar(255) NOT NULL,
  `min_age` smallint(5) unsigned DEFAULT NULL,
  `max_age` smallint(5) unsigned DEFAULT NULL,
  `capacity` int(10) unsigned NOT NULL,
  `pricing_policy` varchar(24) NOT NULL,
  `currency` char(3) NOT NULL,
  `registration_starts_at` datetime DEFAULT NULL,
  `registration_ends_at` datetime DEFAULT NULL,
  `status` varchar(24) NOT NULL DEFAULT 'draft',
  `created_by_trainer_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `terms_version` varchar(64) DEFAULT NULL,
  `consent_text_plain` text DEFAULT NULL,
  `cancellation_policy_plain` text DEFAULT NULL,
  `cancellation_deadline_at` datetime DEFAULT NULL,
  `terms_configured_at` datetime DEFAULT NULL,
  `terms_configured_by_trainer_id` int(11) DEFAULT NULL,
  `activity_kind` varchar(24) NOT NULL DEFAULT 'other',
  `planning_status` varchar(24) NOT NULL DEFAULT 'confirmed',
  `visibility` varchar(24) NOT NULL DEFAULT 'staff',
  `public_description_plain` text DEFAULT NULL,
  `internal_note` text DEFAULT NULL,
  `participant_fee_minor` bigint(20) unsigned NOT NULL DEFAULT 0,
  `fee_due_days` smallint(5) unsigned NOT NULL DEFAULT 14,
  `legacy_race_id` int(11) DEFAULT NULL,
  `public_published_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_club_event_code` (`code`),
  UNIQUE KEY `uq_club_event_legacy_race` (`legacy_race_id`),
  KEY `idx_club_event_status` (`status`,`event_type`),
  KEY `fk_club_event_creator` (`created_by_trainer_id`),
  KEY `idx_club_event_planning_visibility` (`planning_status`,`visibility`,`status`),
  CONSTRAINT `fk_club_event_creator` FOREIGN KEY (`created_by_trainer_id`) REFERENCES `treneri` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `club_member_charge_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `charge_id` bigint(20) unsigned NOT NULL,
  `action` varchar(48) NOT NULL,
  `from_status` varchar(24) DEFAULT NULL,
  `to_status` varchar(24) DEFAULT NULL,
  `actor_type` varchar(24) NOT NULL,
  `actor_id` bigint(20) DEFAULT NULL,
  `reason` varchar(1000) NOT NULL,
  `snapshot_json` longtext NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_member_charge_event` (`charge_id`,`id`),
  CONSTRAINT `fk_member_charge_event_charge` FOREIGN KEY (`charge_id`) REFERENCES `club_member_charges` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `club_member_charges` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sportovec_id` int(11) NOT NULL,
  `payer_account_id` int(11) DEFAULT NULL,
  `public_code` varchar(32) NOT NULL,
  `charge_type` varchar(32) NOT NULL,
  `title_snapshot` varchar(255) NOT NULL,
  `period_from` date DEFAULT NULL,
  `period_to` date DEFAULT NULL,
  `amount_minor` bigint(20) unsigned NOT NULL,
  `currency` char(3) NOT NULL,
  `due_on` date DEFAULT NULL,
  `status` varchar(24) NOT NULL,
  `source_system` varchar(32) NOT NULL,
  `source_external_id` varchar(80) DEFAULT NULL,
  `source_import_run_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_member_charge_public_code` (`public_code`),
  UNIQUE KEY `uq_member_charge_source` (`source_system`,`source_external_id`),
  KEY `idx_member_charge_beneficiary` (`sportovec_id`,`status`,`due_on`,`id`),
  KEY `idx_member_charge_payer` (`payer_account_id`,`status`,`due_on`,`id`),
  KEY `fk_member_charge_import` (`source_import_run_id`),
  CONSTRAINT `fk_member_charge_import` FOREIGN KEY (`source_import_run_id`) REFERENCES `kis_import_runs` (`id`),
  CONSTRAINT `fk_member_charge_payer` FOREIGN KEY (`payer_account_id`) REFERENCES `verejni_uzivatele` (`id`),
  CONSTRAINT `fk_member_charge_sportovec` FOREIGN KEY (`sportovec_id`) REFERENCES `sportovci` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `club_program_enrollment_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `offer_id` bigint(20) unsigned NOT NULL,
  `enrollment_id` bigint(20) unsigned DEFAULT NULL,
  `actor_type` varchar(24) NOT NULL,
  `actor_id` int(11) DEFAULT NULL,
  `action` varchar(48) NOT NULL,
  `payload_json` longtext NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_club_program_event_offer` (`offer_id`,`id`),
  KEY `idx_club_program_event_enrollment` (`enrollment_id`,`id`),
  CONSTRAINT `fk_club_program_event_enrollment` FOREIGN KEY (`enrollment_id`) REFERENCES `club_program_enrollments` (`id`),
  CONSTRAINT `fk_club_program_event_offer` FOREIGN KEY (`offer_id`) REFERENCES `club_program_offers` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `club_program_enrollments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `offer_id` bigint(20) unsigned NOT NULL,
  `sportovec_id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `source_order_item_id` bigint(20) unsigned NOT NULL,
  `status` varchar(24) NOT NULL DEFAULT 'active',
  `active_token` varchar(16) DEFAULT 'active',
  `valid_from` date NOT NULL,
  `valid_to` date NOT NULL,
  `activated_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `ended_at` datetime DEFAULT NULL,
  `ended_reason` varchar(1000) DEFAULT NULL,
  `ended_by_trainer_id` int(11) DEFAULT NULL,
  `terms_snapshot_json` longtext DEFAULT NULL,
  `terms_accepted_at` datetime DEFAULT NULL,
  `terms_accepted_by_account_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_club_program_enrollment_order_item` (`source_order_item_id`),
  UNIQUE KEY `uq_club_program_enrollment_active_person` (`offer_id`,`sportovec_id`,`active_token`),
  KEY `idx_club_program_enrollment_person` (`sportovec_id`,`status`,`valid_to`),
  KEY `fk_club_program_enrollment_account` (`account_id`),
  KEY `idx_club_program_enrollment_order_status` (`source_order_item_id`,`status`),
  KEY `fk_club_program_enrollment_ender` (`ended_by_trainer_id`),
  KEY `idx_club_program_enrollment_offer` (`offer_id`),
  KEY `fk_program_enrollment_terms_account` (`terms_accepted_by_account_id`),
  CONSTRAINT `fk_club_program_enrollment_account` FOREIGN KEY (`account_id`) REFERENCES `verejni_uzivatele` (`id`),
  CONSTRAINT `fk_club_program_enrollment_ender` FOREIGN KEY (`ended_by_trainer_id`) REFERENCES `treneri` (`id`),
  CONSTRAINT `fk_club_program_enrollment_offer` FOREIGN KEY (`offer_id`) REFERENCES `club_program_offers` (`id`),
  CONSTRAINT `fk_club_program_enrollment_order_item` FOREIGN KEY (`source_order_item_id`) REFERENCES `shop_order_items` (`id`),
  CONSTRAINT `fk_club_program_enrollment_person` FOREIGN KEY (`sportovec_id`) REFERENCES `sportovci` (`id`),
  CONSTRAINT `fk_program_enrollment_terms_account` FOREIGN KEY (`terms_accepted_by_account_id`) REFERENCES `verejni_uzivatele` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `club_program_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `program_id` bigint(20) unsigned NOT NULL,
  `offer_id` bigint(20) unsigned DEFAULT NULL,
  `actor_type` varchar(24) NOT NULL,
  `actor_id` int(11) NOT NULL,
  `action` varchar(48) NOT NULL,
  `before_json` longtext DEFAULT NULL,
  `after_json` longtext NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_club_program_audit_program` (`program_id`,`id`),
  KEY `idx_club_program_audit_offer` (`offer_id`,`id`),
  CONSTRAINT `fk_club_program_audit_offer` FOREIGN KEY (`offer_id`) REFERENCES `club_program_offers` (`id`),
  CONSTRAINT `fk_club_program_audit_program` FOREIGN KEY (`program_id`) REFERENCES `club_programs` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `club_program_offers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `program_id` bigint(20) unsigned NOT NULL,
  `season_id` bigint(20) unsigned NOT NULL,
  `team_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  `variant_id` bigint(20) unsigned NOT NULL,
  `code` varchar(64) NOT NULL,
  `name` varchar(180) NOT NULL,
  `starts_on` date NOT NULL,
  `ends_on` date NOT NULL,
  `sales_open_at` datetime DEFAULT NULL,
  `sales_close_at` datetime DEFAULT NULL,
  `capacity` int(10) unsigned DEFAULT NULL,
  `birth_year_from` smallint(6) DEFAULT NULL,
  `birth_year_to` smallint(6) DEFAULT NULL,
  `status` varchar(24) NOT NULL DEFAULT 'draft',
  `created_by_trainer_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_club_program_offer_code` (`code`),
  UNIQUE KEY `uq_club_program_offer_variant` (`variant_id`),
  KEY `idx_club_program_offer_program_dates` (`program_id`,`starts_on`,`ends_on`),
  KEY `idx_club_program_offer_status_sales` (`status`,`sales_open_at`,`sales_close_at`),
  KEY `fk_club_program_offer_season` (`season_id`),
  KEY `fk_club_program_offer_team` (`team_id`),
  KEY `fk_club_program_offer_product` (`product_id`),
  KEY `fk_club_program_offer_creator` (`created_by_trainer_id`),
  CONSTRAINT `fk_club_program_offer_creator` FOREIGN KEY (`created_by_trainer_id`) REFERENCES `treneri` (`id`),
  CONSTRAINT `fk_club_program_offer_product` FOREIGN KEY (`product_id`) REFERENCES `shop_products` (`id`),
  CONSTRAINT `fk_club_program_offer_program` FOREIGN KEY (`program_id`) REFERENCES `club_programs` (`id`),
  CONSTRAINT `fk_club_program_offer_season` FOREIGN KEY (`season_id`) REFERENCES `club_seasons` (`id`),
  CONSTRAINT `fk_club_program_offer_team` FOREIGN KEY (`team_id`) REFERENCES `club_teams` (`id`),
  CONSTRAINT `fk_club_program_offer_variant` FOREIGN KEY (`variant_id`) REFERENCES `shop_variants` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `club_programs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(48) NOT NULL,
  `name` varchar(160) NOT NULL,
  `description` text DEFAULT NULL,
  `status` varchar(24) NOT NULL DEFAULT 'active',
  `created_by_trainer_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_club_program_code` (`code`),
  KEY `idx_club_program_status_name` (`status`,`name`),
  KEY `fk_club_program_creator` (`created_by_trainer_id`),
  CONSTRAINT `fk_club_program_creator` FOREIGN KEY (`created_by_trainer_id`) REFERENCES `treneri` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `club_roster_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `team_id` bigint(20) unsigned NOT NULL,
  `roster_member_id` bigint(20) unsigned DEFAULT NULL,
  `actor_trainer_id` int(11) NOT NULL,
  `action` varchar(32) NOT NULL,
  `before_json` longtext DEFAULT NULL,
  `after_json` longtext NOT NULL,
  `note` varchar(1000) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_club_roster_event_team` (`team_id`,`id`),
  KEY `fk_club_roster_event_member` (`roster_member_id`),
  KEY `fk_club_roster_event_actor` (`actor_trainer_id`),
  CONSTRAINT `fk_club_roster_event_actor` FOREIGN KEY (`actor_trainer_id`) REFERENCES `treneri` (`id`),
  CONSTRAINT `fk_club_roster_event_member` FOREIGN KEY (`roster_member_id`) REFERENCES `club_roster_members` (`id`),
  CONSTRAINT `fk_club_roster_event_team` FOREIGN KEY (`team_id`) REFERENCES `club_teams` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `club_roster_members` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `team_id` bigint(20) unsigned NOT NULL,
  `sportovec_id` int(11) NOT NULL,
  `status` varchar(24) NOT NULL DEFAULT 'active',
  `source` varchar(24) NOT NULL,
  `kis_external_id_snapshot` varchar(80) DEFAULT NULL,
  `valid_from` date NOT NULL,
  `valid_to` date DEFAULT NULL,
  `created_by_trainer_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_club_roster_team_person` (`team_id`,`sportovec_id`),
  KEY `idx_club_roster_team_status` (`team_id`,`status`,`id`),
  KEY `idx_club_roster_person_status` (`sportovec_id`,`status`,`id`),
  KEY `fk_club_roster_creator` (`created_by_trainer_id`),
  CONSTRAINT `fk_club_roster_creator` FOREIGN KEY (`created_by_trainer_id`) REFERENCES `treneri` (`id`),
  CONSTRAINT `fk_club_roster_person` FOREIGN KEY (`sportovec_id`) REFERENCES `sportovci` (`id`),
  CONSTRAINT `fk_club_roster_team` FOREIGN KEY (`team_id`) REFERENCES `club_teams` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `club_roster_rollover_exception_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `exception_id` bigint(20) unsigned NOT NULL,
  `actor_trainer_id` int(11) NOT NULL,
  `before_json` longtext DEFAULT NULL,
  `after_json` longtext NOT NULL,
  `reason` varchar(1000) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_rollover_exception_event` (`exception_id`),
  KEY `fk_rollover_exception_event_actor` (`actor_trainer_id`),
  CONSTRAINT `fk_rollover_exception_event` FOREIGN KEY (`exception_id`) REFERENCES `club_roster_rollover_exceptions` (`id`),
  CONSTRAINT `fk_rollover_exception_event_actor` FOREIGN KEY (`actor_trainer_id`) REFERENCES `treneri` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `club_roster_rollover_exceptions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `source_team_id` bigint(20) unsigned NOT NULL,
  `target_season_id` bigint(20) unsigned NOT NULL,
  `sportovec_id` int(11) NOT NULL,
  `exception_action` varchar(24) NOT NULL,
  `target_team_id` bigint(20) unsigned DEFAULT NULL,
  `reason` varchar(1000) NOT NULL,
  `created_by_trainer_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_roster_rollover_exception` (`source_team_id`,`target_season_id`,`sportovec_id`),
  KEY `fk_rollover_exception_season` (`target_season_id`),
  KEY `fk_rollover_exception_person` (`sportovec_id`),
  KEY `fk_rollover_exception_target` (`target_team_id`),
  KEY `fk_rollover_exception_actor` (`created_by_trainer_id`),
  CONSTRAINT `fk_rollover_exception_actor` FOREIGN KEY (`created_by_trainer_id`) REFERENCES `treneri` (`id`),
  CONSTRAINT `fk_rollover_exception_person` FOREIGN KEY (`sportovec_id`) REFERENCES `sportovci` (`id`),
  CONSTRAINT `fk_rollover_exception_season` FOREIGN KEY (`target_season_id`) REFERENCES `club_seasons` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rollover_exception_source` FOREIGN KEY (`source_team_id`) REFERENCES `club_teams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rollover_exception_target` FOREIGN KEY (`target_team_id`) REFERENCES `club_teams` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `club_roster_rollover_run_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `run_id` bigint(20) unsigned NOT NULL,
  `sportovec_id` int(11) NOT NULL,
  `source_member_id` bigint(20) unsigned NOT NULL,
  `target_team_id` bigint(20) unsigned DEFAULT NULL,
  `target_member_id` bigint(20) unsigned DEFAULT NULL,
  `action` varchar(32) NOT NULL,
  `before_json` longtext NOT NULL,
  `after_json` longtext NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_roster_rollover_run_person` (`run_id`,`sportovec_id`),
  KEY `fk_rollover_item_person` (`sportovec_id`),
  KEY `fk_rollover_item_source_member` (`source_member_id`),
  KEY `fk_rollover_item_target` (`target_team_id`),
  KEY `fk_rollover_item_target_member` (`target_member_id`),
  CONSTRAINT `fk_rollover_item_person` FOREIGN KEY (`sportovec_id`) REFERENCES `sportovci` (`id`),
  CONSTRAINT `fk_rollover_item_run` FOREIGN KEY (`run_id`) REFERENCES `club_roster_rollover_runs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rollover_item_source_member` FOREIGN KEY (`source_member_id`) REFERENCES `club_roster_members` (`id`),
  CONSTRAINT `fk_rollover_item_target` FOREIGN KEY (`target_team_id`) REFERENCES `club_teams` (`id`),
  CONSTRAINT `fk_rollover_item_target_member` FOREIGN KEY (`target_member_id`) REFERENCES `club_roster_members` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `club_roster_rollover_runs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `source_team_id` bigint(20) unsigned NOT NULL,
  `target_season_id` bigint(20) unsigned NOT NULL,
  `preview_fingerprint` char(64) NOT NULL,
  `actor_trainer_id` int(11) NOT NULL,
  `reason` varchar(1000) NOT NULL,
  `moved_count` int(10) unsigned NOT NULL,
  `skipped_count` int(10) unsigned NOT NULL,
  `result_json` longtext NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_roster_rollover_fingerprint` (`source_team_id`,`target_season_id`,`preview_fingerprint`),
  KEY `fk_rollover_run_season` (`target_season_id`),
  KEY `fk_rollover_run_actor` (`actor_trainer_id`),
  CONSTRAINT `fk_rollover_run_actor` FOREIGN KEY (`actor_trainer_id`) REFERENCES `treneri` (`id`),
  CONSTRAINT `fk_rollover_run_season` FOREIGN KEY (`target_season_id`) REFERENCES `club_seasons` (`id`),
  CONSTRAINT `fk_rollover_run_source` FOREIGN KEY (`source_team_id`) REFERENCES `club_teams` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `club_roster_structure_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `subject_type` varchar(24) NOT NULL,
  `subject_id` bigint(20) unsigned NOT NULL,
  `actor_trainer_id` int(11) NOT NULL,
  `action` varchar(48) NOT NULL,
  `reason` varchar(1000) NOT NULL,
  `before_json` longtext NOT NULL,
  `after_json` longtext NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_roster_structure_subject` (`subject_type`,`subject_id`,`id`),
  KEY `fk_roster_structure_actor` (`actor_trainer_id`),
  CONSTRAINT `fk_roster_structure_actor` FOREIGN KEY (`actor_trainer_id`) REFERENCES `treneri` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `club_seasons` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(32) NOT NULL,
  `name` varchar(120) NOT NULL,
  `season_type` varchar(24) NOT NULL DEFAULT 'calendar_year',
  `starts_on` date NOT NULL,
  `ends_on` date NOT NULL,
  `status` varchar(24) NOT NULL DEFAULT 'draft',
  `created_by_trainer_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_club_season_code` (`code`),
  KEY `idx_club_season_status_dates` (`status`,`starts_on`,`ends_on`),
  KEY `fk_club_season_creator` (`created_by_trainer_id`),
  CONSTRAINT `fk_club_season_creator` FOREIGN KEY (`created_by_trainer_id`) REFERENCES `treneri` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `club_team_series` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(48) NOT NULL,
  `name` varchar(160) NOT NULL,
  `series_type` varchar(24) NOT NULL,
  `season_type` varchar(24) NOT NULL,
  `rollover_policy` varchar(32) NOT NULL,
  `next_series_id` bigint(20) unsigned DEFAULT NULL,
  `age_from_years` smallint(5) unsigned DEFAULT NULL,
  `age_to_years` smallint(5) unsigned DEFAULT NULL,
  `birth_year_from` smallint(5) unsigned DEFAULT NULL,
  `birth_year_to` smallint(5) unsigned DEFAULT NULL,
  `status` varchar(24) NOT NULL DEFAULT 'active',
  `created_by_trainer_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_club_team_series_code` (`code`),
  KEY `idx_club_team_series_policy` (`season_type`,`rollover_policy`,`status`),
  KEY `idx_club_team_series_next` (`next_series_id`),
  KEY `fk_club_team_series_creator` (`created_by_trainer_id`),
  CONSTRAINT `fk_club_team_series_creator` FOREIGN KEY (`created_by_trainer_id`) REFERENCES `treneri` (`id`),
  CONSTRAINT `fk_club_team_series_next` FOREIGN KEY (`next_series_id`) REFERENCES `club_team_series` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `club_teams` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `season_id` bigint(20) unsigned NOT NULL,
  `series_id` bigint(20) unsigned DEFAULT NULL,
  `code` varchar(48) NOT NULL,
  `name` varchar(160) NOT NULL,
  `discipline` varchar(120) NOT NULL,
  `age_label` varchar(120) NOT NULL,
  `status` varchar(24) NOT NULL DEFAULT 'active',
  `created_by_trainer_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_club_team_season_code` (`season_id`,`code`),
  KEY `idx_club_team_season_status` (`season_id`,`status`,`name`),
  KEY `fk_club_team_creator` (`created_by_trainer_id`),
  KEY `idx_club_team_series_season` (`series_id`,`season_id`),
  CONSTRAINT `fk_club_team_creator` FOREIGN KEY (`created_by_trainer_id`) REFERENCES `treneri` (`id`),
  CONSTRAINT `fk_club_team_season` FOREIGN KEY (`season_id`) REFERENCES `club_seasons` (`id`),
  CONSTRAINT `fk_club_team_series` FOREIGN KEY (`series_id`) REFERENCES `club_team_series` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cviky` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nazev` varchar(150) NOT NULL,
  `popis` text DEFAULT NULL,
  `poradi` int(11) NOT NULL DEFAULT 0,
  `aktivni` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cviky_nazev` (`nazev`),
  KEY `idx_cviky_active_order` (`aktivni`,`poradi`,`nazev`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dalsi_cinnosti` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `trener_id` int(11) NOT NULL,
  `nazev` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `delka` decimal(5,2) DEFAULT 0.00,
  `poznamka` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `datum` date NOT NULL,
  PRIMARY KEY (`id`),
  KEY `trener_id` (`trener_id`),
  CONSTRAINT `dalsi_cinnosti_ibfk_1` FOREIGN KEY (`trener_id`) REFERENCES `treneri` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `email_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sportovec_id` int(11) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `predmet` varchar(500) DEFAULT NULL,
  `stav` enum('odeslano','chyba','bez_emailu') NOT NULL,
  `odeslano_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `trener_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `odeslano_at` (`odeslano_at`),
  KEY `sportovec_id` (`sportovec_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `evidence_schema_migrations` (
  `id` varchar(190) NOT NULL,
  `checksum` char(64) NOT NULL,
  `applied_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `execution_ms` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `family_calendar_feed_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `feed_id` bigint(20) unsigned NOT NULL,
  `actor_account_id` int(11) NOT NULL,
  `action` varchar(24) NOT NULL,
  `token_hint_snapshot` char(8) NOT NULL,
  `note` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_family_calendar_event` (`feed_id`,`id`),
  KEY `fk_family_calendar_event_actor` (`actor_account_id`),
  CONSTRAINT `fk_family_calendar_event_actor` FOREIGN KEY (`actor_account_id`) REFERENCES `verejni_uzivatele` (`id`),
  CONSTRAINT `fk_family_calendar_event_feed` FOREIGN KEY (`feed_id`) REFERENCES `family_calendar_feeds` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `family_calendar_feeds` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `account_id` int(11) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `token_hint` char(8) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `rotated_at` datetime NOT NULL,
  `revoked_at` datetime DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_family_calendar_account` (`account_id`),
  UNIQUE KEY `uq_family_calendar_token` (`token_hash`),
  CONSTRAINT `fk_family_calendar_account` FOREIGN KEY (`account_id`) REFERENCES `verejni_uzivatele` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `family_weekly_summaries` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `account_id` int(11) NOT NULL,
  `period_from` date NOT NULL,
  `period_to` date NOT NULL,
  `recipient_email` varchar(254) NOT NULL,
  `recipient_name` varchar(255) NOT NULL,
  `subject_plain` varchar(255) NOT NULL,
  `body_plain` text NOT NULL,
  `item_count` smallint(5) unsigned NOT NULL DEFAULT 0,
  `status` enum('pending','processing','sent','failed','cancelled') NOT NULL DEFAULT 'pending',
  `attempts` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `available_at` datetime NOT NULL DEFAULT current_timestamp(),
  `claimed_at` datetime DEFAULT NULL,
  `claim_token` char(32) DEFAULT NULL,
  `sent_at` datetime DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `last_error` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_family_weekly_summary` (`account_id`,`period_from`),
  KEY `idx_family_weekly_summary_queue` (`status`,`available_at`,`id`),
  CONSTRAINT `fk_family_weekly_summary_account` FOREIGN KEY (`account_id`) REFERENCES `verejni_uzivatele` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `family_weekly_summary_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `summary_id` bigint(20) unsigned DEFAULT NULL,
  `account_id` int(11) NOT NULL,
  `actor_type` varchar(24) NOT NULL DEFAULT 'system',
  `actor_id` bigint(20) DEFAULT NULL,
  `action` varchar(32) NOT NULL,
  `from_status` varchar(24) DEFAULT NULL,
  `to_status` varchar(24) DEFAULT NULL,
  `note` varchar(500) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_family_weekly_summary_event` (`summary_id`,`id`),
  KEY `idx_family_weekly_account_event` (`account_id`,`id`),
  CONSTRAINT `fk_family_weekly_event_account` FOREIGN KEY (`account_id`) REFERENCES `verejni_uzivatele` (`id`),
  CONSTRAINT `fk_family_weekly_event_summary` FOREIGN KEY (`summary_id`) REFERENCES `family_weekly_summaries` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `family_weekly_summary_preferences` (
  `account_id` int(11) NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`account_id`),
  CONSTRAINT `fk_weekly_summary_preference_account` FOREIGN KEY (`account_id`) REFERENCES `verejni_uzivatele` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fio_account_movements` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `fio_movement_id` varchar(80) NOT NULL,
  `booked_on` date NOT NULL,
  `amount_minor` bigint(20) NOT NULL,
  `currency` char(3) NOT NULL,
  `variable_symbol` varchar(10) DEFAULT NULL,
  `movement_type` varchar(64) NOT NULL,
  `raw_sha256` char(64) NOT NULL,
  `match_status` varchar(32) NOT NULL,
  `candidate_payment_id` bigint(20) unsigned DEFAULT NULL,
  `candidate_order_id` bigint(20) unsigned DEFAULT NULL,
  `match_reason` varchar(255) NOT NULL,
  `import_run_id` bigint(20) unsigned NOT NULL,
  `first_seen_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fio_movement_id` (`fio_movement_id`),
  KEY `idx_fio_movement_match` (`match_status`,`booked_on`,`id`),
  KEY `idx_fio_movement_vs` (`variable_symbol`,`booked_on`,`id`),
  KEY `fk_fio_movement_run` (`import_run_id`),
  KEY `fk_fio_movement_payment` (`candidate_payment_id`),
  KEY `fk_fio_movement_order` (`candidate_order_id`),
  CONSTRAINT `fk_fio_movement_order` FOREIGN KEY (`candidate_order_id`) REFERENCES `shop_orders` (`id`),
  CONSTRAINT `fk_fio_movement_payment` FOREIGN KEY (`candidate_payment_id`) REFERENCES `payments` (`id`),
  CONSTRAINT `fk_fio_movement_run` FOREIGN KEY (`import_run_id`) REFERENCES `fio_import_runs` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fio_import_runs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `period_from` date NOT NULL,
  `period_to` date NOT NULL,
  `source_account_iban` varchar(34) DEFAULT NULL,
  `status` varchar(16) NOT NULL,
  `fetched_count` int(10) unsigned NOT NULL DEFAULT 0,
  `inserted_count` int(10) unsigned NOT NULL DEFAULT 0,
  `duplicate_count` int(10) unsigned NOT NULL DEFAULT 0,
  `proposed_count` int(10) unsigned NOT NULL DEFAULT 0,
  `review_count` int(10) unsigned NOT NULL DEFAULT 0,
  `ignored_count` int(10) unsigned NOT NULL DEFAULT 0,
  `error_code` varchar(64) DEFAULT NULL,
  `started_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `finished_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_fio_runs_started` (`started_at`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fotky` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `jmeno` varchar(100) NOT NULL,
  `kategorie` enum('žáci','žákyně','kadeti','kadetky','junioři','juniorky','trenéři') NOT NULL,
  `obrazek` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `gs_kategorie` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nazev` varchar(120) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_gs_kategorie_nazev` (`nazev`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `gs_link_targets` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `link_id` int(10) unsigned NOT NULL,
  `target_type` varchar(30) NOT NULL,
  `target_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_gs_link_target` (`link_id`,`target_type`,`target_id`),
  KEY `idx_gs_link_target_lookup` (`target_type`,`target_id`,`link_id`),
  CONSTRAINT `fk_gs_link_target_link` FOREIGN KEY (`link_id`) REFERENCES `gs_linky` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `gs_linky` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `kategorie_id` int(10) unsigned NOT NULL,
  `url` text NOT NULL,
  `nazev` varchar(255) NOT NULL,
  `popis` text DEFAULT NULL,
  `datum` date NOT NULL,
  `viditelnost` enum('treneri','verejny','cilene') NOT NULL DEFAULT 'treneri',
  `vlozil_trener_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_gs_linky_category_created` (`kategorie_id`,`created_at`),
  KEY `idx_gs_linky_trainer` (`vlozil_trener_id`),
  CONSTRAINT `fk_gs_linky_category` FOREIGN KEY (`kategorie_id`) REFERENCES `gs_kategorie` (`id`),
  CONSTRAINT `fk_gs_linky_trainer` FOREIGN KEY (`vlozil_trener_id`) REFERENCES `treneri` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `individualni_lekce` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `trener_id` int(11) NOT NULL,
  `sportoviste_id` int(11) NOT NULL,
  `datum` date NOT NULL,
  `cas_od` time NOT NULL,
  `cas_do` time NOT NULL,
  `slot_delka_min` smallint(6) NOT NULL DEFAULT 60,
  `typ` enum('zelena','zluta') NOT NULL DEFAULT 'zelena',
  `nazev` varchar(200) NOT NULL DEFAULT '',
  `popis` text DEFAULT NULL,
  `cena_kc` decimal(8,2) NOT NULL DEFAULT 0.00,
  `max_osob` int(11) NOT NULL DEFAULT 1,
  `vyjimka_3_dny` tinyint(1) NOT NULL DEFAULT 0,
  `stav` enum('aktivni','zrusena') NOT NULL DEFAULT 'aktivni',
  `vytvoreno` timestamp NOT NULL DEFAULT current_timestamp(),
  `public_exclusive_booking` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_datum` (`datum`),
  KEY `idx_trener` (`trener_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `jidlo_custom_listky` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nazev` varchar(255) NOT NULL,
  `datum` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `jidlo_custom_listky_produkty` (
  `list_id` int(11) NOT NULL,
  `produkt_id` int(11) NOT NULL,
  PRIMARY KEY (`list_id`,`produkt_id`),
  KEY `produkt_id` (`produkt_id`),
  CONSTRAINT `jidlo_custom_listky_produkty_ibfk_1` FOREIGN KEY (`list_id`) REFERENCES `jidlo_custom_listky` (`id`) ON DELETE CASCADE,
  CONSTRAINT `jidlo_custom_listky_produkty_ibfk_2` FOREIGN KEY (`produkt_id`) REFERENCES `jidlo_produkty` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `jidlo_kategorie` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nazev` varchar(255) NOT NULL,
  `poradi` int(11) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `jidlo_produkty` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `kategorie_id` int(11) NOT NULL,
  `nazev` varchar(255) NOT NULL,
  `popis` text DEFAULT NULL,
  `cena` decimal(10,2) DEFAULT NULL,
  `obrazek` varchar(255) DEFAULT NULL,
  `poradi` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `kategorie_id` (`kategorie_id`),
  CONSTRAINT `jidlo_produkty_ibfk_1` FOREIGN KEY (`kategorie_id`) REFERENCES `jidlo_kategorie` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `kis_import_charge_promotion_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `promotion_id` bigint(20) unsigned NOT NULL,
  `action` varchar(24) NOT NULL,
  `actor_id` int(11) NOT NULL,
  `reason` varchar(1000) NOT NULL,
  `snapshot_json` longtext NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_kis_charge_promotion_event` (`promotion_id`,`id`),
  CONSTRAINT `fk_kis_charge_promotion_event_promotion` FOREIGN KEY (`promotion_id`) REFERENCES `kis_import_charge_promotions` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `kis_import_charge_promotion_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `promotion_id` bigint(20) unsigned NOT NULL,
  `staged_payment_row_id` bigint(20) unsigned NOT NULL,
  `source_ref` varchar(32) NOT NULL,
  `public_code` varchar(32) NOT NULL,
  `variable_symbol` varchar(10) DEFAULT NULL,
  `snapshot_fingerprint` char(64) NOT NULL,
  `charge_id` bigint(20) unsigned DEFAULT NULL,
  `payment_id` bigint(20) unsigned DEFAULT NULL,
  `status` varchar(16) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_kis_charge_promotion_item` (`promotion_id`,`staged_payment_row_id`),
  UNIQUE KEY `uq_kis_charge_promotion_ref` (`promotion_id`,`source_ref`),
  KEY `idx_kis_charge_promotion_item_status` (`promotion_id`,`status`,`id`),
  KEY `fk_kis_charge_promotion_item_staging` (`staged_payment_row_id`),
  CONSTRAINT `fk_kis_charge_promotion_item_promotion` FOREIGN KEY (`promotion_id`) REFERENCES `kis_import_charge_promotions` (`id`),
  CONSTRAINT `fk_kis_charge_promotion_item_staging` FOREIGN KEY (`staged_payment_row_id`) REFERENCES `kis_import_payment_rows` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `kis_import_charge_promotions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `import_run_id` int(11) NOT NULL,
  `source_fingerprint` char(64) NOT NULL,
  `contract_version` varchar(32) NOT NULL,
  `status` varchar(16) NOT NULL,
  `item_count` int(10) unsigned NOT NULL,
  `payment_count` int(10) unsigned NOT NULL,
  `apply_count` int(10) unsigned NOT NULL DEFAULT 1,
  `applied_by` int(11) NOT NULL,
  `apply_reason` varchar(1000) NOT NULL,
  `applied_at` datetime NOT NULL DEFAULT current_timestamp(),
  `rolled_back_by` int(11) DEFAULT NULL,
  `rollback_reason` varchar(1000) DEFAULT NULL,
  `rolled_back_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_kis_charge_promotion_run` (`import_run_id`),
  KEY `idx_kis_charge_promotion_status` (`status`,`id`),
  CONSTRAINT `fk_kis_charge_promotion_run` FOREIGN KEY (`import_run_id`) REFERENCES `kis_import_runs` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `kis_import_matches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `run_id` int(11) NOT NULL,
  `row_id` int(11) NOT NULL,
  `sportovec_id` int(11) DEFAULT NULL,
  `match_status` enum('new','matched','ambiguous','conflict','ignored') NOT NULL,
  `confidence` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `reason` varchar(255) DEFAULT NULL,
  `candidate_json` longtext DEFAULT NULL,
  `resolved_by` int(11) DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `resolved_action` enum('create','update','link','ignore','manual') DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_run_id` (`run_id`),
  KEY `idx_row_id` (`row_id`),
  KEY `idx_sportovec_id` (`sportovec_id`),
  KEY `idx_match_status` (`match_status`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `kis_import_payment_rows` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `run_id` int(11) NOT NULL,
  `import_row_id` int(11) NOT NULL,
  `source_ref` varchar(32) NOT NULL,
  `payment_external_id` varchar(80) NOT NULL,
  `status_snapshot` varchar(24) NOT NULL,
  `amount_minor` bigint(20) unsigned NOT NULL,
  `outstanding_minor` bigint(20) unsigned NOT NULL,
  `currency` char(3) NOT NULL,
  `due_on` date DEFAULT NULL,
  `paid_on` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_kis_import_payment_external` (`run_id`,`payment_external_id`),
  UNIQUE KEY `uq_kis_import_payment_ref` (`run_id`,`source_ref`),
  KEY `idx_kis_import_payment_row` (`import_row_id`,`id`),
  CONSTRAINT `fk_kis_import_payment_person` FOREIGN KEY (`import_row_id`) REFERENCES `kis_import_rows` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_kis_import_payment_run` FOREIGN KEY (`run_id`) REFERENCES `kis_import_runs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `kis_import_rows` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `run_id` int(11) NOT NULL,
  `person_key` varchar(180) NOT NULL,
  `jmeno` varchar(100) NOT NULL,
  `prijmeni` varchar(160) NOT NULL,
  `narozeni` date DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `uciid` varchar(80) DEFAULT NULL,
  `oddil` varchar(160) DEFAULT NULL,
  `kis_aktivni` tinyint(1) NOT NULL DEFAULT 0,
  `kis_platebne_aktivni` tinyint(1) NOT NULL DEFAULT 0,
  `kis_neuhrazeno` decimal(10,2) NOT NULL DEFAULT 0.00,
  `kis_posledni_uhrada` date DEFAULT NULL,
  `kis_soupisky` text DEFAULT NULL,
  `raw_json` longtext DEFAULT NULL,
  `kis_external_id` varchar(80) DEFAULT NULL,
  `kis_roster_count` int(10) unsigned NOT NULL DEFAULT 0,
  `kis_payment_paid_count` int(10) unsigned NOT NULL DEFAULT 0,
  `kis_payment_open_count` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_run_id` (`run_id`),
  KEY `idx_person_key` (`person_key`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `kis_import_runs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `status` enum('preview','applied','failed','cancelled') NOT NULL DEFAULT 'preview',
  `source_users` varchar(255) DEFAULT NULL,
  `source_payments` varchar(255) DEFAULT NULL,
  `source_rosters` varchar(255) DEFAULT NULL,
  `stats_json` longtext DEFAULT NULL,
  `warnings_json` longtext DEFAULT NULL,
  `applied_at` datetime DEFAULT NULL,
  `note` text DEFAULT NULL,
  `source_manifest_json` longtext DEFAULT NULL,
  `preview_contract_version` varchar(64) DEFAULT NULL,
  `preview_fingerprint` char(64) DEFAULT NULL,
  `preview_report_json` longtext DEFAULT NULL,
  `classified_rows` int(10) unsigned NOT NULL DEFAULT 0,
  `blocker_rows` int(10) unsigned NOT NULL DEFAULT 0,
  `field_contract_version` varchar(64) DEFAULT NULL,
  `field_contract_fingerprint` char(64) DEFAULT NULL,
  `field_contract_report_json` longtext DEFAULT NULL,
  `field_contract_blockers` int(10) unsigned NOT NULL DEFAULT 0,
  `parity_contract_version` varchar(64) DEFAULT NULL,
  `parity_fingerprint` char(64) DEFAULT NULL,
  `parity_report_json` longtext DEFAULT NULL,
  `parity_blockers` int(10) unsigned NOT NULL DEFAULT 0,
  `parity_generated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_kis_preview_fingerprint` (`preview_fingerprint`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `kis_import_sandbox_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `promotion_id` bigint(20) unsigned NOT NULL,
  `action` varchar(24) NOT NULL,
  `actor_id` int(11) NOT NULL,
  `reason` text NOT NULL,
  `snapshot_json` longtext NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_kis_sandbox_event_promotion` (`promotion_id`,`created_at`),
  CONSTRAINT `fk_kis_sandbox_event_promotion` FOREIGN KEY (`promotion_id`) REFERENCES `kis_import_sandbox_promotions` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `kis_import_sandbox_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `promotion_id` bigint(20) unsigned NOT NULL,
  `source_ref` varchar(128) NOT NULL,
  `action` varchar(32) NOT NULL,
  `target_ref` varchar(128) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_kis_sandbox_item` (`promotion_id`,`source_ref`),
  KEY `idx_kis_sandbox_item_active` (`promotion_id`,`active`),
  CONSTRAINT `fk_kis_sandbox_item_promotion` FOREIGN KEY (`promotion_id`) REFERENCES `kis_import_sandbox_promotions` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `kis_import_sandbox_promotions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `import_run_id` int(11) NOT NULL,
  `preview_fingerprint` char(64) NOT NULL,
  `status` varchar(16) NOT NULL,
  `item_count` int(10) unsigned NOT NULL,
  `apply_count` int(10) unsigned NOT NULL DEFAULT 1,
  `applied_by` int(11) NOT NULL,
  `apply_reason` text NOT NULL,
  `applied_at` datetime NOT NULL DEFAULT current_timestamp(),
  `rolled_back_by` int(11) DEFAULT NULL,
  `rollback_reason` text DEFAULT NULL,
  `rolled_back_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_kis_sandbox_preview` (`import_run_id`,`preview_fingerprint`),
  KEY `idx_kis_sandbox_status` (`status`),
  KEY `idx_kis_sandbox_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `kis_import_source_artifacts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `source_kind` varchar(24) NOT NULL,
  `contract_version` varchar(64) NOT NULL,
  `sha256` char(64) NOT NULL,
  `byte_size` bigint(20) unsigned NOT NULL,
  `original_filename` varchar(255) NOT NULL,
  `storage_key` varchar(96) NOT NULL,
  `archived_by` int(11) DEFAULT NULL,
  `archived_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_kis_source_artifact` (`source_kind`,`contract_version`,`sha256`),
  UNIQUE KEY `uq_kis_source_storage` (`storage_key`),
  KEY `idx_kis_source_archived_at` (`archived_at`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `member_charge_reminder_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `reminder_id` bigint(20) unsigned DEFAULT NULL,
  `account_id` int(11) NOT NULL,
  `actor_type` varchar(24) NOT NULL DEFAULT 'system',
  `actor_id` bigint(20) DEFAULT NULL,
  `action` varchar(32) NOT NULL,
  `from_status` varchar(24) DEFAULT NULL,
  `to_status` varchar(24) DEFAULT NULL,
  `note` varchar(500) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_member_charge_reminder_event` (`reminder_id`,`id`),
  KEY `idx_member_charge_reminder_account_event` (`account_id`,`id`),
  CONSTRAINT `fk_charge_reminder_event_account` FOREIGN KEY (`account_id`) REFERENCES `verejni_uzivatele` (`id`),
  CONSTRAINT `fk_charge_reminder_event_reminder` FOREIGN KEY (`reminder_id`) REFERENCES `member_charge_reminders` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `member_charge_reminder_preferences` (
  `account_id` int(11) NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 0,
  `days_before` tinyint(3) unsigned NOT NULL DEFAULT 7,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`account_id`),
  CONSTRAINT `fk_charge_reminder_preference_account` FOREIGN KEY (`account_id`) REFERENCES `verejni_uzivatele` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `member_charge_reminders` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `charge_id` bigint(20) unsigned NOT NULL,
  `account_id` int(11) NOT NULL,
  `reminder_type` varchar(32) NOT NULL DEFAULT 'due_soon',
  `recipient_email` varchar(254) NOT NULL,
  `recipient_name` varchar(255) NOT NULL,
  `subject_plain` varchar(255) NOT NULL,
  `body_plain` text NOT NULL,
  `status` enum('pending','processing','sent','failed','cancelled') NOT NULL DEFAULT 'pending',
  `attempts` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `available_at` datetime NOT NULL DEFAULT current_timestamp(),
  `claimed_at` datetime DEFAULT NULL,
  `claim_token` char(32) DEFAULT NULL,
  `sent_at` datetime DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `last_error` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_member_charge_reminder` (`charge_id`,`account_id`,`reminder_type`),
  KEY `idx_member_charge_reminder_queue` (`status`,`available_at`,`id`),
  KEY `idx_member_charge_reminder_frequency` (`account_id`,`sent_at`,`id`),
  CONSTRAINT `fk_charge_reminder_account` FOREIGN KEY (`account_id`) REFERENCES `verejni_uzivatele` (`id`),
  CONSTRAINT `fk_charge_reminder_charge` FOREIGN KEY (`charge_id`) REFERENCES `club_member_charges` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mereni` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `trenink_id` int(11) NOT NULL,
  `sportovec_id` int(11) NOT NULL,
  `vzdalenost` varchar(100) DEFAULT NULL,
  `cas` varchar(50) DEFAULT NULL,
  `poznamka` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `trenink_id` (`trenink_id`),
  KEY `sportovec_id` (`sportovec_id`),
  CONSTRAINT `mereni_ibfk_1` FOREIGN KEY (`trenink_id`) REFERENCES `treninky` (`id`) ON DELETE CASCADE,
  CONSTRAINT `mereni_ibfk_2` FOREIGN KEY (`sportovec_id`) REFERENCES `sportovci` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mereni_zaznamy` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `typ` enum('kolo','beh','posilovna','kolo_krouzek','kolo_silnice','kolo_mtb') NOT NULL,
  `sportovec_id` int(10) unsigned DEFAULT NULL,
  `vzdalenost` decimal(10,2) DEFAULT NULL,
  `cas` varchar(50) DEFAULT NULL,
  `prevod` varchar(100) DEFAULT NULL,
  `cvik_id` int(10) unsigned DEFAULT NULL,
  `segment_id` int(10) unsigned DEFAULT NULL,
  `vaha` decimal(10,2) DEFAULT NULL,
  `opakovani` int(11) DEFAULT NULL,
  `rpe` varchar(50) DEFAULT NULL,
  `poznamka` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `contract_version` varchar(40) DEFAULT NULL,
  `distance_unit` varchar(4) DEFAULT NULL,
  `distance_meters` decimal(12,2) DEFAULT NULL,
  `duration_ms` bigint(20) unsigned DEFAULT NULL,
  `rpe_value` decimal(3,1) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_typ` (`typ`),
  KEY `idx_sportovec` (`sportovec_id`),
  KEY `idx_cvik` (`cvik_id`),
  KEY `idx_segment` (`segment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nastaveni` (
  `klic` varchar(80) NOT NULL,
  `hodnota` text NOT NULL DEFAULT '',
  `upraveno` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`klic`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `opravneni` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `klic` varchar(100) NOT NULL,
  `nazev` varchar(200) NOT NULL,
  `popis` varchar(500) DEFAULT NULL,
  `min_role` enum('trener','hlavni','admin') NOT NULL DEFAULT 'hlavni',
  `skupina` varchar(100) DEFAULT 'Ostatní',
  `poradi` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `klic` (`klic`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `osoba_citlive_pristupy` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sensitive_record_id` bigint(20) unsigned DEFAULT NULL,
  `private_file_id` bigint(20) unsigned DEFAULT NULL,
  `sportovec_id` int(11) DEFAULT NULL,
  `request_id` bigint(20) unsigned DEFAULT NULL,
  `actor_trainer_id` int(11) NOT NULL,
  `action` varchar(32) NOT NULL,
  `reason` varchar(1000) NOT NULL DEFAULT '',
  `ip_address` varchar(45) NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_osoba_citlive_access_record` (`sensitive_record_id`,`id`),
  KEY `idx_osoba_citlive_access_file` (`private_file_id`,`id`),
  KEY `idx_osoba_citlive_access_person` (`sportovec_id`,`id`),
  KEY `idx_osoba_citlive_access_request` (`request_id`,`id`),
  KEY `idx_osoba_citlive_access_actor` (`actor_trainer_id`,`id`),
  CONSTRAINT `fk_osoba_citlive_access_actor` FOREIGN KEY (`actor_trainer_id`) REFERENCES `treneri` (`id`),
  CONSTRAINT `fk_osoba_citlive_access_file` FOREIGN KEY (`private_file_id`) REFERENCES `athlete_private_files` (`id`),
  CONSTRAINT `fk_osoba_citlive_access_person` FOREIGN KEY (`sportovec_id`) REFERENCES `sportovci` (`id`),
  CONSTRAINT `fk_osoba_citlive_access_record` FOREIGN KEY (`sensitive_record_id`) REFERENCES `osoba_citlive_udaje` (`id`),
  CONSTRAINT `fk_osoba_citlive_access_request` FOREIGN KEY (`request_id`) REFERENCES `account_person_claim_requests` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `osoba_citlive_udaje` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `record_token` char(32) NOT NULL,
  `request_id` bigint(20) unsigned NOT NULL,
  `sportovec_id` int(11) DEFAULT NULL,
  `rc_ciphertext` varbinary(255) NOT NULL,
  `rc_nonce` binary(24) NOT NULL,
  `rc_key_version` varchar(32) NOT NULL,
  `rc_blind_index` binary(32) NOT NULL,
  `contract_version` varchar(64) NOT NULL DEFAULT 'person-sensitive-v1',
  `status` varchar(24) NOT NULL DEFAULT 'pending',
  `retention_reason` varchar(255) DEFAULT NULL,
  `retention_until` datetime DEFAULT NULL,
  `erased_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_osoba_citlive_token` (`record_token`),
  UNIQUE KEY `uq_osoba_citlive_request` (`request_id`),
  UNIQUE KEY `uq_osoba_citlive_blind_index` (`rc_blind_index`),
  UNIQUE KEY `uq_osoba_citlive_person` (`sportovec_id`),
  CONSTRAINT `fk_osoba_citlive_person` FOREIGN KEY (`sportovec_id`) REFERENCES `sportovci` (`id`),
  CONSTRAINT `fk_osoba_citlive_request` FOREIGN KEY (`request_id`) REFERENCES `account_person_claim_requests` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `oznameni` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nazev` varchar(255) DEFAULT NULL,
  `obsah_html` mediumtext NOT NULL,
  `datum` date NOT NULL,
  `vlozil_trener_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_datum` (`datum`),
  KEY `idx_vlozil_trener_id` (`vlozil_trener_id`),
  CONSTRAINT `fk_oznameni_trener` FOREIGN KEY (`vlozil_trener_id`) REFERENCES `treneri` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `oznameni_targets` (
  `oznameni_id` int(11) NOT NULL,
  `target_type` enum('skupina','podskupina','sportovec') NOT NULL,
  `target_id` int(11) NOT NULL,
  PRIMARY KEY (`oznameni_id`,`target_type`,`target_id`),
  KEY `idx_target` (`target_type`,`target_id`),
  CONSTRAINT `fk_oznameni_targets_oznameni` FOREIGN KEY (`oznameni_id`) REFERENCES `oznameni` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_reset_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `target_type` varchar(16) NOT NULL,
  `target_id` bigint(20) unsigned NOT NULL,
  `delivery_account_id` int(11) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `consumed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_password_reset_token_hash` (`token_hash`),
  KEY `idx_password_reset_target` (`target_type`,`target_id`,`consumed_at`,`expires_at`),
  KEY `fk_password_reset_delivery_account` (`delivery_account_id`),
  CONSTRAINT `fk_password_reset_delivery_account` FOREIGN KEY (`delivery_account_id`) REFERENCES `verejni_uzivatele` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `payable_type` varchar(32) NOT NULL,
  `payable_id` bigint(20) unsigned NOT NULL,
  `method` varchar(32) NOT NULL,
  `status` varchar(24) NOT NULL,
  `amount_minor` bigint(20) unsigned NOT NULL,
  `currency` char(3) NOT NULL,
  `variable_symbol` varchar(10) NOT NULL,
  `iban_snapshot` varchar(34) NOT NULL,
  `bic_snapshot` varchar(11) DEFAULT NULL,
  `account_label_snapshot` varchar(255) NOT NULL,
  `spd_payload` text NOT NULL,
  `due_at` datetime NOT NULL,
  `paid_at` datetime DEFAULT NULL,
  `confirmed_by_trainer_id` int(11) DEFAULT NULL,
  `confirmation_note` varchar(1000) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `refund_sent_at` datetime DEFAULT NULL,
  `refund_reference` varchar(255) DEFAULT NULL,
  `refund_confirmed_by_trainer_id` int(11) DEFAULT NULL,
  `refund_confirmation_note` varchar(1000) DEFAULT NULL,
  `payment_source` varchar(32) NOT NULL DEFAULT 'bank_transfer',
  `stripe_checkout_session_id` varchar(255) DEFAULT NULL,
  `stripe_payment_intent_id` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_payment_payable` (`payable_type`,`payable_id`),
  UNIQUE KEY `uq_payment_variable_symbol` (`variable_symbol`),
  UNIQUE KEY `uq_payment_stripe_session` (`stripe_checkout_session_id`),
  KEY `idx_payment_status_due` (`status`,`due_at`,`id`),
  KEY `fk_payment_confirmed_by` (`confirmed_by_trainer_id`),
  CONSTRAINT `fk_payment_confirmed_by` FOREIGN KEY (`confirmed_by_trainer_id`) REFERENCES `treneri` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `planovane_treninky` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `trener_id` int(11) NOT NULL,
  `skupina_id` int(11) DEFAULT NULL,
  `podskupina_id` int(11) DEFAULT NULL,
  `datum` date NOT NULL,
  `cas_od` time DEFAULT NULL,
  `cas_do` time DEFAULT NULL,
  `sportoviste_id` int(11) DEFAULT NULL,
  `rezervace_id` int(11) DEFAULT NULL,
  `trenink_id` int(11) DEFAULT NULL,
  `nazev` varchar(200) NOT NULL DEFAULT '',
  `kategorie` enum('silnice','mtb','draha','cyklokros','posilovna','atletika','cviceni','plavani') DEFAULT NULL,
  `popis` text DEFAULT NULL,
  `stav` enum('planovany','evidovany','zruseny') NOT NULL DEFAULT 'planovany',
  `vytvoreno` timestamp NOT NULL DEFAULT current_timestamp(),
  `upominka_cas` timestamp NULL DEFAULT NULL COMMENT 'Kdy byla odeslána upomínka trenérovi',
  `serie_id` int(11) DEFAULT NULL COMMENT 'ID série opakujících se tréninků (sdílené mezi instancemi)',
  `je_verejny` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `trener_id` (`trener_id`),
  KEY `skupina_id` (`skupina_id`),
  KEY `podskupina_id` (`podskupina_id`),
  KEY `sportoviste_id` (`sportoviste_id`),
  KEY `trenink_id` (`trenink_id`),
  KEY `idx_planovane_treninky_verejne` (`je_verejny`,`datum`,`stav`),
  CONSTRAINT `planovane_treninky_ibfk_1` FOREIGN KEY (`trener_id`) REFERENCES `treneri` (`id`),
  CONSTRAINT `planovane_treninky_ibfk_2` FOREIGN KEY (`skupina_id`) REFERENCES `skupiny` (`id`),
  CONSTRAINT `planovane_treninky_ibfk_3` FOREIGN KEY (`podskupina_id`) REFERENCES `podskupiny` (`id`),
  CONSTRAINT `planovane_treninky_ibfk_4` FOREIGN KEY (`sportoviste_id`) REFERENCES `sportovist` (`id`),
  CONSTRAINT `planovane_treninky_ibfk_5` FOREIGN KEY (`trenink_id`) REFERENCES `treninky` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `planovane_treninky_podskupiny` (
  `plan_id` int(11) NOT NULL,
  `podskupina_id` int(11) NOT NULL,
  PRIMARY KEY (`plan_id`,`podskupina_id`),
  KEY `idx_podskupina` (`podskupina_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `podskupiny` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hash` varchar(64) NOT NULL,
  `nazev` varchar(100) NOT NULL,
  `skupina_id` int(11) NOT NULL,
  `poradi` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `skupina_id` (`skupina_id`),
  CONSTRAINT `podskupiny_ibfk_1` FOREIGN KEY (`skupina_id`) REFERENCES `skupiny` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50003 TRIGGER `trg_podskupiny_hash` BEFORE INSERT ON `podskupiny` FOR EACH ROW BEGIN
  IF NEW.hash = '' OR NEW.hash IS NULL THEN
    SET NEW.hash = SHA2(CONCAT(NEW.id,'-',NEW.nazev),256);
  END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `public_profile_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `account_id` int(11) NOT NULL,
  `sportovec_id` int(11) NOT NULL,
  `action` varchar(32) NOT NULL,
  `payload_json` longtext NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_public_profile_event` (`account_id`,`id`),
  KEY `fk_public_profile_event_person` (`sportovec_id`),
  CONSTRAINT `fk_public_profile_event_account` FOREIGN KEY (`account_id`) REFERENCES `verejni_uzivatele` (`id`),
  CONSTRAINT `fk_public_profile_event_person` FOREIGN KEY (`sportovec_id`) REFERENCES `sportovci` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `public_profile_settings` (
  `singleton_id` tinyint(3) unsigned NOT NULL,
  `system_trainer_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`singleton_id`),
  KEY `fk_public_profile_system_trainer` (`system_trainer_id`),
  CONSTRAINT `fk_public_profile_system_trainer` FOREIGN KEY (`system_trainer_id`) REFERENCES `treneri` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `public_self_profiles` (
  `account_id` int(11) NOT NULL,
  `sportovec_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`account_id`),
  UNIQUE KEY `uq_public_self_sportovec` (`sportovec_id`),
  CONSTRAINT `fk_public_self_account` FOREIGN KEY (`account_id`) REFERENCES `verejni_uzivatele` (`id`),
  CONSTRAINT `fk_public_self_sportovec` FOREIGN KEY (`sportovec_id`) REFERENCES `sportovci` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `public_velodrome_cart_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `cart_id` bigint(20) unsigned NOT NULL,
  `lesson_id` int(11) NOT NULL,
  `beneficiary_sportovec_id` int(11) NOT NULL,
  `note` varchar(1000) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_public_velo_cart_slot_person` (`cart_id`,`lesson_id`,`beneficiary_sportovec_id`),
  KEY `idx_public_velo_cart_lesson` (`lesson_id`,`id`),
  KEY `fk_public_velo_cart_person` (`beneficiary_sportovec_id`),
  CONSTRAINT `fk_public_velo_cart_cart` FOREIGN KEY (`cart_id`) REFERENCES `shop_carts` (`id`),
  CONSTRAINT `fk_public_velo_cart_lesson` FOREIGN KEY (`lesson_id`) REFERENCES `individualni_lekce` (`id`),
  CONSTRAINT `fk_public_velo_cart_person` FOREIGN KEY (`beneficiary_sportovec_id`) REFERENCES `sportovci` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `public_velodrome_order_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint(20) unsigned NOT NULL,
  `reservation_id` int(11) NOT NULL,
  `lesson_id` int(11) NOT NULL,
  `beneficiary_sportovec_id` int(11) NOT NULL,
  `lesson_name_snapshot` varchar(255) NOT NULL,
  `lesson_date_snapshot` date NOT NULL,
  `starts_at_snapshot` time NOT NULL,
  `ends_at_snapshot` time NOT NULL,
  `exclusive_snapshot` tinyint(1) NOT NULL,
  `note_snapshot` varchar(1000) DEFAULT NULL,
  `quantity` int(10) unsigned NOT NULL DEFAULT 1,
  `unit_amount_minor` bigint(20) unsigned NOT NULL,
  `line_amount_minor` bigint(20) unsigned NOT NULL,
  `currency` char(3) NOT NULL DEFAULT 'CZK',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_public_velo_order_slot_person` (`order_id`,`lesson_id`,`beneficiary_sportovec_id`),
  UNIQUE KEY `uq_public_velo_order_reservation` (`reservation_id`),
  KEY `idx_public_velo_order_lesson` (`lesson_id`,`id`),
  KEY `fk_public_velo_order_person` (`beneficiary_sportovec_id`),
  CONSTRAINT `fk_public_velo_order_lesson` FOREIGN KEY (`lesson_id`) REFERENCES `individualni_lekce` (`id`),
  CONSTRAINT `fk_public_velo_order_order` FOREIGN KEY (`order_id`) REFERENCES `shop_orders` (`id`),
  CONSTRAINT `fk_public_velo_order_person` FOREIGN KEY (`beneficiary_sportovec_id`) REFERENCES `sportovci` (`id`),
  CONSTRAINT `fk_public_velo_order_reservation` FOREIGN KEY (`reservation_id`) REFERENCES `verejne_rezervace` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `public_velodrome_reservation_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `reservation_id` int(11) NOT NULL,
  `actor_type` varchar(16) NOT NULL,
  `actor_id` int(11) DEFAULT NULL,
  `action` varchar(32) NOT NULL,
  `from_status` varchar(24) DEFAULT NULL,
  `to_status` varchar(24) NOT NULL,
  `note` varchar(1000) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_public_velodrome_event` (`reservation_id`,`id`),
  CONSTRAINT `fk_public_velodrome_event_reservation` FOREIGN KEY (`reservation_id`) REFERENCES `verejne_rezervace` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `push_subscriptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `trener_id` int(11) DEFAULT NULL,
  `endpoint` text NOT NULL,
  `p256dh` varchar(255) NOT NULL,
  `auth` varchar(255) NOT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_trener` (`trener_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `results_administratori` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `jmeno` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `heslo_hash` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `results_kategorie` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `udalost_id` int(11) NOT NULL,
  `nazev` varchar(100) DEFAULT NULL,
  `vek_od` int(11) DEFAULT NULL,
  `vek_do` int(11) DEFAULT NULL,
  `startovne` decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `udalost_id` (`udalost_id`),
  CONSTRAINT `results_kategorie_ibfk_1` FOREIGN KEY (`udalost_id`) REFERENCES `results_udalosti` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `results_registrace` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sportovec_id` int(11) NOT NULL,
  `udalost_id` int(11) NOT NULL,
  `kategorie_id` int(11) DEFAULT NULL,
  `startovni_cislo` int(11) DEFAULT NULL,
  `zaplaceno` tinyint(1) DEFAULT 0,
  `stripe_session_id` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sportovec_id` (`sportovec_id`),
  KEY `udalost_id` (`udalost_id`),
  KEY `kategorie_id` (`kategorie_id`),
  CONSTRAINT `results_registrace_ibfk_1` FOREIGN KEY (`sportovec_id`) REFERENCES `results_sportovci` (`id`) ON DELETE CASCADE,
  CONSTRAINT `results_registrace_ibfk_2` FOREIGN KEY (`udalost_id`) REFERENCES `results_udalosti` (`id`) ON DELETE CASCADE,
  CONSTRAINT `results_registrace_ibfk_3` FOREIGN KEY (`kategorie_id`) REFERENCES `results_kategorie` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `results_sportovci` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `jmeno` varchar(100) NOT NULL,
  `prijmeni` varchar(100) NOT NULL,
  `datum_narozeni` date NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `telefon` varchar(50) DEFAULT NULL,
  `uciid` varchar(50) DEFAULT NULL,
  `adresa` text DEFAULT NULL,
  `emergency_email` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uciid` (`uciid`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `results_udalosti` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nazev` varchar(255) NOT NULL,
  `datum` date NOT NULL,
  `registrace_do` date DEFAULT NULL,
  `samoobsluha` tinyint(1) DEFAULT 0,
  `logo` varchar(255) DEFAULT NULL,
  `hlavicka` text DEFAULT NULL,
  `paticka` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `results_vysledky` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `udalost_id` int(11) NOT NULL,
  `sportovec_id` int(11) NOT NULL,
  `kategorie_id` int(11) DEFAULT NULL,
  `startovni_cislo` int(11) DEFAULT NULL,
  `poradi` int(11) DEFAULT NULL,
  `cas` time DEFAULT NULL,
  `poznamka` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `udalost_id` (`udalost_id`),
  KEY `sportovec_id` (`sportovec_id`),
  KEY `kategorie_id` (`kategorie_id`),
  CONSTRAINT `results_vysledky_ibfk_1` FOREIGN KEY (`udalost_id`) REFERENCES `results_udalosti` (`id`) ON DELETE CASCADE,
  CONSTRAINT `results_vysledky_ibfk_2` FOREIGN KEY (`sportovec_id`) REFERENCES `results_sportovci` (`id`) ON DELETE CASCADE,
  CONSTRAINT `results_vysledky_ibfk_3` FOREIGN KEY (`kategorie_id`) REFERENCES `results_kategorie` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `results_zebricek_body` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `poradi` int(11) NOT NULL,
  `body` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `results_zebricek_vysledky` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sportovec_id` int(11) NOT NULL,
  `udalost_id` int(11) NOT NULL,
  `kategorie_id` int(11) DEFAULT NULL,
  `poradi` int(11) DEFAULT NULL,
  `body` int(11) DEFAULT NULL,
  `cas` time DEFAULT NULL,
  `poznamka` text DEFAULT NULL,
  `datum` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sportovec_id` (`sportovec_id`),
  KEY `udalost_id` (`udalost_id`),
  KEY `kategorie_id` (`kategorie_id`),
  CONSTRAINT `results_zebricek_vysledky_ibfk_1` FOREIGN KEY (`sportovec_id`) REFERENCES `results_sportovci` (`id`) ON DELETE CASCADE,
  CONSTRAINT `results_zebricek_vysledky_ibfk_2` FOREIGN KEY (`udalost_id`) REFERENCES `results_udalosti` (`id`) ON DELETE CASCADE,
  CONSTRAINT `results_zebricek_vysledky_ibfk_3` FOREIGN KEY (`kategorie_id`) REFERENCES `results_kategorie` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rezervace_sportovist` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sportoviste_id` int(11) NOT NULL,
  `trener_id` int(11) NOT NULL,
  `datum` date NOT NULL,
  `cas_od` time NOT NULL,
  `cas_do` time NOT NULL,
  `kapacita_dilu` tinyint(4) NOT NULL DEFAULT 1,
  `trenink_id` int(11) DEFAULT NULL,
  `lekce_id` int(11) DEFAULT NULL,
  `poznamka` text DEFAULT NULL,
  `vytvoreno` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_datum_sportoviste` (`datum`,`sportoviste_id`),
  KEY `idx_trener` (`trener_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `segmenty` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nazev` varchar(200) NOT NULL,
  `popis` text DEFAULT NULL,
  `fotografie` varchar(500) DEFAULT NULL,
  `odkaz_1` varchar(500) DEFAULT NULL,
  `odkaz_2` varchar(500) DEFAULT NULL,
  `kategorie` enum('krouzek','silnice','mtb') NOT NULL,
  `poradi` int(11) NOT NULL DEFAULT 0,
  `aktivni` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_kategorie_aktivni` (`kategorie`,`aktivni`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `shop_attribute_choices` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `attribute_id` bigint(20) unsigned NOT NULL,
  `value` varchar(255) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_shop_attribute_choice` (`attribute_id`,`value`),
  KEY `idx_shop_attribute_choice_order` (`attribute_id`,`active`,`sort_order`,`id`),
  CONSTRAINT `fk_shop_attribute_choice_definition` FOREIGN KEY (`attribute_id`) REFERENCES `shop_attribute_definitions` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `shop_attribute_definition_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `attribute_id` bigint(20) unsigned NOT NULL,
  `attribute_key` varchar(191) NOT NULL,
  `actor_type` varchar(24) NOT NULL,
  `actor_id` bigint(20) NOT NULL,
  `action` varchar(48) NOT NULL,
  `before_json` longtext DEFAULT NULL,
  `after_json` longtext NOT NULL,
  `reason` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_shop_attribute_event_definition` (`attribute_id`,`id`),
  KEY `idx_shop_attribute_event_created` (`created_at`,`id`),
  CONSTRAINT `fk_shop_attribute_event_definition` FOREIGN KEY (`attribute_id`) REFERENCES `shop_attribute_definitions` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `shop_attribute_definitions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `attribute_key` varchar(191) NOT NULL,
  `display_name` varchar(255) NOT NULL,
  `value_type` varchar(16) NOT NULL,
  `unit` varchar(32) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `show_in_listing` tinyint(1) NOT NULL DEFAULT 0,
  `show_in_detail` tinyint(1) NOT NULL DEFAULT 1,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `attribute_key` (`attribute_key`),
  KEY `idx_shop_attribute_display` (`active`,`sort_order`,`display_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `shop_bank_settings` (
  `id` tinyint(3) unsigned NOT NULL,
  `iban` varchar(34) NOT NULL,
  `bic` varchar(11) NOT NULL DEFAULT '',
  `account_label` varchar(255) NOT NULL,
  `due_days` smallint(5) unsigned NOT NULL,
  `updated_by_trainer_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_shop_bank_settings_trainer` (`updated_by_trainer_id`),
  CONSTRAINT `fk_shop_bank_settings_trainer` FOREIGN KEY (`updated_by_trainer_id`) REFERENCES `treneri` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `shop_bank_settings_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `actor_type` varchar(16) NOT NULL,
  `actor_id` int(11) NOT NULL,
  `action` varchar(32) NOT NULL,
  `before_json` longtext DEFAULT NULL,
  `after_json` longtext NOT NULL,
  `reason` varchar(1000) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_shop_bank_settings_events_created` (`created_at`,`id`),
  KEY `fk_shop_bank_settings_events_trainer` (`actor_id`),
  CONSTRAINT `fk_shop_bank_settings_events_trainer` FOREIGN KEY (`actor_id`) REFERENCES `treneri` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `shop_cart_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `cart_id` bigint(20) unsigned NOT NULL,
  `variant_id` bigint(20) unsigned NOT NULL,
  `quantity` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `beneficiary_sportovec_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_shop_cart_variant` (`cart_id`,`variant_id`),
  KEY `fk_shop_cart_item_variant` (`variant_id`),
  KEY `idx_shop_cart_item_beneficiary` (`beneficiary_sportovec_id`),
  CONSTRAINT `fk_shop_cart_item_beneficiary` FOREIGN KEY (`beneficiary_sportovec_id`) REFERENCES `sportovci` (`id`),
  CONSTRAINT `fk_shop_cart_item_cart` FOREIGN KEY (`cart_id`) REFERENCES `shop_carts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_shop_cart_item_variant` FOREIGN KEY (`variant_id`) REFERENCES `shop_variants` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `shop_carts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `cart_key` char(32) NOT NULL,
  `account_id` int(11) NOT NULL,
  `active_account_id` int(11) DEFAULT NULL,
  `status` varchar(24) NOT NULL DEFAULT 'active',
  `currency` char(3) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `converted_at` datetime DEFAULT NULL,
  `coupon_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_shop_cart_key` (`cart_key`),
  UNIQUE KEY `uq_shop_cart_active_account` (`active_account_id`),
  KEY `idx_shop_cart_account` (`account_id`,`status`,`id`),
  CONSTRAINT `fk_shop_cart_account` FOREIGN KEY (`account_id`) REFERENCES `verejni_uzivatele` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `shop_catalog_admin_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint(20) unsigned NOT NULL,
  `variant_id` bigint(20) unsigned DEFAULT NULL,
  `actor_type` varchar(24) NOT NULL,
  `actor_id` bigint(20) NOT NULL,
  `action` varchar(48) NOT NULL,
  `before_json` longtext DEFAULT NULL,
  `after_json` longtext NOT NULL,
  `reason` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_shop_catalog_admin_product` (`product_id`,`id`),
  KEY `idx_shop_catalog_admin_variant` (`variant_id`,`id`),
  CONSTRAINT `fk_shop_catalog_admin_product` FOREIGN KEY (`product_id`) REFERENCES `shop_products` (`id`),
  CONSTRAINT `fk_shop_catalog_admin_variant` FOREIGN KEY (`variant_id`) REFERENCES `shop_variants` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `shop_catalog_import_runs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `source_sha256` char(64) NOT NULL,
  `source_filename` varchar(255) NOT NULL,
  `contract_version` varchar(80) NOT NULL,
  `status` varchar(32) NOT NULL,
  `product_count` int(10) unsigned NOT NULL,
  `variant_count` int(10) unsigned NOT NULL,
  `warning_count` int(10) unsigned NOT NULL,
  `manual_review_count` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `promoted_at` datetime DEFAULT NULL,
  `promoted_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_shop_catalog_run_source` (`source_sha256`,`contract_version`),
  KEY `idx_shop_catalog_run_status` (`status`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `shop_catalog_product_candidates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `run_id` bigint(20) unsigned NOT NULL,
  `external_product_key` varchar(191) NOT NULL,
  `source_pair_code` varchar(64) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `offer_type` varchar(32) NOT NULL,
  `classification_confidence` varchar(16) NOT NULL,
  `needs_manual_review` tinyint(1) NOT NULL DEFAULT 0,
  `payload_json` longtext NOT NULL,
  `review_status` varchar(24) NOT NULL DEFAULT 'auto_classified',
  `reviewed_offer_type` varchar(32) DEFAULT NULL,
  `review_note` text DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_shop_catalog_product` (`run_id`,`external_product_key`),
  KEY `idx_shop_catalog_product_review` (`run_id`,`needs_manual_review`),
  CONSTRAINT `fk_shop_catalog_product_run` FOREIGN KEY (`run_id`) REFERENCES `shop_catalog_import_runs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `shop_catalog_promotions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `run_id` bigint(20) unsigned NOT NULL,
  `actor_trainer_id` int(11) NOT NULL,
  `status` varchar(24) NOT NULL,
  `product_count` int(10) unsigned NOT NULL DEFAULT 0,
  `variant_count` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_shop_catalog_promotion_run` (`run_id`),
  CONSTRAINT `fk_shop_catalog_promotion_run` FOREIGN KEY (`run_id`) REFERENCES `shop_catalog_import_runs` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `shop_catalog_review_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `run_id` bigint(20) unsigned NOT NULL,
  `product_candidate_id` bigint(20) unsigned NOT NULL,
  `actor_trainer_id` int(11) NOT NULL,
  `action` varchar(24) NOT NULL,
  `from_offer_type` varchar(32) DEFAULT NULL,
  `to_offer_type` varchar(32) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_shop_review_run` (`run_id`,`created_at`),
  KEY `idx_shop_review_product` (`product_candidate_id`,`created_at`),
  CONSTRAINT `fk_shop_review_product` FOREIGN KEY (`product_candidate_id`) REFERENCES `shop_catalog_product_candidates` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_shop_review_run` FOREIGN KEY (`run_id`) REFERENCES `shop_catalog_import_runs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `shop_catalog_variant_candidates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `run_id` bigint(20) unsigned NOT NULL,
  `product_candidate_id` bigint(20) unsigned NOT NULL,
  `sku` varchar(64) NOT NULL,
  `price_mode` varchar(16) NOT NULL,
  `amount_minor` bigint(20) DEFAULT NULL,
  `currency` char(3) DEFAULT NULL,
  `stock_quantity_decimal` decimal(18,6) DEFAULT NULL,
  `payload_json` longtext NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_shop_catalog_variant` (`run_id`,`sku`),
  KEY `idx_shop_catalog_variant_product` (`product_candidate_id`),
  CONSTRAINT `fk_shop_catalog_variant_product` FOREIGN KEY (`product_candidate_id`) REFERENCES `shop_catalog_product_candidates` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_shop_catalog_variant_run` FOREIGN KEY (`run_id`) REFERENCES `shop_catalog_import_runs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `shop_category_meta` (
  `category_path` varchar(500) NOT NULL,
  `display_name` varchar(255) NOT NULL,
  `parent_path` varchar(500) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `visible_in_menu` tinyint(1) NOT NULL DEFAULT 1,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`category_path`),
  KEY `idx_shop_category_parent` (`parent_path`,`sort_order`),
  KEY `idx_shop_category_menu` (`visible_in_menu`,`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `shop_category_meta_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `category_path` varchar(500) NOT NULL,
  `actor_type` varchar(24) NOT NULL,
  `actor_id` bigint(20) NOT NULL,
  `action` varchar(48) NOT NULL,
  `before_json` longtext DEFAULT NULL,
  `after_json` longtext NOT NULL,
  `reason` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_shop_category_event_path` (`category_path`,`id`),
  KEY `idx_shop_category_event_created` (`created_at`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `shop_coupon_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `coupon_id` bigint(20) unsigned NOT NULL,
  `actor_trainer_id` int(11) NOT NULL,
  `action` varchar(32) NOT NULL,
  `before_json` longtext DEFAULT NULL,
  `after_json` longtext NOT NULL,
  `note` varchar(1000) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_shop_coupon_event` (`coupon_id`,`id`),
  KEY `fk_shop_coupon_event_actor` (`actor_trainer_id`),
  CONSTRAINT `fk_shop_coupon_event_actor` FOREIGN KEY (`actor_trainer_id`) REFERENCES `treneri` (`id`),
  CONSTRAINT `fk_shop_coupon_event_coupon` FOREIGN KEY (`coupon_id`) REFERENCES `shop_coupons` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `shop_coupon_redemptions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `coupon_id` bigint(20) unsigned NOT NULL,
  `order_id` bigint(20) unsigned NOT NULL,
  `account_id` int(11) NOT NULL,
  `code_snapshot` varchar(32) NOT NULL,
  `discount_type_snapshot` varchar(16) NOT NULL,
  `value_snapshot` int(10) unsigned NOT NULL,
  `discount_minor` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `eligible_subtotal_minor` bigint(20) unsigned DEFAULT NULL,
  `applicability_mask_snapshot` smallint(5) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_shop_coupon_redemption_order` (`order_id`),
  KEY `idx_shop_coupon_redemption_coupon` (`coupon_id`,`id`),
  KEY `fk_shop_coupon_redemption_account` (`account_id`),
  CONSTRAINT `fk_shop_coupon_redemption_account` FOREIGN KEY (`account_id`) REFERENCES `verejni_uzivatele` (`id`),
  CONSTRAINT `fk_shop_coupon_redemption_coupon` FOREIGN KEY (`coupon_id`) REFERENCES `shop_coupons` (`id`),
  CONSTRAINT `fk_shop_coupon_redemption_order` FOREIGN KEY (`order_id`) REFERENCES `shop_orders` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `shop_coupons` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(32) NOT NULL,
  `discount_type` varchar(16) NOT NULL,
  `value_minor_or_basis_points` int(10) unsigned NOT NULL,
  `currency` char(3) NOT NULL DEFAULT 'CZK',
  `minimum_order_minor` bigint(20) unsigned NOT NULL DEFAULT 0,
  `maximum_discount_minor` bigint(20) unsigned DEFAULT NULL,
  `usage_limit_total` int(10) unsigned DEFAULT NULL,
  `usage_count` int(10) unsigned NOT NULL DEFAULT 0,
  `valid_from` datetime DEFAULT NULL,
  `valid_until` datetime DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `archived_at` datetime DEFAULT NULL,
  `created_by_trainer_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `applicability_mask` smallint(5) unsigned NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_shop_coupon_code` (`code`),
  KEY `idx_shop_coupon_active_validity` (`active`,`valid_from`,`valid_until`),
  KEY `fk_shop_coupon_creator` (`created_by_trainer_id`),
  CONSTRAINT `fk_shop_coupon_creator` FOREIGN KEY (`created_by_trainer_id`) REFERENCES `treneri` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `shop_inventory_movements` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `variant_id` bigint(20) unsigned NOT NULL,
  `order_id` bigint(20) unsigned DEFAULT NULL,
  `order_item_id` bigint(20) unsigned DEFAULT NULL,
  `movement_type` varchar(32) NOT NULL,
  `actor_type` varchar(24) DEFAULT NULL,
  `actor_id` bigint(20) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `quantity_delta_decimal` decimal(18,6) NOT NULL,
  `stock_after_decimal` decimal(18,6) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_shop_inventory_order_item_type` (`order_item_id`,`movement_type`),
  KEY `idx_shop_inventory_variant` (`variant_id`,`id`),
  KEY `fk_shop_inventory_order` (`order_id`),
  CONSTRAINT `fk_shop_inventory_order` FOREIGN KEY (`order_id`) REFERENCES `shop_orders` (`id`),
  CONSTRAINT `fk_shop_inventory_order_item` FOREIGN KEY (`order_item_id`) REFERENCES `shop_order_items` (`id`),
  CONSTRAINT `fk_shop_inventory_variant` FOREIGN KEY (`variant_id`) REFERENCES `shop_variants` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `shop_member_category_rules` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `team_id` bigint(20) unsigned NOT NULL,
  `category_path` varchar(500) NOT NULL,
  `discount_type` varchar(24) NOT NULL,
  `value_minor_or_basis_points` bigint(20) NOT NULL,
  `currency` char(3) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by_trainer_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_member_category_team_path` (`team_id`,`category_path`),
  KEY `idx_member_category_active` (`active`,`category_path`,`team_id`),
  KEY `fk_member_category_actor` (`created_by_trainer_id`),
  CONSTRAINT `fk_member_category_actor` FOREIGN KEY (`created_by_trainer_id`) REFERENCES `treneri` (`id`),
  CONSTRAINT `fk_member_category_team` FOREIGN KEY (`team_id`) REFERENCES `club_teams` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `shop_member_price_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `rule_type` varchar(24) NOT NULL,
  `rule_id` bigint(20) unsigned NOT NULL,
  `team_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned DEFAULT NULL,
  `category_path` varchar(500) DEFAULT NULL,
  `actor_trainer_id` int(11) NOT NULL,
  `action` varchar(24) NOT NULL,
  `before_json` longtext DEFAULT NULL,
  `after_json` longtext DEFAULT NULL,
  `note` varchar(1000) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_member_price_event_team` (`team_id`,`id`),
  KEY `fk_member_price_event_actor` (`actor_trainer_id`),
  CONSTRAINT `fk_member_price_event_actor` FOREIGN KEY (`actor_trainer_id`) REFERENCES `treneri` (`id`),
  CONSTRAINT `fk_member_price_event_team` FOREIGN KEY (`team_id`) REFERENCES `club_teams` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `shop_member_product_prices` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `team_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  `amount_minor` bigint(20) NOT NULL,
  `currency` char(3) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by_trainer_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_member_product_team_product` (`team_id`,`product_id`),
  KEY `idx_member_product_active` (`active`,`product_id`,`team_id`),
  KEY `fk_member_product_product` (`product_id`),
  KEY `fk_member_product_actor` (`created_by_trainer_id`),
  CONSTRAINT `fk_member_product_actor` FOREIGN KEY (`created_by_trainer_id`) REFERENCES `treneri` (`id`),
  CONSTRAINT `fk_member_product_product` FOREIGN KEY (`product_id`) REFERENCES `shop_products` (`id`),
  CONSTRAINT `fk_member_product_team` FOREIGN KEY (`team_id`) REFERENCES `club_teams` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `shop_order_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint(20) unsigned NOT NULL,
  `actor_type` varchar(24) NOT NULL,
  `actor_id` int(11) DEFAULT NULL,
  `action` varchar(64) NOT NULL,
  `from_status` varchar(24) DEFAULT NULL,
  `to_status` varchar(24) NOT NULL,
  `note` varchar(1000) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_shop_order_event_order` (`order_id`,`id`),
  CONSTRAINT `fk_shop_order_event_order` FOREIGN KEY (`order_id`) REFERENCES `shop_orders` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `shop_order_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  `variant_id` bigint(20) unsigned NOT NULL,
  `product_name_snapshot` varchar(255) NOT NULL,
  `sku_snapshot` varchar(64) NOT NULL,
  `attributes_json_snapshot` longtext NOT NULL,
  `quantity` int(10) unsigned NOT NULL,
  `unit_amount_minor` bigint(20) unsigned NOT NULL,
  `line_amount_minor` bigint(20) unsigned NOT NULL,
  `currency` char(3) NOT NULL,
  `includes_vat_snapshot` tinyint(1) DEFAULT NULL,
  `vat_rate_basis_points_snapshot` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `beneficiary_sportovec_id` int(11) DEFAULT NULL,
  `program_terms_snapshot_json` longtext DEFAULT NULL,
  `program_terms_accepted_at` datetime DEFAULT NULL,
  `program_terms_accepted_by_account_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_shop_order_variant` (`order_id`,`variant_id`),
  KEY `fk_shop_order_item_product` (`product_id`),
  KEY `fk_shop_order_item_variant` (`variant_id`),
  KEY `idx_shop_order_item_beneficiary` (`beneficiary_sportovec_id`),
  KEY `fk_shop_order_item_terms_account` (`program_terms_accepted_by_account_id`),
  CONSTRAINT `fk_shop_order_item_beneficiary` FOREIGN KEY (`beneficiary_sportovec_id`) REFERENCES `sportovci` (`id`),
  CONSTRAINT `fk_shop_order_item_order` FOREIGN KEY (`order_id`) REFERENCES `shop_orders` (`id`),
  CONSTRAINT `fk_shop_order_item_product` FOREIGN KEY (`product_id`) REFERENCES `shop_products` (`id`),
  CONSTRAINT `fk_shop_order_item_terms_account` FOREIGN KEY (`program_terms_accepted_by_account_id`) REFERENCES `verejni_uzivatele` (`id`),
  CONSTRAINT `fk_shop_order_item_variant` FOREIGN KEY (`variant_id`) REFERENCES `shop_variants` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `shop_orders` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `public_code` char(18) NOT NULL,
  `account_id` int(11) NOT NULL,
  `source_cart_id` bigint(20) unsigned NOT NULL,
  `idempotency_key_hash` char(64) NOT NULL,
  `status` varchar(24) NOT NULL,
  `payment_status` varchar(24) NOT NULL,
  `fulfillment_method` varchar(32) NOT NULL,
  `customer_name_snapshot` varchar(255) NOT NULL,
  `customer_email_snapshot` varchar(254) NOT NULL,
  `subtotal_minor` bigint(20) unsigned NOT NULL,
  `discount_minor` bigint(20) unsigned NOT NULL DEFAULT 0,
  `total_minor` bigint(20) unsigned NOT NULL,
  `currency` char(3) NOT NULL,
  `placed_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `cancelled_at` datetime DEFAULT NULL,
  `ready_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `payment_expires_at` datetime DEFAULT NULL,
  `expired_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_shop_order_public_code` (`public_code`),
  UNIQUE KEY `uq_shop_order_idempotency` (`idempotency_key_hash`),
  KEY `idx_shop_order_account` (`account_id`,`created_at`,`id`),
  KEY `fk_shop_order_cart` (`source_cart_id`),
  KEY `idx_shop_order_expiration` (`status`,`payment_status`,`payment_expires_at`,`id`),
  CONSTRAINT `fk_shop_order_account` FOREIGN KEY (`account_id`) REFERENCES `verejni_uzivatele` (`id`),
  CONSTRAINT `fk_shop_order_cart` FOREIGN KEY (`source_cart_id`) REFERENCES `shop_carts` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `shop_product_categories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint(20) unsigned NOT NULL,
  `category_path` varchar(500) NOT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_shop_product_category` (`product_id`,`category_path`),
  CONSTRAINT `fk_shop_category_product` FOREIGN KEY (`product_id`) REFERENCES `shop_products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `shop_product_event_links` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint(20) unsigned NOT NULL,
  `event_id` bigint(20) unsigned NOT NULL,
  `actor_trainer_id` int(11) NOT NULL,
  `decision_note` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_shop_event_product` (`product_id`),
  KEY `idx_shop_event_event` (`event_id`),
  KEY `fk_shop_event_actor` (`actor_trainer_id`),
  CONSTRAINT `fk_shop_event_actor` FOREIGN KEY (`actor_trainer_id`) REFERENCES `treneri` (`id`),
  CONSTRAINT `fk_shop_event_event` FOREIGN KEY (`event_id`) REFERENCES `club_events` (`id`),
  CONSTRAINT `fk_shop_event_product` FOREIGN KEY (`product_id`) REFERENCES `shop_products` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `shop_product_images` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint(20) unsigned NOT NULL,
  `image_url` varchar(2048) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_shop_product_image` (`product_id`,`image_url`(191)),
  CONSTRAINT `fk_shop_image_product` FOREIGN KEY (`product_id`) REFERENCES `shop_products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `shop_product_publication_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint(20) unsigned NOT NULL,
  `actor_trainer_id` int(11) NOT NULL,
  `action` varchar(24) NOT NULL,
  `from_status` varchar(24) DEFAULT NULL,
  `to_status` varchar(24) NOT NULL,
  `public_name` varchar(255) NOT NULL,
  `public_summary` text NOT NULL,
  `note` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_shop_publication_event_product` (`product_id`,`created_at`),
  KEY `fk_shop_publication_event_actor` (`actor_trainer_id`),
  CONSTRAINT `fk_shop_publication_event_actor` FOREIGN KEY (`actor_trainer_id`) REFERENCES `treneri` (`id`),
  CONSTRAINT `fk_shop_publication_event_product` FOREIGN KEY (`product_id`) REFERENCES `shop_products` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `shop_product_publications` (
  `product_id` bigint(20) unsigned NOT NULL,
  `status` varchar(24) NOT NULL DEFAULT 'draft',
  `public_name` varchar(255) NOT NULL,
  `public_summary` text NOT NULL,
  `decision_note` text NOT NULL,
  `activated_by_trainer_id` int(11) DEFAULT NULL,
  `activated_at` datetime DEFAULT NULL,
  `deactivated_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`product_id`),
  KEY `idx_shop_publication_status` (`status`,`activated_at`),
  KEY `fk_shop_publication_actor` (`activated_by_trainer_id`),
  CONSTRAINT `fk_shop_publication_actor` FOREIGN KEY (`activated_by_trainer_id`) REFERENCES `treneri` (`id`),
  CONSTRAINT `fk_shop_publication_product` FOREIGN KEY (`product_id`) REFERENCES `shop_products` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `shop_products` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `source_candidate_id` bigint(20) unsigned DEFAULT NULL,
  `source_run_id` bigint(20) unsigned DEFAULT NULL,
  `origin` varchar(16) NOT NULL DEFAULT 'import',
  `created_by_trainer_id` int(11) DEFAULT NULL,
  `external_product_key` varchar(191) NOT NULL,
  `source_pair_code` varchar(64) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `short_description` text DEFAULT NULL,
  `description_html_untrusted` longtext DEFAULT NULL,
  `offer_type` varchar(32) NOT NULL,
  `visibility` varchar(32) DEFAULT NULL,
  `item_type` varchar(32) DEFAULT NULL,
  `catalog_status` varchar(24) NOT NULL DEFAULT 'draft',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_shop_product_external` (`external_product_key`),
  UNIQUE KEY `uq_shop_product_candidate` (`source_candidate_id`),
  KEY `idx_shop_product_type_status` (`offer_type`,`catalog_status`),
  KEY `fk_shop_product_run` (`source_run_id`),
  KEY `fk_shop_product_creator` (`created_by_trainer_id`),
  CONSTRAINT `fk_shop_product_candidate` FOREIGN KEY (`source_candidate_id`) REFERENCES `shop_catalog_product_candidates` (`id`),
  CONSTRAINT `fk_shop_product_creator` FOREIGN KEY (`created_by_trainer_id`) REFERENCES `treneri` (`id`),
  CONSTRAINT `fk_shop_product_run` FOREIGN KEY (`source_run_id`) REFERENCES `shop_catalog_import_runs` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `shop_variants` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint(20) unsigned NOT NULL,
  `source_candidate_id` bigint(20) unsigned DEFAULT NULL,
  `origin` varchar(16) NOT NULL DEFAULT 'import',
  `created_by_trainer_id` int(11) DEFAULT NULL,
  `sku` varchar(64) NOT NULL,
  `ean` varchar(32) DEFAULT NULL,
  `attributes_json` longtext NOT NULL,
  `price_mode` varchar(16) NOT NULL,
  `amount_minor` bigint(20) DEFAULT NULL,
  `compare_at_amount_minor` bigint(20) DEFAULT NULL,
  `currency` char(3) DEFAULT NULL,
  `includes_vat` tinyint(1) DEFAULT NULL,
  `vat_rate_basis_points` int(11) DEFAULT NULL,
  `stock_quantity_decimal` decimal(18,6) DEFAULT NULL,
  `unit_code` varchar(32) DEFAULT NULL,
  `availability_in_stock` varchar(120) DEFAULT NULL,
  `availability_out_of_stock` varchar(120) DEFAULT NULL,
  `free_shipping` tinyint(1) DEFAULT NULL,
  `free_billing` tinyint(1) DEFAULT NULL,
  `visible` tinyint(1) DEFAULT NULL,
  `catalog_status` varchar(24) NOT NULL DEFAULT 'draft',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_shop_variant_sku` (`sku`),
  UNIQUE KEY `uq_shop_variant_candidate` (`source_candidate_id`),
  KEY `idx_shop_variant_product` (`product_id`),
  KEY `fk_shop_variant_creator` (`created_by_trainer_id`),
  CONSTRAINT `fk_shop_variant_candidate` FOREIGN KEY (`source_candidate_id`) REFERENCES `shop_catalog_variant_candidates` (`id`),
  CONSTRAINT `fk_shop_variant_creator` FOREIGN KEY (`created_by_trainer_id`) REFERENCES `treneri` (`id`),
  CONSTRAINT `fk_shop_variant_product` FOREIGN KEY (`product_id`) REFERENCES `shop_products` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `skupiny` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hash` varchar(200) NOT NULL DEFAULT current_timestamp(),
  `nazev` varchar(100) NOT NULL,
  `poradi` int(11) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `soupiska_mapping` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `soupiska_text` varchar(255) NOT NULL,
  `skupina_id` int(11) DEFAULT NULL,
  `podskupina_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `soupiska_text` (`soupiska_text`),
  KEY `skupina_id` (`skupina_id`),
  KEY `podskupina_id` (`podskupina_id`),
  CONSTRAINT `soupiska_mapping_ibfk_1` FOREIGN KEY (`skupina_id`) REFERENCES `skupiny` (`id`) ON DELETE SET NULL,
  CONSTRAINT `soupiska_mapping_ibfk_2` FOREIGN KEY (`podskupina_id`) REFERENCES `podskupiny` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sportovci` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `jmeno` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `hash` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `odmena_za_trenink` decimal(10,2) NOT NULL DEFAULT 0.00,
  `obdobi_start` date DEFAULT NULL,
  `prijmeni` text NOT NULL,
  `narozeni` date NOT NULL DEFAULT current_timestamp(),
  `category` varchar(100) DEFAULT NULL COMMENT 'Kategorie sportovce',
  `uci` int(11) NOT NULL,
  `uciid` varchar(80) DEFAULT NULL COMMENT 'UCI ID',
  `email` text NOT NULL,
  `stav_clenstvi` enum('aktivni','cekajici','neaktivni','archiv') NOT NULL DEFAULT 'cekajici',
  `stav_duvod` varchar(255) DEFAULT NULL,
  `stav_manualni` tinyint(1) NOT NULL DEFAULT 0,
  `stav_aktualizovan` timestamp NULL DEFAULT NULL,
  `kis_identity_key` varchar(180) DEFAULT NULL,
  `kis_match_confidence` tinyint(3) unsigned DEFAULT NULL,
  `kis_last_seen_at` timestamp NULL DEFAULT NULL,
  `oddil` varchar(160) DEFAULT NULL COMMENT 'Oddil / klub',
  `rc` varchar(20) DEFAULT NULL,
  `telefon` varchar(50) DEFAULT NULL,
  `adresa_ulice` varchar(200) DEFAULT NULL,
  `adresa_cp` varchar(20) DEFAULT NULL,
  `adresa_co` varchar(20) DEFAULT NULL,
  `adresa_obec` varchar(100) DEFAULT NULL,
  `adresa_psc` varchar(10) DEFAULT NULL,
  `first_name_norm` varchar(100) DEFAULT NULL,
  `last_name_norm` varchar(100) DEFAULT NULL,
  `kis_aktivni` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Aktivni v KIS soupisce',
  `kis_platebne_aktivni` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Ma alespon jednu uhrazenou KIS platbu',
  `kis_neuhrazeno` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Souhrn otevrenych KIS plateb',
  `kis_posledni_uhrada` date DEFAULT NULL COMMENT 'Posledni datum uhrady z KIS exportu',
  `kis_posledni_sync` timestamp NULL DEFAULT NULL COMMENT 'Kdy byl sportovec naposledy aktualizovan KIS synchronizaci',
  `kis_soupisky` text DEFAULT NULL COMMENT 'Soupisky z posledniho KIS importu',
  `kis_external_id` varchar(80) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sportovci_kis_external_id` (`kis_external_id`),
  KEY `idx_mr_name_norm` (`last_name_norm`,`first_name_norm`),
  KEY `idx_stav_clenstvi` (`stav_clenstvi`),
  KEY `idx_kis_identity_key` (`kis_identity_key`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sportovec_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sportovec_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `source` enum('manual','kis_import','bulk_action','system') NOT NULL DEFAULT 'manual',
  `event_type` varchar(80) NOT NULL,
  `title` varchar(180) NOT NULL,
  `detail` text DEFAULT NULL,
  `old_json` longtext DEFAULT NULL,
  `new_json` longtext DEFAULT NULL,
  `ref_table` varchar(80) DEFAULT NULL,
  `ref_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_sportovec_id` (`sportovec_id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_event_type` (`event_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sportovec_interni_poznamka` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sportovec_id` int(11) NOT NULL,
  `trener_id` int(11) NOT NULL,
  `datum` datetime NOT NULL DEFAULT current_timestamp(),
  `text` text NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_sportovec_interni_poznamka_sportovec` (`sportovec_id`),
  KEY `fk_sportovec_interni_poznamka_trener` (`trener_id`),
  CONSTRAINT `fk_sportovec_interni_poznamka_sportovec` FOREIGN KEY (`sportovec_id`) REFERENCES `sportovci` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sportovec_interni_poznamka_trener` FOREIGN KEY (`trener_id`) REFERENCES `treneri` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sportovec_obdobi` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sportovec_id` int(11) NOT NULL,
  `datum_od` date NOT NULL,
  `datum_do` date DEFAULT NULL,
  `pocet_treninku` int(11) DEFAULT NULL,
  `sazba_kc` decimal(10,2) NOT NULL,
  `castka_celkem` decimal(10,2) DEFAULT NULL,
  `vyplaceno` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_sportovec_obdobi_sportovec` (`sportovec_id`),
  CONSTRAINT `fk_sportovec_obdobi_sportovec` FOREIGN KEY (`sportovec_id`) REFERENCES `sportovci` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sportovec_podskupina` (
  `sportovec_id` int(11) NOT NULL,
  `podskupina_id` int(11) NOT NULL,
  PRIMARY KEY (`sportovec_id`,`podskupina_id`),
  KEY `fk_sp_ps_podskupina` (`podskupina_id`),
  CONSTRAINT `fk_sp_ps_podskupina` FOREIGN KEY (`podskupina_id`) REFERENCES `podskupiny` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sp_ps_sportovec` FOREIGN KEY (`sportovec_id`) REFERENCES `sportovci` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sportovec_poznamka` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sportovec_id` int(11) NOT NULL,
  `trenink_id` int(11) NOT NULL,
  `poznamka` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_note` (`sportovec_id`,`trenink_id`),
  KEY `trenink_id` (`trenink_id`),
  CONSTRAINT `sportovec_poznamka_ibfk_1` FOREIGN KEY (`sportovec_id`) REFERENCES `sportovci` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sportovec_poznamka_ibfk_2` FOREIGN KEY (`trenink_id`) REFERENCES `treninky` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sportovec_skupina` (
  `sportovec_id` int(11) NOT NULL,
  `skupina_id` int(11) NOT NULL,
  PRIMARY KEY (`sportovec_id`,`skupina_id`),
  KEY `idx_skupina` (`skupina_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sportovist` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `kod` varchar(40) NOT NULL,
  `nazev` varchar(120) NOT NULL,
  `je_verejne` tinyint(1) NOT NULL DEFAULT 0,
  `max_kapacita` int(11) NOT NULL DEFAULT 5,
  `poradi` int(11) NOT NULL DEFAULT 0,
  `aktivni` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_kod` (`kod`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `staff_account_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `trainer_id` int(11) NOT NULL,
  `actor_trainer_id` int(11) NOT NULL,
  `action` varchar(48) NOT NULL,
  `reason` varchar(1000) NOT NULL,
  `before_json` longtext DEFAULT NULL,
  `after_json` longtext NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_staff_account_target` (`trainer_id`,`id`),
  KEY `fk_staff_account_actor` (`actor_trainer_id`),
  CONSTRAINT `fk_staff_account_actor` FOREIGN KEY (`actor_trainer_id`) REFERENCES `treneri` (`id`),
  CONSTRAINT `fk_staff_account_target` FOREIGN KEY (`trainer_id`) REFERENCES `treneri` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `staff_position_assignment_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `trainer_id` int(11) NOT NULL,
  `actor_trainer_id` int(11) NOT NULL,
  `action` varchar(64) NOT NULL,
  `before_json` longtext DEFAULT NULL,
  `after_json` longtext NOT NULL,
  `reason` varchar(1000) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_staff_position_assignment_target` (`trainer_id`,`created_at`,`id`),
  KEY `fk_staff_position_assignment_actor` (`actor_trainer_id`),
  CONSTRAINT `fk_staff_position_assignment_actor` FOREIGN KEY (`actor_trainer_id`) REFERENCES `treneri` (`id`),
  CONSTRAINT `fk_staff_position_assignment_trainer` FOREIGN KEY (`trainer_id`) REFERENCES `treneri` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `staff_position_switch_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `trainer_id` int(11) NOT NULL,
  `from_position_code` varchar(64) DEFAULT NULL,
  `to_position_code` varchar(64) NOT NULL,
  `used_superadmin` tinyint(1) NOT NULL DEFAULT 0,
  `reason` varchar(1000) NOT NULL,
  `ip_address` varchar(45) NOT NULL DEFAULT '',
  `user_agent` varchar(500) NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_staff_position_switch_actor` (`trainer_id`,`created_at`,`id`),
  KEY `fk_staff_position_switch_from` (`from_position_code`),
  KEY `fk_staff_position_switch_to` (`to_position_code`),
  CONSTRAINT `fk_staff_position_switch_from` FOREIGN KEY (`from_position_code`) REFERENCES `staff_positions` (`code`),
  CONSTRAINT `fk_staff_position_switch_to` FOREIGN KEY (`to_position_code`) REFERENCES `staff_positions` (`code`),
  CONSTRAINT `fk_staff_position_switch_trainer` FOREIGN KEY (`trainer_id`) REFERENCES `treneri` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `staff_positions` (
  `code` varchar(64) NOT NULL,
  `label` varchar(160) NOT NULL,
  `sort_order` int(11) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `staff_superadmins` (
  `trainer_id` int(11) NOT NULL,
  `granted_by_trainer_id` int(11) DEFAULT NULL,
  `granted_at` datetime NOT NULL DEFAULT current_timestamp(),
  `reason` varchar(1000) NOT NULL,
  PRIMARY KEY (`trainer_id`),
  KEY `fk_staff_superadmins_actor` (`granted_by_trainer_id`),
  CONSTRAINT `fk_staff_superadmins_actor` FOREIGN KEY (`granted_by_trainer_id`) REFERENCES `treneri` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_staff_superadmins_trainer` FOREIGN KEY (`trainer_id`) REFERENCES `treneri` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `staff_user_positions` (
  `trainer_id` int(11) NOT NULL,
  `position_code` varchar(64) NOT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `assigned_by_trainer_id` int(11) DEFAULT NULL,
  `assigned_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`trainer_id`,`position_code`),
  KEY `idx_staff_user_positions_default` (`trainer_id`,`is_default`),
  KEY `fk_staff_user_positions_position` (`position_code`),
  KEY `fk_staff_user_positions_actor` (`assigned_by_trainer_id`),
  CONSTRAINT `fk_staff_user_positions_actor` FOREIGN KEY (`assigned_by_trainer_id`) REFERENCES `treneri` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_staff_user_positions_position` FOREIGN KEY (`position_code`) REFERENCES `staff_positions` (`code`),
  CONSTRAINT `fk_staff_user_positions_trainer` FOREIGN KEY (`trainer_id`) REFERENCES `treneri` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `story_nastaveni` (
  `typ` enum('skupina','podskupina') NOT NULL,
  `entita_id` int(11) NOT NULL,
  `barva` varchar(10) DEFAULT NULL,
  `hlavicka` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `paticka` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `barva_textu` varchar(10) DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`typ`,`entita_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `story_vygenerovane` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `trenink_id` int(11) NOT NULL,
  `soubor` varchar(255) NOT NULL,
  `created` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `trenink_id` (`trenink_id`),
  CONSTRAINT `story_vygenerovane_ibfk_1` FOREIGN KEY (`trenink_id`) REFERENCES `treninky` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stripe_webhook_events` (
  `event_id` varchar(255) NOT NULL,
  `event_type` varchar(128) NOT NULL,
  `payment_id` bigint(20) unsigned DEFAULT NULL,
  `payload_sha256` char(64) NOT NULL,
  `processing_status` varchar(24) NOT NULL,
  `received_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `processed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`event_id`),
  KEY `idx_stripe_webhook_payment` (`payment_id`,`received_at`),
  CONSTRAINT `fk_stripe_webhook_payment` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tagy` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nazev` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nazev` (`nazev`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `training_roster_expected` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `link_id` bigint(20) unsigned NOT NULL,
  `sportovec_id` int(11) NOT NULL,
  `roster_member_id` bigint(20) unsigned DEFAULT NULL,
  `member_valid_from_snapshot` date NOT NULL,
  `member_valid_to_snapshot` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_training_roster_expected_person` (`link_id`,`sportovec_id`),
  KEY `idx_training_roster_expected_person` (`sportovec_id`,`link_id`),
  KEY `fk_training_roster_expected_member` (`roster_member_id`),
  CONSTRAINT `fk_training_roster_expected_link` FOREIGN KEY (`link_id`) REFERENCES `training_roster_links` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_training_roster_expected_member` FOREIGN KEY (`roster_member_id`) REFERENCES `club_roster_members` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_training_roster_expected_person` FOREIGN KEY (`sportovec_id`) REFERENCES `sportovci` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `training_roster_links` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `plan_id` int(11) DEFAULT NULL,
  `trenink_id` int(11) DEFAULT NULL,
  `team_id` bigint(20) unsigned NOT NULL,
  `target_date` date NOT NULL,
  `team_code_snapshot` varchar(48) NOT NULL,
  `team_name_snapshot` varchar(160) NOT NULL,
  `created_by_trainer_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_training_roster_plan_team` (`plan_id`,`team_id`),
  UNIQUE KEY `uq_training_roster_training_team` (`trenink_id`,`team_id`),
  KEY `idx_training_roster_team_date` (`team_id`,`target_date`),
  KEY `fk_training_roster_actor` (`created_by_trainer_id`),
  CONSTRAINT `fk_training_roster_actor` FOREIGN KEY (`created_by_trainer_id`) REFERENCES `treneri` (`id`),
  CONSTRAINT `fk_training_roster_plan` FOREIGN KEY (`plan_id`) REFERENCES `planovane_treninky` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_training_roster_team` FOREIGN KEY (`team_id`) REFERENCES `club_teams` (`id`),
  CONSTRAINT `fk_training_roster_training` FOREIGN KEY (`trenink_id`) REFERENCES `treninky` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_training_roster_owner` CHECK (`plan_id` is null <> (`trenink_id` is null))
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `treneri` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `velo_user_id` int(11) DEFAULT NULL COMMENT 'ID uživatele ve Velocota DB (pro SSO bridge)',
  `jmeno` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `heslo` varchar(100) NOT NULL,
  `role` enum('trener','hlavni','admin') NOT NULL DEFAULT 'trener',
  `aktivni` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Aktivní účet (0 = deaktivován)',
  `session_version` int(10) unsigned NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_velo_user` (`velo_user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `trenink_mereni` (
  `trenink_id` int(10) unsigned NOT NULL,
  `mereni_id` int(10) unsigned NOT NULL,
  `poradi` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`trenink_id`,`mereni_id`),
  KEY `idx_mereni` (`mereni_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `trenink_podskupina` (
  `trenink_id` int(11) NOT NULL,
  `podskupina_id` int(11) NOT NULL,
  PRIMARY KEY (`trenink_id`,`podskupina_id`),
  KEY `podskupina_id` (`podskupina_id`),
  CONSTRAINT `trenink_podskupina_ibfk_1` FOREIGN KEY (`trenink_id`) REFERENCES `treninky` (`id`),
  CONSTRAINT `trenink_podskupina_ibfk_2` FOREIGN KEY (`podskupina_id`) REFERENCES `podskupiny` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `trenink_skupina` (
  `trenink_id` int(11) NOT NULL,
  `skupina_id` int(11) NOT NULL,
  PRIMARY KEY (`trenink_id`,`skupina_id`),
  KEY `skupina_id` (`skupina_id`),
  CONSTRAINT `trenink_skupina_ibfk_1` FOREIGN KEY (`trenink_id`) REFERENCES `treninky` (`id`),
  CONSTRAINT `trenink_skupina_ibfk_2` FOREIGN KEY (`skupina_id`) REFERENCES `skupiny` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `trenink_sportovec` (
  `trenink_id` int(11) NOT NULL,
  `sportovec_id` int(11) NOT NULL,
  PRIMARY KEY (`trenink_id`,`sportovec_id`),
  KEY `fk_ts_sportovci` (`sportovec_id`),
  CONSTRAINT `fk_ts_sportovci` FOREIGN KEY (`sportovec_id`) REFERENCES `sportovci` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ts_treninky` FOREIGN KEY (`trenink_id`) REFERENCES `treninky` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `trenink_tag` (
  `trenink_id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL,
  PRIMARY KEY (`trenink_id`,`tag_id`),
  KEY `tag_id` (`tag_id`),
  CONSTRAINT `trenink_tag_ibfk_1` FOREIGN KEY (`trenink_id`) REFERENCES `treninky` (`id`) ON DELETE CASCADE,
  CONSTRAINT `trenink_tag_ibfk_2` FOREIGN KEY (`tag_id`) REFERENCES `tagy` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `trenink_trener` (
  `trenink_id` int(11) NOT NULL,
  `trener_id` int(11) NOT NULL,
  PRIMARY KEY (`trenink_id`,`trener_id`),
  KEY `idx_tt_trenink_id` (`trenink_id`),
  KEY `idx_tt_trener_id` (`trener_id`),
  CONSTRAINT `trenink_trener_ibfk_1` FOREIGN KEY (`trenink_id`) REFERENCES `treninky` (`id`) ON DELETE CASCADE,
  CONSTRAINT `trenink_trener_ibfk_2` FOREIGN KEY (`trener_id`) REFERENCES `treneri` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `treninky` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `datum` date NOT NULL,
  `napln` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `poznamka` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `delka` decimal(5,2) DEFAULT 0.00,
  `kategorie` enum('silnice','mtb','draha','cyklokros','posilovna','atletika','cviceni','plavani') DEFAULT NULL,
  `obrazky` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mereni` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mereni_json` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ucto_audit_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uzivatel_id` int(11) DEFAULT NULL,
  `akce` varchar(100) DEFAULT NULL,
  `tabulka` varchar(50) DEFAULT NULL,
  `zaznam_id` int(11) DEFAULT NULL,
  `detail` text DEFAULT NULL,
  `ip_adresa` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `datum` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ucto_dokumenty` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vozidlo_id` int(11) DEFAULT NULL,
  `typ` varchar(50) DEFAULT NULL,
  `platnost_do` date DEFAULT NULL,
  `nazev_souboru` varchar(255) DEFAULT NULL,
  `cesta_k_souboru` varchar(255) DEFAULT NULL,
  `poznamka` text DEFAULT NULL,
  `nahrano_kym` int(11) DEFAULT NULL,
  `nahrano_datum` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `vozidlo_id` (`vozidlo_id`),
  CONSTRAINT `ucto_dokumenty_ibfk_1` FOREIGN KEY (`vozidlo_id`) REFERENCES `ucto_vozidla` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ucto_jizdy` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vozidlo_id` int(11) DEFAULT NULL,
  `datum_start` datetime DEFAULT NULL,
  `datum_konec` datetime DEFAULT NULL,
  `tachometr_start` int(11) DEFAULT NULL,
  `tachometr_konec` int(11) DEFAULT NULL,
  `poloha_start` varchar(255) DEFAULT NULL,
  `poloha_konec` varchar(255) DEFAULT NULL,
  `ridic_id` int(11) DEFAULT NULL,
  `ridic_text` varchar(100) DEFAULT NULL,
  `poznamka` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `vozidlo_id` (`vozidlo_id`),
  CONSTRAINT `ucto_jizdy_ibfk_1` FOREIGN KEY (`vozidlo_id`) REFERENCES `ucto_vozidla` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ucto_servis` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vozidlo_id` int(11) NOT NULL,
  `popis` text NOT NULL,
  `provedeno_dne` date NOT NULL,
  `planovana_kontrola` date DEFAULT NULL,
  `dokument` varchar(255) DEFAULT NULL,
  `vytvoril_id` int(11) DEFAULT NULL,
  `vytvoreno` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `vozidlo_id` (`vozidlo_id`),
  KEY `vytvoril_id` (`vytvoril_id`),
  CONSTRAINT `ucto_servis_ibfk_1` FOREIGN KEY (`vozidlo_id`) REFERENCES `ucto_vozidla` (`id`),
  CONSTRAINT `ucto_servis_ibfk_2` FOREIGN KEY (`vytvoril_id`) REFERENCES `treneri` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ucto_tankovani` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vozidlo_id` int(11) DEFAULT NULL,
  `datum` datetime DEFAULT NULL,
  `mnozstvi_litru` float DEFAULT NULL,
  `cena` decimal(10,2) DEFAULT NULL,
  `uctenka_path` varchar(255) DEFAULT NULL,
  `ridic_id` int(11) DEFAULT NULL,
  `ridic_text` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `vozidlo_id` (`vozidlo_id`),
  CONSTRAINT `ucto_tankovani_ibfk_1` FOREIGN KEY (`vozidlo_id`) REFERENCES `ucto_vozidla` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ucto_uctenky` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `soubor` varchar(255) DEFAULT NULL,
  `castka` decimal(10,2) DEFAULT NULL,
  `vozidlo_id` int(11) DEFAULT NULL,
  `udalost_id` int(11) DEFAULT NULL,
  `poznamka` text DEFAULT NULL,
  `platba` enum('hotove_tym','kartou_tym','vlastni_karta','vlastni_hotovost') DEFAULT NULL,
  `kategorie` enum('zavodni_oddil','velodrom_areal','cyklo_krouzek','ostatni') NOT NULL DEFAULT 'ostatni',
  `zadano_id` int(11) DEFAULT NULL,
  `zadano_text` varchar(255) DEFAULT NULL,
  `vytvoreno` datetime NOT NULL DEFAULT current_timestamp(),
  `nahrano_kym` varchar(255) DEFAULT NULL,
  `nahrano_jmenem` varchar(255) DEFAULT NULL,
  `datum` date NOT NULL,
  `obrazek_path` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `vozidlo_id` (`vozidlo_id`),
  KEY `udalost_id` (`udalost_id`),
  KEY `zadano_id` (`zadano_id`),
  CONSTRAINT `ucto_uctenky_ibfk_1` FOREIGN KEY (`vozidlo_id`) REFERENCES `ucto_vozidla` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ucto_uctenky_ibfk_2` FOREIGN KEY (`udalost_id`) REFERENCES `ucto_udalosti` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ucto_uctenky_ibfk_3` FOREIGN KEY (`zadano_id`) REFERENCES `treneri` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ucto_udalosti` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nazev` varchar(255) NOT NULL,
  `popis` text DEFAULT NULL,
  `datum_od` datetime NOT NULL,
  `datum_do` datetime NOT NULL,
  `typ` enum('zavod','soustredeni','trenink','jine') NOT NULL DEFAULT 'jine',
  `zalohova_castka` decimal(10,2) DEFAULT NULL,
  `zalohu_predal` varchar(255) DEFAULT NULL,
  `stav` enum('otevrena','uzavrena') NOT NULL DEFAULT 'otevrena',
  `vytvoril_id` int(11) NOT NULL,
  `vytvoreno` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ucto_vozidla` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `znacka_model` varchar(100) DEFAULT NULL,
  `spz` varchar(20) DEFAULT NULL,
  `rok_vyroby` year(4) DEFAULT NULL,
  `palivo` varchar(20) DEFAULT NULL,
  `stk_datum` date DEFAULT NULL,
  `dalnicni_znamka_datum` date DEFAULT NULL,
  `poznamka` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `venue_operation_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `target_type` varchar(24) NOT NULL,
  `target_id` bigint(20) unsigned NOT NULL,
  `actor_trainer_id` int(11) NOT NULL,
  `action` varchar(32) NOT NULL,
  `reason` varchar(1000) NOT NULL,
  `payload_json` longtext NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_venue_operation_target` (`target_type`,`target_id`,`id`),
  KEY `fk_venue_operation_actor` (`actor_trainer_id`),
  CONSTRAINT `fk_venue_operation_actor` FOREIGN KEY (`actor_trainer_id`) REFERENCES `treneri` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `verejne_rezervace` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lekce_id` int(11) NOT NULL,
  `uzivatel_id` int(11) NOT NULL,
  `stav` enum('ceka','potvrzena','zamitnuta','zrusena','cekaci_listina') NOT NULL DEFAULT 'ceka',
  `zaplaceno` tinyint(1) NOT NULL DEFAULT 0,
  `poznamka_klienta` text DEFAULT NULL,
  `poznamka_trenera` text DEFAULT NULL,
  `potvrzovaci_token` varchar(64) DEFAULT NULL,
  `cas_rezervace` timestamp NOT NULL DEFAULT current_timestamp(),
  `cas_potvrzeni` timestamp NULL DEFAULT NULL,
  `slot_cas_od` time DEFAULT NULL,
  `slot_cas_do` time DEFAULT NULL,
  `potvrzovaci_token_expires_at` datetime DEFAULT NULL,
  `sportovec_id` int(11) DEFAULT NULL,
  `active_token` varchar(16) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_public_booking_active_person` (`lekce_id`,`sportovec_id`,`active_token`),
  KEY `idx_lekce` (`lekce_id`),
  KEY `idx_uzivatel` (`uzivatel_id`),
  KEY `idx_token` (`potvrzovaci_token`),
  KEY `fk_public_booking_person` (`sportovec_id`),
  KEY `idx_public_booking_capacity` (`lekce_id`,`slot_cas_od`,`stav`,`id`),
  CONSTRAINT `fk_public_booking_person` FOREIGN KEY (`sportovec_id`) REFERENCES `sportovci` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50003 TRIGGER trg_public_velodrome_legacy_close
BEFORE UPDATE ON verejne_rezervace
FOR EACH ROW
BEGIN
    IF OLD.sportovec_id IS NOT NULL
       AND OLD.active_token='active'
       AND NEW.active_token='active'
       AND NEW.stav IN ('zrusena','zamitnuta') THEN
        SET NEW.active_token=NULL;
        INSERT INTO public_velodrome_reservation_events
            (reservation_id,actor_type,actor_id,action,from_status,to_status,note)
        VALUES
            (OLD.id,'legacy',NULL,'legacy_close',OLD.stav,NEW.stav,
             'Stav změněn starším rezervačním průchodem; aktivní token byl bezpečně uvolněn.');
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `verejni_uzivatele` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `jmeno` varchar(80) NOT NULL,
  `prijmeni` varchar(80) NOT NULL,
  `email` varchar(160) NOT NULL,
  `heslo_hash` varchar(255) NOT NULL,
  `telefon` varchar(20) DEFAULT NULL,
  `verifikacni_token` varchar(64) DEFAULT NULL,
  `email_overeno` tinyint(1) NOT NULL DEFAULT 0,
  `aktivni` tinyint(1) NOT NULL DEFAULT 1,
  `registrovan` timestamp NOT NULL DEFAULT current_timestamp(),
  `session_version` int(10) unsigned NOT NULL DEFAULT 1,
  `verifikacni_token_expires_at` datetime DEFAULT NULL,
  `trener_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_email` (`email`),
  UNIQUE KEY `uq_verejni_uzivatele_trener` (`trener_id`),
  KEY `idx_token` (`verifikacni_token`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `zatezove_testy` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sportovec_id` int(11) NOT NULL,
  `datum` date NOT NULL,
  `vek` int(11) DEFAULT NULL,
  `vaha_kg` decimal(5,2) DEFAULT NULL,
  `vyska_cm` decimal(5,2) DEFAULT NULL,
  `popis_interni` text DEFAULT NULL,
  `popis_sportovec` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_zatezove_testy_sportovec` (`sportovec_id`),
  CONSTRAINT `fk_zatezove_testy_sportovec` FOREIGN KEY (`sportovec_id`) REFERENCES `sportovci` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `zatezove_testy_soubory` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `test_id` int(11) NOT NULL,
  `typ` enum('public_img','internal_img','other') NOT NULL,
  `nazev` varchar(255) DEFAULT NULL,
  `cesta` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_zatezove_testy_soubory_test` (`test_id`),
  CONSTRAINT `fk_zatezove_testy_soubory_test` FOREIGN KEY (`test_id`) REFERENCES `zatezove_testy` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `zavod_fotka` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `zavod_id` int(11) NOT NULL,
  `soubor` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `zavod_id` (`zavod_id`),
  CONSTRAINT `zavod_fotka_ibfk_1` FOREIGN KEY (`zavod_id`) REFERENCES `zavody` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `zavod_import` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `zavod_id` int(11) NOT NULL,
  `soubor` varchar(255) NOT NULL,
  `typ` enum('pdf','xls','xlsx') NOT NULL,
  `import_dt` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `zavod_id` (`zavod_id`),
  CONSTRAINT `zavod_import_ibfk_1` FOREIGN KEY (`zavod_id`) REFERENCES `zavody` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `zavod_mereni` (
  `zavod_id` int(11) NOT NULL,
  `mereni_id` int(11) NOT NULL,
  `poradi` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`zavod_id`,`mereni_id`),
  KEY `idx_mereni_id` (`mereni_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `zavod_podskupina` (
  `zavod_id` int(11) NOT NULL,
  `podskupina_id` int(11) NOT NULL,
  PRIMARY KEY (`zavod_id`,`podskupina_id`),
  KEY `podskupina_id` (`podskupina_id`),
  CONSTRAINT `zavod_podskupina_ibfk_1` FOREIGN KEY (`zavod_id`) REFERENCES `zavody` (`id`) ON DELETE CASCADE,
  CONSTRAINT `zavod_podskupina_ibfk_2` FOREIGN KEY (`podskupina_id`) REFERENCES `podskupiny` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `zavod_skupina` (
  `zavod_id` int(11) NOT NULL,
  `skupina_id` int(11) NOT NULL,
  PRIMARY KEY (`zavod_id`,`skupina_id`),
  KEY `skupina_id` (`skupina_id`),
  CONSTRAINT `zavod_skupina_ibfk_1` FOREIGN KEY (`zavod_id`) REFERENCES `zavody` (`id`) ON DELETE CASCADE,
  CONSTRAINT `zavod_skupina_ibfk_2` FOREIGN KEY (`skupina_id`) REFERENCES `skupiny` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `zavod_sportovec` (
  `zavod_id` int(11) NOT NULL,
  `sportovec_id` int(11) NOT NULL,
  `poradi` int(11) DEFAULT NULL,
  `cas` varchar(50) DEFAULT NULL,
  `body` decimal(5,2) DEFAULT NULL,
  `jmeno_ext` varchar(200) DEFAULT NULL,
  `klub` varchar(200) DEFAULT NULL,
  `kategorie_start` varchar(100) DEFAULT NULL,
  `result_contract_version` varchar(40) DEFAULT NULL,
  `result_status` varchar(16) DEFAULT NULL,
  `result_time_ms` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`zavod_id`,`sportovec_id`),
  KEY `sportovec_id` (`sportovec_id`),
  CONSTRAINT `zavod_sportovec_ibfk_1` FOREIGN KEY (`zavod_id`) REFERENCES `zavody` (`id`) ON DELETE CASCADE,
  CONSTRAINT `zavod_sportovec_ibfk_2` FOREIGN KEY (`sportovec_id`) REFERENCES `sportovci` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `zavod_trener` (
  `zavod_id` int(11) NOT NULL,
  `trener_id` int(11) NOT NULL,
  PRIMARY KEY (`zavod_id`,`trener_id`),
  KEY `trener_id` (`trener_id`),
  CONSTRAINT `zavod_trener_ibfk_1` FOREIGN KEY (`zavod_id`) REFERENCES `zavody` (`id`) ON DELETE CASCADE,
  CONSTRAINT `zavod_trener_ibfk_2` FOREIGN KEY (`trener_id`) REFERENCES `treneri` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `zavody` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `datum` date NOT NULL,
  `kategorie` enum('silnice','draha','mtb') NOT NULL DEFAULT 'silnice',
  `popis` text NOT NULL,
  `poznamka` text DEFAULT NULL,
  `url_vysledky` varchar(500) DEFAULT NULL,
  `trener_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `trener_id` (`trener_id`),
  CONSTRAINT `zavody_ibfk_1` FOREIGN KEY (`trener_id`) REFERENCES `treneri` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
SET FOREIGN_KEY_CHECKS=1;
