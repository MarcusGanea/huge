<?php

/**
 * Handles all data manipulation of the admin part
 */
//-- Diese Klasse kümmert sich um alle Admin-Aktionen: Benutzer sperren, löschen oder deren Rolle ändern.
class AdminModel
{
    /**
     * Sets the deletion and suspension values
     *
     * @param $suspensionInDays
     * @param $softDelete
     * @param $userId
     */
    public static function setAccountSuspensionAndDeletionStatus($suspensionInDays, $softDelete, $userId, $role)
    {

        //-- Verhindert, dass ein Admin seinen eigenen Account sperrt oder löscht.
        //-- Sonst würde er sich selbst aus dem System aussperren.
        // Prevent to suspend or delete own account.
        // If admin suspend or delete own account will not be able to do any action.
        if ($userId == Session::get('user_id')) {
            Session::add('feedback_negative', Text::get('FEEDBACK_ACCOUNT_CANT_DELETE_SUSPEND_OWN'));
            return false;
        }

        //-- Prüft, ob die gewählte Rolle (z.B. Admin, Normal-User) überhaupt existiert.
        if (!UserRoleModel::roleExists((int) $role)) {
            Session::add('feedback_negative', Text::get('FEEDBACK_ACCOUNT_TYPE_CHANGE_FAILED'));
            return false;
        }

        //-- Berechnet den genauen Zeitpunkt, bis wann der Nutzer gesperrt sein soll.
        //-- Beispiel: 3 Tage = aktueller Zeitpunkt + (3 × 24 × 60 × 60 Sekunden).
        if ($suspensionInDays > 0) {
            $suspensionTime = time() + ($suspensionInDays * 60 * 60 * 24);
        } else {
            $suspensionTime = null;
        }

        //-- "on" ist der Wert, den eine angeklickte Checkbox im Formular sendet.
        // FYI "on" is what a checkbox delivers by default when submitted. Didn't know that for a long time :)
        if ($softDelete == "on") {
            $delete = 1;
        } else {
            $delete = 0;
        }

        //-- Speichert Sperr- und Löschinfo in der Datenbank.
        // write the above info to the database
        self::writeDeleteAndSuspensionInfoToDatabase($userId, $suspensionTime, $delete, (int) $role);

        //-- Wenn der Nutzer gesperrt oder gelöscht wird, wird er sofort abgemeldet (Session gelöscht).
        // if suspension or deletion should happen, then also kick user out of the application instantly by resetting
        // the user's session :)
        if ($suspensionTime != null || $delete == 1) {
            self::resetUserSession($userId);
        }
    }

    /**
     * Simply write the deletion and suspension info for the user into the database, also puts feedback into session
     *
     * @param $userId
     * @param $suspensionTime
     * @param $delete
     * @return bool
     */
    //-- Speichert Sperr-Zeitpunkt, Lösch-Flag und Rolle in der Datenbank-Zeile des Nutzers.
    private static function writeDeleteAndSuspensionInfoToDatabase($userId, $suspensionTime, $delete, $role)
    {
        //-- Verbindung zur Datenbank herstellen.
        $database = DatabaseFactory::getFactory()->getConnection();

        //-- SQL-Befehl: aktualisiert Sperr-Zeitpunkt, Lösch-Markierung und Kontotyp für den gewählten Nutzer.
        $query = $database->prepare("UPDATE users SET user_suspension_timestamp = :user_suspension_timestamp, user_deleted = :user_deleted, user_account_type = :user_account_type  WHERE user_id = :user_id LIMIT 1");
        $query->execute(array(
                ':user_suspension_timestamp' => $suspensionTime,
                ':user_deleted' => $delete,
                ':user_account_type' => $role,
                ':user_id' => $userId
        ));

        //-- Wenn genau 1 Zeile geändert wurde, war die Aktion erfolgreich.
        if ($query->rowCount() == 1) {
            Session::add('feedback_positive', Text::get('FEEDBACK_ACCOUNT_SUSPENSION_DELETION_STATUS'));
            return true;
        }
    }

    /**
     * Kicks the selected user out of the system instantly by resetting the user's session.
     * This means, the user will be "logged out".
     *
     * @param $userId
     * @return bool
     */
    //-- Meldet den Nutzer sofort ab, indem seine Session-ID in der Datenbank auf null gesetzt wird.
    //-- Beim nächsten Seitenaufruf erkennt das System ihn nicht mehr als eingeloggt.
    private static function resetUserSession($userId)
    {
        $database = DatabaseFactory::getFactory()->getConnection();

        //-- Setzt die Session-ID des Nutzers auf NULL → er wird sofort ausgeloggt.
        $query = $database->prepare("UPDATE users SET session_id = :session_id  WHERE user_id = :user_id LIMIT 1");
        $query->execute(array(
                ':session_id' => null,
                ':user_id' => $userId
        ));

        if ($query->rowCount() == 1) {
            Session::add('feedback_positive', Text::get('FEEDBACK_ACCOUNT_USER_SUCCESSFULLY_KICKED'));
            return true;
        }
    }
}
