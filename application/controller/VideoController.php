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

    /** Liefert ein Bild aus (für <img src="">) */
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

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($path));
        readfile($path);
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

   

}