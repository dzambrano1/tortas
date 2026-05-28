<?php
require_once __DIR__ . DIRECTORY_SEPARATOR . 'db.php';

syncFolderImages();

function sanitizePhone(string $phone): string {
    $clean = preg_replace('/[^0-9]/', '', trim($phone));
    if ($clean === '') {
        return '';
    }

    $allowedPrefixes = ['0412', '0414', '0424', '0426'];

    // Local Venezuelan mobile format: 0412xxxxxxx, 0414xxxxxxx, 0424xxxxxxx, 0426xxxxxxx
    if (strlen($clean) === 11 && in_array(substr($clean, 0, 4), $allowedPrefixes, true)) {
        return '58' . $clean;
    }

    // International format with country code but without the leading 0: 58412xxxxxxx, 58414xxxxxxx, 58424xxxxxxx, 58426xxxxxxx
    if (strlen($clean) === 12 && str_starts_with($clean, '58')) {
        $local = substr($clean, 2);
        if (in_array(substr($local, 0, 4), $allowedPrefixes, true)) {
            return $clean;
        }
    }

    // Accept input that includes +58 and the local 0 prefix: +580412xxxxxxx
    if (strlen($clean) === 13 && str_starts_with($clean, '580')) {
        $local = substr($clean, 3);
        if (in_array(substr($local, 0, 4), $allowedPrefixes, true)) {
            return '58' . $local;
        }
    }

    return '';
}

function getBaseUrl(): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    return $scheme . '://' . $host . $scriptDir;
}

