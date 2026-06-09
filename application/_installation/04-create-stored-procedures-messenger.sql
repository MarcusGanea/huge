DELIMITER //

DROP PROCEDURE IF EXISTS sp_messenger_get_my_chats //
CREATE PROCEDURE sp_messenger_get_my_chats(IN p_current_user_id INT)
BEGIN
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
END //

DROP PROCEDURE IF EXISTS sp_messenger_get_available_users_for_new_chat //
CREATE PROCEDURE sp_messenger_get_available_users_for_new_chat(IN p_current_user_id INT)
BEGIN
    SELECT user_id, user_name, user_email, user_has_avatar
    FROM users
    WHERE user_id != p_current_user_id
      AND user_deleted = 0
      AND user_active = 1
    ORDER BY user_name ASC;
END //

DROP PROCEDURE IF EXISTS sp_messenger_get_partner_data //
CREATE PROCEDURE sp_messenger_get_partner_data(IN p_partner_id INT)
BEGIN
    SELECT user_id, user_name, user_email, user_has_avatar
    FROM users
    WHERE user_id = p_partner_id
    LIMIT 1;
END //

DROP PROCEDURE IF EXISTS sp_messenger_get_direct_chat_id_by_users //
CREATE PROCEDURE sp_messenger_get_direct_chat_id_by_users(
    IN p_user_id INT,
    IN p_partner_id INT
)
BEGIN
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
END //

DROP PROCEDURE IF EXISTS sp_messenger_create_direct_chat //
CREATE PROCEDURE sp_messenger_create_direct_chat(
    IN p_user_id INT,
    IN p_partner_id INT
)
BEGIN
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
END //

DROP PROCEDURE IF EXISTS sp_messenger_get_or_create_direct_chat //
CREATE PROCEDURE sp_messenger_get_or_create_direct_chat(
    IN p_user_id INT,
    IN p_partner_id INT
)
BEGIN
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
END //

DROP PROCEDURE IF EXISTS sp_messenger_get_messages_by_chat_id //
CREATE PROCEDURE sp_messenger_get_messages_by_chat_id(
    IN p_chat_id INT,
    IN p_current_user_id INT
)
BEGIN
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
END //

DROP PROCEDURE IF EXISTS sp_messenger_insert_message //
CREATE PROCEDURE sp_messenger_insert_message(
    IN p_chat_id INT,
    IN p_sender_id INT,
    IN p_content TEXT
)
BEGIN
    INSERT INTO messages (chat_id, sender_id, content, is_read)
    VALUES (p_chat_id, p_sender_id, p_content, 0);

    SELECT ROW_COUNT() AS affected_rows;
END //

DROP PROCEDURE IF EXISTS sp_messenger_mark_chat_as_read //
CREATE PROCEDURE sp_messenger_mark_chat_as_read(
    IN p_chat_id INT,
    IN p_current_user_id INT
)
BEGIN
    UPDATE messages
    SET is_read = 1
    WHERE chat_id = p_chat_id
      AND sender_id != p_current_user_id
      AND is_read = 0;

    SELECT ROW_COUNT() AS affected_rows;
END //

DROP PROCEDURE IF EXISTS sp_messenger_count_unread //
CREATE PROCEDURE sp_messenger_count_unread(IN p_current_user_id INT)
BEGIN
    SELECT COUNT(*) AS unread_total
    FROM messages m
    INNER JOIN chat_participants cp
        ON m.chat_id = cp.chat_id
    WHERE cp.user_id = p_current_user_id
      AND m.sender_id != p_current_user_id
      AND m.is_read = 0;
END //

DROP PROCEDURE IF EXISTS sp_messenger_get_all_chats //
CREATE PROCEDURE sp_messenger_get_all_chats()
BEGIN
    SELECT chat_id, chat_name
    FROM chats;
END //

DELIMITER ;
