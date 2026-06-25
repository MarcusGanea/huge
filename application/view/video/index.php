<div class="vid-page">
    <header class="vid-header">
        <h1>Videos</h1>
        <p class="vid-subtitle">Lade Videos hoch, teile sie und entdecke öffentliche Clips.</p>
    </header>

    <?php $this->renderFeedbackMessages(); ?>

    <!-- Upload-Karte -->
    <div class="vid-upload-card">
        <div class="vid-upload-icon">
            <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                <polyline points="17 8 12 3 7 8"></polyline>
                <line x1="12" y1="3" x2="12" y2="15"></line>
            </svg>
        </div>
        <div class="vid-upload-body">
            <h3>Video hochladen</h3>
            <form method="post" action="<?= Config::get('URL') ?>video/upload" enctype="multipart/form-data" class="vid-upload-form">
                <input type="file" name="video" accept=".mp4,.webm,.ogg" required>
                <button type="submit" class="vid-btn vid-btn-primary">Hochladen</button>
            </form>
        </div>
    </div>

    <!-- Meine Videos -->
    <section class="vid-section">
        <h2 class="vid-section-title">Meine Videos</h2>
        <?php if (empty($this->my_files)): ?>
            <p class="vid-empty">Noch keine Videos hochgeladen.</p>
        <?php else: ?>
            <div class="vid-grid">
                <?php foreach ($this->my_files as $file): ?>
                    <article class="vid-card">
                        <a class="vid-thumb" href="<?= Config::get('URL') ?>video/watch/<?= $file->video_id ?>">
                            <video preload="metadata" muted class="vid-thumb-video">
                                <source src="<?= Config::get('URL') ?>video/serve/<?= $file->video_id ?>#t=0.5" type="video/mp4">
                            </video>
                            <span class="vid-play">
                                <svg viewBox="0 0 24 24" width="26" height="26" fill="currentColor"><path d="M8 5v14l11-7z"></path></svg>
                            </span>
                            <span class="vid-badge <?= $file->shared ? 'is-public' : 'is-private' ?>">
                                <?= $file->shared ? 'Öffentlich' : 'Privat' ?>
                            </span>
                        </a>

                        <div class="vid-card-body">
                            <h3 class="vid-title" title="<?= htmlspecialchars($file->original_name) ?>">
                                <a href="<?= Config::get('URL') ?>video/watch/<?= $file->video_id ?>"><?= htmlspecialchars($file->original_name) ?></a>
                            </h3>
                            <div class="vid-meta">
                                <span class="vid-meta-item">
                                    <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>
                                    <?= (int)$file->like_count ?>
                                </span>
                                <span class="vid-meta-item"><?= round($file->file_size / 1024, 1) ?> KB</span>
                                <span class="vid-meta-item"><?= (int)$file->downloads ?> Downloads</span>
                            </div>
                            <div class="vid-actions">
                                <a class="vid-icon-btn" title="Download" href="<?= Config::get('URL') ?>video/download/<?= $file->video_id ?>">
                                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                                </a>
                                <a class="vid-icon-btn" title="<?= $file->shared ? 'Auf privat stellen' : 'Veröffentlichen' ?>" href="<?= Config::get('URL') ?>video/toggleShare/<?= $file->video_id ?>">
                                    <?php if ($file->shared): ?>
                                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg>
                                    <?php else: ?>
                                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                                    <?php endif; ?>
                                </a>
                                <a class="vid-icon-btn vid-icon-danger" title="Löschen" href="<?= Config::get('URL') ?>video/delete/<?= $file->video_id ?>"
                                   onclick="return confirm('Video wirklich löschen?')">
                                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                </a>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- Öffentliche Videos -->
    <section class="vid-section">
        <h2 class="vid-section-title">Öffentliche Videos</h2>
        <?php if (empty($this->shared_files)): ?>
            <p class="vid-empty">Keine öffentlichen Videos vorhanden.</p>
        <?php else: ?>
            <div class="vid-grid">
                <?php foreach ($this->shared_files as $file): ?>
                    <article class="vid-card">
                        <a class="vid-thumb" href="<?= Config::get('URL') ?>video/watch/<?= $file->video_id ?>">
                            <video preload="metadata" muted class="vid-thumb-video">
                                <source src="<?= Config::get('URL') ?>video/serve/<?= $file->video_id ?>#t=0.5" type="video/mp4">
                            </video>
                            <span class="vid-play">
                                <svg viewBox="0 0 24 24" width="26" height="26" fill="currentColor"><path d="M8 5v14l11-7z"></path></svg>
                            </span>
                        </a>

                        <div class="vid-card-body">
                            <h3 class="vid-title" title="<?= htmlspecialchars($file->original_name) ?>">
                                <a href="<?= Config::get('URL') ?>video/watch/<?= $file->video_id ?>"><?= htmlspecialchars($file->original_name) ?></a>
                            </h3>
                            <div class="vid-meta">
                                <span class="vid-meta-item">
                                    <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>
                                    <?= (int)$file->like_count ?>
                                </span>
                                <span class="vid-meta-item vid-author"><?= htmlspecialchars($file->user_name) ?></span>
                            </div>
                            <div class="vid-actions">
                                <a class="vid-icon-btn" title="Download" href="<?= Config::get('URL') ?>video/download/<?= $file->video_id ?>">
                                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                                </a>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>

