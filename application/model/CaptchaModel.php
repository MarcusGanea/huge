<?php

/**
 * Class CaptchaModel
 *
 * This model class handles all the captcha stuff.
 * Currently this uses the excellent Captcha generator lib from https://github.com/Gregwar/Captcha
 * Have a look there for more options etc.
 */
//-- Diese Klasse erzeugt ein CAPTCHA-Bild (zufällige Zeichenfolge zum Eintippen)
//-- und prüft, ob der Nutzer es richtig abgetippt hat.
//-- CAPTCHAs verhindern, dass automatische Programme (Bots) Formulare ausfüllen.
class CaptchaModel
{
    /**
     * Generates the captcha, "returns" a real image, this is why there is header('Content-type: image/jpeg')
     * Note: This is a very special method, as this is echoes out binary data.
     */
    //-- Erstellt ein neues CAPTCHA-Bild und gibt es direkt als JPG-Bild aus (kein normaler Text).
    //-- Die angezeigten Zeichen werden in der Session gespeichert, um später vergleichen zu können.
    public static function generateAndShowCaptcha()
    {
        //-- CaptchaBuilder-Bibliothek (via Composer geladen) erstellt ein zufälliges Bild mit Zeichen.
        // create a captcha with the CaptchaBuilder lib (loaded via Composer)
        $captcha = new Gregwar\Captcha\CaptchaBuilder;
        $captcha->build(
            Config::get('CAPTCHA_WIDTH'),
            Config::get('CAPTCHA_HEIGHT')
        );

        //-- Die korrekten Zeichen des CAPTCHAs in der Session merken (für spätere Prüfung).
        // write the captcha character into session
        Session::set('captcha', $captcha->getPhrase());

        //-- Browser mitteilen: Jetzt kommt kein HTML, sondern ein JPEG-Bild.
        // render an image showing the characters (=the captcha)
        header('Content-type: image/jpeg');
        $captcha->output();
    }

    /**
     * Checks if the entered captcha is the same like the one from the rendered image which has been saved in session
     * @param $captcha string The captcha characters
     * @return bool success of captcha check
     */
    //-- Vergleicht die vom Nutzer eingetippten Zeichen mit dem gespeicherten CAPTCHA-Wert.
    //-- Gibt true zurück, wenn sie übereinstimmen – sonst false.
    public static function checkCaptcha($captcha)
    {
        //-- Session enthält die richtigen Zeichen; Eingabe des Nutzers wird damit verglichen.
        if (Session::get('captcha') && ($captcha == Session::get('captcha'))) {
            return true;
        }

        return false;
    }
}
