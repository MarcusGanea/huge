<div class="container">
    <h1>Neuer Chat</h1>
    <div class="box">
        <?php $this->renderFeedbackMessages(); ?>

        <table class="overview-table">
            <thead>
                <tr>
                    <th>Id</th>
                    <th>Benutzername</th>
                    <th>E-Mail</th>
                    <th>Chat</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($this->users as $user) { ?>
                    <tr>
                        <td><?= $user->user_id; ?></td>
                        <td><?= $user->user_name; ?></td>
                        <td><?= $user->user_email; ?></td>
                        <td>
                            <a href="<?= Config::get('URL') . 'messenger/start/' . $user->user_id; ?>">
                                Chat starten
                            </a>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</div>