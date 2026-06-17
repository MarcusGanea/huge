<?php

//-- Diese Klasse verwaltet die Bildergalerie: Bilder hochladen, anzeigen, löschen und teilen.
//-- Jeder Nutzer hat einen eigenen Ordner auf dem Server für seine Bilder.
class GalleryModel
{
    //-- Erlaubte Bildtypen: JPG, PNG, GIF und WEBP.
    const ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    //-- Maximale Dateigröße: 5 MB (in Bytes).
    const MAX_SIZE     = 5242880; // 5 MB

    //-- Gibt den absoluten Pfad zum Hauptordner aller Nutzerbilder zurück (z.B. /var/www/userpictures/).
    /** Absoluter Basispfad zum userpictures-Ordner */
    public static function getBasePath()
    {
        return realpath(dirname(__FILE__) . '/../../') . DIRECTORY_SEPARATOR . 'userpictures' . DIRECTORY_SEPARATOR;
    }

    //-- Gibt den vollständigen Pfad zu einer konkreten Bilddatei zurück (z.B. /userpictures/5/abc.jpg).
    /** Absoluter Pfad zu einer konkreten Datei */
    public static function getFilePath($user_id, $stored_name)
    {
        return self::getBasePath() . (int)$user_id . DIRECTORY_SEPARATOR . $stored_name;
    }

    //-- Gibt alle eigenen Bilder des eingeloggten Nutzers aus der Datenbank zurück, neueste zuerst.
    /** Alle eigenen Bilder */
    public static function getMyFiles()
    {
        $db  = DatabaseFactory::getFactory()->getConnection();
        $sql = "SELECT * FROM files WHERE user_id = :uid ORDER BY uploaded_at DESC";
        $q   = $db->prepare($sql);
        $q->execute([':uid' => Session::get('user_id')]);
        return $q->fetchAll();
    }

    //-- Gibt alle Bilder anderer Nutzer zurück, die als öffentlich markiert wurden.
    /** Alle öffentlichen Bilder anderer User */
    public static function getSharedFiles()
    {
        $db  = DatabaseFactory::getFactory()->getConnection();
        $sql = "SELECT f.*, u.user_name
                FROM files f
                JOIN users u ON f.user_id = u.user_id
                WHERE f.shared = 1 AND f.user_id != :uid
                ORDER BY f.uploaded_at DESC";
        $q   = $db->prepare($sql);
        $q->execute([':uid' => Session::get('user_id')]);
        return $q->fetchAll();
    }

    //-- Lädt ein einzelnes Bild aus der Datenbank anhand seiner ID.
    /** Ein Bild anhand ID laden */
    public static function getFileById($file_id)
    {
        $db  = DatabaseFactory::getFactory()->getConnection();
        $sql = "SELECT * FROM files WHERE file_id = :id LIMIT 1";
        $q   = $db->prepare($sql);
        $q->execute([':id' => (int)$file_id]);
        return $q->fetch();
    }

    //-- Verarbeitet einen Bild-Upload: prüft Fehler, Größe und Typ, speichert die Datei und trägt sie in die DB ein.
    /** Bild hochladen + in DB speichern */
    public static function uploadFile()
    {
        //-- Prüft, ob eine Datei angekommen ist und kein Upload-Fehler vorliegt.
        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            Session::add('feedback_negative', 'Upload fehlgeschlagen (Fehlercode: ' . ($_FILES['image']['error'] ?? '?') . ').');
            return false;
        }

        //-- Datei darf nicht größer als 5 MB sein.
        if ($_FILES['image']['size'] > self::MAX_SIZE) {
            Session::add('feedback_negative', 'Datei zu groß (max. 5 MB).');
            return false;
        }

