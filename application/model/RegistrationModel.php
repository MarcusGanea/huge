<?php

/**
 * Class RegistrationModel
 *
 * Everything registration-related happens here.
 */
//-- Diese Klasse regelt den kompletten Registrierungsprozess: Eingaben prüfen, Nutzer anlegen und in der DB speichern.
class RegistrationModel
{
    /**
     * Handles the entire registration process for DEFAULT users (not for people who register with
     * 3rd party services, like facebook) and creates a new user in the database if everything is fine
     *
     * @return boolean Gives back the success status of the registration
     */
    //-- Hauptfunktion für die Registrierung: Liest Formulardaten, validiert sie und legt den Nutzer an.
    public static function registerNewUser()
    {
        //-- Formulareingaben einlesen und gefährliche HTML-Tags entfernen (strip_tags = Sicherheit).
        // clean the input
        $user_name = strip_tags(Request::post('user_name'));
        $user_email = strip_tags(Request::post('user_email'));
        $user_email_repeat = strip_tags(Request::post('user_email_repeat'));
        $user_password_new = Request::post('user_password_new');
        $user_password_repeat = Request::post('user_password_repeat');

        //-- Alle Eingaben prüfen (Benutzername, E-Mail, Passwort).
        //-- Falls eine Prüfung fehlschlägt, wird die Registrierung abgebrochen.
        // stop registration flow if registrationInputValidation() returns false (= anything breaks the input check rules)
        $validation_result = self::registrationInputValidation($user_name, $user_password_new, $user_password_repeat, $user_email, $user_email_repeat);
        if (!$validation_result) {
            return false;
        }

        //-- Passwort sicher hashen – wird niemals im Klartext gespeichert.
        // crypt the password with the PHP 5.5's password_hash() function, results in a 60 character hash string.
        // @see php.net/manual/en/function.password-hash.php for more, especially for potential options
        $user_password_hash = password_hash($user_password_new, PASSWORD_DEFAULT);

        // make return a bool variable, so both errors can come up at once if needed
        $return = true;

        //-- Prüfen, ob der Benutzername schon vergeben ist.
        // check if username already exists
        if (UserModel::doesUsernameAlreadyExist($user_name)) {
            Session::add('feedback_negative', Text::get('FEEDBACK_USERNAME_ALREADY_TAKEN'));
            $return = false;
        }

        //-- Prüfen, ob die E-Mail-Adresse schon registriert ist.
        // check if email already exists
        if (UserModel::doesEmailAlreadyExist($user_email)) {
            Session::add('feedback_negative', Text::get('FEEDBACK_USER_EMAIL_ALREADY_TAKEN'));
            $return = false;
        }

        //-- Falls Benutzername oder E-Mail schon belegt, abbrechen.
        // if Username or Email were false, return false
        if (!$return) return false;

        //-- Zufälligen 80-Zeichen-Hash für die E-Mail-Verifizierung erzeugen.
        // generate random hash for email verification (40 bytes)
        $user_activation_hash = bin2hex(random_bytes(40));

        //-- Neuen Nutzer in die Datenbank schreiben.
        // write user data to database
        if (!self::writeNewUserToDatabase($user_name, $user_password_hash, $user_email, time(), $user_activation_hash)) {
            Session::add('feedback_negative', Text::get('FEEDBACK_ACCOUNT_CREATION_FAILED'));
            return false; // no reason not to return false here
        }

        //-- Die neue Nutzer-ID anhand des Benutzernamens ermitteln.
        // get user_id of the user that has been created, to keep things clean we DON'T use lastInsertId() here
        $user_id = UserModel::getUserIdByUsername($user_name);

        if (!$user_id) {
            Session::add('feedback_negative', Text::get('FEEDBACK_UNKNOWN_ERROR'));
            return false;
        }
        Session::add('feedback_positive', Text::get('FEEDBACK_ACCOUNT_SUCCESSFULLY_CREATED'));
        return true;

        /*// send verification email
        if (self::sendVerificationEmail($user_id, $user_email, $user_activation_hash)) {
            Session::add('feedback_positive', Text::get('FEEDBACK_ACCOUNT_SUCCESSFULLY_CREATED'));
            return true;
        }

        // if verification email sending failed: instantly delete the user
        self::rollbackRegistrationByUserId($user_id);
        Session::add('feedback_negative', Text::get('FEEDBACK_VERIFICATION_MAIL_SENDING_FAILED'));
        return false;*/
    }

