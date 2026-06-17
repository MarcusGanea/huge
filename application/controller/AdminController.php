<?php

// ============================================================
// AdminController.php
// Dieser Controller verwaltet den Admin-Bereich der Anwendung.
// Nur Benutzer mit der Rolle "7" (Administrator) dürfen
// die Methoden in diesem Controller aufrufen.
// Controller = die "Schaltzentrale": Er empfängt Anfragen,
// ruft Daten aus dem Model ab und gibt sie an die View weiter.
// ============================================================

// Diese Klasse "AdminController" erbt von der Basisklasse "Controller"
// (zu finden in application/core/Controller.php).
// Das "extends Controller" bedeutet: AdminController bekommt alle
// Fähigkeiten (Methoden, Eigenschaften) von Controller automatisch.
class AdminController extends Controller
{
    /**
     * Construct this object by extending the basic Controller class
     *
     * ANFÄNGER-ERKLÄRUNG:
     * Der Konstruktor (__construct) wird automatisch ausgeführt,
     * sobald ein neues Objekt dieser Klasse erstellt wird.
     * Hier wird zuerst der übergeordnete (parent) Konstruktor aufgerufen,
     * danach wird geprüft, ob der Benutzer ein Admin ist.
     * Falls nicht, wird er weitergeleitet oder erhält einen Fehler.
     */
    public function __construct()
    {
        // parent::__construct() ruft den Konstruktor der Elternklasse "Controller" auf.
        // Dort werden grundlegende Dinge initialisiert (z.B. die View, Session, etc.).
        // Ohne diesen Aufruf würde der Controller nicht richtig funktionieren!
        // -> Schau in application/core/Controller.php für die Details.
        parent::__construct();

        //-- special authentication check for the entire controller: Note the check-ADMIN-authentication!
        //-- All methods inside this controller are only accessible for admins (= users that have role type 7)
        // Auth::checkAdminAuthentication() prüft, ob der aktuell eingeloggte Benutzer
        // die Admin-Rolle (Typ 7) hat. Falls nicht, wird er umgeleitet.
        // -> Schau in application/core/Auth.php für die Implementierung.
        Auth::checkAdminAuthentication();

        // # Alternative: Man könnte die Prüfung auch in jede einzelne Methode schreiben,
        // # statt sie im Konstruktor zu zentralisieren - aber das wäre redundant.
    }

    /**
     * This method controls what happens when you move to /admin or /admin/index in your app.
     *
     * ANFÄNGER-ERKLÄRUNG:
     * Diese Methode wird aufgerufen, wenn der Admin die URL /admin oder /admin/index öffnet.
     * Sie holt alle Benutzer und alle Rollen aus der Datenbank und
     * gibt diese Daten an die Admin-View weiter, wo sie angezeigt werden.
     */
    public function index()
    {
        // $this->View->render() zeigt eine View-Datei an.
        // 'admin/index' = die Datei application/view/admin/index.php wird gerendert.
        // Das zweite Argument ist ein Array mit Daten, die an die View übergeben werden.
        $this->View->render('admin/index', array(
                // UserModel::getPublicProfilesOfAllUsers() holt alle Benutzer aus der Datenbank.
                // -> Schau in application/model/UserModel.php für die Implementierung.
                // Das Ergebnis wird unter dem Schlüssel 'users' an die View übergeben.
                'users' => UserModel::getPublicProfilesOfAllUsers(),
                // UserRoleModel::getAllRoles() holt alle verfügbaren Benutzerrollen aus der Datenbank.
                // -> Schau in application/model/UserRoleModel.php für die Implementierung.
                // Das Ergebnis wird unter dem Schlüssel 'roles' an die View übergeben.
                'roles' => UserRoleModel::getAllRoles())
        );
        // # Alternative: Man könnte die Daten zuerst in Variablen speichern und dann
        // # als Array übergeben, was den Code lesbarer macht: $users = UserModel::...; $this->View->render('admin/index', compact('users', 'roles'));
    }

    /**
     * actionAccountSettings
     *
     * ANFÄNGER-ERKLÄRUNG:
     * Diese Methode wird aufgerufen, wenn das Admin-Formular abgeschickt wird (POST-Request).
     * Sie verarbeitet die Formulardaten: Sperrdauer, Lösch-Flag, Benutzer-ID und Rolle.
     * Danach ruft sie das Model auf, das die Änderungen in der Datenbank speichert.
     * Am Ende wird der Admin zurück zur Admin-Übersicht weitergeleitet.
     */
    public function actionAccountSettings()
    {
        // AdminModel::setAccountSuspensionAndDeletionStatus() ist die Methode im Model,
        // die die Sperrung und Löschung eines Benutzers in der Datenbank verwaltet.
        // -> Schau in application/model/AdminModel.php für die Implementierung.
        AdminModel::setAccountSuspensionAndDeletionStatus(
            // Request::post('suspension') holt den Wert des Formularfeldes "suspension"
            // aus dem POST-Request (= Anzahl der Sperrtage, die das Formular gesendet hat).
            // -> Schau in application/core/Request.php für die Implementierung.
            Request::post('suspension'),
            // Request::post('softDelete') holt den Checkbox-Wert "softDelete" aus dem Formular.
            // Ein Checkbox-Wert ist "on" wenn angehakt, oder null wenn nicht angehakt.
            Request::post('softDelete'),
            // Request::post('user_id') holt die ID des zu bearbeitenden Benutzers aus dem Formular.
            // (verstecktes Feld in der Admin-View)
            Request::post('user_id'),
            // Request::post('roles') holt die ausgewählte Benutzerrolle aus dem Dropdown-Menü.
            Request::post('roles')
        );

        // Redirect::to("admin") leitet den Browser zur Admin-Startseite weiter.
        // -> Schau in application/core/Redirect.php für die Implementierung.
        Redirect::to("admin");

        // # Alternative: Man könnte nach der Aktion eine AJAX-Antwort zurücksenden (JSON),
        // # statt eine klassische Seitenweiterleitung durchzuführen - moderner bei SPAs.
    }
}
