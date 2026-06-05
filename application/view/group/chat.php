<?php $currentUserId = (int) Session::get('user_id'); ?>
<div class="container">

    <?php $this->renderFeedbackMessages(); ?>

    <div class="chat-shell">

        <!-- SIDEBAR: only groups -->
        <div class="chat-sidebar">
            <div class="chat-sidebar-section">
                <h4>Gruppen</h4>
                <a href="<?= Config::get('URL') . 'group/create'; ?>" class="new-btn">+ Neue Gruppe</a>
            </div>
            <ul>
                <?php foreach ($this->my_groups as $g) { ?>
                    <li>
                        <a href="<?= Config::get('URL') . 'group/chat/' . $g->chat_id; ?>"
                           <?php if ((int)$g->chat_id === (int)$this->active_chat_id) { echo 'class="active-chat"'; } ?>>
                            <?= htmlspecialchars($g->chat_name, ENT_QUOTES, 'UTF-8'); ?>
                            <?php if (!empty($g->unread_count)) { ?>
                                <span class="message-badge"><?= (int)$g->unread_count; ?></span>
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
                <h2><?= htmlspecialchars($this->group->chat_name, ENT_QUOTES, 'UTF-8'); ?></h2>
                <div class="chat-header-actions">
                    <?php if (!empty($this->non_members)) { ?>
                        <form action="<?= Config::get('URL') . 'group/doAddMember'; ?>" method="post" class="add-member-form">
                            <input type="hidden" name="chat_id" value="<?= (int)$this->group->chat_id; ?>">
                            <select name="user_id">
                                <?php foreach ($this->non_members as $u) { ?>
                                    <option value="<?= (int)$u->user_id; ?>">
                                        <?= htmlspecialchars($u->user_name, ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php } ?>
                            </select>
                            <button type="submit">+ Mitglied hinzufügen</button>
                        </form>
                    <?php } ?>
                    <a href="<?= Config::get('URL') . 'group/index'; ?>">Gruppenübersicht</a>
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
                        <?php if (!$isOwn && !$samePrev) { ?>
                            <div class="bubble-sender-name"><?= htmlspecialchars($message->sender_name, ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php } ?>
                        <?= nl2br(htmlspecialchars($message->content, ENT_QUOTES, 'UTF-8')); ?>
                    </div>
                <?php } ?>
            </div>

            <!-- Send form -->
            <form action="<?= Config::get('URL') . 'group/send'; ?>" method="post" class="chat-form">
                <input type="hidden" name="chat_id" value="<?= (int)$this->group->chat_id; ?>">
                <input type="text" name="content" placeholder="Nachricht schreiben..." autocomplete="off" required>
                <input type="submit" value="Senden">
            </form>

        </div><!-- /.chat-main -->
    </div><!-- /.chat-shell -->
</div>
