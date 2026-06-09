DELIMITER //
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
END //
DELIMITER ;