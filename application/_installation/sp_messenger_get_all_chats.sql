DELIMITER //
CREATE PROCEDURE sp_messenger_get_all_chats()
BEGIN
    SELECT chat_id, chat_name
    FROM chats;
END //
DELIMITER ;