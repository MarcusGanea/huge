<div class="container">
    <h1>Videos</h1>
    <div class="box">

        <!-- echo out the system feedback (error and success messages) -->
        <?php $this->renderFeedbackMessages(); ?>

        <!-- Upload-Formular -->
    <div class="box">
        <h3>Video hochladen</h3>
        <form method="post" action="<?= Config::get('URL') ?>video/upload" enctype="multipart/form-data">
            <input type="file" name="video" accept=".mp4,.webm,.ogg" required>
            <button type="submit">Hochladen</button>
        </form>
    </div>

    </div>
</div>
