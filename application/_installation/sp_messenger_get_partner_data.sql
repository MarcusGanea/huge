DELIMITER //
CREATE PROCEDURE sp_messenger_get_partner_data(
IN p_partner_id INT
)
BEGIN
SELECT user_id, user_name, user_email, user_has_avatar
FROM users
WHERE user_id = p_partner_id
LIMIT 1;
END //
DELIMITER ;