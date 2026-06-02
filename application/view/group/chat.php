<?php $currentUserId = (int) Session::get('user_id'); ?>
<div class="container">
    <h1>Gruppe: <?= $this->group->chat_name; ?></h1>

    <div class="box">
        <?php $this->renderFeedbackMessages(); ?>

        <div class="messenger-layout">
            <!-- Sidebar: own groups + DM list -->
            <div class="messenger-sidebar">
                <p><strong>Gruppen</strong></p>
                <p><a href="<?= Config::get('URL') . 'group/create'; ?>">+ Neue Gruppe</a></p>
                <ul>
                    <?php foreach ($this->my_groups as $group) { ?>
                        <li>
                            <a href="<?= Config::get('URL') . 'group/chat/' . $group->chat_id; ?>"
                               <?php if ((int)$group->chat_id === (int)$this->active_chat_id) { echo 'class="active-chat"'; } ?>>
                                <?= $group->chat_name; ?>
                                <?php if (!empty($group->unread_count)) { ?>
                                    <span class="message-badge"><?= $group->unread_count; ?></span>
                                <?php } ?>
                            </a>
                        </li>
                    <?php } ?>
                </ul>

                <hr>

                <p><strong>Direktnachrichten</strong></p>
                <p><a href="<?= Config::get('URL') . 'messenger/new'; ?>">+ Neuer Chat</a></p>
                <ul>
                    <?php foreach ($this->dm_chats as $chat) { ?>
                        <li>
                            <a href="<?= Config::get('URL') . 'messenger/chat/' . $chat->partner_id; ?>">
                                <?= $chat->partner_name; ?>
                                <?php if (!empty($chat->unread_count)) { ?>
                                    <span class="message-badge"><?= $chat->unread_count; ?></span>
                                <?php } ?>
                            </a>
                        </li>
                    <?php } ?>
                </ul>
            </div>

            <!-- Chat window: identical layout to messenger/chat.php, adds sender name for foreign messages -->
            <div class="messenger-content">
                <div class="discussion">
                    <?php foreach ($this->messages as $index => $message) { ?>
                        <?php
                        $isOwnMessage = ((int) $message->sender_id === $currentUserId);
                        $bubbleSideClass = $isOwnMessage ? 'recipient' : 'sender';

                        $previousMessage = isset($this->messages[$index - 1]) ? $this->messages[$index - 1] : null;
                        $nextMessage     = isset($this->messages[$index + 1]) ? $this->messages[$index + 1] : null;

                        $sameAsPrevious = $previousMessage && ((int) $previousMessage->sender_id === (int) $message->sender_id);
                        $sameAsNext     = $nextMessage     && ((int) $nextMessage->sender_id     === (int) $message->sender_id);

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
                            <?php if (!$isOwnMessage && !$sameAsPrevious) { ?>
                                <div class="bubble-sender-name"><?= $this->encodeHTML($message->sender_name); ?></div>
                            <?php } ?>
                            <?= nl2br($this->encodeHTML($message->content)); ?>
                        </div>
                    <?php } ?>
                </div>

                <form action="<?= Config::get('URL') . 'group/send'; ?>" method="post">
                    <input type="hidden" name="chat_id" value="<?= (int) $this->group->chat_id; ?>">
                    <input type="text" name="content" placeholder="Nachricht schreiben..." required>
                    <input type="submit" value="Nachricht senden">
                </form>
            </div>
        </div>
    </div>
</div>
