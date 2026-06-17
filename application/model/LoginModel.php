<?php

/**
 * LoginModel
 *
 * The login part of the model: Handles the login / logout stuff
 */
//-- Diese Klasse steuert den kompletten Login- und Logout-Prozess:
//-- Eingaben prüfen, Passwort verifizieren, Session starten, Cookie setzen und wieder abmelden.
class LoginModel
{
    /**
     * Login process (for DEFAULT user accounts).
     *
     * @param $user_name string The user's name
     * @param $user_password string The user's password
     * @param $set_remember_me_cookie mixed Marker for usage of remember-me cookie feature
     *
     * @return bool success state
     */
    //-- Hauptfunktion für den Login: prüft Eingaben, verifiziert Nutzer und startet die Session.
    public static function login($user_name, $user_password, $set_remember_me_cookie = null)
    {
        //-- Wenn Benutzername oder Passwort leer sind, wird der Login sofort abgebrochen.
        // we do negative-first checks here, for simplicity empty username and empty password in one line
        if (empty($user_name) OR empty($user_password)) {
            Session::add('feedback_negative', Text::get('FEEDBACK_USERNAME_OR_PASSWORD_FIELD_EMPTY'));
            return false;
        }

        //-- Alle Prüfungen durchführen: existiert der Nutzer? Ist das Passwort richtig? Ist er nicht gesperrt?
        // checks if user exists, if login is not blocked (due to failed logins) and if password fits the hash
        $result = self::validateAndGetUser($user_name, $user_password);

        //-- Wenn die Validierung fehlschlägt, Login ablehnen (Fehlermeldung wird intern gesetzt).
        // check if that user exists. We don't give back a cause in the feedback to avoid giving an attacker details.
        if (!$result) {
            //No Need to give feedback here since whole validateAndGetUser controls gives a feedback
            return false;
        }

        //-- Gelöschte Konten dürfen sich nicht anmelden.
        // stop the user's login if account has been soft deleted
        if ($result->user_deleted == 1) {
            Session::add('feedback_negative', Text::get('FEEDBACK_DELETED'));
            return false;
        }

        //-- Gesperrte Konten dürfen sich nicht anmelden. Verbleibende Sperrzeit wird angezeigt.
        // stop the user from logging in if user has a suspension, display how long they have left in the feedback.
        if ($result->user_suspension_timestamp != null && $result->user_suspension_timestamp - time() > 0) {
            $suspensionTimer = Text::get('FEEDBACK_ACCOUNT_SUSPENDED') . round(abs($result->user_suspension_timestamp - time())/60/60, 2) . " hours left";
            Session::add('feedback_negative', $suspensionTimer);
            return false;
        }

        //-- Nach erfolgreichem Login: Zähler für fehlgeschlagene Logins zurücksetzen.
        // reset the failed login counter for that user (if necessary)
        if ($result->user_last_failed_login > 0) {
            self::resetFailedLoginCounterOfUser($result->user_name);
        }

        //-- Login-Zeitpunkt in der Datenbank speichern.
        // save timestamp of this login in the database line of that user
        self::saveTimestampOfLoginOfUser($result->user_name);

        //-- Falls "Angemeldet bleiben" angeklickt wurde, wird ein Cookie im Browser gesetzt.
        // if user has checked the "remember me" checkbox, then write token into database and into cookie
        if ($set_remember_me_cookie) {
            self::setRememberMeInDatabaseAndCookie($result->user_id);
        }

        //-- Alle Nutzerdaten in die Session schreiben – damit ist der Nutzer offiziell eingeloggt.
        // successfully logged in, so we write all necessary data into the session and set "user_logged_in" to true
        self::setSuccessfulLoginIntoSession(
            $result->user_id, $result->user_name, $result->user_email, $result->user_account_type
        );

        //-- Login war erfolgreich: true zurückgeben.
        // return true to make clear the login was successful
        // maybe do this in dependence of setSuccessfulLoginIntoSession ?
        return true;
    }

