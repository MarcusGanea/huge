<?php

/**
 * GroupModel
 * Handles all database operations for group chats.
 * Reuses MessengerModel methods where applicable (getMessagesByChatId, markChatAsRead, countUnreadMessages).
 */
class GroupModel
{
    /**
     * Creates a new group chat and adds the creator as first participant.
     *
     * @param int    $creator_id
     * @param string $group_name
     * @return int|false  The new chat_id, or false on failure.
     */
    public static function createGroupChat($creator_id, $group_name)
    {
        if (empty(trim($group_name))) {
            return false;
        }

        $database = DatabaseFactory::getFactory()->getConnection();

        $sql = "INSERT INTO chats (chat_name, chat_type) VALUES (:chat_name, 'GROUP')";
        $query = $database->prepare($sql);
        $query->execute([':chat_name' => $group_name]);

        $chat_id = $database->lastInsertId();

        $sql = "INSERT INTO chat_participants (chat_id, user_id) VALUES (:chat_id, :user_id)";
        $query = $database->prepare($sql);
        $query->execute([':chat_id' => $chat_id, ':user_id' => $creator_id]);

        return (int) $chat_id;
    }

    /**
     * Adds a user to an existing group chat.
     *
     * @param int $user_id
     * @param int $chat_id
     * @return bool
     */
    public static function joinGroupChat($user_id, $chat_id)
    {
        if (self::isUserInChat($user_id, $chat_id)) {
            return false;
        }

        $database = DatabaseFactory::getFactory()->getConnection();

        // Security: verify it's actually a GROUP chat
        $sql = "SELECT chat_id FROM chats WHERE chat_id = :chat_id AND chat_type = 'GROUP' LIMIT 1";
        $query = $database->prepare($sql);
        $query->execute([':chat_id' => $chat_id]);

        if (!$query->fetch()) {
            return false;
        }

        $sql = "INSERT INTO chat_participants (chat_id, user_id) VALUES (:chat_id, :user_id)";
        $query = $database->prepare($sql);
        $query->execute([':chat_id' => $chat_id, ':user_id' => $user_id]);

        return $query->rowCount() === 1;
    }

    /**
     * Checks whether a user is a participant of a given chat.
     *
     * @param int $user_id
     * @param int $chat_id
     * @return bool
     */
    public static function isUserInChat($user_id, $chat_id)
    {
        $database = DatabaseFactory::getFactory()->getConnection();

        $sql = "SELECT 1 FROM chat_participants
                WHERE user_id = :user_id AND chat_id = :chat_id
                LIMIT 1";
        $query = $database->prepare($sql);
        $query->execute([':user_id' => $user_id, ':chat_id' => $chat_id]);

        return (bool) $query->fetch();
    }

    /**
     * Returns all group chats the current user is a member of,
     * including member count and unread message count.
     *
     * @return array
     */
    public static function getMyGroupChats()
    {
        $database = DatabaseFactory::getFactory()->getConnection();

        $sql = "SELECT
                    c.chat_id,
                    c.chat_name,
                    (
                        SELECT COUNT(*)
                        FROM chat_participants cp2
                        WHERE cp2.chat_id = c.chat_id
                    ) AS member_count,
                    (
                        SELECT COUNT(*)
                        FROM messages m2
                        WHERE m2.chat_id = c.chat_id
                          AND m2.sender_id != :current_user_id
                          AND m2.is_read = 0
                    ) AS unread_count
                FROM chats c
                INNER JOIN chat_participants cp ON c.chat_id = cp.chat_id
                WHERE cp.user_id = :current_user_id
                  AND c.chat_type = 'GROUP'
                ORDER BY c.chat_id DESC";

        $query = $database->prepare($sql);
        $query->execute([':current_user_id' => Session::get('user_id')]);

        $chats = $query->fetchAll();

        foreach ($chats as $chat) {
            array_walk_recursive($chat, 'Filter::XSSFilter');
        }

        return $chats;
    }

