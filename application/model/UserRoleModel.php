<?php

/**
 * Class UserRoleModel
 *
 * This class contains everything that is related to up- and downgrading accounts.
 */
//-- Diese Klasse verwaltet die Nutzerrollen (z.B. "Normal" oder "Admin").
//-- Rollen bestimmen, was ein Nutzer in der Anwendung tun darf.
class UserRoleModel
{
    /**
     * Returns all available roles from the database.
     *
     * @return array
     */
    //-- Gibt alle verfügbaren Rollen aus der Datenbank zurück (z.B. für ein Dropdown-Menü im Admin-Bereich).
    public static function getAllRoles()
    {
        $database = DatabaseFactory::getFactory()->getConnection();

        //-- Alle Rollen nach ID aufsteigend sortiert laden.
        $query = $database->prepare("SELECT user_role_id, user_role_name FROM user_roles ORDER BY user_role_id ASC");
        $query->execute();

        return $query->fetchAll();
    }

    /**
     * Checks whether a role exists in the database.
     *
     * @param int $type
     *
     * @return bool
     */
    //-- Prüft, ob eine bestimmte Rolle in der Datenbank existiert. Schutz vor ungültigen Rollenwerten.
    public static function roleExists($type)
    {
        $database = DatabaseFactory::getFactory()->getConnection();

        //-- Sucht nach der Rollen-ID in der Tabelle user_roles.
        $query = $database->prepare("SELECT user_role_id FROM user_roles WHERE user_role_id = :user_role_id LIMIT 1");
        $query->execute(array(':user_role_id' => (int) $type));

        //-- Gibt true zurück, wenn genau 1 Treffer gefunden wurde.
        return ($query->rowCount() === 1);
    }

    /**
     * Upgrades / downgrades the user's account. Currently it's just the field user_account_type in the database that
     * can be 1 or 2 (maybe "basic" or "premium"). Put some more complex stuff in here, maybe a pay-process or whatever
     * you like.
     *
     * @param $type
     *
     * @return bool
     */
    //-- Ändert die Rolle des eingeloggten Nutzers (z.B. von "Normal" auf "Premium" oder "Admin").
    //-- Prüft zuerst, ob die gewünschte Rolle überhaupt existiert.
    public static function changeUserRole($type)
    {
        //-- Ohne gültigen Rollenwert abbrechen.
        if (!$type) {
            return false;
        }

        //-- Rolle in der Datenbank speichern.
        // save new role to database
        if (self::saveRoleToDatabase($type, Session::get('user_id'))) {
            Session::add('feedback_positive', Text::get('FEEDBACK_ACCOUNT_TYPE_CHANGE_SUCCESSFUL'));
            return true;
        } else {
            Session::add('feedback_negative', Text::get('FEEDBACK_ACCOUNT_TYPE_CHANGE_FAILED'));
            return false;
        }
    }

    /**
     * Writes the new account type marker to the database and to the session
     *
     * @param $type
     *
     * @return bool
     */
    //-- Speichert den neuen Rollentyp in der Datenbank und aktualisiert bei Bedarf die Session.
    public static function saveRoleToDatabase($type, $userId)
    {
        //-- Sicherheitscheck: Rolle muss in der Tabelle existieren.
        if (!self::roleExists($type)) {
            return false;
        }

        $database = DatabaseFactory::getFactory()->getConnection();

        //-- Rollen-Feld des Nutzers in der Datenbank aktualisieren.
        $query = $database->prepare("UPDATE users SET user_account_type = :new_type WHERE user_id = :user_id LIMIT 1");
        $query->execute(array(
            ':new_type' => $type,
            ':user_id' => $userId
        ));

        if ($query->rowCount() == 1) {
            //-- Wenn es die eigene Rolle ist, auch die Session aktualisieren (sofortige Wirkung).
            if ($userId == Session::get('user_id')) {
                Session::set('user_account_type', $type);
            }
            return true;
        }

        return false;
    }
}
