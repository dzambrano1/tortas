<?php
session_start();

if (empty($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . DIRECTORY_SEPARATOR . 'db.php';

syncFolderImages();
$errors = [];
$successMessage = '';
$editRecord = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $record = [
            'id' => isset($_POST['id']) ? (int)$_POST['id'] : null,
            'filename' => trim($_POST['filename'] ?? ''),
            'slug' => trim($_POST['slug'] ?? ''),
            'title' => trim($_POST['title'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'active' => isset($_POST['active']) ? 1 : 0,
        ];

        if ($record['filename'] === '') {
            $errors[] = 'Selecciona un nombre de archivo válido.';
        } elseif (!is_file(__DIR__ . DIRECTORY_SEPARATOR . 'image' . DIRECTORY_SEPARATOR . $record['filename'])) {
            $errors[] = 'El archivo no existe en la carpeta ./image.';
        } else {
            $saved = saveImageRecord($record);
            if ($saved) {
                $successMessage = $record['id'] ? 'Registro actualizado correctamente.' : 'Registro creado correctamente.';
            } else {
                $errors[] = 'No se pudo guardar el registro. El nombre de archivo ya existe o hubo un error.';
            }
        }
    }

    if ($action === 'delete' && !empty($_POST['id'])) {
        if (deleteImageRecord((int)$_POST['id'])) {
            $successMessage = 'Registro eliminado correctamente.';
        } else {
            $errors[] = 'No se pudo eliminar el registro.';
        }
    }

    if ($action === 'edit' && !empty($_POST['id'])) {
        $editRecord = getImageById((int)$_POST['id']);
    }
}

$records = getImageRecords(false);
$allFiles = [];
$patterns = ['*.png', '*.jpg', '*.jpeg', '*.gif', '*.webp'];
foreach ($patterns as $pattern) {
    foreach (glob(__DIR__ . DIRECTORY_SEPARATOR . 'image' . DIRECTORY_SEPARATOR . $pattern, GLOB_BRACE) as $filePath) {
        if (is_file($filePath)) {
            $allFiles[] = basename($filePath);
        }
    }
}
$allFiles = array_unique($allFiles);
sort($allFiles, SORT_NATURAL | SORT_FLAG_CASE);

function getBaseUrl(): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    return $scheme . '://' . $host . $scriptDir;
}

function buildImageUrl(string $slug): string {
    return getBaseUrl() . '/view.php?slug=' . rawurlencode($slug);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrar imágenes</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<div class="container">
    <h1>Administrar imágenes</h1>
    <p>Gestión CRUD de los registros de imagenes en <code>./image</code>. Cada registro contiene URL válida, título y descripción.</p>

    <div class="note">
        <strong>Flujo recomendado:</strong>
        <ol style="padding-left: 18px; margin: 0;">
            <li>Agrega o actualiza los metadatos de las imágenes.</li>
            <li>Marca los productos activos para que aparezcan en el envío por WhatsApp.</li>
            <li>El app principal envía el enlace directo a la imagen.</li>
        </ol>
    </div>

    <?php if ($errors): ?>
        <div class="error">
            <ul style="margin: 0; padding-left: 18px;">
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($successMessage): ?>
        <p class="success"><?= htmlspecialchars($successMessage, ENT_QUOTES | ENT_SUBSTITUTE) ?></p>
    <?php endif; ?>

    <div style="margin-bottom: 20px; display:flex; gap: 12px; flex-wrap: wrap; justify-content: space-between; align-items: center; width: 100%;">
        <div style="display:flex; gap: 12px; flex-wrap: wrap;">
            <a href="index.php" class="primary-button" style="display:inline-block; width: auto;">Volver a enviar por WhatsApp</a>
            <a href="manage.php" class="primary-button" style="display:inline-block; width: auto;">Administrar productos</a>
        </div>
        <div>
            <a href="logout.php" class="secondary-button" style="display:inline-block; border-color: #dc2626; color: #dc2626; text-decoration: none; font-weight: 700;">Cerrar sesión</a>
        </div>
    </div>

    <section class="note">
        <h2>Registros existentes</h2>
        <div class="product-list">
            <?php foreach ($records as $record): ?>
                <article class="product-card">
                    <div class="product-card-thumb">
                        <img src="image/<?= rawurlencode($record['filename']) ?>" alt="<?= htmlspecialchars($record['title'] ?: $record['filename'], ENT_QUOTES | ENT_SUBSTITUTE) ?>">
                    </div>
                    <div class="product-card-body">
                        <div class="product-card-meta">
                            <span class="product-id">ID <?= htmlspecialchars($record['id']) ?></span>
                            <span class="product-status <?= $record['active'] ? 'active' : 'inactive' ?>">
                                <?= $record['active'] ? 'Activo' : 'Inactivo' ?>
                            </span>
                        </div>
                        <div class="product-name"><?= htmlspecialchars($record['filename'], ENT_QUOTES | ENT_SUBSTITUTE) ?></div>
                    </div>
                    <div class="product-card-actions">
                        <a href="<?= htmlspecialchars(buildImageUrl($record['slug']), ENT_QUOTES | ENT_SUBSTITUTE) ?>" target="_blank" class="secondary-button">Ver</a>
                        <form method="post" class="action-form">
                            <input type="hidden" name="action" value="edit">
                            <input type="hidden" name="id" value="<?= htmlspecialchars($record['id']) ?>">
                            <button type="submit">Editar</button>
                        </form>
                        <form method="post" class="action-form">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= htmlspecialchars($record['id']) ?>">
                            <button type="submit" onclick="return confirm('¿Eliminar este registro?');">Eliminar</button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="note">
        <h2><?= $editRecord ? 'Editar registro' : 'Nuevo registro' ?></h2>
        <form method="post" class="grid">
            <input type="hidden" name="action" value="save">
            <?php if ($editRecord): ?>
                <input type="hidden" name="id" value="<?= htmlspecialchars($editRecord['id']) ?>">
            <?php endif; ?>

            <div>
                <label for="filename">Archivo</label>
                <select id="filename" name="filename" required>
                    <option value="">Selecciona un archivo</option>
                    <?php foreach ($allFiles as $file): ?>
                        <option value="<?= htmlspecialchars($file, ENT_QUOTES | ENT_SUBSTITUTE) ?>"<?= $editRecord && $editRecord['filename'] === $file ? ' selected' : '' ?>>
                            <?= htmlspecialchars($file, ENT_QUOTES | ENT_SUBSTITUTE) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="slug">Slug público</label>
                <input id="slug" name="slug" type="text" value="<?= htmlspecialchars($editRecord['slug'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE) ?>" placeholder="slug-amigable-para-url" autocomplete="off">
                <small>Si se deja vacío, se generará automáticamente desde el título.</small>
            </div>

            <div>
                <label for="title">Título</label>
                <input id="title" name="title" type="text" value="<?= htmlspecialchars($editRecord['title'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE) ?>" placeholder="Nombre del producto">
            </div>

            <div>
                <label for="description">Descripción</label>
                <input id="description" name="description" type="text" value="<?= htmlspecialchars($editRecord['description'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE) ?>" placeholder="Breve descripción del producto">
            </div>

            <div>
                <label for="active">Activo</label>
                <input id="active" name="active" type="checkbox" value="1" <?= empty($editRecord) || $editRecord['active'] ? 'checked' : '' ?>>
            </div>

            <div>
                <button type="submit" class="primary-button"><?= $editRecord ? 'Actualizar registro' : 'Crear registro' ?></button>
            </div>
        </form>
    </section>
</div>
</body>
</html>