    /**
     * Returns all group chats that exist but the current user has NOT yet joined.
     *
     * @return array
     */
    public static function getAvailableGroupChats()
    {
        $database = DatabaseFactory::getFactory()->getConnection();

        $sql = "SELECT
                    c.chat_id,
                    c.chat_name,
                    (
                        SELECT COUNT(*)
                        FROM chat_participants cp2
                        WHERE cp2.chat_id = c.chat_id
                    ) AS member_count
                FROM chats c
                WHERE c.chat_type = 'GROUP'
                  AND NOT EXISTS (
                      SELECT 1 FROM chat_participants cp
                      WHERE cp.chat_id = c.chat_id
                        AND cp.user_id = :current_user_id
                  )
                ORDER BY c.chat_name ASC";

        $query = $database->prepare($sql);
        $query->execute([':current_user_id' => Session::get('user_id')]);

        $chats = $query->fetchAll();

        foreach ($chats as $chat) {
            array_walk_recursive($chat, 'Filter::XSSFilter');
        }

        return $chats;
    }

    /**
     * Returns metadata for a single group chat (validates it is type GROUP).
     *
     * @param int $chat_id
     * @return object|false
     */
    public static function getGroupChatData($chat_id)
    {
        $database = DatabaseFactory::getFactory()->getConnection();

        $sql = "SELECT chat_id, chat_name
                FROM chats
                WHERE chat_id = :chat_id AND chat_type = 'GROUP'
                LIMIT 1";
        $query = $database->prepare($sql);
        $query->execute([':chat_id' => $chat_id]);

        $chat = $query->fetch();

        if ($chat) {
            array_walk_recursive($chat, 'Filter::XSSFilter');
        }

        return $chat;
    }

    /**
     * Sends a message to a group chat.
     * Uses the shared messages table (same as DMs).
     *
     * @param int    $sender_id
     * @param int    $chat_id
     * @param string $content
     * @return bool
     */
    public static function sendMessageToGroup($sender_id, $chat_id, $content)
    {
        if (!$chat_id || !$content) {
            return false;
        }

        // Authorisation: sender must be a participant
        if (!self::isUserInChat($sender_id, $chat_id)) {
            return false;
        }

        $database = DatabaseFactory::getFactory()->getConnection();

        $sql = "INSERT INTO messages (chat_id, sender_id, content, is_read)
                VALUES (:chat_id, :sender_id, :content, 0)";
        $query = $database->prepare($sql);
        $query->execute([
            ':chat_id'   => $chat_id,
            ':sender_id' => $sender_id,
            ':content'   => $content
        ]);

        return $query->rowCount() === 1;
    }

    /** Returns active users NOT yet in the given group. */
    public static function getNonMembersForGroup($chat_id)
    {
        $database = DatabaseFactory::getFactory()->getConnection();
        $sql = "SELECT u.user_id, u.user_name
                FROM users u
                WHERE u.user_active = 1
                  AND u.user_id NOT IN (
                      SELECT cp.user_id FROM chat_participants cp WHERE cp.chat_id = :chat_id
                  )
                ORDER BY u.user_name ASC";
        $query = $database->prepare($sql);
        $query->execute([':chat_id' => $chat_id]);
        return $query->fetchAll();
    }

    /** Adds a specific user to a group chat (any member may invite). */
    public static function addMemberToGroup($chat_id, $user_id)
    {
        $database = DatabaseFactory::getFactory()->getConnection();
        $sql = "SELECT 1 FROM chats WHERE chat_id = :chat_id AND chat_type = 'GROUP' LIMIT 1";
        $query = $database->prepare($sql);
        $query->execute([':chat_id' => $chat_id]);
        if (!$query->fetch()) { return false; }
        if (self::isUserInChat($user_id, $chat_id)) { return false; }
        $sql = "INSERT INTO chat_participants (chat_id, user_id) VALUES (:chat_id, :user_id)";
        $query = $database->prepare($sql);
        $query->execute([':chat_id' => $chat_id, ':user_id' => $user_id]);
        return $query->rowCount() === 1;
    }
}