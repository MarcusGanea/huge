<div class="container">
    <h1>Chat mit <?= $this->partner->user_name; ?></h1>

    <div class="box">
        <?php $this->renderFeedbackMessages(); ?>

        <p>
            <a href="<?= Config::get('URL') . 'messenger/index'; ?>">Zurück zur Chatliste</a>
        </p>

        <div class="messenger-layout">
            <div class="messenger-sidebar">
                <p><a href="<?= Config::get('URL') . 'messenger/new'; ?>">Neuer Chat</a></p>

                <ul>
                    <?php foreach ($this->chats as $chat) { ?>
                        <li>
                            <a href="<?= Config::get('URL') . 'messenger/chat/' . $chat->partner_id; ?>">
                                <?= $chat->partner_name; ?>
                                <?php if (!empty($chat->unread_count)) { ?>
                                    (<?= $chat->unread_count; ?>)
                                <?php } ?>
                            </a>
                        </li>
                    <?php } ?>
                </ul>
            </div>

            <div class="messenger-content">
                <div class="messenger-messages">
                    <?php foreach ($this->messages as $message) { ?>
                        <div class="<?= ((int) $message->sender_id === (int) Session::get('user_id')) ? 'message-own' : 'message-other'; ?>">
                            <strong><?= $message->sender_name; ?>:</strong>
                            <p><?= $message->content; ?></p>
                        </div>
                    <?php } ?>
                </div>

                <form action="<?= Config::get('URL') . 'messenger/send'; ?>" method="post">
                    <input type="hidden" name="partner_id" value="<?= $this->partner->user_id; ?>">
                    <textarea name="content" rows="4" cols="50" required></textarea>
                    <br>
                    <input type="submit" value="Nachricht senden">
                </form>
            </div>
        </div>
    </div>
</div>