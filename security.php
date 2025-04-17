<?php
declare(strict_types=1);

class Security {
    /**
     * Generiert ein CSRF-Token und speichert es in der Session
     */
    public static function generateCSRFToken(): string {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION['csrf_token'];
    }

    /**
     * Überprüft, ob das übergebene Token mit dem in der Session gespeicherten übereinstimmt
     */
    public static function validateCSRFToken(?string $token): bool {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['csrf_token']) || !isset($token)) {
            return false;
        }
        
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Generiert ein verstecktes CSRF-Token-Feld für Formulare
     */
    public static function getCSRFTokenField(): string {
        $token = self::generateCSRFToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
    }

    /**
     * Überprüft, ob ein Passwort den Sicherheitsanforderungen entspricht
     * 
     * @param string $password Das zu überprüfende Passwort
     * @param array &$errors Array, das mit Fehlermeldungen gefüllt wird
     * @return bool True, wenn das Passwort den Anforderungen entspricht
     */
    public static function validatePassword(string $password, array &$errors = []): bool {
        $errors = [];
        
        // Minimallänge: 10 Zeichen
        if (strlen($password) < 10) {
            $errors[] = "Das Passwort muss mindestens 10 Zeichen lang sein.";
        }
        
        // Großbuchstaben
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = "Das Passwort muss mindestens einen Großbuchstaben enthalten.";
        }
        
        // Kleinbuchstaben
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = "Das Passwort muss mindestens einen Kleinbuchstaben enthalten.";
        }
        
        // Zahlen
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = "Das Passwort muss mindestens eine Zahl enthalten.";
        }
        
        // Sonderzeichen
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = "Das Passwort muss mindestens ein Sonderzeichen enthalten.";
        }
        
        // Häufige Passwörter überprüfen
        if (self::isCommonPassword($password)) {
            $errors[] = "Dieses Passwort ist zu einfach. Bitte wählen Sie ein sichereres Passwort.";
        }
        
        return empty($errors);
    }
    
    /**
     * Überprüft, ob ein Passwort zu den häufig verwendeten gehört
     */
    private static function isCommonPassword(string $password): bool {
        // Liste häufiger Passwörter (könnte erweitert werden)
        $commonPasswords = [
            'password', '123456', '12345678', 'qwerty', 'abc123', 
            'password123', 'admin', 'letmein', 'welcome', 'monkey',
            'dragon', 'baseball', 'football', 'shadow', 'master',
            'hello', 'freedom', 'whatever', 'qazwsx', 'trustno1'
        ];
        
        return in_array(strtolower($password), $commonPasswords);
    }
    
    /**
     * Überprüft, ob zu viele Login-Versuche unternommen wurden
     */
    public static function checkLoginAttempts(): bool {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Initialisiere Login-Versuche, falls nicht vorhanden
        if (!isset($_SESSION['login_attempts'])) {
            $_SESSION['login_attempts'] = 0;
            $_SESSION['last_attempt_time'] = time();
        }
        
        // Prüfe, ob die Sperrzeit abgelaufen ist (30 Minuten)
        if (isset($_SESSION['lockout_until']) && time() < $_SESSION['lockout_until']) {
            return false; // Noch gesperrt
        }
        
        // Zurücksetzen der Versuche nach 30 Minuten Inaktivität
        if (time() - $_SESSION['last_attempt_time'] > 1800) {
            $_SESSION['login_attempts'] = 0;
        }
        
        // Aktualisiere die Zeit des letzten Versuchs
        $_SESSION['last_attempt_time'] = time();
        
        // Erlaube maximal 5 Versuche
        if ($_SESSION['login_attempts'] >= 5) {
            // Sperre für 30 Minuten
            $_SESSION['lockout_until'] = time() + 1800;
            return false;
        }
        
        return true;
    }
    
    /**
     * Erhöht den Zähler für fehlgeschlagene Login-Versuche
     */
    public static function incrementLoginAttempts(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['login_attempts'])) {
            $_SESSION['login_attempts'] = 0;
        }
        
        $_SESSION['login_attempts']++;
    }
    
    /**
     * Setzt die Login-Versuche zurück (bei erfolgreichem Login)
     */
    public static function resetLoginAttempts(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $_SESSION['login_attempts'] = 0;
        unset($_SESSION['lockout_until']);
    }
    
    /**
     * Initialisiert eine sichere Session
     */
    public static function initSecureSession(): void {
        // Stelle sicher, dass die Session sicher ist
        ini_set('session.cookie_httponly', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_secure', '1');
        
        // Starte die Session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Setze Session-Timeout (30 Minuten)
        if (!isset($_SESSION['last_activity'])) {
            $_SESSION['last_activity'] = time();
        } else if (time() - $_SESSION['last_activity'] > 1800) {
            // Session abgelaufen, zerstöre sie
            session_unset();
            session_destroy();
            session_start();
            $_SESSION['last_activity'] = time();
        } else {
            // Aktualisiere die Zeit der letzten Aktivität
            $_SESSION['last_activity'] = time();
        }
        
        // Regelmäßige Session-Erneuerung (alle 10 Minuten)
        if (!isset($_SESSION['created'])) {
            $_SESSION['created'] = time();
        } else if (time() - $_SESSION['created'] > 600) {
            // Erneuere die Session-ID
            session_regenerate_id(true);
            $_SESSION['created'] = time();
        }
    }
    
    /**
     * Überprüft, ob die Session gültig ist
     */
    public static function isSessionValid(): bool {
        if (session_status() === PHP_SESSION_NONE) {
            return false;
        }
        
        // Prüfe, ob die Session abgelaufen ist
        if (isset($_SESSION['last_activity']) && time() - $_SESSION['last_activity'] > 1800) {
            return false;
        }
        
        return true;
    }
} 