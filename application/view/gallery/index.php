<div class="container">
    <h1>Galerie</h1>
    <p>Bewege die Maus über ein Bild für Details und Aktionen!</p>

    <?php $this->renderFeedbackMessages(); ?>

    <!-- Upload-Formular -->
    <div class="box">
        <h3>Bild hochladen</h3>
        <form method="post" action="<?= Config::get('URL') ?>gallery/upload" enctype="multipart/form-data">
            <input type="file" name="image" accept=".jpg,.jpeg,.png,.gif,.webp" required>
            <button type="submit">Hochladen</button>
        </form>
    </div>

    <!-- Meine Bilder -->
    <div class="box">
        <h2>Meine Bilder</h2>
        <?php if (empty($this->my_files)): ?>
            <p>Noch keine Bilder hochgeladen.</p>
        <?php else: ?>
            <section class="gallery">
                <?php foreach ($this->my_files as $file): ?>
                    <figure tabindex="1">
                        <img src="<?= Config::get('URL') ?>gallery/serve/<?= $file->file_id ?>"
                             alt="<?= htmlspecialchars($file->original_name) ?>">
                        <figcaption>
                            <strong><?= htmlspecialchars($file->original_name) ?></strong><br>
                            <small><?= round($file->file_size / 1024, 1) ?> KB &bull; Downloads: <?= $file->downloads ?></small>
                            <div class="fig-actions">
                                <a href="<?= Config::get('URL') ?>gallery/download/<?= $file->file_id ?>">⬇ Download</a>
                                <a href="<?= Config::get('URL') ?>gallery/toggleShare/<?= $file->file_id ?>">
                                    <?= $file->shared ? '🔓 Öffentlich' : '🔒 Privat' ?>
                                </a>
                                <a href="<?= Config::get('URL') ?>gallery/delete/<?= $file->file_id ?>"
                                   onclick="return confirm('Bild wirklich löschen?')">🗑 Löschen</a>
                            </div>
                        </figcaption>
                    </figure>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    </div>

    <!-- Öffentliche Bilder anderer User -->
    <div class="box">
        <h2>Öffentliche Galerie</h2>
        <?php if (empty($this->shared_files)): ?>
            <p>Keine öffentlichen Bilder vorhanden.</p>
        <?php else: ?>
            <section class="gallery">
                <?php foreach ($this->shared_files as $file): ?>
                    <figure tabindex="1">
                        <img src="<?= Config::get('URL') ?>gallery/serve/<?= $file->file_id ?>"
                             alt="<?= htmlspecialchars($file->original_name) ?>">
                        <figcaption>
                            <strong><?= htmlspecialchars($file->original_name) ?></strong><br>
                            <small>Von: <?= htmlspecialchars($file->user_name) ?></small>
                            <div class="fig-actions">
                                <a href="<?= Config::get('URL') ?>gallery/download/<?= $file->file_id ?>">⬇ Download</a>
                            </div>
                        </figcaption>
                    </figure>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    </div>
</div>

<style>
/* ── Zoom-Grid (nach selfhtml.org Vorlage) ───────────────────────── */
.gallery {
    --size: 10em;
    --gap:  1em;
    --zoom: 2;

    display: grid;
    gap: var(--gap);
    grid-template-columns: repeat(auto-fill, var(--size));
    margin-top: 1em;
}

.gallery figure {
    margin: 0;
    padding: 0;
    position: relative;
    width: var(--size);
    height: var(--size);
    overflow: visible;
    z-index: 0;
}

.gallery figure:hover,
.gallery figure:focus {
    z-index: 10;
}

/* Bild: startet klein, zoomt bei Hover */
.gallery figure img {
    width: 0;
    height: 0;
    min-width: 100%;
    min-height: 100%;
    object-fit: cover;
    cursor: pointer;
    filter: grayscale(70%);
    transition: .35s linear;
    display: block;
}

.gallery figure:hover img,
.gallery figure:focus img {
    filter: grayscale(0);
    min-width: unset;
    min-height: unset;
    width:  calc(var(--size) * var(--zoom));
    height: calc(var(--size) * var(--zoom));
}

/* Figcaption: nur bei Hover sichtbar */
.gallery figcaption {
    position: absolute;
    bottom: 0;
    left: 0;
    width: 100%;
    padding: 6px 8px;
    box-sizing: border-box;
    color: white;
    background: rgb(0 0 0 / 0.55);
    opacity: 0;
    transition: opacity .25s;
    font-size: 11px;
    line-height: 1.4;
    pointer-events: none;
}

.gallery figure:hover figcaption,
.gallery figure:focus figcaption {
    opacity: 1;
    pointer-events: auto;
    /* bei gezoomtem Bild mitgehen */
    width:  calc(var(--size) * var(--zoom));
}

/* Aktions-Links in der Figcaption */
.fig-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-top: 5px;
}

.fig-actions a {
    color: #aee;
    text-decoration: none;
    font-size: 11px;
    white-space: nowrap;
}

.fig-actions a:hover {
    text-decoration: underline;
    color: #fff;
}
</style>