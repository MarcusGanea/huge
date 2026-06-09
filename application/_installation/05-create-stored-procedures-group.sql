DELIMITER //

DROP PROCEDURE IF EXISTS sp_group_create_group_chat //
CREATE PROCEDURE sp_group_create_group_chat(
    IN p_creator_id INT,
    IN p_group_name VARCHAR(255)
)
BEGIN
    DECLARE v_chat_id BIGINT;

    INSERT INTO chats (chat_name, chat_type)
    VALUES (p_group_name, 'GROUP');

    SET v_chat_id = LAST_INSERT_ID();

    INSERT INTO chat_participants (chat_id, user_id)
    VALUES (v_chat_id, p_creator_id);

    SELECT v_chat_id AS chat_id;
END //

DROP PROCEDURE IF EXISTS sp_group_join_group_chat //
CREATE PROCEDURE sp_group_join_group_chat(
    IN p_user_id INT,
    IN p_chat_id INT
)
BEGIN
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
END //

DROP PROCEDURE IF EXISTS sp_group_is_user_in_chat //
CREATE PROCEDURE sp_group_is_user_in_chat(
    IN p_user_id INT,
    IN p_chat_id INT
)
BEGIN
    SELECT 1 AS is_in_chat
    FROM chat_participants
    WHERE user_id = p_user_id
      AND chat_id = p_chat_id
    LIMIT 1;
END //

DROP PROCEDURE IF EXISTS sp_group_get_my_group_chats //
CREATE PROCEDURE sp_group_get_my_group_chats(IN p_current_user_id INT)
BEGIN
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
END //

DROP PROCEDURE IF EXISTS sp_group_get_available_group_chats //
CREATE PROCEDURE sp_group_get_available_group_chats(IN p_current_user_id INT)
BEGIN
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
END //

DROP PROCEDURE IF EXISTS sp_group_get_group_chat_data //
CREATE PROCEDURE sp_group_get_group_chat_data(IN p_chat_id INT)
BEGIN
    SELECT chat_id, chat_name
    FROM chats
    WHERE chat_id = p_chat_id
      AND chat_type = 'GROUP'
    LIMIT 1;
END //

DROP PROCEDURE IF EXISTS sp_group_send_message //
CREATE PROCEDURE sp_group_send_message(
    IN p_sender_id INT,
    IN p_chat_id INT,
    IN p_content TEXT
)
BEGIN
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
END //

DROP PROCEDURE IF EXISTS sp_group_get_non_members_for_group //
CREATE PROCEDURE sp_group_get_non_members_for_group(IN p_chat_id INT)
BEGIN
    SELECT u.user_id, u.user_name
    FROM users u
    WHERE u.user_active = 1
      AND u.user_id NOT IN (
          SELECT cp.user_id
          FROM chat_participants cp
          WHERE cp.chat_id = p_chat_id
      )
    ORDER BY u.user_name ASC;
END //

DROP PROCEDURE IF EXISTS sp_group_add_member //
CREATE PROCEDURE sp_group_add_member(
    IN p_chat_id INT,
    IN p_user_id INT
)
BEGIN
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
END //

DELIMITER ;
