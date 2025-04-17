<?php
// login.php
declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/security.php';

// Sichere Session initialisieren
Security::initSecureSession();

// Lade .env nur, wenn sie existiert und der Hash gebraucht wird
$adminPasswordHash = null;
if (file_exists(__DIR__ . '/.env')) {
    try {
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
        $dotenv->load();
        $adminPasswordHash = $_ENV['ADMIN_PASSWORD_HASH'] ?? null;
    } catch (Exception $e) {
         error_log("Fehler beim Laden der .env in login.php: " . $e->getMessage());
    }
}

$errorMessage = '';
$showForm = true; // Variable, um zu steuern, ob das Formular angezeigt wird

// Prüfen, ob der Hash korrekt geladen wurde
if (!$adminPasswordHash) {
    $errorMessage = 'Admin-Passwort ist nicht konfiguriert. Login nicht möglich.';
    $showForm = false; // Kein Formular anzeigen, wenn Konfiguration fehlt
}

// Prüfen, ob das Formular gesendet wurde
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $showForm) {
    // CSRF-Schutz: Token überprüfen
    if (!Security::validateCSRFToken($_POST['csrf_token'] ?? null)) {
        $errorMessage = 'Ungültige Anfrage. Bitte versuchen Sie es erneut.';
    } else {
        // Brute-Force-Schutz: Prüfen, ob zu viele Versuche
        if (!Security::checkLoginAttempts()) {
            $lockoutTime = isset($_SESSION['lockout_until']) ? ceil(($_SESSION['lockout_until'] - time()) / 60) : 30;
            $errorMessage = "Zu viele fehlgeschlagene Anmeldeversuche. Bitte versuchen Sie es in {$lockoutTime} Minuten erneut.";
            $showForm = false;
        } else {
            $enteredPassword = $_POST['password'] ?? '';

            // Vergleiche das eingegebene Passwort mit dem Hash
            if (password_verify($enteredPassword, $adminPasswordHash)) {
                // Passwort korrekt: Session-Variable setzen und weiterleiten
                $_SESSION['is_admin_logged_in'] = true;
                $_SESSION['last_activity'] = time();
                $_SESSION['created'] = time();
                
                // Login-Versuche zurücksetzen
                Security::resetLoginAttempts();
                
                header('Location: admin.php');
                exit;
            } else {
                // Passwort falsch: Login-Versuche erhöhen
                Security::incrementLoginAttempts();
                
                // Berechne verbleibende Versuche
                $remainingAttempts = 5 - ($_SESSION['login_attempts'] ?? 0);
                $errorMessage = "Ungültiges Passwort. Noch {$remainingAttempts} Versuche übrig.";
            }
        }
    }
}

// Wenn bereits eingeloggt, direkt weiterleiten
if (isset($_SESSION['is_admin_logged_in']) && $_SESSION['is_admin_logged_in'] === true) {
    // Prüfen, ob die Session noch gültig ist
    if (Security::isSessionValid()) {
        header('Location: admin.php');
        exit;
    } else {
        // Session abgelaufen, ausloggen
        session_unset();
        session_destroy();
        $errorMessage = 'Ihre Sitzung ist abgelaufen. Bitte melden Sie sich erneut an.';
    }
}

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Prompt Technik Finder</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <header>
        <h1><i class="fas fa-lock"></i> Admin Login</h1>
    </header>
    
    <main>
        <section class="login-section fade-in">
            <h2><i class="fas fa-user-shield"></i> Administrator-Zugang</h2>
            <p class="section-description">Bitte geben Sie Ihr Administrator-Passwort ein, um auf den geschützten Bereich zuzugreifen.</p>

            <?php if ($errorMessage): ?>
                <div class="feedback error"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($errorMessage); ?></div>
            <?php endif; ?>

            <?php if ($showForm): ?>
                <form action="login.php" method="POST" class="login-form">
                    <?php echo Security::getCSRFTokenField(); ?>
                    <div class="form-group">
                        <label for="password"><i class="fas fa-key"></i> Passwort:</label>
                        <input type="password" id="password" name="password" required autocomplete="current-password">
                    </div>
                    <button type="submit"><i class="fas fa-sign-in-alt"></i> Login</button>
                </form>
            <?php endif; ?>
            
            <div class="admin-footer-link">
                <a href="index.html"><i class="fas fa-home"></i> Zur Hauptseite</a>
            </div>
        </section>
    </main>
    
    <footer>
        <p>&copy; 2023 Prompt Technik Finder</p>
    </footer>
</body>
</html>