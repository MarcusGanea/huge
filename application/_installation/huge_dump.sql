-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Erstellungszeit: 11. Jun 2026 um 08:22
-- Server-Version: 10.4.32-MariaDB
-- PHP-Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Datenbank: `huge`
--

DELIMITER $$
--
-- Prozeduren
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_group_add_member` (IN `p_chat_id` INT, IN `p_user_id` INT)   BEGIN
    IF EXISTS (
        SELECT 1 FROM chats
        WHERE chat_id = p_chat_id
          AND chat_type = 'GROUP'
        LIMIT 1
    )
    AND NOT EXISTS (
        SELECT 1 FROM chat_participants
        WHERE chat_id = p_chat_id
          AND user_id = p_user_id
        LIMIT 1
    ) THEN
        INSERT INTO chat_participants (chat_id, user_id)
        VALUES (p_chat_id, p_user_id);
    END IF;

    SELECT ROW_COUNT() AS affected_rows;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_group_create_group_chat` (IN `p_creator_id` INT, IN `p_group_name` VARCHAR(255))   BEGIN
    DECLARE v_chat_id BIGINT;

    INSERT INTO chats (chat_name, chat_type)
    VALUES (p_group_name, 'GROUP');

    SET v_chat_id = LAST_INSERT_ID();

    INSERT INTO chat_participants (chat_id, user_id)
    VALUES (v_chat_id, p_creator_id);

    SELECT v_chat_id AS chat_id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_group_get_available_group_chats` (IN `p_current_user_id` INT)   BEGIN
    SELECT
        c.chat_id,
        c.chat_name,
        (
            SELECT COUNT(*)
            FROM chat_participants cp2
            WHERE cp2.chat_id = c.chat_id
        ) AS member_count
    FROM chats c
    WHERE c.chat_type = 'GROUP'
      AND NOT EXISTS (
          SELECT 1 FROM chat_participants cp
          WHERE cp.chat_id = c.chat_id
            AND cp.user_id = p_current_user_id
      )
    ORDER BY c.chat_name ASC;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_group_get_group_chat_data` (IN `p_chat_id` INT)   BEGIN
    SELECT chat_id, chat_name
    FROM chats
    WHERE chat_id = p_chat_id
      AND chat_type = 'GROUP'
    LIMIT 1;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_group_get_my_group_chats` (IN `p_current_user_id` INT)   BEGIN
    SELECT
        c.chat_id,
        c.chat_name,
        (
            SELECT COUNT(*)
            FROM chat_participants cp2
            WHERE cp2.chat_id = c.chat_id
        ) AS member_count,
        (
            SELECT COUNT(*)
            FROM messages m2
            WHERE m2.chat_id = c.chat_id
              AND m2.sender_id != p_current_user_id
              AND m2.is_read = 0
        ) AS unread_count
    FROM chats c
    INNER JOIN chat_participants cp
        ON c.chat_id = cp.chat_id
    WHERE cp.user_id = p_current_user_id
      AND c.chat_type = 'GROUP'
    ORDER BY c.chat_id DESC;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_group_get_non_members_for_group` (IN `p_chat_id` INT)   BEGIN
    SELECT u.user_id, u.user_name
    FROM users u
    WHERE u.user_active = 1
      AND u.user_id NOT IN (
          SELECT cp.user_id
          FROM chat_participants cp
          WHERE cp.chat_id = p_chat_id
      )
    ORDER BY u.user_name ASC;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_group_is_user_in_chat` (IN `p_user_id` INT, IN `p_chat_id` INT)   BEGIN
    SELECT 1 AS is_in_chat
    FROM chat_participants
    WHERE user_id = p_user_id
      AND chat_id = p_chat_id
    LIMIT 1;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_group_join_group_chat` (IN `p_user_id` INT, IN `p_chat_id` INT)   BEGIN
    IF EXISTS (
        SELECT 1 FROM chats
        WHERE chat_id = p_chat_id
          AND chat_type = 'GROUP'
        LIMIT 1
    )
    AND NOT EXISTS (
        SELECT 1 FROM chat_participants
        WHERE chat_id = p_chat_id
          AND user_id = p_user_id
        LIMIT 1
    ) THEN
        INSERT INTO chat_participants (chat_id, user_id)
        VALUES (p_chat_id, p_user_id);
    END IF;

    SELECT ROW_COUNT() AS affected_rows;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_group_send_message` (IN `p_sender_id` INT, IN `p_chat_id` INT, IN `p_content` TEXT)   BEGIN
    IF EXISTS (
        SELECT 1
        FROM chat_participants
        WHERE user_id = p_sender_id
          AND chat_id = p_chat_id
        LIMIT 1
    ) THEN
        INSERT INTO messages (chat_id, sender_id, content, is_read)
        VALUES (p_chat_id, p_sender_id, p_content, 0);
    END IF;

    SELECT ROW_COUNT() AS affected_rows;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_messenger_count_unread` (IN `p_current_user_id` INT)   BEGIN
    SELECT COUNT(*) AS unread_total
    FROM messages m
    INNER JOIN chat_participants cp
        ON m.chat_id = cp.chat_id
    WHERE cp.user_id = p_current_user_id
      AND m.sender_id != p_current_user_id
      AND m.is_read = 0;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_messenger_create_direct_chat` (IN `p_user_id` INT, IN `p_partner_id` INT)   BEGIN
    DECLARE v_chat_name VARCHAR(255);
    DECLARE v_chat_id BIGINT;

    SET v_chat_name = CONCAT('DM_', LEAST(p_user_id, p_partner_id), '_', GREATEST(p_user_id, p_partner_id));

    START TRANSACTION;

    INSERT INTO chats (chat_name, chat_type)
    VALUES (v_chat_name, 'DM');

    SET v_chat_id = LAST_INSERT_ID();

    INSERT INTO chat_participants (chat_id, user_id)
    VALUES (v_chat_id, p_user_id), (v_chat_id, p_partner_id);

    COMMIT;

    SELECT v_chat_id AS chat_id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_messenger_get_all_chats` ()   BEGIN
    SELECT chat_id, chat_name
    FROM chats;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_messenger_get_available_users_for_new_chat` (IN `p_current_user_id` INT)   BEGIN
    SELECT user_id, user_name, user_email, user_has_avatar
    FROM users
    WHERE user_id != p_current_user_id
      AND user_deleted = 0
      AND user_active = 1
    ORDER BY user_name ASC;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_messenger_get_direct_chat_id_by_users` (IN `p_user_id` INT, IN `p_partner_id` INT)   BEGIN
    SELECT cp1.chat_id
    FROM chat_participants cp1
    INNER JOIN chat_participants cp2
        ON cp1.chat_id = cp2.chat_id
    INNER JOIN chats c
        ON c.chat_id = cp1.chat_id
    WHERE cp1.user_id = p_user_id
      AND cp2.user_id = p_partner_id
      AND c.chat_type = 'DM'
      AND (
          SELECT COUNT(*)
          FROM chat_participants cp3
          WHERE cp3.chat_id = cp1.chat_id
      ) = 2
    LIMIT 1;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_messenger_get_messages_by_chat_id` (IN `p_chat_id` INT, IN `p_current_user_id` INT)   BEGIN
    SELECT
        m.message_id,
        m.chat_id,
        m.sender_id,
        m.content,
        m.is_read,
        u.user_name AS sender_name
    FROM messages m
    INNER JOIN users u
        ON m.sender_id = u.user_id
    WHERE m.chat_id = p_chat_id
      AND EXISTS (
          SELECT 1
          FROM chat_participants cp
          WHERE cp.chat_id = m.chat_id
            AND cp.user_id = p_current_user_id
      )
    ORDER BY m.message_id ASC;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_messenger_get_my_chats` (IN `p_current_user_id` INT)   BEGIN
    SELECT DISTINCT
        c.chat_id,
        c.chat_name,
        u.user_id AS partner_id,
        u.user_name AS partner_name,
        u.user_email AS partner_email,
        u.user_has_avatar,
        (
            SELECT COUNT(*)
            FROM messages m2
            WHERE m2.chat_id = c.chat_id
              AND m2.sender_id != p_current_user_id
              AND m2.is_read = 0
        ) AS unread_count
    FROM chats c
    INNER JOIN chat_participants cp_me
        ON c.chat_id = cp_me.chat_id
    INNER JOIN chat_participants cp_other
        ON c.chat_id = cp_other.chat_id
    INNER JOIN users u
        ON cp_other.user_id = u.user_id
    WHERE cp_me.user_id = p_current_user_id
      AND cp_other.user_id != p_current_user_id
      AND c.chat_type = 'DM'
    ORDER BY c.chat_id DESC;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_messenger_get_or_create_direct_chat` (IN `p_user_id` INT, IN `p_partner_id` INT)   BEGIN
    DECLARE v_chat_id BIGINT DEFAULT NULL;
    DECLARE v_chat_name VARCHAR(255);

    IF p_user_id IS NULL OR p_partner_id IS NULL OR p_user_id = p_partner_id THEN
        SELECT NULL AS chat_id;
    ELSE
        SELECT cp1.chat_id
          INTO v_chat_id
        FROM chat_participants cp1
        INNER JOIN chat_participants cp2
            ON cp1.chat_id = cp2.chat_id
        INNER JOIN chats c
            ON c.chat_id = cp1.chat_id
        WHERE cp1.user_id = p_user_id
          AND cp2.user_id = p_partner_id
          AND c.chat_type = 'DM'
          AND (
              SELECT COUNT(*)
              FROM chat_participants cp3
              WHERE cp3.chat_id = cp1.chat_id
          ) = 2
        LIMIT 1;

        IF v_chat_id IS NULL THEN
            SET v_chat_name = CONCAT('DM_', LEAST(p_user_id, p_partner_id), '_', GREATEST(p_user_id, p_partner_id));

            START TRANSACTION;

            INSERT INTO chats (chat_name, chat_type)
            VALUES (v_chat_name, 'DM');

            SET v_chat_id = LAST_INSERT_ID();

            INSERT INTO chat_participants (chat_id, user_id)
            VALUES (v_chat_id, p_user_id), (v_chat_id, p_partner_id);

            COMMIT;
        END IF;

        SELECT v_chat_id AS chat_id;
    END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_messenger_get_partner_data` (IN `p_partner_id` INT)   BEGIN
    SELECT user_id, user_name, user_email, user_has_avatar
    FROM users
    WHERE user_id = p_partner_id
    LIMIT 1;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_messenger_insert_message` (IN `p_chat_id` INT, IN `p_sender_id` INT, IN `p_content` TEXT)   BEGIN
    INSERT INTO messages (chat_id, sender_id, content, is_read)
    VALUES (p_chat_id, p_sender_id, p_content, 0);

    SELECT ROW_COUNT() AS affected_rows;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_messenger_mark_chat_as_read` (IN `p_chat_id` INT, IN `p_current_user_id` INT)   BEGIN
    UPDATE messages
    SET is_read = 1
    WHERE chat_id = p_chat_id
      AND sender_id != p_current_user_id
      AND is_read = 0;

    SELECT ROW_COUNT() AS affected_rows;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `chats`
