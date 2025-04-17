<?php
// login.php
declare(strict_types=1);
session_start(); // Session starten, um Login-Status zu speichern

require __DIR__ . '/vendor/autoload.php';

// Lade .env nur, wenn sie existiert und der Hash gebraucht wird
$adminPasswordHash = null;
if (file_exists(__DIR__ . '/.env')) {
    try {
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
        $dotenv->load();
        $adminPasswordHash = $_ENV['ADMIN_PASSWORD_HASH'] ?? null;
    } catch (Exception $e) {
         error_log("Fehler beim Laden der .env in login.php: " . $e->getMessage());
         // Kein Abbruch hier, die $errorMessage Logik greift unten
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $showForm) { // Nur verarbeiten, wenn Formular angezeigt werden darf
    $enteredPassword = $_POST['password'] ?? '';

    // Vergleiche das eingegebene Passwort mit dem Hash
    if (password_verify($enteredPassword, $adminPasswordHash)) {
        // Passwort korrekt: Session-Variable setzen und weiterleiten
        $_SESSION['is_admin_logged_in'] = true;
        header('Location: admin.php'); // Leite zum Admin-Bereich weiter
        exit;
    } else {
        // Passwort falsch
        $errorMessage = 'Ungültiges Passwort.';
    }
}

// Wenn bereits eingeloggt, direkt weiterleiten
if (isset($_SESSION['is_admin_logged_in']) && $_SESSION['is_admin_logged_in'] === true) {
    header('Location: admin.php');
    exit;
}

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Prompt Technik Finder</title>
    <link rel="stylesheet" href="style.css">
    <!-- Der interne <style>-Block wurde entfernt -->
</head>
<body>
    <!-- Verwende <main> für Konsistenz mit anderen Seiten -->
    <main>
        <h1>Admin Login</h1>

        <?php
        // Fehlermeldung anzeigen, falls vorhanden (nutzt jetzt die .feedback Klasse)
        if ($errorMessage): ?>
            <div class="feedback error"><?php echo htmlspecialchars($errorMessage); ?></div>
        <?php endif; ?>

        <?php
        // Zeige das Formular nur, wenn die Konfiguration geladen wurde
        if ($showForm): ?>
            <form action="login.php" method="POST">
                <!-- <div class="form-group"> Optional, wenn benötigt -->
                    <label for="password">Passwort:</label>
                    <input type="password" id="password" name="password" required>
                <!-- </div> -->
                <button type="submit">Login</button>
            </form>
        <?php endif; ?>

    </main> <!-- Ende <main> -->
</body>
</html>