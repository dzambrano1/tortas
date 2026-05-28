<?php
function getImages(string $dir): array {
    $files = [];
    $patterns = ['*.{png,PNG}', '*.{jpg,JPG}', '*.{jpeg,JPEG}', '*.{gif,GIF}', '*.{webp,WEBP}'];
    // Standardize directory slashes to forward slashes for robust cross-platform globbing
    $dir = str_replace('\\', '/', $dir);
    foreach ($patterns as $pattern) {
        $paths = glob($dir . '/' . $pattern, GLOB_BRACE);
        if (is_array($paths)) {
            foreach ($paths as $file) {
                if (is_file($file)) {
                    $files[] = basename($file);
                }
            }
        }
    }
    sort($files, SORT_NATURAL | SORT_FLAG_CASE);
    return $files;
}

function getConfig(): array {
    $configPath = __DIR__ . DIRECTORY_SEPARATOR . 'config.php';
    if (!is_file($configPath)) {
        return [
            'telegram_bot_token' => '',
            'telegram_chat_id' => '',
        ];
    }
    return require $configPath;
}

function sendTelegramPhoto(string $botToken, string $chatId, string $filePath, string $caption = ''): array {
    $endpoint = "https://api.telegram.org/bot{$botToken}/sendPhoto";
    $photo = curl_file_create($filePath, mime_content_type($filePath) ?: 'application/octet-stream', basename($filePath));
    $body = [
        'chat_id' => $chatId,
        'photo' => $photo,
        'caption' => $caption,
    ];

    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    $response = curl_exec($ch);
    $error = curl_error($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['status' => $status, 'response' => $response, 'error' => $error];
}

$config = getConfig();
$images = getImages(__DIR__ . DIRECTORY_SEPARATOR . 'image');
$selected = $images[0] ?? '';
$chatIdValue = $config['telegram_chat_id'] ?? '';
$successMessage = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected = $_POST['image'] ?? $selected;
    $chatIdValue = trim($_POST['chat_id'] ?? $chatIdValue);

    if ($selected === '' || !in_array($selected, $images, true)) {
        $error = 'Please select a valid image from the list.';
    } elseif ($chatIdValue === '') {
        $error = 'Please enter the chat ID or channel username.';
    } elseif (empty($config['telegram_bot_token'])) {
        $error = 'Telegram bot token is not configured in config.php.';
    } else {
        $imagePath = __DIR__ . DIRECTORY_SEPARATOR . 'image' . DIRECTORY_SEPARATOR . $selected;
        if (!is_file($imagePath)) {
            $error = 'Selected image could not be found.';
        } else {
            $response = sendTelegramPhoto($config['telegram_bot_token'], $chatIdValue, $imagePath, 'Here is the selected image.');
            if ($response['status'] !== 200) {
                $data = json_decode((string)$response['response'], true);
                $details = $response['error'] ?: ($data['description'] ?? $response['response']);
                $error = 'Telegram send failed: ' . $details;
            } else {
                $successMessage = 'Image sent successfully through the Telegram bot.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Send Image via Telegram</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<div class="container">
    <h1>Send Image via Telegram</h1>
    <p>This page sends the selected image directly through a Telegram bot. It is free to use with a bot token and a target chat.</p>

    <?php if (empty($config['telegram_bot_token'])): ?>
        <div class="note">
            Configure <code>telegram_bot_token</code> in <code>config.php</code> before sending.
            Your recipient must also start the bot or the bot must be allowed to send messages to the target chat.
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <p class="error"><?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE) ?></p>
    <?php endif; ?>
    <?php if ($successMessage): ?>
        <p class="success"><?= htmlspecialchars($successMessage, ENT_QUOTES | ENT_SUBSTITUTE) ?></p>
    <?php endif; ?>

    <form method="post" id="sendForm" class="grid grid-2">
        <div>
            <label for="image">Choose image</label>
            <select id="image" name="image" required>
                <?php foreach ($images as $image): ?>
                    <option value="<?= htmlspecialchars($image, ENT_QUOTES | ENT_SUBSTITUTE) ?>"<?= $image === $selected ? ' selected' : '' ?>>
                        <?= htmlspecialchars($image, ENT_QUOTES | ENT_SUBSTITUTE) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="chat_id" class="field-label">Telegram chat ID or channel username</label>
            <input id="chat_id" name="chat_id" type="text" placeholder="e.g. -123456789 or @channelusername" value="<?= htmlspecialchars($chatIdValue, ENT_QUOTES | ENT_SUBSTITUTE) ?>" required>

            <button type="submit" class="primary-button">Send Image via Telegram</button>
        </div>

        <div class="preview drop-zone">
            <?php if ($selected): ?>
                <img id="imagePreview" src="image/<?= rawurlencode($selected) ?>" alt="Selected image preview">
                <p class="drop-hint">Drag an image file from the <code>./image</code> folder here to select it.</p>
            <?php else: ?>
                <p>No images found in the image folder.</p>
            <?php endif; ?>
        </div>
    </form>

    <div class="note">
        <strong>Telegram bot setup notes</strong>
        <ul style="padding-left: 18px; margin: 0;">
            <li>Create a bot with <code>@BotFather</code> and place its token in <code>config.php</code>.</li>
            <li>The recipient must send a message to the bot first so the bot can message them.</li>
            <li>Use a chat ID, or a public channel username like <code>@channelusername</code>.</li>
        </ul>
    </div>

    <div class="note">
        <strong>Want the WhatsApp app too?</strong>
        <p><a href="index.php">Open WhatsApp image share page</a></p>
    </div>
</div>

<script>
    const imageSelect = document.getElementById('image');
    const imagePreview = document.getElementById('imagePreview');
    const dropZone = document.querySelector('.drop-zone');

    if (imageSelect && imagePreview) {
        imageSelect.addEventListener('change', () => {
            imagePreview.src = 'image/' + encodeURIComponent(imageSelect.value);
        });
    }

    if (dropZone) {
        const availableImages = Array.from(imageSelect.options).map(option => option.value);
        const setSelectedImage = fileName => {
            const matching = availableImages.find(image => image.toLowerCase() === fileName.toLowerCase());
            if (!matching) {
                return false;
            }
            imageSelect.value = matching;
            imagePreview.src = 'image/' + encodeURIComponent(matching);
            return true;
        };

        const preventDefault = event => {
            event.preventDefault();
            event.stopPropagation();
        };

        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, event => {
                preventDefault(event);
                dropZone.classList.add('drag-over');
            });
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, event => {
                preventDefault(event);
                dropZone.classList.remove('drag-over');
            });
        });

        dropZone.addEventListener('drop', event => {
            const file = event.dataTransfer.files[0];
            if (file && file.name) {
                const isValid = setSelectedImage(file.name);
                if (!isValid) {
                    alert('This file is not recognized. Please drop an image from the ./image folder.');
                }
            }
        });
    }
</script>
</body>
</html>