    /**
     * Validates the registration input
     *
     * @param $captcha
     * @param $user_name
     * @param $user_password_new
     * @param $user_password_repeat
     * @param $user_email
     * @param $user_email_repeat
     *
     * @return bool
     */
    //-- Prüft alle Registrierungseingaben auf Gültigkeit (Benutzername, E-Mail, Passwort).
    //-- Gibt true zurück, wenn alles korrekt ist.
    public static function registrationInputValidation($user_name, $user_password_new, $user_password_repeat, $user_email, $user_email_repeat)
    {
        $return = true;

        //-- Google reCAPTCHA prüfen ("Ich bin kein Roboter"). Schlägt sie fehl, wird abgebrochen.
        if (!CaptchaModel::checkReCaptcha()) {
            Session::add('feedback_negative', Text::get('FEEDBACK_CAPTCHA_WRONG'));
            $return = false;
        }

        // if username, email and password are all correctly validated, but make sure they all run on first sumbit
        if (self::validateUserName($user_name) AND self::validateUserEmail($user_email, $user_email_repeat) AND self::validateUserPassword($user_password_new, $user_password_repeat) AND $return) {
            return true;
        }

        // otherwise, return false
        return false;
    }

    /**
     * Validates the username
     *
     * @param $user_name
     * @return bool
     */
    //-- Prüft, ob der Benutzername gültig ist: nicht leer, 2-64 Zeichen, nur Buchstaben und Ziffern.
    public static function validateUserName($user_name)
    {
        //-- Benutzername muss eingegeben worden sein.
        if (empty($user_name)) {
            Session::add('feedback_negative', Text::get('FEEDBACK_USERNAME_FIELD_EMPTY'));
            return false;
        }

        //-- Benutzername: 2 bis 64 Zeichen, nur a-z, A-Z, 0-9.
        // if username is too short (2), too long (64) or does not fit the pattern (aZ09)
        if (!preg_match('/^[a-zA-Z0-9]{2,64}$/', $user_name)) {
            Session::add('feedback_negative', Text::get('FEEDBACK_USERNAME_DOES_NOT_FIT_PATTERN'));
            return false;
        }

        return true;
    }

    /**
     * Validates the email
     *
     * @param $user_email
     * @param $user_email_repeat
     * @return bool
     */
    //-- Prüft, ob die E-Mail-Adresse gültig ist: nicht leer, Wiederholung stimmt überein, gültiges Format.
    public static function validateUserEmail($user_email, $user_email_repeat)
    {
        if (empty($user_email)) {
            Session::add('feedback_negative', Text::get('FEEDBACK_EMAIL_FIELD_EMPTY'));
            return false;
        }

        //-- Beide E-Mail-Felder müssen übereinstimmen (Tippfehler vermeiden).
        if ($user_email !== $user_email_repeat) {
            Session::add('feedback_negative', Text::get('FEEDBACK_EMAIL_REPEAT_WRONG'));
            return false;
        }

        //-- PHP-interne Prüfung auf gültiges E-Mail-Format.
        // validate the email with PHP's internal filter
        // side-fact: Max length seems to be 254 chars
        // @see http://stackoverflow.com/questions/386294/what-is-the-maximum-length-of-a-valid-email-address
        if (!filter_var($user_email, FILTER_VALIDATE_EMAIL)) {
            Session::add('feedback_negative', Text::get('FEEDBACK_EMAIL_DOES_NOT_FIT_PATTERN'));
            return false;
        }

        return true;
    }

    /**
     * Validates the password
     *
     * @param $user_password_new
     * @param $user_password_repeat
     * @return bool
     */
    //-- Prüft das Passwort: nicht leer, Wiederholung stimmt überein, mindestens 6 Zeichen.
    public static function validateUserPassword($user_password_new, $user_password_repeat)
    {
        if (empty($user_password_new) OR empty($user_password_repeat)) {
            Session::add('feedback_negative', Text::get('FEEDBACK_PASSWORD_FIELD_EMPTY'));
            return false;
        }

        if ($user_password_new !== $user_password_repeat) {
            Session::add('feedback_negative', Text::get('FEEDBACK_PASSWORD_REPEAT_WRONG'));
            return false;
        }

        if (strlen($user_password_new) < 6) {
            Session::add('feedback_negative', Text::get('FEEDBACK_PASSWORD_TOO_SHORT'));
            return false;
        }

        return true;
    }

