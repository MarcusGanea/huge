<?php

class VideoController extends Controller{

public function __construct()
{
        parent::__construct();
        Auth::checkAuthentication();
}

/** Zeigt die Video-Seite ( meine Videos + öffentliche Videos ) */
    public function index()
    {
        $this->View->render('video/index', array(
        'my_files'     => VideoModel::getMyFiles(),
        'shared_files' => VideoModel::getSharedFiles(),
    ));
    }


    /** Verarbeitet den Datei-Upload (POST) */
    public function upload()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Redirect::to('video/index');
            return;
        }

        if (VideoModel::uploadFile()) {
            Session::add('feedback_positive', 'Video erfolgreich hochgeladen.');
        }

        Redirect::to('video/index');
    }

    /** Liefert ein Video aus und unterstützt Streaming (Vor-/Zurückspulen) */
    public function serve($file_id)
    {
        $file = VideoModel::getFileById((int)$file_id);

        if (!$file || ($file->user_id != Session::get('user_id') && !$file->shared)) {
            Redirect::to('error/index');
            return;
        }

        $path = VideoModel::getFilePath($file->user_id, $file->stored_name);

        if (!file_exists($path)) {
            Redirect::to('error/index');
            return;
        }

        $size  = filesize($path);
        $mime  = (new finfo(FILEINFO_MIME_TYPE))->file($path);
        $start = 0;
        $end   = $size - 1;

        header('Content-Type: ' . $mime);
        header('Accept-Ranges: bytes');

        // Hat der Browser einen bestimmten Bereich angefragt (z.B. beim Vorspulen)?
        // preg_match liest start/end sauber aus – ganz ohne PHP-Notices.
        if (isset($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d+)-(\d*)/', $_SERVER['HTTP_RANGE'], $m)) {
            $start = (int) $m[1];
            if ($m[2] !== '') {
                $end = (int) $m[2];
            }
            header('HTTP/1.1 206 Partial Content');
            header("Content-Range: bytes $start-$end/$size");
        }

        $length = $end - $start + 1;
        header('Content-Length: ' . $length);

        // Nur den angefragten Abschnitt häppchenweise senden (spart Arbeitsspeicher)
        $stream = fopen($path, 'rb');
        fseek($stream, $start);
        $remaining = $length;
        while ($remaining > 0 && !feof($stream)) {
            $read = ($remaining > 8192) ? 8192 : $remaining;
            echo fread($stream, $read);
            $remaining -= $read;
            flush();
        }
        fclose($stream);
        exit;
    }

    /** Erzwingt einen Download */
    public function download($file_id)
    {
        $file = VideoModel::getFileById((int)$file_id);

        if (!$file || ($file->user_id != Session::get('user_id') && !$file->shared)) {
            Redirect::to('error/index');
            return;
        }

        $path = VideoModel::getFilePath($file->user_id, $file->stored_name);

        if (!file_exists($path)) {
            Redirect::to('error/index');
            return;
        }

        VideoModel::incrementDownloads((int)$file_id);

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . $file->original_name . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    /** Löscht ein Bild (nur eigene) */
    public function delete($file_id)
    {
        if (VideoModel::deleteFile((int)$file_id)) {
            Session::add('feedback_positive', 'Video gelöscht.');
        } else {
            Session::add('feedback_negative', 'Fehler beim Löschen.');
        }

        Redirect::to('video/index');
    }

    /** Schaltet Öffentlich/Privat um */
    public function toggleShare($file_id)
    {
        VideoModel::toggleShare((int)$file_id);
        Redirect::to('video/index');
    }

    /** Zeigt die Detailseite eines Videos (großer Player + Likes + Kommentare) */
    public function watch($video_id)
    {
        $file = VideoModel::getFileById((int)$video_id);

        // Zugriff nur auf eigene oder öffentliche Videos
        if (!$file || ($file->user_id != Session::get('user_id') && !$file->shared)) {
            Session::add('feedback_negative', 'Video nicht gefunden.');
            Redirect::to('video/index');
            return;
        }

        $this->View->render('video/watch', array(
            'file'       => $file,
            'like_count' => VideoModel::getLikeCount((int)$video_id),
            'has_liked'  => VideoModel::userHasLiked((int)$video_id, Session::get('user_id')),
            'comments'   => VideoModel::getComments((int)$video_id),
        ));
    }

    /** Like hinzufügen oder entfernen (Toggle)*/
    public function like($video_id)
    {
        VideoModel::toggleLike((int)$video_id);
        Redirect::to('video/watch/' . (int)$video_id);
    }

    /** Speichert einen Kommentar (POST) */
    public function comment($video_id)
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            VideoModel::addComment((int)$video_id, Request::post('comment_text', true));
        }
        Redirect::to('video/watch/' . (int)$video_id);
    }

}