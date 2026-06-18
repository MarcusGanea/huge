<?php

class VideoController extends Controller{

public function __construct()
{
        parent::__construct();
        Auth::checkAuthentication();
}

/** Zeigt die Video-Seite (meine Videos + öffentliche Videos) */
    public function index()
    {
        $this->View->render('video/index');
    }



}