$images = getImageRecords(true);
$selected = $images[0]['id'] ?? 0;
$selectedRecord = getImageById($selected);
$selectedFilename = $selectedRecord['filename'] ?? '';
$phoneValue = '';
$shareUrl = '';
$error = '';
$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected = isset($_POST['image']) ? (int)$_POST['image'] : 0;
    $phoneValue = $_POST['phone'] ?? '';
    $phone = sanitizePhone($phoneValue);
    $record = getImageById($selected);

    if (!$record || !$record['active']) {
        $error = 'Please select a valid active image from the list.';
    } elseif ($phone === '') {
        $error = 'Please enter a valid Venezuelan phone number in the format +58 xxx-xxxx.';
    } else {
        $selectedRecord = $record;
        $selectedFilename = $record['filename'];
        $baseUrl = getBaseUrl();
        $productPageUrl = $baseUrl . '/view.php?slug=' . rawurlencode($record['slug']);
        $message = rawurlencode("Hello! Please check this product image:\n$productPageUrl");
        $shareUrl = "https://wa.me/{$phone}?text={$message}";
        $successMessage = 'WhatsApp share link is ready below. Open it on your phone or desktop to send the image URL.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enviar Catálogo - WhatsApp</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<div class="wa-chat-container">
    <!-- WhatsApp App Bar / Header -->
    <header class="wa-app-bar">
        <div class="wa-profile-area">
            <div class="wa-avatar">
                <span>🍰</span>
            </div>
            <div class="wa-status-info">
                <span class="wa-title">Tortas & Postres Delivery</span>
                <span class="wa-status"><span class="wa-status-dot"></span>En línea</span>
            </div>
        </div>
        <div class="wa-actions">
            <a href="manage.php">Panel Admin</a>
        </div>
    </header>

    <!-- WhatsApp Chat Thread -->
    <main class="wa-chat-thread">
        <!-- System notification bubble -->
        <div class="wa-bubble wa-bubble-system">
            🔒 Los mensajes y enlaces de productos son seguros. <strong>Selecciona una torta, ingresa el teléfono del cliente y pulsa el botón para abrir un chat directo con el enlace.</strong>
        </div>

        <div class="wa-desktop-grid">
            <!-- Left Side: Interactive Input Bubble -->
            <section class="wa-bubble wa-bubble-recv" style="max-width: 100%; display: flex; flex-direction: column; gap: 14px;">
                <h2 style="font-size: 1.1rem; color: var(--wa-green-deep); margin-bottom: 4px;">Enviar Producto</h2>
                
                <form method="post" id="shareForm" class="wa-form">
                    <div class="wa-form-group">
                        <label for="searchImage">Buscar Producto</label>
                        <input type="text" id="searchImage" class="wa-input" placeholder="Escribe para filtrar catálogo..." autocomplete="off">
                    </div>

                    <div class="wa-form-group">
                        <label for="image">Selecciona el Producto</label>
                        <select id="image" name="image" class="wa-select" required>
                            <?php foreach ($images as $image): ?>
                                <option value="<?= htmlspecialchars($image['id'], ENT_QUOTES | ENT_SUBSTITUTE) ?>"<?= $image['id'] === $selected ? ' selected' : '' ?> data-filename="<?= htmlspecialchars($image['filename'], ENT_QUOTES | ENT_SUBSTITUTE) ?>" data-title="<?= htmlspecialchars($image['title'] ?: $image['filename'], ENT_QUOTES | ENT_SUBSTITUTE) ?>" data-description="<?= htmlspecialchars($image['description'] ?: 'Delicioso postre artesanal.', ENT_QUOTES | ENT_SUBSTITUTE) ?>">
                                    <?= htmlspecialchars($image['title'] ?: $image['filename'], ENT_QUOTES | ENT_SUBSTITUTE) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="wa-form-group">
                        <label for="phone">Teléfono del Cliente</label>
                        <input id="phone" name="phone" class="wa-input" type="text" placeholder="e.g. 0414 1234567" value="<?= htmlspecialchars($phoneValue, ENT_QUOTES | ENT_SUBSTITUTE) ?>" required>
                        <small style="color: var(--wa-text-secondary); font-size: 0.78rem; margin-top: 2px;">Formato venezolano local (0412/0414/0424/0426/0416).</small>
                    </div>

                    <button type="submit" class="wa-btn" style="margin-top: 8px;">
                        <span>Crear Enlace de WhatsApp</span>
                        <span>✉️</span>
                    </button>
                </form>
            </section>

            <!-- Right Side: Sent Media Attachment Bubble -->
            <section class="wa-bubble wa-bubble-sent" style="max-width: 100%;">
                <div class="wa-image-preview-card">
                    <div class="wa-image-container">
                        <?php if ($selectedFilename): ?>
                            <img id="imagePreview" src="image/<?= rawurlencode($selectedFilename) ?>" alt="Selected image preview">
                        <?php else: ?>
                            <p style="padding: 40px 20px; color: var(--wa-text-secondary);">No hay imágenes activas en el catálogo.</p>
                        <?php endif; ?>
                    </div>
                    <div class="wa-image-caption">
                        <strong id="captionTitle"><?= htmlspecialchars($selectedRecord['title'] ?? 'Torta Seleccionada', ENT_QUOTES | ENT_SUBSTITUTE) ?></strong>
                        <div id="captionDesc" style="font-size: 0.82rem; color: var(--wa-text-secondary); margin-top: 2px;">
                            <?= htmlspecialchars($selectedRecord['description'] ?: 'Delicioso postre artesanal.', ENT_QUOTES | ENT_SUBSTITUTE) ?>
                        </div>
                    </div>
                    <div class="wa-time-status">
                        <span><?= date('H:i') ?></span>
                        <span class="wa-ticks">✓✓</span>
                    </div>
                </div>
            </section>
        </div>

        <!-- Notification of Errors -->
        <?php if ($error): ?>
            <div class="wa-bubble wa-bubble-recv" style="border-left: 4px solid #dc2626; align-self: center; width: 100%;">
                <p class="wa-error-text" style="margin: 0;">⚠️ <?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE) ?></p>
            </div>
        <?php endif; ?>

        <!-- Notification of Success & Link Sharing -->
        <?php if ($successMessage): ?>
            <div class="wa-bubble wa-bubble-sent" style="align-self: center; width: 100%;">
                <p class="wa-success-text" style="color: var(--wa-green-deep); margin-bottom: 8px;">🎉 <?= htmlspecialchars($successMessage, ENT_QUOTES | ENT_SUBSTITUTE) ?></p>
                <?php if ($shareUrl): ?>
                    <div class="wa-share-box">
                        <strong>Enlace de Chat Listo:</strong>
                        <p style="margin: 8px 0;">
                            <a href="<?= htmlspecialchars($shareUrl, ENT_QUOTES | ENT_SUBSTITUTE) ?>" target="_blank" rel="noreferrer noopener" class="wa-share-link">
                                Abrir chat para enviar →
                            </a>
                        </p>
                        <small style="color: var(--wa-text-secondary); display: block;">Haz clic para redirigir directamente al chat de tu cliente con el mensaje precargado.</small>
                    </div>
                <?php endif; ?>
                <div class="wa-time-status">
                    <span><?= date('H:i') ?></span>
                    <span class="wa-ticks" style="color: #53bdeb;">✓✓</span>
                </div>
            </div>
        <?php endif; ?>
    </main>
