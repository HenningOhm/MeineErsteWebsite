<?php
// process_add_technique.php
declare(strict_types=1);
session_start(); // Session starten

// Prüfen, ob der Benutzer eingeloggt ist
if (!isset($_SESSION['is_admin_logged_in']) || $_SESSION['is_admin_logged_in'] !== true) {
    // Nicht eingeloggt: Zugriff verweigern oder weiterleiten
    // Da dies ein API-Endpunkt ist, ist eine Fehlermeldung oder 403 Forbidden besser als Redirect
    http_response_code(403); // Forbidden
    echo "Zugriff verweigert. Bitte einloggen.";
    // Alternativ: header('Location: login.php');
    exit;
}

// Wenn eingeloggt, führe den Rest des Skripts aus...
// (Hier beginnt der bisherige Inhalt von process_add_technique.php)
require __DIR__ . '/vendor/autoload.php'; // Sicherstellen, dass Autoloader hier auch geladen wird
// .env laden (optional hier, wenn keine API Keys gebraucht, aber schadet nicht)
try {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
} catch (Dotenv\Exception\InvalidPathException $e) {
    // Fehler, wenn .env fehlt, aber hier weniger kritisch
}

// Datenbank Setup
$dbPath = __DIR__ . '/prompt_techniques.db';
$dsn = 'sqlite:' . $dbPath;
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, null, null, $options);
} catch (PDOException $e) {
    // Schwerwiegender DB-Verbindungsfehler
    // Leite zurück zum Formular mit Fehlermeldung
    header('Location: admin.php?status=error&message=' . urlencode('DB Connection Failed: ' . $e->getMessage()));
    exit;
}

// --- Formulardaten empfangen und validieren ---
// Nur ausführen, wenn die Anfrage per POST kommt
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Daten aus $_POST holen und trimmen
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $keywords = trim($_POST['keywords'] ?? ''); // Keywords sind optional

    // Einfache Validierung: Name und Beschreibung dürfen nicht leer sein
    if (empty($name) || empty($description)) {
        // Leite zurück zum Formular mit Fehlermeldung
        header('Location: admin.php?status=error&message=' . urlencode('Name und Beschreibung dürfen nicht leer sein.'));
        exit;
    }

    // --- Daten in die Datenbank einfügen (mit Prepared Statements!) ---
    try {
        $sql = "INSERT INTO techniques (name, description, keywords) VALUES (:name, :description, :keywords)";
        $stmt = $pdo->prepare($sql);

        // Werte binden
        $stmt->bindValue(':name', $name, PDO::PARAM_STR);
        $stmt->bindValue(':description', $description, PDO::PARAM_STR);
        // Keywords können leer sein, PDO::PARAM_STR ist ok
        $stmt->bindValue(':keywords', $keywords, PDO::PARAM_STR);

        // Ausführen
        $stmt->execute();

        // Erfolg! Leite zurück zum Formular mit Erfolgsmeldung
        header('Location: admin.php?status=success');
        exit;

    } catch (PDOException $e) {
        // Fehler beim Einfügen (z.B. DB-Constraint verletzt, unwahrscheinlich hier)
        // Leite zurück zum Formular mit Fehlermeldung
        $dbErrorMessage = $e->getMessage();
        // Optional: Logge den Fehler für dich selbst
        error_log("Fehler beim Einfügen in DB: " . $dbErrorMessage);
        header('Location: admin.php?status=error&message=' . urlencode('Fehler beim Speichern in der Datenbank.'));
        exit;
    }

} else {
    // Wenn jemand versucht, das Skript direkt aufzurufen (nicht per POST)
    // Einfach zum Admin-Formular weiterleiten
    header('Location: admin.php');
    exit;
}
?>