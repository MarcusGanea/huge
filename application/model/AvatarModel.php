<?php

//-- Diese Klasse kümmert sich um alles rund ums Profilbild (Avatar):
//-- Bild hochladen, verkleinern, löschen und den Pfad zum Bild ermitteln.
class AvatarModel
{
    /**
     * Gets a gravatar image link from given email address
     *
     * Gravatar is the #1 (free) provider for email address based global avatar hosting.
     * The URL (or image) returns always a .jpg file ! For deeper info on the different parameter possibilities:
     * @see http://gravatar.com/site/implement/images/
     * @source http://gravatar.com/site/implement/images/php/
     *
     * This method will return something like http://www.gravatar.com/avatar/79e2e5b48aec07710c08d50?s=80&d=mm&r=g
     * Note: the url does NOT have something like ".jpg" ! It works without.
     *
     * Set the configs inside the application/config/ files.
     *
     * @param string $email The email address
     * @return string
     */
    //-- Erstellt einen Link zum Gravatar-Bild des Nutzers anhand seiner E-Mail-Adresse.
    //-- Gravatar ist ein kostenloser Dienst, der jedem E-Mail-Konto ein globales Profilbild zuordnet.
    public static function getGravatarLinkByEmail($email)
    {
        //-- md5() erzeugt einen eindeutigen "Fingerabdruck" der E-Mail – so bleibt die Adresse geheim.
        return 'http://www.gravatar.com/avatar/' .
        md5(strtolower(trim($email))) .
        '?s=' . Config::get('AVATAR_SIZE') . '&d=' . Config::get('GRAVATAR_DEFAULT_IMAGESET') . '&r=' . Config::get('GRAVATAR_RATING');
    }

    /**
     * Gets the user's avatar file path
     * @param int $user_has_avatar Marker from database
     * @param int $user_id User's id
     * @return string Avatar file path
     */
    //-- Gibt den öffentlichen Pfad zum Profilbild eines Nutzers zurück.
    //-- Hat der Nutzer kein Bild, wird ein Standard-Platzhalterbild verwendet.
    public static function getPublicAvatarFilePathOfUser($user_has_avatar, $user_id)
    {
        //-- Wenn der Nutzer ein eigenes Bild hat, wird dessen Pfad zurückgegeben.
        if ($user_has_avatar) {
            return Config::get('URL') . Config::get('PATH_AVATARS_PUBLIC') . $user_id . '.jpg';
        }

        //-- Sonst: Standard-Avatar zurückgeben.
        return Config::get('URL') . Config::get('PATH_AVATARS_PUBLIC') . Config::get('AVATAR_DEFAULT_IMAGE');
    }

    /**
     * Gets the user's avatar file path
     * @param $user_id integer The user's id
     * @return string avatar picture path
     */
    //-- Sucht in der Datenbank nach, ob der Nutzer ein Bild hat, und gibt dann den passenden Pfad zurück.
    public static function getPublicUserAvatarFilePathByUserId($user_id)
    {
        $database = DatabaseFactory::getFactory()->getConnection();

        //-- Fragt die Datenbank ab: Hat dieser Nutzer ein Profilbild gesetzt?
        $query = $database->prepare("SELECT user_has_avatar FROM users WHERE user_id = :user_id LIMIT 1");
        $query->execute(array(':user_id' => $user_id));

        if ($query->fetch()->user_has_avatar) {
            return Config::get('URL') . Config::get('PATH_AVATARS_PUBLIC') . $user_id . '.jpg';
        }

        return Config::get('URL') . Config::get('PATH_AVATARS_PUBLIC') . Config::get('AVATAR_DEFAULT_IMAGE');
    }

    /**
     * Create an avatar picture (and checks all necessary things too)
     * TODO decouple
     * TODO total rebuild
     */
    //-- Hauptfunktion für den Avatar-Upload: prüft Ordner und Datei, verkleinert das Bild und speichert es.
    public static function createAvatar()
    {
        //-- Erst wird geprüft: Ist der Ordner beschreibbar? Ist das Bild gültig?
        // check avatar folder writing rights, check if upload fits all rules
        if (self::isAvatarFolderWritable() AND self::validateImageFile()) {

            //-- Pfad für die neue Datei zusammenbauen (z.B. /avatars/42).
            // create a jpg file in the avatar folder, write marker to database
            $target_file_path = Config::get('PATH_AVATARS') . Session::get('user_id');
            //-- Bild auf die konfigurierte Größe (z.B. 80x80 Pixel) zuschneiden und speichern.
            self::resizeAvatarImage($_FILES['avatar_file']['tmp_name'], $target_file_path, Config::get('AVATAR_SIZE'), Config::get('AVATAR_SIZE'));
            //-- In der Datenbank vermerken, dass dieser Nutzer jetzt ein eigenes Bild hat.
            self::writeAvatarToDatabase(Session::get('user_id'));
            //-- Session aktualisieren, damit das neue Bild sofort in der Navigation erscheint.
            Session::set('user_avatar_file', self::getPublicUserAvatarFilePathByUserId(Session::get('user_id')));
            Session::add('feedback_positive', Text::get('FEEDBACK_AVATAR_UPLOAD_SUCCESSFUL'));
        }
    }

