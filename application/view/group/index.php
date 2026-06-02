<div class="container">
    <h1>Gruppen</h1>
    <div class="box">
        <?php $this->renderFeedbackMessages(); ?>

        <p>
            <a href="<?= Config::get('URL') . 'group/create'; ?>">+ Neue Gruppe erstellen</a>
            &nbsp;|&nbsp;
            <a href="<?= Config::get('URL') . 'messenger/index'; ?>">Direktnachrichten</a>
        </p>

        <h3>Meine Gruppen</h3>
        <?php if (empty($this->my_groups)) { ?>
            <p>Du bist noch in keiner Gruppe Mitglied.</p>
        <?php } else { ?>
            <ul>
                <?php foreach ($this->my_groups as $group) { ?>
                    <li>
                        <a href="<?= Config::get('URL') . 'group/chat/' . $group->chat_id; ?>">
                            <?= $group->chat_name; ?>
                            (<?= $group->member_count; ?> Mitglieder)
                            <?php if (!empty($group->unread_count)) { ?>
                                <span class="message-badge"><?= $group->unread_count; ?></span>
                            <?php } ?>
                        </a>
                    </li>
                <?php } ?>
            </ul>
        <?php } ?>

        <h3>Verfügbare Gruppen beitreten</h3>
        <?php if (empty($this->available_groups)) { ?>
            <p>Keine weiteren Gruppen zum Beitreten verfügbar.</p>
        <?php } else { ?>
            <table class="overview-table">
                <thead>
                    <tr>
                        <th>Gruppenname</th>
                        <th>Mitglieder</th>
                        <th>Aktion</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($this->available_groups as $group) { ?>
                        <tr>
                            <td><?= $group->chat_name; ?></td>
                            <td><?= $group->member_count; ?></td>
                            <td>
                                <a href="<?= Config::get('URL') . 'group/join/' . $group->chat_id; ?>">
                                    Beitreten
                                </a>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        <?php } ?>
    </div>
</div>
