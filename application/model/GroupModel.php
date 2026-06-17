<?php

/**
 * GroupModel
 * Handles all database operations for group chats.
 * Reuses MessengerModel methods where applicable (getMessagesByChatId, markChatAsRead, countUnreadMessages).
 */
//-- Diese Klasse verwaltet Gruppen-Chats: erstellen, beitreten, Mitglieder hinzufügen und Nachrichten senden.
//-- Sie ähnelt dem Messenger, ist aber für Chats mit mehreren Personen gedacht.
class GroupModel
{
    /**
     * Creates a new group chat and adds the creator as first participant.
     *
     * @param int    $creator_id
     * @param string $group_name
     * @return int|false  The new chat_id, or false on failure.
     */
    //-- Erstellt einen neuen Gruppen-Chat und fügt den Ersteller als erstes Mitglied hinzu.
    //-- Gibt die neue Chat-ID zurück (oder false bei Fehler).
    public static function createGroupChat($creator_id, $group_name)
    {
        //-- Gruppenname darf nicht leer sein.
        if (empty(trim($group_name))) {
            return false;
        }

        $database = DatabaseFactory::getFactory()->getConnection();

        //-- Ruft eine gespeicherte Datenbankprozedur auf, die den Chat anlegt.
        $sql = "CALL sp_group_create_group_chat(:creator_id, :group_name)";
        $query = $database->prepare($sql);
        $query->execute([
            ':creator_id' => $creator_id,
            ':group_name' => $group_name
        ]);

        $result = $query->fetch();
        $query->closeCursor();

        return $result ? (int) $result->chat_id : false;
    }

    /**
     * Adds a user to an existing group chat.
     *
     * @param int $user_id
     * @param int $chat_id
     * @return bool
     */
    //-- Fügt einen Nutzer einem bestehenden Gruppen-Chat hinzu ("Beitreten").
    public static function joinGroupChat($user_id, $chat_id)
    {
        $database = DatabaseFactory::getFactory()->getConnection();

        $sql = "CALL sp_group_join_group_chat(:user_id, :chat_id)";
        $query = $database->prepare($sql);
        $query->execute([
            ':user_id' => $user_id,
            ':chat_id' => $chat_id
        ]);

        $result = $query->fetch();
        $query->closeCursor();

        return $result && (int) $result->affected_rows === 1;
    }

    /**
     * Checks whether a user is a participant of a given chat.
     *
     * @param int $user_id
     * @param int $chat_id
     * @return bool
     */
    //-- Prüft, ob ein Nutzer bereits Mitglied eines bestimmten Chats ist.
    //-- Nützlich, um Doppelbeitritte oder unerlaubten Zugriff zu verhindern.
    public static function isUserInChat($user_id, $chat_id)
    {
        $database = DatabaseFactory::getFactory()->getConnection();

        $sql = "CALL sp_group_is_user_in_chat(:user_id, :chat_id)";
        $query = $database->prepare($sql);
        $query->execute([':user_id' => $user_id, ':chat_id' => $chat_id]);

        $result = $query->fetch();
        $query->closeCursor();

        return (bool) $result;
    }

    /**
     * Returns all group chats the current user is a member of,
     * including member count and unread message count.
     *
     * @return array
     */
    //-- Gibt alle Gruppen-Chats des eingeloggten Nutzers zurück.
    //-- Inklusive Mitgliederzahl und Anzahl ungelesener Nachrichten.
    public static function getMyGroupChats()
    {
        $database = DatabaseFactory::getFactory()->getConnection();

        $sql = "CALL sp_group_get_my_group_chats(:current_user_id)";

        $query = $database->prepare($sql);
        $query->execute([':current_user_id' => Session::get('user_id')]);

        $chats = $query->fetchAll();
        $query->closeCursor();

        //-- Schutz vor bösartigem Code (XSS): alle Texte in den Chat-Daten werden bereinigt.
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
    //-- Gibt alle Gruppen-Chats zurück, denen der eingeloggte Nutzer noch NICHT beigetreten ist.
    //-- Damit kann der Nutzer offene Gruppen sehen und ihnen beitreten.
    public static function getAvailableGroupChats()
    {
        $database = DatabaseFactory::getFactory()->getConnection();

        $sql = "CALL sp_group_get_available_group_chats(:current_user_id)";

        $query = $database->prepare($sql);
        $query->execute([':current_user_id' => Session::get('user_id')]);

        $chats = $query->fetchAll();
        $query->closeCursor();

        //-- XSS-Filter: verhindert, dass Schadcode in Chat-Namen ausgeführt wird.
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
    //-- Lädt die Basisinformationen (Name, Typ usw.) eines einzelnen Gruppen-Chats.
    //-- Prüft auch, dass es wirklich ein Gruppen-Chat ist (nicht ein Direkt-Chat).
    public static function getGroupChatData($chat_id)
    {
        $database = DatabaseFactory::getFactory()->getConnection();

        $sql = "CALL sp_group_get_group_chat_data(:chat_id)";
        $query = $database->prepare($sql);
        $query->execute([':chat_id' => $chat_id]);

        $chat = $query->fetch();
        $query->closeCursor();

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
    //-- Sendet eine Nachricht an einen Gruppen-Chat.
    //-- Funktioniert genauso wie beim Direkt-Chat, nur mit einer Gruppen-Chat-ID.
    public static function sendMessageToGroup($sender_id, $chat_id, $content)
    {
        //-- Ohne gültige Chat-ID oder Inhalt kann keine Nachricht gesendet werden.
        if (!$chat_id || !$content) {
            return false;
        }

        $database = DatabaseFactory::getFactory()->getConnection();

        $sql = "CALL sp_group_send_message(:sender_id, :chat_id, :content)";
        $query = $database->prepare($sql);
        $query->execute([
            ':sender_id' => $sender_id,
            ':chat_id'   => $chat_id,
            ':content'   => $content
        ]);

        $result = $query->fetch();
        $query->closeCursor();

        return $result && (int) $result->affected_rows === 1;
    }

    //-- Gibt alle aktiven Nutzer zurück, die noch NICHT Mitglied der angegebenen Gruppe sind.
    //-- Nützlich für das Einladen neuer Mitglieder.
    /** Returns active users NOT yet in the given group. */
    public static function getNonMembersForGroup($chat_id)
    {
        $database = DatabaseFactory::getFactory()->getConnection();
        $sql = "CALL sp_group_get_non_members_for_group(:chat_id)";
        $query = $database->prepare($sql);
        $query->execute([':chat_id' => $chat_id]);
        $result = $query->fetchAll();
        $query->closeCursor();
        return $result;
    }

    //-- Fügt einen bestimmten Nutzer zu einem Gruppen-Chat hinzu (z.B. eingeladen durch ein Mitglied).
    /** Adds a specific user to a group chat (any member may invite). */
    public static function addMemberToGroup($chat_id, $user_id)
    {
        $database = DatabaseFactory::getFactory()->getConnection();

        $sql = "CALL sp_group_add_member(:chat_id, :user_id)";
        $query = $database->prepare($sql);
        $query->execute([
            ':chat_id' => $chat_id,
            ':user_id' => $user_id
        ]);

        $result = $query->fetch();
        $query->closeCursor();

        return $result && (int) $result->affected_rows === 1;
    }
}