--

CREATE TABLE `chats` (
  `chat_id` int(11) NOT NULL,
  `chat_name` varchar(40) NOT NULL,
  `chat_type` enum('DM','GROUP') NOT NULL DEFAULT 'DM'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Daten für Tabelle `chats`
--

INSERT INTO `chats` (`chat_id`, `chat_name`, `chat_type`) VALUES
(1, 'DM_1_2', 'DM'),
(2, 'DM_1_9', 'DM'),
(3, 'Team LBS Eibiswald', 'GROUP'),
(4, 'Gruppe 2', 'GROUP'),
(5, 'Team LBS Eibiswald', 'GROUP'),
(6, 'test gruppe', 'GROUP'),
(7, 'test gruppe 2', 'GROUP'),
(8, 'no', 'GROUP'),
(9, 'DM_8_9', 'DM'),
(10, 'SP Gruppe Test', 'GROUP');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `chat_participants`
--

CREATE TABLE `chat_participants` (
  `chat_participants_id` int(11) NOT NULL,
  `chat_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Daten für Tabelle `chat_participants`
--

INSERT INTO `chat_participants` (`chat_participants_id`, `chat_id`, `user_id`) VALUES
(21, 1, 1),
(22, 1, 2),
(23, 2, 1),
(24, 2, 9),
(25, 3, 1),
(26, 4, 1),
(27, 4, 2),
(28, 4, 9),
(29, 4, 8),
(30, 4, 10),
(31, 5, 1),
(32, 6, 1),
(33, 6, 2),
(34, 6, 8),
(35, 7, 1),
(36, 7, 2),
(37, 7, 9),
(38, 7, 8),
(39, 7, 10),
(40, 8, 9),
(41, 8, 1),
(42, 8, 10),
(43, 9, 9),
(44, 9, 8),
(45, 10, 1),
(46, 10, 2),
(47, 10, 9);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `messages`
--

CREATE TABLE `messages` (
  `message_id` int(11) NOT NULL,
  `chat_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `content` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Daten für Tabelle `messages`
--

INSERT INTO `messages` (`message_id`, `chat_id`, `sender_id`, `content`, `is_read`) VALUES
(1, 1, 1, 'hi', 1),
(2, 1, 1, 'hello?', 1),
(3, 1, 2, 'hiiii', 1),
(4, 1, 1, 'helloo', 1),
(5, 1, 1, 'hi', 1),
(6, 1, 1, 'hola', 1),
(7, 2, 1, 'hey', 1),
(8, 1, 1, 'das', 1),
(9, 1, 1, 'hi', 1),
(10, 3, 1, 'hi', 0),
(11, 4, 1, 'hi', 1),
(12, 4, 1, 'jo', 1),
(13, 4, 1, 'hi', 1),
(14, 7, 1, 'haloo', 1),
(15, 7, 1, 'hi', 1),
(16, 7, 2, 'oh hallo', 1),
(17, 7, 2, 'hier ist demo 2', 1),
(18, 7, 9, 'hi leute', 1),
(19, 2, 9, 'hey', 1),
(20, 8, 9, 'ich teste hier', 1),
(21, 2, 9, 'hi', 1),
(22, 2, 9, 'der count', 1),
(23, 2, 9, 'der count', 1),
(24, 2, 9, 'sollte jetzt 4 sein', 1),
(25, 2, 1, 'test', 1),
(26, 2, 9, 'test', 1),
(27, 3, 1, 'Testnachricht mit Stored Procedure', 0),
(28, 2, 1, 'Testnachricht mit Stored Procedure', 0),
(29, 2, 1, 'test 2', 0),
(30, 1, 1, 'SP test message', 0),
(31, 1, 1, 'Hallo Gruppe via SP', 0);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `notes`
--

CREATE TABLE `notes` (
  `note_id` int(11) UNSIGNED NOT NULL,
  `note_text` text NOT NULL,
  `user_id` int(11) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='user notes';

--
-- Daten für Tabelle `notes`
--

INSERT INTO `notes` (`note_id`, `note_text`, `user_id`) VALUES
(1, 'test note', 1),
(2, 'test note test', 1);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL COMMENT 'auto incrementing user_id of each user, unique index',
  `session_id` varchar(48) DEFAULT NULL COMMENT 'stores session cookie id to prevent session concurrency',
  `user_name` varchar(64) NOT NULL COMMENT 'user''s name, unique',
  `user_password_hash` varchar(255) DEFAULT NULL COMMENT 'user''s password in salted and hashed format',
  `user_email` varchar(254) NOT NULL COMMENT 'user''s email, unique',
  `user_active` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'user''s activation status',
  `user_deleted` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'user''s deletion status',
  `user_account_type` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'user''s account type (basic, premium, etc)',
  `user_has_avatar` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 if user has a local avatar, 0 if not',
  `user_remember_me_token` varchar(64) DEFAULT NULL COMMENT 'user''s remember-me cookie token',
  `user_creation_timestamp` bigint(20) DEFAULT NULL COMMENT 'timestamp of the creation of user''s account',
  `user_suspension_timestamp` bigint(20) DEFAULT NULL COMMENT 'Timestamp till the end of a user suspension',
  `user_last_login_timestamp` bigint(20) DEFAULT NULL COMMENT 'timestamp of user''s last login',
  `user_failed_logins` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'user''s failed login attempts',
  `user_last_failed_login` int(10) DEFAULT NULL COMMENT 'unix timestamp of last failed login attempt',
  `user_activation_hash` varchar(80) DEFAULT NULL COMMENT 'user''s email verification hash string',
  `user_password_reset_hash` char(80) DEFAULT NULL COMMENT 'user''s password reset code',
  `user_password_reset_timestamp` bigint(20) DEFAULT NULL COMMENT 'timestamp of the password reset request',
  `user_provider_type` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='user data';

--
-- Daten für Tabelle `users`
--

INSERT INTO `users` (`user_id`, `session_id`, `user_name`, `user_password_hash`, `user_email`, `user_active`, `user_deleted`, `user_account_type`, `user_has_avatar`, `user_remember_me_token`, `user_creation_timestamp`, `user_suspension_timestamp`, `user_last_login_timestamp`, `user_failed_logins`, `user_last_failed_login`, `user_activation_hash`, `user_password_reset_hash`, `user_password_reset_timestamp`, `user_provider_type`) VALUES
(1, '4n83s4c47aqhd4ioavs6slndrg', 'demo', '$2y$10$OvprunjvKOOhM1h9bzMPs.vuwGIsOqZbw88rzSyGCTJTcE61g5WXi', 'demo@demo.com', 1, 0, 7, 0, NULL, 1422205178, NULL, 1781011815, 0, NULL, NULL, NULL, NULL, 'DEFAULT'),
(2, NULL, 'demo2', '$2y$10$OvprunjvKOOhM1h9bzMPs.vuwGIsOqZbw88rzSyGCTJTcE61g5WXi', 'demo2@demo.com', 1, 0, 2, 0, NULL, 1422205178, NULL, 1780649839, 0, NULL, NULL, NULL, NULL, 'DEFAULT'),
(4, NULL, 'marcus', '$2y$10$bxbNiu.8.fD/.PBOgtlFzO.MsBD3iPE/k9ONHzsByWUGCe7spOAqa', 'ganea.marcus1@gmail.com', 0, 0, 1, 0, NULL, 1778590798, NULL, NULL, 5, 1781007156, '55aed39fbdc92d4dbd94bc6de28ab37b90b4e80d9b5fbaf2f7100b81a7d5ccd8e423eba1073f8ff7', NULL, NULL, 'DEFAULT'),
(5, NULL, 'marcus2', '$2y$10$dXnw7p/9mk4.yj7XVRRXFu1gy3N0fh6TVWzyQfi0GnnIF9V/nDxki', 'ganea.marcus2@gmail.com', 0, 0, 1, 0, NULL, 1778591073, NULL, NULL, 0, NULL, '2e6af3113a74efe00981e3d61cbc418deb35dc38013acfd38b4b2aaf5b54b265bdbb01d10d34ef78', NULL, NULL, 'DEFAULT'),
(6, NULL, 'marcus3', '$2y$10$xOiUGD8aEGNZ4wSgMt1/I.L1JOpvrCmBQV/LlRpTdXDJCVvHEACPu', 'ganea.marcus3@gmail.com', 0, 0, 1, 0, NULL, 1778591247, NULL, NULL, 0, NULL, '5a8162eeb0902aa0dd59b4a7fed604d39f96f06b6a38d51e2ae42435d57c7500948f980a90c3b26a', NULL, NULL, 'DEFAULT'),
(7, NULL, 'marcus4', '$2y$10$wuBAyxziB3ZEkNb/TMlHG.j5eZfHXfZSbrtlDZho9I/v8dCn3IGHe', 'ganea.marcus4@gmail.com', 0, 0, 1, 0, NULL, 1778591459, NULL, NULL, 0, NULL, 'c00814f603260bb2cff764bd19b17fbe9bc315d8b4609b02b8a81724b704b64cea7adc988629473b', NULL, NULL, 'DEFAULT'),
(8, NULL, 'marcus5', '$2y$10$UngfAs1eGUiQLrPWQlMiW.2N6BdLZHUrAWztUy5lGEIP0ffKVp.GG', 'ganea.marcus5@gmail.com', 1, 0, 1, 0, NULL, 1778592225, NULL, NULL, 0, NULL, '80d28f48793efffbefbc5e63c9fe2ae102a7b9f9223ce7989899703e60777bf2f6eb6bf0ae8f9a67', NULL, NULL, 'DEFAULT'),
(9, NULL, 'ganea', '$2y$10$ZRiRID5AttYt/qxnppLxiOlDGKax9IWBuYnv/36Ng660Dj/Epahuu', 'ganea.marcus@gmail.com', 1, 0, 1, 0, NULL, 1778592745, NULL, 1781009681, 0, NULL, '448665949e07d64a45b6b699534c17840e583cf6034fec0afff62783afef971f4dc96dcf51ca8d1f', NULL, NULL, 'DEFAULT'),
(10, NULL, 'test', '$2y$10$uEOqaCEoX1yZ/53.wUkSquOcjqxn04jbLBc1F9CEKdLkcBkOizE8.', 'ganea.marcus9@gmail.com', 1, 0, 1, 0, NULL, 1778593033, NULL, NULL, 0, NULL, '8d9ab3cead0c43b5c6672e86cebc08a27fdae24cb592bbfe48d76e6e1da508997c60a5ad292cdea3', NULL, NULL, 'DEFAULT');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `user_roles`
--

CREATE TABLE `user_roles` (
  `user_role_id` int(11) NOT NULL,
  `user_role_name` varchar(40) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Daten für Tabelle `user_roles`
--

INSERT INTO `user_roles` (`user_role_id`, `user_role_name`) VALUES
(1, 'Gast'),
(2, 'User'),
(7, 'Admin');

--
-- Indizes der exportierten Tabellen
--

--
-- Indizes für die Tabelle `chats`
--
ALTER TABLE `chats`
  ADD PRIMARY KEY (`chat_id`);

--
-- Indizes für die Tabelle `chat_participants`
--
ALTER TABLE `chat_participants`
  ADD PRIMARY KEY (`chat_participants_id`),
  ADD KEY `fk_chat_participants_user` (`user_id`),
  ADD KEY `fk_chat_participants_chat` (`chat_id`);

--
-- Indizes für die Tabelle `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`message_id`),
  ADD KEY `fk_messages_sender` (`sender_id`),
  ADD KEY `fk_messages_chat` (`chat_id`);

--
-- Indizes für die Tabelle `notes`
--
ALTER TABLE `notes`
  ADD PRIMARY KEY (`note_id`);

--
-- Indizes für die Tabelle `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `user_name` (`user_name`),
  ADD UNIQUE KEY `user_email` (`user_email`);

--
-- Indizes für die Tabelle `user_roles`
--
ALTER TABLE `user_roles`
  ADD PRIMARY KEY (`user_role_id`);

--
-- AUTO_INCREMENT für exportierte Tabellen
--

--
-- AUTO_INCREMENT für Tabelle `chats`
--
ALTER TABLE `chats`
  MODIFY `chat_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT für Tabelle `chat_participants`
--
ALTER TABLE `chat_participants`
  MODIFY `chat_participants_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- AUTO_INCREMENT für Tabelle `messages`
--
ALTER TABLE `messages`
  MODIFY `message_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT für Tabelle `notes`
--
ALTER TABLE `notes`
  MODIFY `note_id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT für Tabelle `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'auto incrementing user_id of each user, unique index', AUTO_INCREMENT=11;

--
-- Constraints der exportierten Tabellen
--

--
-- Constraints der Tabelle `chat_participants`
--
ALTER TABLE `chat_participants`
  ADD CONSTRAINT `fk_chat_participants_chat` FOREIGN KEY (`chat_id`) REFERENCES `chats` (`chat_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_chat_participants_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints der Tabelle `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `fk_messages_chat` FOREIGN KEY (`chat_id`) REFERENCES `chats` (`chat_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_messages_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
