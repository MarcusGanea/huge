<?php

class MessengerModel{


    public static function getMyChats()
    {
        $database = DatabaseFactory::getFactory()->getConnection();

        $sql = "SELECT
                c.chat_id,
                c.chat_name,
                u.user_id AS partner_id,
                u.user_name AS partner_name,
                u.user_email AS partner_email,
                u.user_has_avatar
            FROM chats c
            INNER JOIN chat_participants cp_me
                ON c.chat_id = cp_me.chat_id
            INNER JOIN chat_participants cp_other
                ON c.chat_id = cp_other.chat_id
            INNER JOIN users u
                ON cp_other.user_id = u.user_id
            WHERE cp_me.user_id = :current_user_id
              AND cp_other.user_id != :current_user_id";
              
        $query = $database->prepare($sql);
        $query->execute(array(':current_user_id' => Session::get('user_id')));

        return $query->fetchAll();
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