<?php

class GalleryModel
{
    const ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    const MAX_SIZE     = 5242880; // 5 MB

    /** Absoluter Basispfad zum userpictures-Ordner */
    public static function getBasePath()
    {
        return realpath(dirname(__FILE__) . '/../../') . DIRECTORY_SEPARATOR . 'userpictures' . DIRECTORY_SEPARATOR;
    }

    /** Absoluter Pfad zu einer konkreten Datei */
    public static function getFilePath($user_id, $stored_name)
    {
        return self::getBasePath() . (int)$user_id . DIRECTORY_SEPARATOR . $stored_name;
    }

    /** Alle eigenen Bilder */
    public static function getMyFiles()
    {
        $db  = DatabaseFactory::getFactory()->getConnection();
        $sql = "SELECT * FROM files WHERE user_id = :uid ORDER BY uploaded_at DESC";
        $q   = $db->prepare($sql);
        $q->execute([':uid' => Session::get('user_id')]);
        return $q->fetchAll();
    }

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

    /** Ein Bild anhand ID laden */
    public static function getFileById($file_id)
    {
        $db  = DatabaseFactory::getFactory()->getConnection();
        $sql = "SELECT * FROM files WHERE file_id = :id LIMIT 1";
        $q   = $db->prepare($sql);
        $q->execute([':id' => (int)$file_id]);
        return $q->fetch();
    }

    /** Bild hochladen + in DB speichern */
    public static function uploadFile()
    {
        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            Session::add('feedback_negative', 'Upload fehlgeschlagen (Fehlercode: ' . ($_FILES['image']['error'] ?? '?') . ').');
            return false;
        }

        if ($_FILES['image']['size'] > self::MAX_SIZE) {
            Session::add('feedback_negative', 'Datei zu groß (max. 5 MB).');
            return false;
        }

        // MIME aus Dateiinhalt prüfen – NICHT $_FILES['type'] verwenden!
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES['image']['tmp_name']);
        if (!in_array($mime, self::ALLOWED_MIME)) {
            Session::add('feedback_negative', 'Nur Bilder (JPG, PNG, GIF, WEBP) erlaubt.');
            return false;
        }

        $user_id       = Session::get('user_id');
        $original_name = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($_FILES['image']['name']));
        $stored_name   = time() . '_' . bin2hex(random_bytes(8)) . '_' . $original_name;

        $dir = self::getBasePath() . $user_id . DIRECTORY_SEPARATOR;
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (!move_uploaded_file($_FILES['image']['tmp_name'], $dir . $stored_name)) {
            Session::add('feedback_negative', 'Datei konnte nicht gespeichert werden.');
            return false;
        }

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

    /** Bild löschen (nur eigene!) */
    public static function deleteFile($file_id)
    {
        $file = self::getFileById($file_id);

        if (!$file || $file->user_id != Session::get('user_id')) {
            Session::add('feedback_negative', 'Kein Zugriff.');
            return false;
        }

        $path = self::getFilePath($file->user_id, $file->stored_name);
        if (file_exists($path)) {
            unlink($path);
        }

        $db  = DatabaseFactory::getFactory()->getConnection();
        $sql = "DELETE FROM files WHERE file_id = :id AND user_id = :uid";
        $q   = $db->prepare($sql);
        return $q->execute([':id' => $file_id, ':uid' => Session::get('user_id')]);
    }

    /** Öffentlich/Privat umschalten (nur eigene!) */
    public static function toggleShare($file_id)
    {
        $db  = DatabaseFactory::getFactory()->getConnection();
        $sql = "UPDATE files SET shared = 1 - shared
                WHERE file_id = :id AND user_id = :uid";
        $q   = $db->prepare($sql);
        return $q->execute([':id' => $file_id, ':uid' => Session::get('user_id')]);
    }

    /** Download-Zähler erhöhen */
    public static function incrementDownloads($file_id)
    {
        $db  = DatabaseFactory::getFactory()->getConnection();
        $sql = "UPDATE files SET downloads = downloads + 1 WHERE file_id = :id";
        $q   = $db->prepare($sql);
        $q->execute([':id' => $file_id]);
    }
}