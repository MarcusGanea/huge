DELIMITER //
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
DELIMITER ;