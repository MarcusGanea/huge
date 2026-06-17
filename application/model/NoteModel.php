<?php

/**
 * NoteModel
 * This is basically a simple CRUD (Create/Read/Update/Delete) demonstration.
 */
//-- Diese Klasse verwaltet Notizen: erstellen, lesen, ändern und löschen.
//-- CRUD steht für Create (Erstellen), Read (Lesen), Update (Ändern), Delete (Löschen) – die 4 Grundoperationen jeder Datenbankanwendung.
class NoteModel
{
    /**
     * Get all notes (notes are just example data that the user has created)
     * @return array an array with several objects (the results)
     */
    //-- Gibt alle Notizen des eingeloggten Nutzers aus der Datenbank zurück.
    public static function getAllNotes()
    {
        $database = DatabaseFactory::getFactory()->getConnection();

        //-- SQL: Alle Notizen des aktuellen Nutzers abfragen.
        $sql = "SELECT user_id, note_id, note_text FROM notes WHERE user_id = :user_id";
        $query = $database->prepare($sql);
        $query->execute(array(':user_id' => Session::get('user_id')));

        //-- fetchAll() gibt alle Ergebniszeilen als Array zurück.
        // fetchAll() is the PDO method that gets all result rows
        return $query->fetchAll();
    }

    /**
     * Get a single note
     * @param int $note_id id of the specific note
     * @return object a single object (the result)
     */
    //-- Lädt eine einzelne Notiz anhand ihrer ID (nur eigene Notizen).
    public static function getNote($note_id)
    {
        $database = DatabaseFactory::getFactory()->getConnection();

        //-- SQL: Eine bestimmte Notiz des aktuellen Nutzers laden.
        $sql = "SELECT user_id, note_id, note_text FROM notes WHERE user_id = :user_id AND note_id = :note_id LIMIT 1";
        $query = $database->prepare($sql);
        $query->execute(array(':user_id' => Session::get('user_id'), ':note_id' => $note_id));

        //-- fetch() gibt genau eine Ergebniszeile zurück.
        // fetch() is the PDO method that gets a single result
        return $query->fetch();
    }

    public static function getNoteMySQLi($note_id)
    {
        $database = DatabaseFactoryMySQLi::getFactory()->getConnection();

        $sql = "SELECT user_id, note_id, note_text
                FROM notes
                WHERE user_id = ? AND note_id = ?
                LIMIT 1";

        $stmt = $database->prepare($sql);

        $userId = Session::get('user_id');
        $stmt->bind_param("ii", $userId, $note_id);
        $stmt->execute();

        $result = $stmt->get_result();
        $note = $result->fetch_object();

        $stmt->close();

        return $note;
    }

    /**
     * Set a note (create a new one)
     * @param string $note_text note text that will be created
     * @return bool feedback (was the note created properly ?)
     */
    //-- Erstellt eine neue Notiz für den eingeloggten Nutzer.
    //-- Gibt true zurück wenn erfolgreich, sonst false mit Fehlermeldung.
    public static function createNote($note_text)
    {
        //-- Leere Notizen werden nicht gespeichert.
        if (!$note_text || strlen($note_text) == 0) {
            Session::add('feedback_negative', Text::get('FEEDBACK_NOTE_CREATION_FAILED'));
            return false;
        }

        $database = DatabaseFactory::getFactory()->getConnection();

        $sql = "INSERT INTO notes (note_text, user_id) VALUES (:note_text, :user_id)";
        $query = $database->prepare($sql);
        $query->execute(array(':note_text' => $note_text, ':user_id' => Session::get('user_id')));

        if ($query->rowCount() == 1) {
            return true;
        }

        // default return
        Session::add('feedback_negative', Text::get('FEEDBACK_NOTE_CREATION_FAILED'));
        return false;
    }

    /**
     * Update an existing note
     * @param int $note_id id of the specific note
     * @param string $note_text new text of the specific note
     * @return bool feedback (was the update successful ?)
     */
    //-- Ändert den Text einer bestehenden Notiz (nur eigene Notizen können geändert werden).
    public static function updateNote($note_id, $note_text)
    {
        //-- ID und Text müssen vorhanden sein.
        if (!$note_id || !$note_text) {
            return false;
        }

        $database = DatabaseFactory::getFactory()->getConnection();

        $sql = "UPDATE notes SET note_text = :note_text WHERE note_id = :note_id AND user_id = :user_id LIMIT 1";
        $query = $database->prepare($sql);
        $query->execute(array(':note_id' => $note_id, ':note_text' => $note_text, ':user_id' => Session::get('user_id')));

        if ($query->rowCount() == 1) {
            return true;
        }

        Session::add('feedback_negative', Text::get('FEEDBACK_NOTE_EDITING_FAILED'));
        return false;
    }

    /**
     * Delete a specific note
     * @param int $note_id id of the note
     * @return bool feedback (was the note deleted properly ?)
     */
    //-- Löscht eine Notiz anhand ihrer ID (nur eigene Notizen können gelöscht werden).
    public static function deleteNote($note_id)
    {
        //-- ID muss vorhanden sein.
        if (!$note_id) {
            return false;
        }

        $database = DatabaseFactory::getFactory()->getConnection();

        $sql = "DELETE FROM notes WHERE note_id = :note_id AND user_id = :user_id LIMIT 1";
        $query = $database->prepare($sql);
        $query->execute(array(':note_id' => $note_id, ':user_id' => Session::get('user_id')));

        if ($query->rowCount() == 1) {
            return true;
        }

        // default return
        Session::add('feedback_negative', Text::get('FEEDBACK_NOTE_DELETION_FAILED'));
        return false;
    }
}
