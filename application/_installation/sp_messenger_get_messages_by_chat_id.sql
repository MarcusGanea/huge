DELIMITER //
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
DELIMITER ;