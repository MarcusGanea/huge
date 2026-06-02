<?php $currentUserId = (int) Session::get('user_id'); ?>
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
                <div class="discussion">
                    <?php foreach ($this->messages as $index => $message) { ?>
                        <?php
                        $isOwnMessage = ((int) $message->sender_id === $currentUserId);
                        $bubbleSideClass = $isOwnMessage ? 'recipient' : 'sender';

                        $previousMessage = isset($this->messages[$index - 1]) ? $this->messages[$index - 1] : null;
                        $nextMessage = isset($this->messages[$index + 1]) ? $this->messages[$index + 1] : null;

                        $sameAsPrevious = $previousMessage && ((int) $previousMessage->sender_id === (int) $message->sender_id);
                        $sameAsNext = $nextMessage && ((int) $nextMessage->sender_id === (int) $message->sender_id);

                        $bubblePositionClass = '';
                        if (!$sameAsPrevious && $sameAsNext) {
                            $bubblePositionClass = 'first';
                        } elseif ($sameAsPrevious && $sameAsNext) {
                            $bubblePositionClass = 'middle';
                        } elseif ($sameAsPrevious && !$sameAsNext) {
                            $bubblePositionClass = 'last';
                        }
                        ?>

                        <div class="bubble <?= $bubbleSideClass; ?><?= $bubblePositionClass ? ' ' . $bubblePositionClass : ''; ?>">
                            <?= nl2br($this->encodeHTML($message->content)); ?>
                        </div>
                    <?php } ?>
                </div>

                <form action="<?= Config::get('URL') . 'messenger/send'; ?>" method="post">
                    <input type="hidden" name="partner_id" value="<?= (int) $this->partner->user_id; ?>">
                    <input type="text" name="content" placeholder="Nachricht schreiben..." required>
                    <input type="submit" value="Nachricht senden">
                </form>
            </div>
        </div>
    </div>
</div>