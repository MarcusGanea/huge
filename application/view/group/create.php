<div class="container">
    <h1>Neue Gruppe erstellen</h1>
    <div class="box">
        <?php $this->renderFeedbackMessages(); ?>

        <p><a href="<?= Config::get('URL') . 'group/index'; ?>">&larr; Zurück zur Gruppenübersicht</a></p>

        <form action="<?= Config::get('URL') . 'group/doCreate'; ?>" method="post">
            <label for="group_name">Gruppenname:</label>
            <input type="text"
                   id="group_name"
                   name="group_name"
                   maxlength="40"
                   required
                   placeholder="z.B. Projektteam Alpha">
            <input type="submit" value="Gruppe erstellen">
        </form>
    </div>
</div>
