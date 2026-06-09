DELIMITER //
CREATE PROCEDURE sp_messenger_count_unread(IN p_current_user_id INT)
BEGIN
    SELECT COUNT(*) AS unread_total
        FROM messages m
    INNER JOIN chat_participants cp ON m.chat_id = cp.chat_id
        WHERE cp.user_id = p_current_user_id
        AND m.sender_id != p_current_user_id
        AND m.is_read = 0;
END //

DELIMITER ;