    /**
     * Checks if the avatar folder exists and is writable
     *
     * @return bool success status
     */
    //-- Prüft, ob der Ordner für Avatare existiert und neue Dateien hineingeschrieben werden dürfen.
    public static function isAvatarFolderWritable()
    {
        if (is_dir(Config::get('PATH_AVATARS')) AND is_writable(Config::get('PATH_AVATARS'))) {
            return true;
        }

        Session::add('feedback_negative', Text::get('FEEDBACK_AVATAR_FOLDER_DOES_NOT_EXIST_OR_NOT_WRITABLE'));
        return false;
    }

    /**
     * Validates the image
     * Only accepts gif, jpg, png types
     * @see http://php.net/manual/en/function.image-type-to-mime-type.php
     *
     * @return bool
     */
    //-- Überprüft, ob die hochgeladene Datei wirklich ein gültiges Bild ist.
    //-- Nur JPG, PNG und GIF werden akzeptiert. Größe und Mindestauflösung werden ebenfalls geprüft.
    public static function validateImageFile()
    {
        //-- Wurde überhaupt eine Datei hochgeladen?
        if (!isset($_FILES['avatar_file'])) {
            Session::add('feedback_negative', Text::get('FEEDBACK_AVATAR_IMAGE_UPLOAD_FAILED'));
            return false;
        }

        //-- Datei darf nicht größer als 5 MB sein.
        // if input file too big (>5MB)
        if ($_FILES['avatar_file']['size'] > 5000000) {
            Session::add('feedback_negative', Text::get('FEEDBACK_AVATAR_UPLOAD_TOO_BIG'));
            return false;
        }

        //-- Bildgröße (Breite, Höhe) und Typ auslesen.
        // get the image width, height and mime type
        $image_proportions = getimagesize($_FILES['avatar_file']['tmp_name']);

        //-- Bild darf nicht kleiner als die konfigurierte Mindestgröße sein.
        // if input file too small, [0] is the width, [1] is the height
        if ($image_proportions[0] < Config::get('AVATAR_SIZE') OR $image_proportions[1] < Config::get('AVATAR_SIZE')) {
            Session::add('feedback_negative', Text::get('FEEDBACK_AVATAR_UPLOAD_TOO_SMALL'));
            return false;
        }

        //-- Nur bestimmte Dateitypen erlaubt: JPG, GIF, PNG.
        // if file type is not jpg, gif or png
        if (!in_array($image_proportions['mime'], array('image/jpeg', 'image/gif', 'image/png'))) {
            Session::add('feedback_negative', Text::get('FEEDBACK_AVATAR_UPLOAD_WRONG_TYPE'));
            return false;
        }

        return true;
    }

    /**
     * Writes marker to database, saying user has an avatar now
     *
     * @param $user_id
     */
    //-- Speichert in der Datenbank, dass der Nutzer jetzt ein eigenes Profilbild hat.
    public static function writeAvatarToDatabase($user_id)
    {
        $database = DatabaseFactory::getFactory()->getConnection();

        //-- Setzt das Flag "user_has_avatar" auf TRUE für diesen Nutzer.
        $query = $database->prepare("UPDATE users SET user_has_avatar = TRUE WHERE user_id = :user_id LIMIT 1");
        $query->execute(array(':user_id' => $user_id));
    }

