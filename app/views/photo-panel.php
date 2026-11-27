<?php
declare(strict_types=1);

$photoFile = isset($photoFile) && is_string($photoFile)
    ? $photoFile
    : 'editorial-chair.png';
$photoAlt = isset($photoAlt) && is_string($photoAlt)
    ? $photoAlt
    : 'Editorial Vestí com roupas e estilo pessoal';
?>
<aside class="photo-panel">
    <img
        src="<?= e(app_url('assets/images/' . $photoFile)) ?>"
        alt="<?= e($photoAlt) ?>"
    >
    <p><?= e(APP_SLOGAN) ?></p>
</aside>
