<?php

class MessengerModel{


    public static function getMyChats()
    {
        $database = DatabaseFactory::getFactory()->getConnection();

        $sql = "SELECT DISTINCT
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
                          AND m2.sender_id != :current_user_id
                          AND m2.is_read = 0
                    ) AS unread_count
                FROM chats c
                INNER JOIN chat_participants cp_me
                    ON c.chat_id = cp_me.chat_id
                INNER JOIN chat_participants cp_other
                    ON c.chat_id = cp_other.chat_id
                INNER JOIN users u
                    ON cp_other.user_id = u.user_id
                WHERE cp_me.user_id = :current_user_id
                  AND cp_other.user_id != :current_user_id
                  AND c.chat_type = 'DM'
                ORDER BY c.chat_id DESC";

        $query = $database->prepare($sql);
        $query->execute(array(':current_user_id' => Session::get('user_id')));

        $chats = $query->fetchAll();

        foreach ($chats as $chat) {
            array_walk_recursive($chat, 'Filter::XSSFilter');
            $chat->partner_avatar_link = (
                Config::get('USE_GRAVATAR')
                    ? AvatarModel::getGravatarLinkByEmail($chat->partner_email)
                    : AvatarModel::getPublicAvatarFilePathOfUser($chat->user_has_avatar, $chat->partner_id)
            );
        }