    /**
     * Validates the inputs of the users, checks if password is correct etc.
     * If successful, user is returned
     *
     * @param $user_name
     * @param $user_password
     *
     * @return bool|mixed
     */
    //-- Prüft, ob Nutzername und Passwort korrekt sind und ob der Account aktiv ist.
    //-- Enthält auch Schutz gegen Brute-Force-Angriffe (zu viele Fehlversuche = kurze Sperre).
    private static function validateAndGetUser($user_name, $user_password)
    {
        //-- Brute-Force-Schutz: Wenn jemand 3x falsch war und der letzte Versuch weniger als 30 Sek. zurückliegt,
        //-- wird der Login temporär gesperrt. Verhindert automatisiertes Ausprobieren von Passwörtern.
        // brute force attack mitigation: use session failed login count and last failed login for not found users.
        // block login attempt if somebody has already failed 3 times and the last login attempt is less than 30sec ago
        // (limits user searches in database)
        if (Session::get('failed-login-count') >= 3 AND (Session::get('last-failed-login') > (time() - 30))) {
            Session::add('feedback_negative', Text::get('FEEDBACK_LOGIN_FAILED_3_TIMES'));
            return false;
        }

        //-- Nutzerinformationen aus der Datenbank laden anhand des Benutzernamens.
        // get all data of that user (to later check if password and password_hash fit)
        $result = UserModel::getUserDataByUsername($user_name);

        //-- Wenn der Nutzer nicht gefunden wird, Fehlerzahl erhöhen und neutrale Fehlermeldung ausgeben.
        //-- (Absichtlich keine genaue Angabe, ob Nutzer oder Passwort falsch sind – Sicherheitsmaßnahme!)
        // check if that user exists. We don't give back a cause in the feedback to avoid giving an attacker details.
        // brute force attack mitigation: reset failed login counter because of found user
        if (!$result) {

            //-- Fehlversuchs-Zähler erhöhen, um Benutzernamen-Erraten zu erschweren.
            // increment the user not found count, helps mitigate user enumeration
            self::incrementUserNotFoundCounter();

            // user does not exist, but we won't to give a potential attacker this details, so we just use a basic feedback message
            Session::add('feedback_negative', Text::get('FEEDBACK_USERNAME_OR_PASSWORD_WRONG'));
            return false;
        }

        //-- Erneuter Brute-Force-Check auf Basis der in der DB gespeicherten Fehlversuche.
        // block login attempt if somebody has already failed 3 times and the last login attempt is less than 30sec ago
        if (($result->user_failed_logins >= 3) AND ($result->user_last_failed_login > (time() - 30))) {
            Session::add('feedback_negative', Text::get('FEEDBACK_PASSWORD_WRONG_3_TIMES'));
            return false;
        }

        //-- Das eingegebene Passwort wird gegen den gespeicherten Hash geprüft.
        //-- password_verify() vergleicht sicher – nie das Passwort im Klartext speichern!
        // if hash of provided password does NOT match the hash in the database: +1 failed-login counter
        if (!password_verify($user_password, $result->user_password_hash)) {
            self::incrementFailedLoginCounterOfUser($result->user_name);
            Session::add('feedback_negative', Text::get('FEEDBACK_USERNAME_OR_PASSWORD_WRONG'));
            return false;
        }

        //-- Nutzer muss sein Konto per E-Mail bestätigt haben (active = 1), sonst kein Login.
        // if user is not active (= has not verified account by verification mail)
        if ($result->user_active != 1) {
            Session::add('feedback_negative', Text::get('FEEDBACK_ACCOUNT_NOT_ACTIVATED_YET'));
            return false;
        }

        //-- Alles ok: Fehlversuchs-Zähler zurücksetzen und Nutzerdaten zurückgeben.
        // reset the user not found counter
        self::resetUserNotFoundCounter();

        return $result;
    }

    /**
     * Reset the failed-login-count to 0.
     * Reset the last-failed-login to an empty string.
     */
    //-- Setzt den Fehlversuchs-Zähler (für "Nutzer nicht gefunden") zurück auf 0.
    private static function resetUserNotFoundCounter()
    {
        Session::set('failed-login-count', 0);
        Session::set('last-failed-login', '');
    }