    /**
     * Resize avatar image (while keeping aspect ratio and cropping it off in a clean way).
     * Only works with gif, jpg and png file types. If you want to change this also have a look into
     * method validateImageFile() inside this model.
     *
     * TROUBLESHOOTING: You don't see the new image ? Press F5 or CTRL-F5 to refresh browser cache.
     *
     * @param string $source_image The location to the original raw image
     * @param string $destination The location to save the new image
     * @param int $final_width The desired width of the new image
     * @param int $final_height The desired height of the new image
     *
     * @return bool success state
     */
    //-- Verkleinert ein hochgeladenes Bild auf die gewünschte Größe (z.B. 80x80 Pixel) und speichert es als JPG.
    //-- Dabei wird das Bild quadratisch zugeschnitten – überschüssige Ränder werden abgeschnitten.
    public static function resizeAvatarImage($source_image, $destination, $final_width = 44, $final_height = 44)
    {
        //-- Bildbreite, -höhe und Typ ermitteln.
        $imageData = getimagesize($source_image);
        $width = $imageData[0];
        $height = $imageData[1];
        $mimeType = $imageData['mime'];

        //-- Wenn das Bild keine gültige Größe hat, abbrechen.
        if (!$width || !$height) {
            return false;
        }

        //-- Je nach Bildformat die richtige PHP-Funktion zum Laden wählen.
        switch ($mimeType) {
            case 'image/jpeg': $myImage = imagecreatefromjpeg($source_image); break;
            case 'image/png': $myImage = imagecreatefrompng($source_image); break;
            case 'image/gif': $myImage = imagecreatefromgif($source_image); break;
            default: return false;
        }

        //-- Ausschnitt des Originalbilds berechnen, damit das Ergebnis quadratisch wird.
        // calculating the part of the image to use for thumbnail
        if ($width > $height) {
            $verticalCoordinateOfSource = 0;
            $horizontalCoordinateOfSource = ($width - $height) / 2;
            $smallestSide = $height;
        } else {
            $horizontalCoordinateOfSource = 0;
            $verticalCoordinateOfSource = ($height - $width) / 2;
            $smallestSide = $width;
        }

        //-- Neues leeres Bild in Zielgröße erstellen und den Ausschnitt hineinkopieren.
        // copying the part into thumbnail, maybe edit this for square avatars
        $thumb = imagecreatetruecolor($final_width, $final_height);
        imagecopyresampled($thumb, $myImage, 0, 0, $horizontalCoordinateOfSource, $verticalCoordinateOfSource, $final_width, $final_height, $smallestSide, $smallestSide);

        //-- Fertig verkleinertes Bild als .jpg-Datei abspeichern. Dann Speicher freigeben.
        // add '.jpg' to file path, save it as a .jpg file with our $destination_filename parameter
        imagejpeg($thumb, $destination . '.jpg', Config::get('AVATAR_JPEG_QUALITY'));
        imagedestroy($thumb);

        if (file_exists($destination)) {
            return true;
        }
        return false;
    }

    /**
     * Delete a user's avatar
     *
     * @param int $userId
     * @return bool success
     */
    //-- Löscht das Profilbild des Nutzers: erst die Datei vom Server, dann den Eintrag in der Datenbank.
    public static function deleteAvatar($userId)
    {
        //-- Sicherheitscheck: userId muss eine reine Zahl sein.
        if (!ctype_digit($userId)) {
            Session::add("feedback_negative", Text::get("FEEDBACK_AVATAR_IMAGE_DELETE_FAILED"));
            return false;
        }

        //-- Versucht, die Bilddatei zu löschen (auch wenn es fehlschlägt, geht es weiter).
        // try to delete image, but still go on regardless of file deletion result
        self::deleteAvatarImageFile($userId);

        $database = DatabaseFactory::getFactory()->getConnection();

        //-- Setzt "user_has_avatar" in der Datenbank auf 0 (= kein Bild mehr vorhanden).
        $sth = $database->prepare("UPDATE users SET user_has_avatar = 0 WHERE user_id = :user_id LIMIT 1");
        $sth->bindValue(":user_id", (int)$userId, PDO::PARAM_INT);
        $sth->execute();

        if ($sth->rowCount() == 1) {
            //-- Session aktualisieren, damit sofort das Standard-Bild angezeigt wird.
            Session::set('user_avatar_file', self::getPublicUserAvatarFilePathByUserId($userId));
            Session::add("feedback_positive", Text::get("FEEDBACK_AVATAR_IMAGE_DELETE_SUCCESSFUL"));
            return true;
        } else {
            Session::add("feedback_negative", Text::get("FEEDBACK_AVATAR_IMAGE_DELETE_FAILED"));
            return false;
        }
    }

    /**
     * Removes the avatar image file from the filesystem
     *
     * @param integer $userId
     * @return bool
     */
    //-- Löscht die physische Bilddatei vom Server (z.B. /avatars/42.jpg).
    public static function deleteAvatarImageFile($userId)
    {
        //-- Zuerst prüfen, ob die Datei überhaupt existiert.
        // Check if file exists
        if (!file_exists(Config::get('PATH_AVATARS') . $userId . ".jpg")) {
            Session::add("feedback_negative", Text::get("FEEDBACK_AVATAR_IMAGE_DELETE_NO_FILE"));
            return false;
        }

        //-- Datei von der Festplatte löschen.
        // Delete avatar file
        if (!unlink(Config::get('PATH_AVATARS') . $userId . ".jpg")) {
            Session::add("feedback_negative", Text::get("FEEDBACK_AVATAR_IMAGE_DELETE_FAILED"));
            return false;
        }

        return true;
    }
}