        return $chats;
    }

    public static function getAvailableUsersForNewChat()
    {
        $database = DatabaseFactory::getFactory()->getConnection();

        $sql = "SELECT user_id, user_name, user_email, user_has_avatar
                FROM users
                WHERE user_id != :current_user_id
                  AND user_deleted = 0
                  AND user_active = 1
                ORDER BY user_name ASC";

        $query = $database->prepare($sql);
        $query->execute(array(':current_user_id' => Session::get('user_id')));

        $users = $query->fetchAll();

        foreach ($users as $user) {
            array_walk_recursive($user, 'Filter::XSSFilter');
            $user->user_avatar_link = (
                Config::get('USE_GRAVATAR')
                    ? AvatarModel::getGravatarLinkByEmail($user->user_email)
                    : AvatarModel::getPublicAvatarFilePathOfUser($user->user_has_avatar, $user->user_id)
            );
        }

        return $users;
    }

    public static function getPartnerData($partner_id)
    {
        $database = DatabaseFactory::getFactory()->getConnection();

        $sql = "SELECT user_id, user_name, user_email, user_has_avatar
                FROM users
                WHERE user_id = :partner_id
                LIMIT 1";

        $query = $database->prepare($sql);
        $query->execute(array(':partner_id' => $partner_id));

        $user = $query->fetch();

        if ($user) {
            array_walk_recursive($user, 'Filter::XSSFilter');
        }

        return $user;
    }

    public static function getDirectChatIdByUsers($user_id, $partner_id)
    {
        $database = DatabaseFactory::getFactory()->getConnection();

        $sql = "SELECT cp1.chat_id
                FROM chat_participants cp1
                INNER JOIN chat_participants cp2
                    ON cp1.chat_id = cp2.chat_id
                WHERE cp1.user_id = :user_id
                  AND cp2.user_id = :partner_id
                  AND (
                      SELECT COUNT(*)
                      FROM chat_participants cp3
                      WHERE cp3.chat_id = cp1.chat_id
                  ) = 2
                LIMIT 1";

        $query = $database->prepare($sql);
        $query->execute(array(
            ':user_id' => $user_id,
            ':partner_id' => $partner_id
        ));

        $result = $query->fetch();

        return $result ? $result->chat_id : null;
    }

    public static function createDirectChat($user_id, $partner_id)
    {
        $database = DatabaseFactory::getFactory()->getConnection();

        $chat_name = 'DM_' . min($user_id, $partner_id) . '_' . max($user_id, $partner_id);

        $sql = "INSERT INTO chats (chat_name, chat_type) VALUES (:chat_name, 'DM')";
        $query = $database->prepare($sql);
        $query->execute(array(':chat_name' => $chat_name));

        $chat_id = $database->lastInsertId();

        $sql = "INSERT INTO chat_participants (chat_id, user_id) VALUES (:chat_id, :user_id)";
        $query = $database->prepare($sql);
        $query->execute(array(':chat_id' => $chat_id, ':user_id' => $user_id));
        $query->execute(array(':chat_id' => $chat_id, ':user_id' => $partner_id));

        return $chat_id;
    }

    public static function getOrCreateDirectChat($user_id, $partner_id)
    {
        if (!$partner_id || $user_id == $partner_id) {
            return null;
        }

        $chat_id = self::getDirectChatIdByUsers($user_id, $partner_id);

        if ($chat_id !== null) {
                return $chat_id;
            }

        return self::createDirectChat($user_id, $partner_id);
    }

    public static function getMessagesByChatId($chat_id, $current_user_id)
    {
        $database = DatabaseFactory::getFactory()->getConnection();

        $sql = "SELECT
                    m.message_id,
                    m.chat_id,
                    m.sender_id,
                    m.content,
                    m.is_read,
                    u.user_name AS sender_name
                FROM messages m
                INNER JOIN users u
                    ON m.sender_id = u.user_id
                WHERE m.chat_id = :chat_id
                  AND EXISTS (
                      SELECT 1
                      FROM chat_participants cp
                      WHERE cp.chat_id = m.chat_id
                        AND cp.user_id = :current_user_id
                  )
                ORDER BY m.message_id ASC";

        $query = $database->prepare($sql);
        $query->execute(array(
            ':chat_id' => $chat_id,
            ':current_user_id' => $current_user_id
        ));

        $messages = $query->fetchAll();

        foreach ($messages as $message) {
            array_walk_recursive($message, 'Filter::XSSFilter');
        }

        return $messages;
    }

    public static function sendMessageToPartner($sender_id, $partner_id, $content)
    {
        if (!$partner_id || !$content || $sender_id == $partner_id) {
            return false;
        }

        $chat_id = self::getOrCreateDirectChat($sender_id, $partner_id);

        if ($chat_id === null) {
            return false;
        }

        $database = DatabaseFactory::getFactory()->getConnection();

        $sql = "INSERT INTO messages (chat_id, sender_id, content, is_read)
                VALUES (:chat_id, :sender_id, :content, 0)";

        $query = $database->prepare($sql);
        $query->execute(array(
            ':chat_id' => $chat_id,
            ':sender_id' => $sender_id,
            ':content' => $content
        ));

        return $query->rowCount() === 1;
    }

    public static function markChatAsRead($chat_id, $current_user_id)
    {
        $database = DatabaseFactory::getFactory()->getConnection();

        $sql = "UPDATE messages
                SET is_read = 1
                WHERE chat_id = :chat_id
                  AND sender_id != :current_user_id
                  AND is_read = 0";

        $query = $database->prepare($sql);
        $query->execute(array(
            ':chat_id' => $chat_id,
            ':current_user_id' => $current_user_id
        ));
    }

    public static function countUnreadMessages($current_user_id)
    {
        $database = DatabaseFactory::getFactory()->getConnection();

        $sql = "SELECT COUNT(*) AS unread_total
                FROM messages m
                INNER JOIN chat_participants cp
                    ON m.chat_id = cp.chat_id
                WHERE cp.user_id = :current_user_id
                  AND m.sender_id != :current_user_id
                  AND m.is_read = 0";

        $query = $database->prepare($sql);
        $query->execute(array(':current_user_id' => $current_user_id));

        $result = $query->fetch();

        return $result ? (int) $result->unread_total : 0;
    }



    public static function getAllChats()
    {
        $database = DatabaseFactory::getFactory()->getConnection();

        $sql = "SELECT chat_id, chat_name FROM chats";
        $query = $database->prepare($sql);
        $query->execute();

        $all_chats = array();

        foreach ($query->fetchAll() as $chat) {

            // all elements of array passed to Filter::XSSFilter for XSS sanitation, have a look into
            // application/core/Filter.php for more info on how to use. Removes (possibly bad) JavaScript etc from
            // the user's values
            array_walk_recursive($chat, 'Filter::XSSFilter');

            $all_chats[$chat->chat_id] = new stdClass();
            $all_chats[$chat->chat_id]->chat_id = $chat->chat_id;
            $all_chats[$chat->chat_id]->chat_name = $chat->chat_name;
            //$all_users_profiles[$user->user_id]->user_avatar_link = (Config::get('USE_GRAVATAR') ? AvatarModel::getGravatarLinkByEmail($user->user_email) : AvatarModel::getPublicAvatarFilePathOfUser($user->user_has_avatar, $user->user_id));
        }

        return $all_chats;
    }






}