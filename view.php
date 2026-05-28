<?php
require_once __DIR__ . DIRECTORY_SEPARATOR . 'db.php';

$slug = $_GET['slug'] ?? '';
$slug = trim($slug);
$image = null;
$error = '';

if ($slug === '') {
    $error = 'No se ha proporcionado un identificador válido.';
} else {
    $image = getImageBySlug($slug);
    if (!$image || !$image['active']) {
        $error = 'Imagen no encontrada o no está activa.';
    }
}

function getBaseUrl(): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    return $scheme . '://' . $host . $scriptDir;
}

$imageUrl = $image ? getBaseUrl() . '/image/' . rawurlencode($image['filename']) : '';
$pageTitle = $image ? ($image['title'] ?: 'Producto') : 'Imagen no disponible';
$pageDescription = $image ? ($image['description'] ?: 'Imagen del producto disponible.') : 'No se encontró la imagen.';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES | ENT_SUBSTITUTE) ?></title>
    <?php if ($image): ?>
        <meta property="og:type" content="website">
        <meta property="og:title" content="<?= htmlspecialchars($pageTitle, ENT_QUOTES | ENT_SUBSTITUTE) ?>">
        <meta property="og:description" content="<?= htmlspecialchars($pageDescription, ENT_QUOTES | ENT_SUBSTITUTE) ?>">
        <meta property="og:image" content="<?= htmlspecialchars($imageUrl, ENT_QUOTES | ENT_SUBSTITUTE) ?>">
        <meta property="twitter:card" content="summary_large_image">
    <?php endif; ?>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<div class="wa-chat-container" style="max-width: 500px;">
    <!-- WhatsApp style Header -->
    <header class="wa-app-bar">
        <div class="wa-profile-area">
            <div class="wa-avatar">
                <span>🍰</span>
            </div>
            <div class="wa-status-info">
                <span class="wa-title"><?= htmlspecialchars($pageTitle, ENT_QUOTES | ENT_SUBSTITUTE) ?></span>
                <span class="wa-status"><span class="wa-status-dot"></span>En línea</span>
            </div>
        </div>
        <div class="wa-actions">
            <a href="index.php">Volver</a>
        </div>
    </header>

    <!-- WhatsApp Chat Thread -->
    <main class="wa-chat-thread" style="padding: 16px;">
        <?php if ($error): ?>
            <div class="wa-bubble wa-bubble-recv" style="border-left: 4px solid #dc2626; width: 100%;">
                <p class="wa-error-text" style="margin: 0;">⚠️ <?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE) ?></p>
                <div style="margin-top: 12px;">
                    <a href="index.php" class="wa-action-btn" style="display: inline-block;">Ir al Catálogo</a>
                </div>
            </div>
        <?php else: ?>
            <!-- Cake Image inside WhatsApp Sent Media bubble -->
            <div class="wa-bubble wa-bubble-sent" style="max-width: 100%; width: 100%;">
                <div class="wa-image-preview-card">
                    <div class="wa-image-container">
                        <img src="image/<?= rawurlencode($image['filename']) ?>" alt="<?= htmlspecialchars($pageTitle, ENT_QUOTES | ENT_SUBSTITUTE) ?>">
                    </div>
                    <div class="wa-image-caption">
                        <strong style="font-size: 1.05rem; display: block; margin-bottom: 4px;"><?= htmlspecialchars($pageTitle, ENT_QUOTES | ENT_SUBSTITUTE) ?></strong>
                        <span style="font-size: 0.88rem; color: var(--wa-text-secondary);"><?= htmlspecialchars($pageDescription, ENT_QUOTES | ENT_SUBSTITUTE) ?></span>
                    </div>
                    <div class="wa-time-status">
                        <span><?= date('H:i') ?></span>
                        <span class="wa-ticks">✓✓</span>
                    </div>
                </div>
            </div>

            <!-- Action buttons inside an incoming chat bubble -->
            <div class="wa-bubble wa-bubble-recv" style="width: 100%; display: flex; flex-direction: column; gap: 8px;">
                <p style="font-size: 0.85rem; color: var(--wa-text-secondary);">Enlace directo para compartir:</p>
                <input class="wa-input" type="text" readonly value="<?= htmlspecialchars($imageUrl, ENT_QUOTES | ENT_SUBSTITUTE) ?>" onclick="this.select();" style="font-size: 0.82rem; background-color: #f0f2f5;">
                <div style="display: flex; gap: 10px; margin-top: 6px;">
                    <a href="index.php" class="wa-action-btn" style="flex: 1; text-align: center; text-decoration: none; padding: 10px;">Catálogo Completo</a>
                    <a href="image/<?= rawurlencode($image['filename']) ?>" target="_blank" class="wa-action-btn" style="flex: 1; text-align: center; text-decoration: none; padding: 10px; background-color: var(--wa-green-deep); color: white;">Ver Original</a>
                </div>
            </div>
        <?php endif; ?>
    </main>
</div>
</body>
</html>

