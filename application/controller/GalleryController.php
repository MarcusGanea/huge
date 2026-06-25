<?php

class GalleryController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        Auth::checkAuthentication();
    }

    /** Zeigt die Galerie-Seite (meine Bilder + öffentliche Bilder) */
    public function index()
    {
        $this->View->render('gallery/index', array(
            'my_files'     => GalleryModel::getMyFiles(),
            'shared_files' => GalleryModel::getSharedFiles(),
        ));
    }

    /** Verarbeitet den Datei-Upload (POST) */
    public function upload()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Redirect::to('gallery/index');
            return;
        }

        if (GalleryModel::uploadFile()) {
            Session::add('feedback_positive', 'Bild erfolgreich hochgeladen.');
        }

        Redirect::to('gallery/index');
    }

    /** Liefert ein Bild aus (für <img src="">) */
    public function serve($file_id)
    {
        $file = GalleryModel::getFileById((int)$file_id);

        if (!$file || ($file->user_id != Session::get('user_id') && !$file->shared)) {
            Redirect::to('error/index');
           return;
        }

        $path = GalleryModel::getFilePath($file->user_id, $file->stored_name);

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
        $file = GalleryModel::getFileById((int)$file_id);

        if (!$file || ($file->user_id != Session::get('user_id') && !$file->shared)) {
            Redirect::to('error/index');
            return;
        }

        $path = GalleryModel::getFilePath($file->user_id, $file->stored_name);

        if (!file_exists($path)) {
            Redirect::to('error/index');
            return;
        }

        GalleryModel::incrementDownloads((int)$file_id);

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
        if (GalleryModel::deleteFile((int)$file_id)) {
            Session::add('feedback_positive', 'Bild gelöscht.');
        } else {
            Session::add('feedback_negative', 'Fehler beim Löschen.');
        }

        Redirect::to('gallery/index');
    }

    /** Schaltet Öffentlich/Privat um */
    public function toggleShare($file_id)
    {
        GalleryModel::toggleShare((int)$file_id);
        Redirect::to('gallery/index');
    }
}