    //-- Erhöht den Zähler für "Nutzer nicht gefunden" und merkt sich den Zeitpunkt des Fehlversuchs.
    private static function incrementUserNotFoundCounter()
    {
        //-- Verhindert, dass ein Angreifer durch viele Anfragen gültige Benutzernamen herausfindet.
        // Username enumeration prevention: set session failed login count and last failed login for users not found
        Session::set('failed-login-count', Session::get('failed-login-count') + 1);
        Session::set('last-failed-login', time());
    }

    /**
     * performs the login via cookie (for DEFAULT user account, FACEBOOK-accounts are handled differently)
     * TODO add throttling here ?
     *
     * @param $cookie string The cookie "remember_me"
     *
     * @return bool success state
     */
    //-- Einloggen über einen gespeicherten Cookie ("Angemeldet bleiben"-Funktion).
    //-- Der Cookie enthält verschlüsselte Nutzer-ID, ein zufälliges Token und einen Prüfwert.
    public static function loginWithCookie($cookie)
    {
        //-- Wenn kein Cookie vorhanden ist, abbrechen.
        // do we have a cookie ?
        if (!$cookie) {
            Session::add('feedback_negative', Text::get('FEEDBACK_COOKIE_INVALID'));
            return false;
        }

        //-- Cookie muss genau 3 Teile enthalten (getrennt durch Doppelpunkt).
        // before list(), check it can be split into 3 strings.
        if (count (explode(':', $cookie)) !== 3) {
            Session::add('feedback_negative', Text::get('FEEDBACK_COOKIE_INVALID'));
            return false;
        }

        //-- Cookie in seine 3 Bestandteile aufteilen: Nutzer-ID, Token, Hash.
        // check cookie's contents, check if cookie contents belong together or token is empty
        list ($user_id, $token, $hash) = explode(':', $cookie);

        //-- Nutzer-ID entschlüsseln (sie wurde beim Setzen des Cookies verschlüsselt).
        // decrypt user id
        $user_id = Encryption::decrypt($user_id);

        //-- Prüfen, ob der Hash stimmt und Felder nicht leer sind – Manipulationsschutz.
        if ($hash !== hash('sha256', $user_id . ':' . $token) OR empty($token) OR empty($user_id)) {
            Session::add('feedback_negative', Text::get('FEEDBACK_COOKIE_INVALID'));
            return false;
        }

        //-- In der Datenbank nachschauen, ob Nutzer-ID und Token zusammenpassen.
        // get data of user that has this id and this token
        $result = UserModel::getUserDataByUserIdAndToken($user_id, $token);

        // if user with that id and exactly that cookie token exists in database
        if ($result) {

            //-- Cookie-Login erfolgreich: Session starten und Login-Zeitpunkt speichern.
            // successfully logged in, so we write all necessary data into the session and set "user_logged_in" to true
            self::setSuccessfulLoginIntoSession($result->user_id, $result->user_name, $result->user_email, $result->user_account_type);

            // save timestamp of this login in the database line of that user
            self::saveTimestampOfLoginOfUser($result->user_name);

            //-- Kein neuer Cookie wird gesetzt – nach einer Weile muss sich der Nutzer erneut per Formular anmelden.
            // NOTE: we don't set another remember_me-cookie here as the current cookie should always
            // be invalid after a certain amount of time, so the user has to login with username/password
            // again from time to time. This is good and safe ! ;)

            Session::add('feedback_positive', Text::get('FEEDBACK_COOKIE_LOGIN_SUCCESSFUL'));
            return true;
        } else {
            Session::add('feedback_negative', Text::get('FEEDBACK_COOKIE_INVALID'));
            return false;
        }
    }

    /**
     * Log out process: delete cookie, delete session
     */
    //-- Abmelden: Cookie löschen, Session zerstören und Session-ID in DB zurücksetzen.
    public static function logout()
    {
        $user_id = Session::get('user_id');

        self::deleteCookie($user_id);

        Session::destroy();
        Session::updateSessionId($user_id);
    }

