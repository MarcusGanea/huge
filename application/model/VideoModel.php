<?php

    Class VideoModel{

        const ALLOWED_MIME = ['video/mp4', 'video/webm', 'video/ogg'];
        const MAX_SIZE     = 2147483648; // 2 GB

         //-- Gibt den absoluten Pfad zum Hauptordner aller Nutzerbilder zurück (z.B. /var/www/uservideos/).
    /** Absoluter Basispfad zum uservideos-Ordner */
    public static function getBasePath()
    {
        return realpath(dirname(__FILE__) . '/../../') . DIRECTORY_SEPARATOR . 'uservideos' . DIRECTORY_SEPARATOR;
    }

    //-- Gibt den vollständigen Pfad zu einer konkreten Bilddatei zurück (z.B. /uservideos/5/abc.jpg).
    /** Absoluter Pfad zu einer konkreten Datei */
    public static function getFilePath($user_id, $stored_name)
    {
        return self::getBasePath() . (int)$user_id . DIRECTORY_SEPARATOR . $stored_name;
    }

    //-- Gibt alle eigenen Videos des eingeloggten Nutzers zurück, nach Likes absteigend sortiert.
    /** Alle eigenen Videos (inkl. Like-Anzahl, sortiert nach Likes) */
    public static function getMyFiles()
    {
        $db  = DatabaseFactory::getFactory()->getConnection();
        $sql = "SELECT v.*,
                       (SELECT COUNT(*) FROM video_likes l WHERE l.video_id = v.video_id) AS like_count
                FROM videos v
                WHERE v.user_id = :uid
                ORDER BY like_count DESC, v.uploaded_at DESC";
        $q   = $db->prepare($sql);
        $q->execute([':uid' => Session::get('user_id')]);
        return $q->fetchAll();
    }

    //-- Gibt alle Videos anderer Nutzer zurück, die als öffentlich markiert wurden.
    /** Alle öffentlichen Videos anderer User (inkl. Like-Anzahl, sortiert nach Likes) */
    public static function getSharedFiles()
    {
        $db  = DatabaseFactory::getFactory()->getConnection();
        $sql = "SELECT v.*, u.user_name,
                       (SELECT COUNT(*) FROM video_likes l WHERE l.video_id = v.video_id) AS like_count
                FROM videos v
                JOIN users u ON v.user_id = u.user_id
                WHERE v.shared = 1 AND v.user_id != :uid
                ORDER BY like_count DESC, v.uploaded_at DESC";
        $q   = $db->prepare($sql);
        $q->execute([':uid' => Session::get('user_id')]);
        return $q->fetchAll();
    }

    //-- Lädt ein einzelnes Bild aus der Datenbank anhand seiner ID.
    /** Ein Bild anhand ID laden */
    public static function getFileById($video_id)
    {
        $db  = DatabaseFactory::getFactory()->getConnection();
        $sql = "SELECT * FROM videos WHERE video_id = :id LIMIT 1";
        $q   = $db->prepare($sql);
        $q->execute([':id' => (int)$video_id]);
        return $q->fetch();
    }

    //-- Verarbeitet einen Bild-Upload: prüft Fehler, Größe und Typ, speichert die Datei und trägt sie in die DB ein.
    /** Bild hochladen + in DB speichern */
    public static function uploadFile()
    {
        //-- Prüft, ob eine Datei angekommen ist und kein Upload-Fehler vorliegt.
        if (!isset($_FILES['video']) || $_FILES['video']['error'] !== UPLOAD_ERR_OK) {
            Session::add('feedback_negative', 'Upload fehlgeschlagen (Fehlercode: ' . ($_FILES['video']['error'] ?? '?') . ').');
            return false;
        }

        //-- Datei darf nicht größer als 2 GB sein.
        if ($_FILES['video']['size'] > self::MAX_SIZE) {
            Session::add('feedback_negative', 'Datei zu groß (max. 2 GB).');
            return false;
        }

        //-- Sicherheitscheck: MIME-Typ wird aus dem Dateiinhalt gelesen, nicht aus dem Dateinamen.
        //-- Das verhindert, dass jemand eine schädliche Datei als Bild tarnt.
        // MIME aus Dateiinhalt prüfen – NICHT $_FILES['type'] verwenden!
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES['video']['tmp_name']);
        if (!in_array($mime, self::ALLOWED_MIME)) {
            Session::add('feedback_negative', 'Nur Bilder (JPG, PNG, GIF, WEBP) erlaubt.');
            return false;
        }

        $user_id       = Session::get('user_id');
        //-- Unsichere Zeichen aus dem Dateinamen entfernen (Sicherheit).
        $original_name = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($_FILES['video']['name']));
        //-- Eindeutigen Speichernamen erzeugen: Zeitstempel + Zufallsstring + Originalname.
        //-- So werden Namenskonflikte vermieden und Angriffe erschwert.
        $stored_name   = time() . '_' . bin2hex(random_bytes(8)) . '_' . $original_name;

        //-- Nutzerspezifischen Ordner erstellen, falls er noch nicht existiert.
        $dir = self::getBasePath() . $user_id . DIRECTORY_SEPARATOR;
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        //-- Datei vom temporären Upload-Ordner in den Zielordner verschieben.
        if (!move_uploaded_file($_FILES['video']['tmp_name'], $dir . $stored_name)) {
            Session::add('feedback_negative', 'Datei konnte nicht gespeichert werden.');
            return false;
        }

        //-- Dateiinformationen in der Datenbank speichern.
        $db  = DatabaseFactory::getFactory()->getConnection();
        $sql = "INSERT INTO videos (user_id, original_name, stored_name, file_size)
                VALUES (:uid, :orig, :stored, :size)";
        $q   = $db->prepare($sql);
        return $q->execute([
            ':uid'    => $user_id,
            ':orig'   => $original_name,
            ':stored' => $stored_name,
            ':size'   => $_FILES['video']['size'],
        ]);
    }

    //-- Löscht ein Bild – aber nur, wenn es dem eingeloggten Nutzer gehört.
    /** Bild löschen (nur eigene!) */
    public static function deleteFile($video_id)
    {
        //-- Bild aus DB laden und Eigentümer prüfen. Fremde Bilder dürfen nicht gelöscht werden.
        $file = self::getFileById($video_id);

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
        $sql = "DELETE FROM videos WHERE video_id = :id AND user_id = :uid";
        $q   = $db->prepare($sql);
        return $q->execute([':id' => $video_id, ':uid' => Session::get('user_id')]);
    }

    //-- Schaltet ein Bild zwischen öffentlich (shared=1) und privat (shared=0) um.
    //-- Der Trick: 1 - shared dreht den Wert einfach um (1→0 oder 0→1).
    /** Öffentlich/Privat umschalten (nur eigene!) */
    public static function toggleShare($video_id)
    {
        $db  = DatabaseFactory::getFactory()->getConnection();
        $sql = "UPDATE videos SET shared = 1 - shared
                WHERE video_id = :id AND user_id = :uid";
        $q   = $db->prepare($sql);
        return $q->execute([':id' => $video_id, ':uid' => Session::get('user_id')]);
    }

    //-- Erhöht den Download-Zähler eines Bildes um 1, jedes Mal wenn es heruntergeladen wird.
    /** Download-Zähler erhöhen */
    public static function incrementDownloads($video_id)
    {
        $db  = DatabaseFactory::getFactory()->getConnection();
        $sql = "UPDATE videos SET downloads = downloads + 1 WHERE video_id = :id";
        $q   = $db->prepare($sql);
        $q->execute([':id' => $video_id]);
    }

    // ───────────────────────────────────────────────────────────────
    //  LIKES
    // ───────────────────────────────────────────────────────────────

    //-- Zählt, wie viele Likes ein Video insgesamt hat.
    /** Anzahl der Likes eines Videos */
    public static function getLikeCount($video_id)
    {
        $db  = DatabaseFactory::getFactory()->getConnection();
        $sql = "SELECT COUNT(*) AS cnt FROM video_likes WHERE video_id = :id";
        $q   = $db->prepare($sql);
        $q->execute([':id' => (int)$video_id]);
        return (int) $q->fetch()->cnt;
    }

    //-- Prüft, ob ein bestimmter Nutzer dieses Video bereits geliked hat.
    /** Hat dieser User das Video schon geliked? (true/false) */
    public static function userHasLiked($video_id, $user_id)
    {
        $db  = DatabaseFactory::getFactory()->getConnection();
        $sql = "SELECT COUNT(*) AS cnt FROM video_likes WHERE video_id = :vid AND user_id = :uid";
        $q   = $db->prepare($sql);
        $q->execute([':vid' => (int)$video_id, ':uid' => (int)$user_id]);
        return ((int) $q->fetch()->cnt) > 0;
    }

    //-- Liked ein Video oder entfernt den Like wieder (Umschalter).
    //-- Hat der User noch nicht geliked -> Like einfügen, sonst -> Like löschen.
    /** Like setzen oder entfernen (Toggle) */
    public static function toggleLike($video_id)
    {
        $user_id = Session::get('user_id');
        $db      = DatabaseFactory::getFactory()->getConnection();

        if (self::userHasLiked($video_id, $user_id)) {
            $sql = "DELETE FROM video_likes WHERE video_id = :vid AND user_id = :uid";
        } else {
            $sql = "INSERT INTO video_likes (video_id, user_id) VALUES (:vid, :uid)";
        }

        $q = $db->prepare($sql);
        return $q->execute([':vid' => (int)$video_id, ':uid' => (int)$user_id]);
    }

    // ───────────────────────────────────────────────────────────────
    //  KOMMENTARE
    // ───────────────────────────────────────────────────────────────

    //-- Lädt alle Kommentare eines Videos inkl. Name des Verfassers, neueste zuerst.
    /** Alle Kommentare eines Videos */
    public static function getComments($video_id)
    {
        $db  = DatabaseFactory::getFactory()->getConnection();
        $sql = "SELECT c.*, u.user_name
                FROM video_comments c
                JOIN users u ON c.user_id = u.user_id
                WHERE c.video_id = :vid
                ORDER BY c.created_at DESC";
        $q   = $db->prepare($sql);
        $q->execute([':vid' => (int)$video_id]);
        return $q->fetchAll();
    }

    //-- Speichert einen neuen Kommentar. Leere Kommentare werden abgelehnt.
    /** Kommentar speichern */
    public static function addComment($video_id, $text)
    {
        $text = trim((string)$text);
        if ($text === '') {
            Session::add('feedback_negative', 'Kommentar darf nicht leer sein.');
            return false;
        }

        $db  = DatabaseFactory::getFactory()->getConnection();
        $sql = "INSERT INTO video_comments (video_id, user_id, comment_text)
                VALUES (:vid, :uid, :text)";
        $q   = $db->prepare($sql);
        return $q->execute([
            ':vid'  => (int)$video_id,
            ':uid'  => Session::get('user_id'),
            ':text' => $text,
        ]);
    }
    }