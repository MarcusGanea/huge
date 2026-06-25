<div class="watch-page">

    <a class="watch-back" href="<?= Config::get('URL') ?>video/index">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
        Zurück zur Übersicht
    </a>

    <?php $this->renderFeedbackMessages(); ?>

    <!-- Player -->
    <div class="watch-player-wrap">
        <video controls autoplay preload="metadata" class="watch-player">
            <source src="<?= Config::get('URL') ?>video/serve/<?= $this->file->video_id ?>" type="video/mp4">
            Dein Browser unterstützt dieses Video-Tag nicht.
        </video>
    </div>

    <!-- Titel + Like-Leiste -->
    <div class="watch-bar">
        <h1 class="watch-title"><?= htmlspecialchars($this->file->original_name) ?></h1>
        <a class="like-btn <?= $this->has_liked ? 'is-liked' : '' ?>"
           href="<?= Config::get('URL') ?>video/like/<?= $this->file->video_id ?>">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="<?= $this->has_liked ? 'currentColor' : 'none' ?>" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>
            <span><?= $this->has_liked ? 'Geliked' : 'Liken' ?></span>
            <span class="like-count"><?= (int)$this->like_count ?></span>
        </a>
    </div>

    <!-- Kommentare -->
    <div class="comment-section">
        <h2 class="comment-heading"><?= count($this->comments) ?> Kommentar<?= count($this->comments) == 1 ? '' : 'e' ?></h2>

        <form method="post" action="<?= Config::get('URL') ?>video/comment/<?= $this->file->video_id ?>" class="comment-form">
            <textarea name="comment_text" rows="2" placeholder="Schreibe einen Kommentar..." required></textarea>
            <div class="comment-form-actions">
                <button type="submit" class="vid-btn vid-btn-primary">Kommentieren</button>
            </div>
        </form>

        <?php if (empty($this->comments)): ?>
            <p class="comment-empty">Noch keine Kommentare. Sei der Erste!</p>
        <?php else: ?>
            <ul class="comment-list">
                <?php foreach ($this->comments as $comment): ?>
                    <li class="comment-item">
                        <div class="comment-avatar"><?= strtoupper(substr($comment->user_name, 0, 1)) ?></div>
                        <div class="comment-body">
                            <div class="comment-head">
                                <strong><?= htmlspecialchars($comment->user_name) ?></strong>
                                <span class="comment-date"><?= htmlspecialchars($comment->created_at) ?></span>
                            </div>
                            <p class="comment-text"><?= nl2br(htmlspecialchars($comment->comment_text)) ?></p>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<style>
.watch-page {
    --accent: #4f46e5;
    --accent-hover: #4338ca;
    --like: #e0245e;
    --bg-card: #ffffff;
    --border: #e5e7eb;
    --text: #111827;
    --text-muted: #6b7280;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    color: var(--text);
    max-width: 900px;
    margin: 0 auto;
    padding: 8px 4px 40px;
}

.watch-back {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: var(--text-muted);
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
    margin-bottom: 16px;
    transition: color .2s ease;
}
.watch-back:hover { color: var(--accent); }

/* Player */
.watch-player-wrap {
    background: #000;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 8px 30px rgb(0 0 0 / 0.18);
}
.watch-player {
    width: 100%;
    max-height: 70vh;
    display: block;
}

/* Titel + Like-Leiste */
.watch-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
    margin: 20px 2px 0;
}
.watch-title { font-size: 22px; font-weight: 700; margin: 0; line-height: 1.3; }

.like-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 9px 18px;
    border: 1px solid var(--border);
    border-radius: 999px;
    background: #fff;
    color: var(--text);
    text-decoration: none;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: background .2s ease, border-color .2s ease, color .2s ease;
}
.like-btn:hover { background: #f9fafb; }
.like-btn .like-count {
    background: #f3f4f6;
    border-radius: 999px;
    padding: 1px 9px;
    font-size: 13px;
}
.like-btn.is-liked {
    background: #fdf2f6;
    border-color: var(--like);
    color: var(--like);
}
.like-btn.is-liked .like-count { background: rgb(224 36 94 / 0.12); }

/* Buttons (geteilt mit index) */
.vid-btn {
    border: none;
    border-radius: 9px;
    padding: 9px 18px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: background .2s ease, transform .1s ease;
}
.vid-btn-primary { background: var(--accent); color: #fff; }
.vid-btn-primary:hover { background: var(--accent-hover); }
.vid-btn-primary:active { transform: translateY(1px); }

/* Kommentare */
.comment-section { margin-top: 36px; }
.comment-heading { font-size: 18px; font-weight: 700; margin: 0 0 18px; }

.comment-form {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 14px;
    margin-bottom: 28px;
}
.comment-form textarea {
    width: 100%;
    box-sizing: border-box;
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 10px 12px;
    font-size: 14px;
    font-family: inherit;
    resize: vertical;
    outline: none;
    transition: border-color .2s ease;
}
.comment-form textarea:focus { border-color: var(--accent); }
.comment-form-actions { display: flex; justify-content: flex-end; margin-top: 10px; }
.comment-empty { color: var(--text-muted); font-size: 15px; }

.comment-list { list-style: none; padding: 0; margin: 0; }
.comment-item {
    display: flex;
    gap: 14px;
    padding: 16px 0;
    border-bottom: 1px solid var(--border);
}
.comment-avatar {
    flex: none;
    width: 40px; height: 40px;
    display: grid; place-items: center;
    background: var(--accent);
    color: #fff;
    border-radius: 50%;
    font-weight: 700;
    font-size: 16px;
}
.comment-body { flex: 1; }
.comment-head { display: flex; align-items: baseline; gap: 8px; margin-bottom: 3px; }
.comment-head strong { font-size: 14px; }
.comment-date { font-size: 12px; color: var(--text-muted); }
.comment-text { margin: 0; font-size: 14px; line-height: 1.5; color: #374151; }
</style>