    /**
     * The real login process: The user's data is written into the session.
     * Cheesy name, maybe rename. Also maybe refactoring this, using an array.
     *
     * @param $user_id
     * @param $user_name
     * @param $user_email
     * @param $user_account_type
     */
    //-- Schreibt alle Nutzerdaten in die Session und markiert den Nutzer als "eingeloggt".
    //-- Erzeugt auch eine neue Session-ID (Sicherheit gegen Session-Fixation-Angriffe).
    public static function setSuccessfulLoginIntoSession($user_id, $user_name, $user_email, $user_account_type)
    {
        Session::init();

        //-- Alte Session-ID wird ungültig gemacht und eine neue vergeben.
        //-- Das ist wichtig bei sensitiven Aktionen wie dem Login (verhindert Session-Fixation).
        // remove old and regenerate session ID.
        // It's important to regenerate session on sensitive actions,
        // and to avoid fixated session.
        // e.g. when a user logs in
        session_regenerate_id(true);
        $_SESSION = array();

        //-- Alle wichtigen Nutzerdaten in die Session schreiben.
        Session::set('user_id', $user_id);
        Session::set('user_name', $user_name);
        Session::set('user_email', $user_email);
        Session::set('user_account_type', $user_account_type);
        Session::set('user_provider_type', 'DEFAULT');

        //-- Avatar-Links für die Navigation berechnen und in der Session speichern.
        // get and set avatars
        Session::set('user_avatar_file', AvatarModel::getPublicUserAvatarFilePathByUserId($user_id));
        Session::set('user_gravatar_image_url', AvatarModel::getGravatarLinkByEmail($user_email));

        //-- Nutzer gilt jetzt als eingeloggt.
        // finally, set user as logged-in
        Session::set('user_logged_in', true);

        //-- Neue Session-ID auch in der Datenbank aktualisieren.
        // update session id in database
        Session::updateSessionId($user_id, session_id());

        //-- Session-Cookie manuell setzen mit allen Sicherheitsoptionen (Pfad, Domain, HTTPS, HttpOnly).
        // set session cookie setting manually,
        // Why? because you need to explicitly set session expiry, path, domain, secure, and HTTP.
        // @see https://www.owasp.org/index.php/PHP_Security_Cheat_Sheet#Cookies
        setcookie(session_name(), session_id(), time() + Config::get('SESSION_RUNTIME'), Config::get('COOKIE_PATH'),
            Config::get('COOKIE_DOMAIN'), Config::get('COOKIE_SECURE'), Config::get('COOKIE_HTTP'));

    }

    /**
     * Increments the failed-login counter of a user
     *
     * @param $user_name
     */
    public static function incrementFailedLoginCounterOfUser($user_name)
    {
        $database = DatabaseFactory::getFactory()->getConnection();

        $sql = "UPDATE users
                   SET user_failed_logins = user_failed_logins+1, user_last_failed_login = :user_last_failed_login
                 WHERE user_name = :user_name OR user_email = :user_name
                 LIMIT 1";
        $sth = $database->prepare($sql);
        $sth->execute(array(':user_name' => $user_name, ':user_last_failed_login' => time() ));
    }

    /**
     * Resets the failed-login counter of a user back to 0
     *
     * @param $user_name
     */
    public static function resetFailedLoginCounterOfUser($user_name)
    {
        $database = DatabaseFactory::getFactory()->getConnection();

        $sql = "UPDATE users
                   SET user_failed_logins = 0, user_last_failed_login = NULL
                 WHERE user_name = :user_name AND user_failed_logins != 0
                 LIMIT 1";
        $sth = $database->prepare($sql);
        $sth->execute(array(':user_name' => $user_name));
    }

    /**
     * Write timestamp of this login into database (we only write a "real" login via login form into the database,
     * not the session-login on every page request
     *
     * @param $user_name
     */
    public static function saveTimestampOfLoginOfUser($user_name)
    {
        $database = DatabaseFactory::getFactory()->getConnection();

        $sql = "UPDATE users SET user_last_login_timestamp = :user_last_login_timestamp
                WHERE user_name = :user_name LIMIT 1";
        $sth = $database->prepare($sql);
        $sth->execute(array(':user_name' => $user_name, ':user_last_login_timestamp' => time()));
    }

