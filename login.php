<?php
session_start();

// If already logged in, redirect to manage.php
if (!empty($_SESSION['admin_logged_in'])) {
    header('Location: manage.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    if ($email === 'douglasezambrano@gmail.com' && $password === 'Wutevogo7754*') {
        $_SESSION['admin_logged_in'] = true;
        header('Location: manage.php');
        exit;
    } else {
        $error = 'Correo electrónico o contraseña incorrectos.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - Administrador</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<div class="wa-chat-container" style="max-width: 440px;">
    <!-- App Bar -->
    <header class="wa-app-bar" style="justify-content: center; height: 120px;">
        <div class="wa-profile-area" style="flex-direction: column; align-items: center; gap: 6px;">
            <div class="wa-avatar" style="width: 50px; height: 50px; background-color: #ffffff; display: flex; align-items: center; justify-content: center;">
                <span style="font-size: 1.8rem;">🔒</span>
            </div>
            <span class="wa-title" style="font-size: 1.15rem; font-weight: 700; color: #ffffff;">Panel de Administración</span>
            <span class="wa-status" style="font-size: 0.8rem; color: rgba(255, 255, 255, 0.85);"><span class="wa-status-dot" style="background-color: #f1c40f; box-shadow: 0 0 6px #f1c40f;"></span>Acceso restringido</span>
        </div>
    </header>

    <!-- Chat Thread (Contains Form) -->
    <main class="wa-chat-thread" style="padding: 20px;">
        <div class="wa-bubble wa-bubble-recv" style="max-width: 100%; width: 100%; display: flex; flex-direction: column; gap: 14px;">
            <?php if ($error): ?>
                <div style="background-color: #fee2e2; border-left: 4px solid #dc2626; padding: 10px; border-radius: 8px; font-size: 0.88rem; color: #991b1b; font-weight: 600;">
                    ⚠️ <?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE) ?>
                </div>
            <?php endif; ?>

            <form method="post" class="wa-form">
                <div class="wa-form-group">
                    <label for="email">Correo electrónico</label>
                    <input id="email" name="email" type="text" class="wa-input" placeholder="ejemplo@correo.com" value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE) ?>" required autocomplete="email">
                </div>

                <div class="wa-form-group">
                    <label for="password">Contraseña</label>
                    <input id="password" name="password" type="password" class="wa-input" placeholder="Ingresa tu contraseña" required autocomplete="current-password">
                </div>

                <button type="submit" class="wa-btn" style="margin-top: 8px;">
                    <span>Acceder al panel</span>
                    <span>🔑</span>
                </button>
            </form>
        </div>

        <div class="wa-bubble wa-bubble-system">
            🔒 Esta conexión está encriptada y protegida.
            <br>
            <a href="index.php" style="color: var(--wa-green-deep); font-weight: 700; text-decoration: none; display: inline-block; margin-top: 8px;">← Volver al catálogo</a>
        </div>
    </main>
</div>
</body>
</html>

