<?php
// admin.php
declare(strict_types=1);
session_start(); // Session starten

// Prüfen, ob der Benutzer eingeloggt ist
if (!isset($_SESSION['is_admin_logged_in']) || $_SESSION['is_admin_logged_in'] !== true) {
    // Nicht eingeloggt: Weiterleiten zur Login-Seite
    header('Location: login.php');
    exit;
}

// Wenn eingeloggt, kann der Rest der Seite geladen werden...
// (Hier beginnt der bisherige Inhalt von admin.php)
// Optional: Logout-Link hinzufügen (z.B. am Ende der Seite vor </body>)
// echo '<p><a href="logout.php">Logout</a></p>';
?>
<!DOCTYPE html>
<html lang="de">
<head>
     <meta charset="UTF-8">
     <meta name="viewport" content="width=device-width, initial-scale=1.0">
     <title>Admin-Bereich - Prompt Technik Finder</title> <!-- Titel anpassen ist gut -->
     <link rel="stylesheet" href="style.css">
 </head>
<body>
    <main>
        <h1>Neue Prompt-Technik hinzufügen</h1>

        <?php
        // Bereich für Feedback-Nachrichten (Erfolg/Fehler)
        // Wir verwenden hier eine einfache GET-Variable zur Übertragung
        if (isset($_GET['status'])) {
            if ($_GET['status'] === 'success') {
                echo '<div class="feedback success">Technik erfolgreich hinzugefügt!</div>';
            } elseif ($_GET['status'] === 'error') {
                // Optional: Fehlermeldung genauer anzeigen, wenn wir sie übergeben
                $errorMessage = htmlspecialchars($_GET['message'] ?? 'Unbekannter Fehler beim Hinzufügen.');
                echo '<div class="feedback error">Fehler: ' . $errorMessage . '</div>';
            }
        }
        ?>

        <!-- Das Formular sendet die Daten an 'process_add_technique.php' per POST -->
        <form action="process_add_technique.php" method="POST">
            <label for="name">Technik-Name:</label>
            <input type="text" id="name" name="name" required>

            <label for="description">Beschreibung:</label>
            <textarea id="description" name="description" required></textarea>

            <label for="keywords">Keywords (Komma-getrennt):</label>
            <input type="text" id="keywords" name="keywords">

            <button type="submit">Technik hinzufügen</button>
        </form>

        <div class="admin-footer-link">
            <a href="logout.php">Logout</a>  <!-- Sicherstellen, dass href="logout.php" ist -->
            <!-- Optional: Den Link zur Hauptseite kannst du zusätzlich einfügen, wenn du möchtest -->
            <a href="index.html" style="margin-left: 20px;">Zur Hauptseite</a> <!-- Beispiel für zusätzlichen Link -->
        </div>

    </main>
</body>
</html>