    /**
     * Write remember-me token into database and into cookie
     * Maybe splitting this into database and cookie part ?
     *
     * @param $user_id
     */
    //-- Setzt den "Angemeldet bleiben"-Cookie: erzeugt ein Zufallstoken, speichert es in DB und setzt den Cookie.
    public static function setRememberMeInDatabaseAndCookie($user_id)
    {
        $database = DatabaseFactory::getFactory()->getConnection();

        //-- Zufälliges 64-Zeichen-Token erzeugen (SHA-256 Hash einer Zufallszahl).
        // generate 64 char random string
        $random_token_string = hash('sha256', mt_rand());

        //-- Token in der Datenbank für diesen Nutzer speichern.
        // write that token into database
        $sql = "UPDATE users SET user_remember_me_token = :user_remember_me_token WHERE user_id = :user_id LIMIT 1";
        $sth = $database->prepare($sql);
        $sth->execute(array(':user_remember_me_token' => $random_token_string, ':user_id' => $user_id));

        //-- Cookie-Inhalt zusammenbauen: verschlüsselte ID + Token + Hash.
        //-- Die ID wird verschlüsselt, damit Angreifer sie nicht direkt ablesen können.
        // generate cookie string that consists of user id, random string and combined hash of both
        // never expose the original user id, instead, encrypt it.
        $cookie_string_first_part = Encryption::encrypt($user_id) . ':' . $random_token_string;
        $cookie_string_hash       = hash('sha256', $user_id . ':' . $random_token_string);
        $cookie_string            = $cookie_string_first_part . ':' . $cookie_string_hash;

        //-- Cookie im Browser des Nutzers setzen. Sicherheitsoptionen verhindern XSS-Diebstahl des Cookies.
        // set cookie, and make it available only for the domain created on (to avoid XSS attacks, where the
        // attacker could steal your remember-me cookie string and would login itself).
        // If you are using HTTPS, then you should set the "secure" flag (the second one from right) to true, too.
        // @see http://www.php.net/manual/en/function.setcookie.php
        setcookie('remember_me', $cookie_string, time() + Config::get('COOKIE_RUNTIME'), Config::get('COOKIE_PATH'),
            Config::get('COOKIE_DOMAIN'), Config::get('COOKIE_SECURE'), Config::get('COOKIE_HTTP'));
    }

    /**
     * Deletes the cookie
     * It's necessary to split deleteCookie() and logout() as cookies are deleted without logging out too!
     * Sets the remember-me-cookie to ten years ago (3600sec * 24 hours * 365 days * 10).
     * that's obviously the best practice to kill a cookie @see http://stackoverflow.com/a/686166/1114320
     *
     * @param string $user_id
     */
    //-- Löscht den "Angemeldet bleiben"-Cookie. Setzt ihn 10 Jahre in die Vergangenheit – so löscht der Browser ihn.
    public static function deleteCookie($user_id = null)
    {
        //-- Falls eine Nutzer-ID angegeben wurde, auch das Token in der Datenbank löschen.
        // is $user_id was set, then clear remember_me token in database
        if (isset($user_id)) {

            $database = DatabaseFactory::getFactory()->getConnection();

            $sql = "UPDATE users SET user_remember_me_token = :user_remember_me_token WHERE user_id = :user_id LIMIT 1";
            $sth = $database->prepare($sql);
            $sth->execute(array(':user_remember_me_token' => null, ':user_id' => $user_id));
        }

        //-- Cookie im Browser löschen: Ablaufzeit auf die Vergangenheit setzen – Browser löscht ihn sofort.
        // delete remember_me cookie in browser
        setcookie('remember_me', false, time() - (3600 * 24 * 3650), Config::get('COOKIE_PATH'),
            Config::get('COOKIE_DOMAIN'), Config::get('COOKIE_SECURE'), Config::get('COOKIE_HTTP'));
    }

    /**
     * Returns the current state of the user's login
     *
     * @return bool user's login status
     */
    public static function isUserLoggedIn()
    {
        return Session::userIsLoggedIn();
    }
}
