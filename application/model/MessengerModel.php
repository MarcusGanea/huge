<?php

class MessengerModel{


    public static function getMyChats()
    {
        $database = DatabaseFactory::getFactory()->getConnection();

        $sql = "CALL sp_messenger_get_my_chats(:current_user_id)";

        $query = $database->prepare($sql);
        $query->execute(array(':current_user_id' => Session::get('user_id')));

        $chats = $query->fetchAll();
        $query->closeCursor();

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

        $sql = "CALL sp_messenger_get_available_users_for_new_chat(:current_user_id)";

        $query = $database->prepare($sql);
        $query->execute(array(':current_user_id' => Session::get('user_id')));

        $users = $query->fetchAll();
        $query->closeCursor();

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

        $sql = "CALL sp_messenger_get_partner_data(:partner_id)";

        $query = $database->prepare($sql);
        $query->execute(array(':partner_id' => $partner_id));

        $user = $query->fetch();
        $query->closeCursor();

        if ($user) {
            array_walk_recursive($user, 'Filter::XSSFilter');
        }

        return $user;
    }

    public static function getDirectChatIdByUsers($user_id, $partner_id)
    {
        $database = DatabaseFactory::getFactory()->getConnection();

        $sql = "CALL sp_messenger_get_direct_chat_id_by_users(:user_id, :partner_id)";

        $query = $database->prepare($sql);
        $query->execute(array(
            ':user_id' => $user_id,
            ':partner_id' => $partner_id
        ));

        $result = $query->fetch();
        $query->closeCursor();

        return $result ? $result->chat_id : null;
    }

    public static function createDirectChat($user_id, $partner_id)
    {
        $database = DatabaseFactory::getFactory()->getConnection();

        $sql = "CALL sp_messenger_create_direct_chat(:user_id, :partner_id)";
        $query = $database->prepare($sql);
        $query->execute(array(
            ':user_id' => $user_id,
            ':partner_id' => $partner_id
        ));

        $result = $query->fetch();
        $query->closeCursor();

        return $result ? (int) $result->chat_id : null;
    }

    public static function getOrCreateDirectChat($user_id, $partner_id)
    {
        if (!$partner_id || $user_id == $partner_id) {
            return null;
        }

        $database = DatabaseFactory::getFactory()->getConnection();

        $sql = "CALL sp_messenger_get_or_create_direct_chat(:user_id, :partner_id)";
        $query = $database->prepare($sql);
        $query->execute(array(
            ':user_id' => $user_id,
            ':partner_id' => $partner_id
        ));

        $result = $query->fetch();
        $query->closeCursor();

        return $result ? (int) $result->chat_id : null;
    }

    public static function getMessagesByChatId($chat_id, $current_user_id)
    {
        $database = DatabaseFactory::getFactory()->getConnection();

        $sql = "CALL sp_messenger_get_messages_by_chat_id(:chat_id, :current_user_id)";

        $query = $database->prepare($sql);
        $query->execute(array(
            ':chat_id' => $chat_id,
            ':current_user_id' => $current_user_id
        ));

        $messages = $query->fetchAll();
        $query->closeCursor();

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

        $sql = "CALL sp_messenger_insert_message(:chat_id, :sender_id, :content)";

        $query = $database->prepare($sql);
        $query->execute(array(
            ':chat_id' => $chat_id,
            ':sender_id' => $sender_id,
            ':content' => $content
        ));

        $result = $query->fetch();
        $query->closeCursor();

        return $result && (int) $result->affected_rows === 1;
    }

    public static function markChatAsRead($chat_id, $current_user_id)
    {
        $database = DatabaseFactory::getFactory()->getConnection();

        $sql = "CALL sp_messenger_mark_chat_as_read(:chat_id, :current_user_id)";

        $query = $database->prepare($sql);
        $query->execute(array(
            ':chat_id' => $chat_id,
            ':current_user_id' => $current_user_id
        ));

        $query->closeCursor();
    }

    public static function countUnreadMessages($current_user_id)
    {
        $database = DatabaseFactory::getFactory()->getConnection();

        $sql = "CALL sp_messenger_count_unread(:current_user_id)";

        $query = $database->prepare($sql);
        $query->execute(array(':current_user_id' => $current_user_id));

        $result = $query->fetch();
        $query->closeCursor();

        return $result ? (int) $result->unread_total : 0;
    }



    public static function getAllChats()
    {
        $database = DatabaseFactory::getFactory()->getConnection();

        $sql = "CALL sp_messenger_get_all_chats();";
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
        $query->closeCursor();

        return $all_chats;
    }






}