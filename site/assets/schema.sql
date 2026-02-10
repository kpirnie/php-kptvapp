-- ============================================================
-- KPTV Database Schema
-- ============================================================

-- ------------------------------------------------------------
-- Users
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `kptv_users`;
CREATE TABLE `kptv_users` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `u_role` int(11) NOT NULL DEFAULT 0,
  `u_active` tinyint(1) NOT NULL DEFAULT 0,
  `u_name` varchar(128) NOT NULL,
  `u_pass` char(97) NOT NULL,
  `u_hash` char(64) NOT NULL,
  `u_email` varchar(512) NOT NULL,
  `u_lname` varchar(128) DEFAULT NULL,
  `u_fname` varchar(128) DEFAULT NULL,
  `u_created` datetime NOT NULL DEFAULT current_timestamp(),
  `u_updated` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `last_login` datetime DEFAULT NULL,
  `login_attempts` int(11) DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_uname` (`u_name`),
  UNIQUE KEY `idx_uemail` (`u_email`),
  KEY `idx_uactive` (`u_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- Stream Providers
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `kptv_stream_providers`;
CREATE TABLE `kptv_stream_providers` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `u_id` bigint(20) NOT NULL,
  `sp_should_filter` tinyint(1) NOT NULL DEFAULT 1,
  `sp_priority` int(11) NOT NULL DEFAULT 99,
  `sp_name` varchar(256) NOT NULL,
  `sp_cnx_limit` int(11) NOT NULL DEFAULT 1,
  `sp_type` tinyint(1) NOT NULL DEFAULT 0,
  `sp_domain` varchar(256) NOT NULL,
  `sp_username` varchar(1024) DEFAULT NULL,
  `sp_password` varchar(1024) DEFAULT NULL,
  `sp_stream_type` tinyint(1) NOT NULL DEFAULT 0,
  `sp_refresh_period` int(11) NOT NULL DEFAULT 3,
  `sp_last_synced` datetime DEFAULT NULL,
  `sp_added` datetime NOT NULL DEFAULT current_timestamp(),
  `sp_updated` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_uid` (`u_id`),
  KEY `idx_spname` (`sp_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- Streams
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `kptv_streams`;
CREATE TABLE `kptv_streams` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `u_id` bigint(20) NOT NULL,
  `p_id` bigint(20) NOT NULL DEFAULT 0,
  `s_type_id` tinyint(4) NOT NULL DEFAULT 0,
  `s_active` tinyint(1) NOT NULL DEFAULT 0,
  `s_channel` varchar(32) NOT NULL DEFAULT '0',
  `s_name` varchar(1024) NOT NULL,
  `s_orig_name` varchar(1024) NOT NULL,
  `s_stream_uri` varchar(2048) NOT NULL DEFAULT '',
  `s_tvg_id` varchar(1024) DEFAULT NULL,
  `s_tvg_group` varchar(1024) DEFAULT NULL,
  `s_tvg_logo` varchar(2048) DEFAULT NULL,
  `s_extras` varchar(2048) DEFAULT NULL,
  `s_created` datetime NOT NULL DEFAULT current_timestamp(),
  `s_updated` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  -- -- B-tree indexes
  KEY `idx_uid` (`u_id`),
  KEY `idx_pid` (`p_id`),
  KEY `idx_stypeid` (`s_type_id`),
  KEY `idx_sactive` (`s_active`),
  KEY `idx_schannel` (`s_channel`),
  KEY `idx_sactive_stvgid` (`s_active`, `s_tvg_id`(255)),
  KEY `idx_sname_supdated` (`s_name`(255), `s_updated`),
  -- -- Fulltext indexes
  FULLTEXT KEY `idx_ft_sname` (`s_name`),
  FULLTEXT KEY `idx_ft_sorigname` (`s_orig_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- Stream Filters
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `kptv_stream_filters`;
CREATE TABLE `kptv_stream_filters` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `u_id` bigint(20) NOT NULL,
  `sf_active` tinyint(1) NOT NULL DEFAULT 1,
  `sf_type_id` tinyint(4) NOT NULL DEFAULT 0,
  `sf_filter` varchar(1024) NOT NULL,
  `sf_created` datetime NOT NULL DEFAULT current_timestamp(),
  `sf_updated` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  -- -- Composite covers the common lookup pattern, individual indexes on
  -- -- uid, sfactive, and sftypeid are redundant with leftmost prefix
  KEY `idx_uid_sfactive_sftypeid` (`u_id`, `sf_active`, `sf_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- Stream Missing (tracking streams not found across providers)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `kptv_stream_missing`;
CREATE TABLE `kptv_stream_missing` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `u_id` bigint(20) NOT NULL,
  `p_id` bigint(20) NOT NULL,
  `stream_id` bigint(20) NOT NULL DEFAULT 0,
  `other_id` bigint(20) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_uid` (`u_id`),
  KEY `idx_pid` (`p_id`),
  KEY `idx_streamid` (`stream_id`),
  KEY `idx_otherid` (`other_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- Stream Temp (staging table for provider syncs)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `kptv_stream_temp`;
CREATE TABLE `kptv_stream_temp` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `u_id` bigint(20) NOT NULL,
  `p_id` bigint(20) NOT NULL,
  `s_type_id` tinyint(4) NOT NULL DEFAULT 0,
  `s_orig_name` varchar(1024) NOT NULL,
  `s_stream_uri` varchar(2048) NOT NULL DEFAULT '',
  `s_tvg_id` varchar(512) DEFAULT NULL,
  `s_tvg_logo` varchar(2048) DEFAULT NULL,
  `s_extras` varchar(2048) DEFAULT NULL,
  `s_group` varchar(1024) DEFAULT NULL,
  `s_orig_name_lower` varchar(255) GENERATED ALWAYS AS (lcase(`s_orig_name`)) VIRTUAL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- Stored Procedures
-- ============================================================

-- ------------------------------------------------------------
-- CleanupStreams
-- Removes orphaned streams (provider deleted) and deduplicates
-- by s_stream_uri keeping only the newest row
-- ------------------------------------------------------------
DELIMITER $$
DROP PROCEDURE IF EXISTS `CleanupStreams`$$
CREATE PROCEDURE `CleanupStreams`()
BEGIN
    START TRANSACTION;

    -- -- Remove streams whose provider no longer exists
    DELETE FROM kptv_streams
    WHERE NOT EXISTS (
        SELECT 1
        FROM kptv_stream_providers
        WHERE kptv_stream_providers.id = kptv_streams.p_id
    );

    -- -- Deduplicate by stream URI, keep newest
    DELETE s1
    FROM kptv_streams s1
    LEFT JOIN (
        SELECT MAX(id) AS max_id, s_stream_uri
        FROM kptv_streams
        GROUP BY s_stream_uri
    ) s2 ON s1.id = s2.max_id
    WHERE s2.max_id IS NULL;

    -- -- Clear the staging table
    TRUNCATE TABLE kptv_stream_temp;

    COMMIT;
END$$

-- ------------------------------------------------------------
-- ResetStreamIDs
-- Renumbers kptv_streams.id sequentially starting from 1
-- and resets auto_increment
-- ------------------------------------------------------------
DROP PROCEDURE IF EXISTS `ResetStreamIDs`$$
CREATE PROCEDURE `ResetStreamIDs`()
BEGIN
    DECLARE v_max_id BIGINT;
    DECLARE v_next_val BIGINT;
    DECLARE v_sql TEXT;

    SET FOREIGN_KEY_CHECKS = 0;

    -- -- Strip auto_increment so we can drop PK
    ALTER TABLE kptv_streams MODIFY id BIGINT NOT NULL;
    ALTER TABLE kptv_streams DROP PRIMARY KEY;

    -- -- Renumber sequentially
    SET @counter = 0;
    UPDATE kptv_streams
    SET id = (@counter := @counter + 1)
    ORDER BY id;

    -- -- Restore PK and auto_increment
    ALTER TABLE kptv_streams
    MODIFY id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY;

    SELECT COALESCE(MAX(id), 0) INTO v_max_id FROM kptv_streams;
    SET v_next_val = v_max_id + 1;
    SET v_sql = CONCAT('ALTER TABLE kptv_streams AUTO_INCREMENT = ', v_next_val);

    PREPARE stmt FROM v_sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;

    SET FOREIGN_KEY_CHECKS = 1;
END$$
DELIMITER ;