<?php
// admin.php
declare(strict_types=1);

require_once __DIR__ . '/security.php';

// Sichere Session initialisieren
Security::initSecureSession();

// Prüfen, ob der Benutzer eingeloggt ist
if (!isset($_SESSION['is_admin_logged_in']) || $_SESSION['is_admin_logged_in'] !== true) {
    // Nicht eingeloggt: Weiterleiten zur Login-Seite
    header('Location: login.php');
    exit;
}

// Prüfen, ob die Session noch gültig ist
if (!Security::isSessionValid()) {
    // Session abgelaufen, ausloggen
    session_unset();
    session_destroy();
    header('Location: login.php?message=' . urlencode('Ihre Sitzung ist abgelaufen. Bitte melden Sie sich erneut an.'));
    exit;
}

// Aktualisiere die Zeit der letzten Aktivität
$_SESSION['last_activity'] = time();

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin-Bereich - Prompt Technik Finder</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <header>
        <h1><i class="fas fa-cogs"></i> Admin-Bereich</h1>
    </header>
    
    <main>
        <section class="admin-section fade-in">
            <h2><i class="fas fa-plus-circle"></i> Neue Prompt-Technik hinzufügen</h2>
            <p class="section-description">Fügen Sie hier eine neue Prompt-Technik zur Datenbank hinzu. Geben Sie einen aussagekräftigen Namen, eine detaillierte Beschreibung und relevante Keywords ein.</p>

            <?php
            // Bereich für Feedback-Nachrichten (Erfolg/Fehler)
            if (isset($_GET['status'])) {
                if ($_GET['status'] === 'success') {
                    echo '<div class="feedback success"><i class="fas fa-check-circle"></i> Technik erfolgreich hinzugefügt!</div>';
                } elseif ($_GET['status'] === 'error') {
                    $errorMessage = htmlspecialchars($_GET['message'] ?? 'Unbekannter Fehler beim Hinzufügen.');
                    echo '<div class="feedback error"><i class="fas fa-exclamation-circle"></i> Fehler: ' . $errorMessage . '</div>';
                }
            }
            ?>

            <!-- Das Formular sendet die Daten an 'process_add_technique.php' per POST -->
            <form action="process_add_technique.php" method="POST" class="admin-form">
                <?php echo Security::getCSRFTokenField(); ?>
                
                <div class="form-group">
                    <label for="name"><i class="fas fa-tag"></i> Technik-Name:</label>
                    <input type="text" id="name" name="name" required placeholder="z.B. Chain-of-Thought, Role-Playing, Few-Shot Learning...">
                </div>

                <div class="form-group">
                    <label for="description"><i class="fas fa-align-left"></i> Beschreibung:</label>
                    <textarea id="description" name="description" required placeholder="Beschreiben Sie die Technik detailliert. Wie funktioniert sie? Wann sollte sie verwendet werden? Welche Vorteile bietet sie?"></textarea>
                </div>

                <div class="form-group">
                    <label for="keywords"><i class="fas fa-key"></i> Keywords (Komma-getrennt):</label>
                    <input type="text" id="keywords" name="keywords" placeholder="z.B. Kreativität, Struktur, Analyse, Problemlösung...">
                </div>

                <button type="submit"><i class="fas fa-save"></i> Technik hinzufügen</button>
            </form>
        </section>
        
        <section class="admin-info-section fade-in">
            <h2><i class="fas fa-info-circle"></i> Informationen</h2>
            <p>Im Admin-Bereich können Sie neue Prompt-Techniken zur Datenbank hinzufügen. Diese werden dann für alle Benutzer verfügbar sein.</p>
            <p>Bitte stellen Sie sicher, dass die Beschreibungen klar und hilfreich sind, damit die Benutzer die Techniken optimal nutzen können.</p>
        </section>

        <div class="admin-footer-link">
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            <a href="index.html"><i class="fas fa-home"></i> Zur Hauptseite</a>
        </div>
    </main>
    
    <footer>
        <p>&copy; 2023 Prompt Technik Finder | Admin-Bereich</p>
    </footer>
</body>
</html>