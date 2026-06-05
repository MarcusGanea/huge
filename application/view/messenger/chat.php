<?php $currentUserId = (int) Session::get('user_id'); ?>
<div class="container">

    <?php $this->renderFeedbackMessages(); ?>

    <div class="chat-shell">

        <!-- SIDEBAR: only DMs -->
        <div class="chat-sidebar">
            <div class="chat-sidebar-section">
                <h4>Direktnachrichten</h4>
                <a href="<?= Config::get('URL') . 'messenger/new'; ?>" class="new-btn">+ Neuer Chat</a>
            </div>
            <ul>
                <?php foreach ($this->chats as $chat) { ?>
                    <li>
                        <a href="<?= Config::get('URL') . 'messenger/chat/' . $chat->partner_id; ?>"
                           <?php if ((int)$chat->partner_id === (int)$this->partner->user_id) { echo 'class="active-chat"'; } ?>>
                            <?= htmlspecialchars($chat->partner_name, ENT_QUOTES, 'UTF-8'); ?>
                            <?php if (!empty($chat->unread_count)) { ?>
                                <span class="message-badge"><?= (int)$chat->unread_count; ?></span>
                            <?php } ?>
                        </a>
                    </li>
                <?php } ?>
            </ul>
        </div>

        <!-- MAIN CHAT AREA -->
        <div class="chat-main">

            <!-- Header -->
            <div class="chat-header">
                <h2>Chat mit <?= htmlspecialchars($this->partner->user_name, ENT_QUOTES, 'UTF-8'); ?></h2>
                <div class="chat-header-actions">
                    <a href="<?= Config::get('URL') . 'messenger/index'; ?>">Übersicht</a>
                </div>
            </div>

            <!-- Messages -->
            <div class="chat-messages">
                <?php foreach ($this->messages as $index => $message) {
                    $isOwn = ((int)$message->sender_id === $currentUserId);
                    $side  = $isOwn ? 'recipient' : 'sender';
                    $prev  = isset($this->messages[$index - 1]) ? $this->messages[$index - 1] : null;
                    $next  = isset($this->messages[$index + 1]) ? $this->messages[$index + 1] : null;
                    $samePrev = $prev && ((int)$prev->sender_id === (int)$message->sender_id);
                    $sameNext = $next && ((int)$next->sender_id === (int)$message->sender_id);
                    $pos = '';
                    if (!$samePrev && $sameNext)       $pos = 'first';
                    elseif ($samePrev && $sameNext)    $pos = 'middle';
                    elseif ($samePrev && !$sameNext)   $pos = 'last';
                ?>
                    <div class="bubble <?= $side; ?><?= $pos ? ' ' . $pos : ''; ?>">
                        <?= nl2br(htmlspecialchars($message->content, ENT_QUOTES, 'UTF-8')); ?>
                    </div>
                <?php } ?>
            </div>

            <!-- Send form -->
            <form action="<?= Config::get('URL') . 'messenger/send'; ?>" method="post" class="chat-form">
                <input type="hidden" name="partner_id" value="<?= (int)$this->partner->user_id; ?>">
                <input type="text" name="content" placeholder="Nachricht schreiben..." autocomplete="off" required>
                <input type="submit" value="Senden">
            </form>

        </div><!-- /.chat-main -->
    </div><!-- /.chat-shell -->
</div>