<style>
.vid-page {
    --accent: #000000;
    --accent-hover: #5c5c61;
    --bg-card: #ffffff;
    --border: #e5e7eb;
    --text: #111827;
    --text-muted: #6b7280;
    --danger: #dc2626;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    color: var(--text);
    max-width: 1100px;
    margin: 0 auto;
    padding: 8px 4px 40px;
}

.vid-header { margin-bottom: 20px; }
.vid-header h1 { font-size: 28px; font-weight: 700; margin: 0 0 4px; }
.vid-subtitle { color: var(--text-muted); margin: 0; font-size: 15px; }

/* Upload-Karte */
.vid-upload-card {
    display: flex;
    align-items: center;
    gap: 16px;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 18px 20px;
    margin-bottom: 32px;
    box-shadow: 0 1px 3px rgb(0 0 0 / 0.06);
}
.vid-upload-icon {
    flex: none;
    width: 48px; height: 48px;
    display: grid; place-items: center;
    background: #eef2ff;
    color: var(--accent);
    border-radius: 12px;
}
.vid-upload-body { flex: 1; }
.vid-upload-body h3 { margin: 0 0 10px; font-size: 16px; font-weight: 600; }
.vid-upload-form { display: flex; gap: 12px; flex-wrap: wrap; align-items: center; }
.vid-upload-form input[type="file"] { font-size: 14px; color: var(--text-muted); }

/* Buttons */
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

/* Sektionen */
.vid-section { margin-bottom: 40px; }
.vid-section-title { font-size: 20px; font-weight: 700; margin: 0 0 16px; }
.vid-empty { color: var(--text-muted); font-size: 15px; }

/* Grid */
.vid-grid {
    display: grid;
    gap: 20px;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
}

/* Karte */
.vid-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 14px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgb(0 0 0 / 0.06);
    transition: box-shadow .25s ease, transform .25s ease;
}
.vid-card:hover {
    box-shadow: 0 10px 28px rgb(0 0 0 / 0.12);
    transform: translateY(-3px);
}

/* Thumbnail */
.vid-thumb {
    position: relative;
    display: block;
    aspect-ratio: 16 / 9;
    background: #000;
    overflow: hidden;
}
.vid-thumb-video {
    width: 100%; height: 100%;
    object-fit: cover;
    display: block;
    pointer-events: none;
    transition: transform .35s ease, filter .35s ease;
}
.vid-thumb:hover .vid-thumb-video {
    transform: scale(1.06);
    filter: brightness(0.65);
}
.vid-play {
    position: absolute;
    top: 50%; left: 50%;
    transform: translate(-50%, -50%) scale(0.85);
    width: 56px; height: 56px;
    display: grid; place-items: center;
    background: rgb(255 255 255 / 0.92);
    color: var(--accent);
    border-radius: 50%;
    opacity: 0;
    transition: opacity .3s ease, transform .3s ease;
    box-shadow: 0 4px 14px rgb(0 0 0 / 0.3);
}
.vid-thumb:hover .vid-play,
.vid-thumb:focus .vid-play {
    opacity: 1;
    transform: translate(-50%, -50%) scale(1);
}
.vid-badge {
    position: absolute;
    top: 10px; left: 10px;
    padding: 3px 10px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 600;
    color: #fff;
    backdrop-filter: blur(4px);
}
.vid-badge.is-public { background: rgb(16 185 129 / 0.9); }
.vid-badge.is-private { background: rgb(107 114 128 / 0.85); }

/* Karten-Body */
.vid-card-body { padding: 14px 16px 16px; }
.vid-title {
    font-size: 15px;
    font-weight: 600;
    margin: 0 0 8px;
    line-height: 1.35;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.vid-title a { color: var(--text); text-decoration: none; }
.vid-title a:hover { color: var(--accent); }

.vid-meta {
    display: flex;
    align-items: center;
    gap: 14px;
    color: var(--text-muted);
    font-size: 13px;
    margin-bottom: 12px;
    flex-wrap: wrap;
}
.vid-meta-item { display: inline-flex; align-items: center; gap: 5px; }
.vid-author { font-weight: 500; }

/* Aktionen */
.vid-actions {
    display: flex;
    gap: 6px;
    border-top: 1px solid var(--border);
    padding-top: 12px;
}
.vid-icon-btn {
    width: 36px; height: 36px;
    display: grid; place-items: center;
    border-radius: 9px;
    color: var(--text-muted);
    text-decoration: none;
    transition: background .2s ease, color .2s ease;
}
.vid-icon-btn:hover { background: #f3f4f6; color: var(--text); }
.vid-icon-danger:hover { background: #fef2f2; color: var(--danger); }
</style>