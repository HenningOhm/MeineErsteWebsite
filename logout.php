<?php
// logout.php
declare(strict_types=1);
session_start(); // Session starten, um sie bearbeiten zu können

// Alle Session-Variablen löschen
$_SESSION = [];

// Session-Cookie löschen (optional, aber empfohlen)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Session zerstören
session_destroy();

// Zur Login-Seite weiterleiten
header('Location: login.php');
exit;
?>