        //-- Sicherheitscheck: MIME-Typ wird aus dem Dateiinhalt gelesen, nicht aus dem Dateinamen.
        //-- Das verhindert, dass jemand eine schädliche Datei als Bild tarnt.
        // MIME aus Dateiinhalt prüfen – NICHT $_FILES['type'] verwenden!
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES['image']['tmp_name']);
        if (!in_array($mime, self::ALLOWED_MIME)) {
            Session::add('feedback_negative', 'Nur Bilder (JPG, PNG, GIF, WEBP) erlaubt.');
            return false;
        }

        $user_id       = Session::get('user_id');
        //-- Unsichere Zeichen aus dem Dateinamen entfernen (Sicherheit).
        $original_name = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($_FILES['image']['name']));
        //-- Eindeutigen Speichernamen erzeugen: Zeitstempel + Zufallsstring + Originalname.
        //-- So werden Namenskonflikte vermieden und Angriffe erschwert.
        $stored_name   = time() . '_' . bin2hex(random_bytes(8)) . '_' . $original_name;

        //-- Nutzerspezifischen Ordner erstellen, falls er noch nicht existiert.
        $dir = self::getBasePath() . $user_id . DIRECTORY_SEPARATOR;
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        //-- Datei vom temporären Upload-Ordner in den Zielordner verschieben.
        if (!move_uploaded_file($_FILES['image']['tmp_name'], $dir . $stored_name)) {
            Session::add('feedback_negative', 'Datei konnte nicht gespeichert werden.');
            return false;
        }

        //-- Dateiinformationen in der Datenbank speichern.
        $db  = DatabaseFactory::getFactory()->getConnection();
        $sql = "INSERT INTO files (user_id, original_name, stored_name, file_size)
                VALUES (:uid, :orig, :stored, :size)";
        $q   = $db->prepare($sql);
        return $q->execute([
            ':uid'    => $user_id,
            ':orig'   => $original_name,
            ':stored' => $stored_name,
            ':size'   => $_FILES['image']['size'],
        ]);
    }

    //-- Löscht ein Bild – aber nur, wenn es dem eingeloggten Nutzer gehört.
    /** Bild löschen (nur eigene!) */
    public static function deleteFile($file_id)
    {
        //-- Bild aus DB laden und Eigentümer prüfen. Fremde Bilder dürfen nicht gelöscht werden.
        $file = self::getFileById($file_id);

        if (!$file || $file->user_id != Session::get('user_id')) {
            Session::add('feedback_negative', 'Kein Zugriff.');
            return false;
        }

        //-- Physische Datei vom Server löschen.
        $path = self::getFilePath($file->user_id, $file->stored_name);
        if (file_exists($path)) {
            unlink($path);
        }

        //-- Datenbankeintrag löschen.
        $db  = DatabaseFactory::getFactory()->getConnection();
        $sql = "DELETE FROM files WHERE file_id = :id AND user_id = :uid";
        $q   = $db->prepare($sql);
        return $q->execute([':id' => $file_id, ':uid' => Session::get('user_id')]);
    }

    //-- Schaltet ein Bild zwischen öffentlich (shared=1) und privat (shared=0) um.
    //-- Der Trick: 1 - shared dreht den Wert einfach um (1→0 oder 0→1).
    /** Öffentlich/Privat umschalten (nur eigene!) */
    public static function toggleShare($file_id)
    {
        $db  = DatabaseFactory::getFactory()->getConnection();
        $sql = "UPDATE files SET shared = 1 - shared
                WHERE file_id = :id AND user_id = :uid";
        $q   = $db->prepare($sql);
        return $q->execute([':id' => $file_id, ':uid' => Session::get('user_id')]);
    }

    //-- Erhöht den Download-Zähler eines Bildes um 1, jedes Mal wenn es heruntergeladen wird.
    /** Download-Zähler erhöhen */
    public static function incrementDownloads($file_id)
    {
        $db  = DatabaseFactory::getFactory()->getConnection();
        $sql = "UPDATE files SET downloads = downloads + 1 WHERE file_id = :id";
        $q   = $db->prepare($sql);
        $q->execute([':id' => $file_id]);
    }
}