</div>

<script>
    const searchInput = document.getElementById('searchImage');
    const imageSelect = document.getElementById('image');
    const imagePreview = document.getElementById('imagePreview');
    const captionTitle = document.getElementById('captionTitle');
    const captionDesc = document.getElementById('captionDesc');
    const form = document.getElementById('shareForm');

    const updatePreviewAndCaption = option => {
        if (!option || !option.value) {
            if (imagePreview) imagePreview.style.display = 'none';
            if (captionTitle) captionTitle.textContent = 'Ninguna torta seleccionada';
            if (captionDesc) captionDesc.textContent = '';
            return;
        }
        
        if (imagePreview) {
            imagePreview.src = 'image/' + encodeURIComponent(option.dataset.filename);
            imagePreview.style.display = '';
        }
        if (captionTitle) {
            captionTitle.textContent = option.dataset.title || option.text;
        }
        if (captionDesc) {
            captionDesc.textContent = option.dataset.description || 'Delicioso postre artesanal.';
        }
    };

    if (imageSelect) {
        imageSelect.addEventListener('change', () => {
            const selectedOption = imageSelect.options[imageSelect.selectedIndex];
            updatePreviewAndCaption(selectedOption);
        });
    }

    if (searchInput && imageSelect) {
        const originalOptions = Array.from(imageSelect.options);

        searchInput.addEventListener('input', () => {
            const query = searchInput.value.toLowerCase().trim();
            const currentSelectedValue = imageSelect.value;

            imageSelect.innerHTML = '';

            const matchingOptions = originalOptions.filter(option => {
                const text = option.text.toLowerCase();
                return text.includes(query);
            });

            let selectedOptionToRestore = null;

            matchingOptions.forEach(option => {
                imageSelect.appendChild(option);
                if (option.value === currentSelectedValue) {
                    selectedOptionToRestore = option;
                }
            });

            if (matchingOptions.length > 0) {
                if (selectedOptionToRestore) {
                    imageSelect.value = currentSelectedValue;
                } else {
                    imageSelect.selectedIndex = 0;
                }
                updatePreviewAndCaption(imageSelect.options[imageSelect.selectedIndex]);
            } else {
                const placeholderOpt = document.createElement('option');
                placeholderOpt.value = '';
                placeholderOpt.disabled = true;
                placeholderOpt.selected = true;
                placeholderOpt.textContent = 'Ningún producto coincide';
                imageSelect.appendChild(placeholderOpt);
                updatePreviewAndCaption(null);
            }
        });
    }

    if (form) {
        form.addEventListener('submit', (e) => {
            if (imageSelect.value === '') {
                alert('Por favor, selecciona un producto válido.');
                e.preventDefault();
                return;
            }
            if (document.getElementById('phone').value.trim() === '') {
                alert('Por favor, ingresa el número de teléfono.');
                e.preventDefault();
            }
        });
    }
</script>
</body>
</html>