    /**
     * Writes the new user's data to the database
     *
     * @param $user_name
     * @param $user_password_hash
     * @param $user_email
     * @param $user_creation_timestamp
     * @param $user_activation_hash
     *
     * @return bool
     */
    //-- Schreibt alle Daten des neuen Nutzers in die Datenbank (Benutzername, Passwort-Hash, E-Mail usw.).
    public static function writeNewUserToDatabase($user_name, $user_password_hash, $user_email, $user_creation_timestamp, $user_activation_hash)
    {
        $database = DatabaseFactory::getFactory()->getConnection();

        //-- SQL INSERT: neuen Nutzer einfügen. user_active = 1 bedeutet: Konto sofort aktiv.
        // write new users data into database
        $sql = "INSERT INTO users (user_name, user_password_hash, user_email, user_creation_timestamp, user_activation_hash, user_provider_type, user_active)
                    VALUES (:user_name, :user_password_hash, :user_email, :user_creation_timestamp, :user_activation_hash, :user_provider_type, :user_active)";
        $query = $database->prepare($sql);
        $query->execute(array(':user_name' => $user_name,
                              ':user_password_hash' => $user_password_hash,
                              ':user_email' => $user_email,
                              ':user_creation_timestamp' => $user_creation_timestamp,
                              ':user_activation_hash' => $user_activation_hash,
                              ':user_provider_type' => 'DEFAULT',
                              ':user_active' => 1));
        $count =  $query->rowCount();

        if ($count == 1) {
            return true;
        }

        return false;
    }

    /**
     * Deletes the user from users table. Currently used to rollback a registration when verification mail sending
     * was not successful.
     *
     * @param $user_id
     */
    //-- Rückgängig machen einer fehlgeschlagenen Registrierung: Nutzer wird wieder aus der DB gelöscht.
    //-- Wird benutzt, wenn z.B. der Versand der Bestätigungsmail fehlschlägt.
    public static function rollbackRegistrationByUserId($user_id)
    {
        $database = DatabaseFactory::getFactory()->getConnection();

        $query = $database->prepare("DELETE FROM users WHERE user_id = :user_id");
        $query->execute(array(':user_id' => $user_id));
    }

    /**
     * Sends the verification email (to confirm the account).
     * The construction of the mail $body looks weird at first, but it's really just a simple string.
     *
     * @param int $user_id user's id
     * @param string $user_email user's email
     * @param string $user_activation_hash user's mail verification hash string
     *
     * @return boolean gives back true if mail has been sent, gives back false if no mail could been sent
     */
    /*public static function sendVerificationEmail($user_id, $user_email, $user_activation_hash)
    {
        $body = Config::get('EMAIL_VERIFICATION_CONTENT') . Config::get('URL') . Config::get('EMAIL_VERIFICATION_URL')
                . '/' . urlencode($user_id) . '/' . urlencode($user_activation_hash);

        $mail = new Mail;
        $mail_sent = $mail->sendMail($user_email, Config::get('EMAIL_VERIFICATION_FROM_EMAIL'),
            Config::get('EMAIL_VERIFICATION_FROM_NAME'), Config::get('EMAIL_VERIFICATION_SUBJECT'), $body
        );

        if ($mail_sent) {
            Session::add('feedback_positive', Text::get('FEEDBACK_VERIFICATION_MAIL_SENDING_SUCCESSFUL'));
            return true;
        } else {
            Session::add('feedback_negative', Text::get('FEEDBACK_VERIFICATION_MAIL_SENDING_ERROR') . $mail->getError() );
            return false;
        }
    }*/

    /**
     * checks the email/verification code combination and set the user's activation status to true in the database
     *
     * @param int $user_id user id
     * @param string $user_activation_verification_code verification token
     *
     * @return bool success status
     */
    //-- Aktiviert ein Nutzerkonto: Bestätigungscode aus der E-Mail wird mit dem in der DB gespeicherten verglichen.
    //-- Bei Übereinstimmung wird das Konto auf "aktiv" gesetzt.
    public static function verifyNewUser($user_id, $user_activation_verification_code)
    {
        $database = DatabaseFactory::getFactory()->getConnection();

        $sql = "UPDATE users SET user_active = 1, user_activation_hash = NULL
                WHERE user_id = :user_id AND user_activation_hash = :user_activation_hash LIMIT 1";
        $query = $database->prepare($sql);
        $query->execute(array(':user_id' => $user_id, ':user_activation_hash' => $user_activation_verification_code));

        if ($query->rowCount() == 1) {
            Session::add('feedback_positive', Text::get('FEEDBACK_ACCOUNT_ACTIVATION_SUCCESSFUL'));
            return true;
        }

        Session::add('feedback_negative', Text::get('FEEDBACK_ACCOUNT_ACTIVATION_FAILED'));
        return false;
    }
}
