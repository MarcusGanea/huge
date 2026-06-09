DELIMITER //
CREATE PROCEDURE sp_messenger_get_available_users_for_new_chat(
IN p_current_user_id INT
)
BEGIN
SELECT user_id, user_name, user_email, user_has_avatar
FROM users
WHERE user_id != p_current_user_id
AND user_deleted = 0
AND user_active = 1
ORDER BY user_name ASC;
END //
DELIMITER ;