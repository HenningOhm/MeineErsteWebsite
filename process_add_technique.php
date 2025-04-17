<?php
// process_add_technique.php
declare(strict_types=1);

require_once __DIR__ . '/security.php';

// Sichere Session initialisieren
Security::initSecureSession();

// Prüfen, ob der Benutzer eingeloggt ist
if (!isset($_SESSION['is_admin_logged_in']) || $_SESSION['is_admin_logged_in'] !== true) {
    // Nicht eingeloggt: Zugriff verweigern oder weiterleiten
    http_response_code(403); // Forbidden
    echo "Zugriff verweigert. Bitte einloggen.";
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

require __DIR__ . '/vendor/autoload.php';

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
    header('Location: admin.php?status=error&message=' . urlencode('DB Connection Failed: ' . $e->getMessage()));
    exit;
}

// --- Formulardaten empfangen und validieren ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF-Schutz: Token überprüfen
    if (!Security::validateCSRFToken($_POST['csrf_token'] ?? null)) {
        header('Location: admin.php?status=error&message=' . urlencode('Ungültige Anfrage. Bitte versuchen Sie es erneut.'));
        exit;
    }

    // Daten aus $_POST holen und trimmen
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $keywords = trim($_POST['keywords'] ?? '');

    // Einfache Validierung: Name und Beschreibung dürfen nicht leer sein
    if (empty($name) || empty($description)) {
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
        $stmt->bindValue(':keywords', $keywords, PDO::PARAM_STR);

        // Ausführen
        $stmt->execute();

        // Erfolg! Leite zurück zum Formular mit Erfolgsmeldung
        header('Location: admin.php?status=success');
        exit;

    } catch (PDOException $e) {
        error_log("Fehler beim Einfügen in DB: " . $e->getMessage());
        header('Location: admin.php?status=error&message=' . urlencode('Fehler beim Speichern in der Datenbank.'));
        exit;
    }

} else {
    // Wenn jemand versucht, das Skript direkt aufzurufen (nicht per POST)
    header('Location: admin.php');
    exit;
}
?>