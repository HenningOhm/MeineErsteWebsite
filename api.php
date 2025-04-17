<?php // Startet einen PHP-Codeblock

// Strikte Typisierung aktivieren (MUSS die erste Anweisung sein!)
declare(strict_types=1);

// Composer Autoloader einbinden (lädt alle installierten Bibliotheken)
require __DIR__ . '/vendor/autoload.php';

// phpdotenv laden und .env-Datei verarbeiten
try {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
} catch (Dotenv\Exception\InvalidPathException $e) {
    // Fehlerbehandlung, falls .env nicht gefunden wird
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Konfigurationsfehler: .env Datei nicht gefunden oder lesbar.']);
    exit;
}

// --- BLOCK 0: Datenbank Setup ---
$dbPath = __DIR__ . '/prompt_techniques.db';
$dsn = 'sqlite:' . $dbPath;
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, null, null, $options);
    // Tabelle erstellen, falls nicht vorhanden (Code unverändert)
    $tableCheck = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='techniques'");
    if ($tableCheck->fetch() === false) {
        $pdo->exec("CREATE TABLE techniques (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        name TEXT NOT NULL,
                        description TEXT NOT NULL,
                        keywords TEXT
                    )");
        $pdo->exec("INSERT INTO techniques (name, description, keywords) VALUES
                        ('5 Whys', 'Frage wiederholt Warum, um zur Ursache eines Problems zu gelangen.', 'Ursachenforschung, Problemanalyse'),
                        ('Chain of Thought', 'Leite die KI an, Schritt für Schritt zu denken und Zwischenergebnisse zu nennen.', 'Logik, Argumentation, komplexe Aufgaben'),
                        ('Zero-Shot', 'Gib der KI eine Aufgabe ohne spezifische Beispiele.', 'Allgemeinwissen, einfache Aufgaben')
                    ");
    }
} catch (PDOException $e) {
    // DB-Verbindungsfehler
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Datenbankfehler: ' . $e->getMessage()]);
    exit;
}

// --- BLOCK 1: Anfrage-Typ prüfen ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // --- BLOCK 2: Daten aus der Anfrage holen ---
    $topic = isset($_POST['topic']) ? htmlspecialchars(trim((string)$_POST['topic'])) : '';

    // --- BLOCK 3: Suche, Prompt-Erstellung und KI-Anruf ---
    $response = [];

    if (!empty($topic)) {
        // --- 3a: API-Key laden ---
        $apiKey = $_ENV['GEMINI_API_KEY'] ?? null;

        if (!$apiKey) {
            $response['success'] = false;
            $response['message'] = 'Fehler: Gemini API Key nicht in .env konfiguriert.';
            http_response_code(500);
        } else {
            // --- 3b: Relevante Techniken aus DB holen (Retrieval - Verbessert) ---
            $retrievedTechniquesText = '';
            $techniques = []; // Initialisiere als leeres Array

            try {
                // 1. Liste deutscher Stoppwörter (kann erweitert werden)
                $stopWords = [
                    'a', 'ab', 'aber', 'ach', 'acht', 'achte', 'achten', 'achter', 'achtes', 'ag',
                    'alle', 'allein', 'allem', 'allen', 'aller', 'allerdings', 'alles', 'allgemeinen',
                    'als', 'also', 'am', 'an', 'ander', 'andere', 'anderem', 'anderen', 'anderer',
                    'anderes', 'anderm', 'andern', 'anderr', 'anders', 'auch', 'auf', 'aus', 'ausser',
                    'ausserdem', 'außer', 'außerdem', 'b', 'bald', 'bei', 'beide', 'beiden', 'beim', 'beispiel',
                    'bekannt', 'bereits', 'besonders', 'besser', 'besten', 'bin', 'bis', 'bisher', 'bist',
                    'c', 'd', 'da', 'dabei', 'dadurch', 'dafür', 'dagegen', 'daher', 'dahin', 'dahinter',
                    'damals', 'damit', 'danach', 'daneben', 'dank', 'dann', 'daran', 'darauf', 'daraus',
                    'darf', 'darfst', 'darin', 'darüber', 'darum', 'darunter', 'das', 'dasein', 'daselbst',
                    'dass', 'dasselbe', 'davon', 'davor', 'dazu', 'dazwischen', 'dein', 'deine', 'deinem',
                    'deinen', 'deiner', 'deines', 'dem', 'dementsprechend', 'demgegenüber', 'demgemäss',
                    'demgemäß', 'demselben', 'demzufolge', 'den', 'denen', 'denn', 'denselben', 'der', 'deren',
                    'derer', 'derjenige', 'derjenigen', 'dermassen', 'dermaßen', 'derselbe', 'derselben', 'des',
                    'deshalb', 'desselben', 'dessen', 'deswegen', 'dich', 'die', 'diejenige', 'diejenigen',
                    'dies', 'diese', 'dieselbe', 'dieselben', 'diesem', 'diesen', 'dieser', 'dieses', 'dir',
                    'doch', 'dort', 'drei', 'drin', 'dritte', 'dritten', 'dritter', 'drittes', 'du', 'durch',
                    'durchaus', 'durfte', 'durften', 'dürfen', 'dürft', 'e', 'eben', 'ebenso', 'ehrlich', 'ei',
                    'ei,', 'eigen', 'eigene', 'eigenen', 'eigener', 'eigenes', 'ein', 'eine', 'einem', 'einen',
                    'einer', 'eines', 'einig', 'einige', 'einigem', 'einigen', 'einiger', 'einiges', 'einmal',
                    'eins', 'elf', 'en', 'ende', 'endlich', 'entweder', 'er', 'erst', 'erste', 'ersten',
                    'erster', 'erstes', 'es', 'etwa', 'etwas', 'euch', 'euer', 'eure', 'eurem', 'euren',
                    'eurer', 'eures', 'f', 'folgende', 'früher', 'fünf', 'fünfte', 'fünften', 'fünfter',
                    'fünftes', 'für', 'g', 'gab', 'ganz', 'ganze', 'ganzen', 'ganzer', 'ganzes', 'gar',
                    'geb', 'geben', 'gegen', 'geht', 'gehst', 'gekonnt', 'gemacht', 'gemäss', 'gemäß', 'genug',
                    'gern', 'gesagt', 'geschweige', 'gewesen', 'gewollt', 'geworden', 'gibt', 'gibst', 'ging',
                    'gleich', 'gott', 'gross', 'grosse', 'grossen', 'grosser', 'grosses', 'groß', 'große',
                    'großen', 'großer', 'großes', 'gut', 'gute', 'guter', 'gutes', 'h', 'hab', 'habe', 'haben',
                    'habt', 'hast', 'hat', 'hatte', 'hatten', 'hattest', 'hattet', 'heisst', 'her', 'heute',
                    'hier', 'hin', 'hinter', 'hoch', 'hätte', 'hätten', 'i', 'ich', 'ihm', 'ihn', 'ihnen',
                    'ihr', 'ihre', 'ihrem', 'ihren', 'ihrer', 'ihres', 'ihretwegen', 'im', 'immer', 'in',
                    'indem', 'infolgedessen', 'ins', 'irgend', 'ist', 'j', 'ja', 'jahr', 'jahre', 'jahren',
                    'je', 'jede', 'jedem', 'jeden', 'jeder', 'jedermann', 'jedermanns', 'jedes', 'jedoch', 'jemand',
                    'jemandem', 'jemanden', 'jene', 'jenem', 'jenen', 'jener', 'jenes', 'jetzt', 'k', 'kam',
                    'kann', 'kannst', 'kaum', 'kein', 'keine', 'keinem', 'keinen', 'keiner', 'keines', 'klar',
                    'können', 'könnt', 'konnte', 'konnten', 'kurz', 'l', 'lang', 'lange', 'leicht', 'leide',
                    'lieber', 'los', 'm', 'machen', 'macht', 'machte', 'mag', 'magst', 'mahn', 'man', 'manche',
                    'manchem', 'manchen', 'mancher', 'manches', 'mann', 'mehr', 'mein', 'meine', 'meinem',
                    'meinen', 'meiner', 'meines', 'mensch', 'menschen', 'mich', 'mir', 'mit', 'mittel', 'mochte',
                    'mochten', 'morgen', 'muss', 'musst', 'musste', 'mussten', 'muß', 'mußt', 'mögen', 'möglich',
                    'mögt', 'müssen', 'müsst', 'müßte', 'n', 'na', 'nach', 'nachdem', 'nahm', 'natürlich',
                    'neben', 'nein', 'neue', 'neuen', 'neun', 'neunte', 'neunten', 'neunter', 'neuntes', 'nicht',
                    'nichts', 'nie', 'niemand', 'niemandem', 'niemanden', 'noch', 'nun', 'nur', 'o', 'ob',
                    'oben', 'oder', 'offen', 'oft', 'ohne', 'ordnung', 'p', 'q', 'r', 'recht', 'rechte',
                    'rechten', 'rechter', 'rechtes', 'richtig', 'rund', 's', 'sa', 'sache', 'sagt', 'sagte',
                    'sah', 'satt', 'schlecht', 'schluss', 'schon', 'sechs', 'sechste', 'sechsten', 'sechster',
                    'sechstes', 'sehr', 'sei', 'seid', 'seien', 'sein', 'seine', 'seinem', 'seinen', 'seiner',
                    'seines', 'seit', 'seitdem', 'selbst', 'selbst,', 'sich', 'sie', 'sieben', 'siebente',
                    'siebenten', 'siebenter', 'siebentes', 'sind', 'so', 'solang', 'solche', 'solchem', 'solchen',
                    'solcher', 'solches', 'soll', 'sollen', 'sollst', 'sollt', 'sollte', 'sollten', 'sondern',
                    'sonst', 'soweit', 'sowie', 'später', 'startseite', 'statt', 'steht', 'suche', 't', 'tag',
                    'tage', 'tagen', 'tat', 'teil', 'tel', 'tritt', 'trotzdem', 'tun', 'u', 'uhr', 'um', 'und',
                    'und?', 'uns', 'unser', 'unsere', 'unserem', 'unseren', 'unserer', 'unseres', 'unter',
                    'v', 'vergangenen', 'viel', 'viele', 'vielem', 'vielen', 'vielleicht', 'vier', 'vierte',
                    'vierten', 'vierter', 'viertes', 'vom', 'von', 'vor', 'w', 'wahr?', 'wann', 'war', 'waren',
                    'wart', 'warum', 'was', 'wegen', 'weil', 'weit', 'weiter', 'weitere', 'weiteren', 'weiteres',
                    'welche', 'welchem', 'welchen', 'welcher', 'welches', 'wem', 'wen', 'wenig', 'wenige', 'weniger',
                    'wenigstens', 'wenn', 'wer', 'werde', 'werden', 'werdet', 'weshalb', 'wessen', 'wie', 'wieder',
                    'wieso', 'will', 'willst', 'wir', 'wird', 'wirklich', 'wirst', 'wissen', 'wo', 'woher', 'wohin',
                    'wohl', 'wollen', 'wollt', 'wollte', 'wollten', 'worden', 'wurde', 'wurden', 'während',
                    'währenddem', 'währenddessen', 'wäre', 'würde', 'würden', 'x', 'y', 'z', 'z.b.', 'zehn',
                    'zehnte', 'zehnten', 'zehnter', 'zehntes', 'zeit', 'zu', 'zuerst', 'zugleich', 'zum',
                    'zunächst', 'zur', 'zurück', 'zusammen', 'zwanzig', 'zwar', 'zwei', 'zweite', 'zweiten',
                    'zweiter', 'zweites', 'zwischen', 'zwölf', 'über', 'überhaupt', 'übrigens'
                ];

                // 2. Topic bereinigen und tokenisieren
                $lowerTopic = mb_strtolower(trim($topic), 'UTF-8'); // Kleinbuchstaben, Leerzeichen trimmen
                // Zerlegt den String anhand von Leerzeichen und diversen Satzzeichen
                $tokens = preg_split('/[\s,.;:!?\-()\/"\'’]+/', $lowerTopic, -1, PREG_SPLIT_NO_EMPTY);

                // 3. Stoppwörter und kurze Wörter filtern
                $relevantTokens = [];
                if ($tokens) {
                    $relevantTokens = array_filter($tokens, function($word) use ($stopWords) {
                        return !in_array($word, $stopWords) && mb_strlen($word) > 2; // Mindestlänge 3 Zeichen
                    });
                    $relevantTokens = array_values($relevantTokens); // Wichtig: Indizes neu ordnen!
                }

                // 4. Dynamische SQL-Abfrage bauen, wenn relevante Tokens existieren
                if (!empty($relevantTokens)) {
                    $sqlConditions = [];
                    $bindings = [];
                    foreach ($relevantTokens as $index => $token) {
                        $placeholder = ':token' . $index;
                        // Suche nach dem Token in Name, Beschreibung ODER Keywords
                        $sqlConditions[] = "(LOWER(name) LIKE " . $placeholder . " OR LOWER(description) LIKE " . $placeholder . " OR LOWER(keywords) LIKE " . $placeholder . ")";
                        $bindings[$placeholder] = '%' . $token . '%'; // Wildcards hinzufügen
                    }

                    // Baue die WHERE-Klausel zusammen (verknüpft mit OR)
                    $whereClause = implode(' OR ', $sqlConditions);

                    // Komplette SQL-Abfrage
                    // HINWEIS: Überlege dir eine bessere Sortierung als nur 'name ASC'.
                    // Vielleicht eine Zählung, wie oft Tokens matchen? (Fortgeschritten)
                    $sql = "SELECT id, name, description, keywords
                            FROM techniques
                            WHERE " . $whereClause . "
                            ORDER BY name ASC LIMIT 5"; // Limit beibehalten

                    $stmt = $pdo->prepare($sql);

                    // Binde die Werte sicher an die Platzhalter
                    foreach ($bindings as $placeholder => $value) {
                        $stmt->bindValue($placeholder, $value, PDO::PARAM_STR);
                    }

                    $stmt->execute();
                    $techniques = $stmt->fetchAll();

                } else {
                    // Keine relevanten Tokens gefunden nach Filterung
                    // $techniques bleibt ein leeres Array
                    // Wir könnten hier eine spezielle Nachricht für den Prompt setzen,
                    // aber das machen wir im nächsten Schritt (Prompt Optimierung)
                }

                // 5. Text für den Prompt erstellen (bleibt ähnlich, aber $techniques ist jetzt relevanter)
                if ($techniques) {
                    $retrievedTechniquesText .= "Hier sind einige möglicherweise relevante Prompting-Techniken aus meiner Wissensdatenbank, die auf deiner Anfrage basieren:\n\n";
                    foreach ($techniques as $technik) {
                        $retrievedTechniquesText .= "- Name: " . htmlspecialchars($technik['name']) . "\n"; // htmlspecialchars hier zur Sicherheit
                        $retrievedTechniquesText .= "  Beschreibung: " . htmlspecialchars($technik['description']) . "\n";
                        if (!empty($technik['keywords'])) {
                            $retrievedTechniquesText .= "  Keywords: " . htmlspecialchars($technik['keywords']) . "\n";
                        }
                        $retrievedTechniquesText .= "\n";
                    }
                } else {
                     // Wenn keine Tokens relevant waren ODER die Suche nichts ergab
                     $retrievedTechniquesText = "Ich habe keine spezifischen Techniken zu den relevanten Begriffen deiner Anfrage ('" . implode("', '", $relevantTokens) . "') in meiner Datenbank gefunden.\n";
                     if (empty($relevantTokens) && !empty($tokens)) {
                         $retrievedTechniquesText = "Deine Anfrage ('".htmlspecialchars($topic)."') enthielt hauptsächlich Füllwörter. Bitte formuliere sie spezifischer.\n";
                     } elseif (empty($tokens)) {
                          $retrievedTechniquesText = "Deine Anfrage war leer oder ungültig.\n";
                     }
                }

            } catch (PDOException $e) {
                // Bestehende Fehlerbehandlung für DB-Fehler
                $response['success'] = false;
                $response['message'] = "Datenbankfehler bei der Suche: " . $e->getMessage();
                error_log("Datenbankfehler bei Suche: " . $e->getMessage() . " | SQL: " . ($sql ?? 'Nicht erstellt') . " | Bindings: " . json_encode($bindings ?? [])); // Verbessertes Logging
                http_response_code(500);
                goto send_response; // Sprung zur Antwort
            }
            // Ende von Block 3b (Retrieval)

            // --- 3c: Prompt für Gemini erstellen (Augmentation - Verbessert) ---

            // Definiere die Rolle und die Aufgabe der KI klarer
            $prompt = "Du bist ein hilfreicher Assistent, spezialisiert auf Prompting-Techniken für Sprachmodelle.\n";
            $prompt .= "Der Benutzer hat folgendes Thema oder Problem beschrieben:\n";
            $prompt .= "--- Benutzeranfrage ---\n";
            $prompt .= "\"" . htmlspecialchars($topic) . "\"\n"; // Originalanfrage beibehalten, aber encoded
            $prompt .= "--- Ende Benutzeranfrage ---\n\n";

            // Füge die gefundenen Techniken oder die "Nichts gefunden"-Nachricht hinzu
            $prompt .= "--- Informationen aus der Wissensdatenbank ---\n";
            if ($techniques) {
                $prompt .= $retrievedTechniquesText; // Enthält bereits die Liste oder die "Nichts gefunden"-Info
            } else {
                // Stelle sicher, dass auch hier eine klare Info steht, falls $retrievedTechniquesText leer sein sollte
                 $prompt .= "Es wurden keine passenden Techniken in der Datenbank gefunden oder die Anfrage war zu unspezifisch.\n";
            }
            $prompt .= "--- Ende Informationen aus der Wissensdatenbank ---\n\n";

            // Gib klare Anweisungen, was die KI tun soll
            $prompt .= "--- Deine Aufgabe ---\n";
            $prompt .= "Basierend auf der Benutzeranfrage und den (falls vorhanden) oben genannten Techniken aus der Wissensdatenbank:\n";
            $prompt .= "1. Identifiziere die 1-2 am besten passenden Prompting-Technik(en) für das Problem des Benutzers.\n";
            $prompt .= "2. Wenn Techniken aus der Datenbank relevant erscheinen, beziehe dich **primär** auf diese und erkläre kurz und prägnant, **warum** sie passen.\n";
            $prompt .= "3. Wenn keine Techniken aus der Datenbank passen oder gefunden wurden, schlage **eine** allgemeine, passende Prompting-Strategie vor, die dem Benutzer helfen könnte, sein Ziel zu erreichen.\n";
            $prompt .= "4. Formuliere deine Antwort direkt an den Benutzer, sei hilfreich, präzise und anfängerfreundlich.\n";
            $prompt .= "5. Gib **nur** deine Empfehlung und Erklärung aus. Wiederhole nicht die Eingabe oder die Datenbankinhalte, es sei denn, du zitierst den Namen einer Technik.\n";
            $prompt .= "6. Formatiere deine Antwort ggf. mit Markdown für bessere Lesbarkeit (z.B. Fett für Technik-Namen).\n";
            $prompt .= "--- Ende Deiner Aufgabe ---\n";

            // --- 3d: Gemini API aufrufen (Generation) ---
            $model = 'gemini-1.5-flash-latest';
            $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/" . $model . ":generateContent?key=" . $apiKey;
            $postData = json_encode([
                'contents' => [[
                    'parts' => [['text' => $prompt]]
                ]]
            ]);

            // Log des Prompts entfernt
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $apiUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            // curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Timeout optional

            $apiResponse = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if (curl_errno($ch)) {
                $response['success'] = false;
                $response['message'] = 'Fehler bei der Kommunikation mit der Gemini API: ' . curl_error($ch);
                http_response_code(500);
            } else {
                curl_close($ch);
                if ($httpCode == 200) {
                    $responseData = json_decode($apiResponse, true);
                    $generatedText = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? null;

                    if ($generatedText) {
                        $response['success'] = true;
                        $response['message'] = "Antwort von Gemini erhalten.";
                        $response['ai_response'] = nl2br(htmlspecialchars($generatedText));
                    } else {
                        $response['success'] = false;
                        $response['message'] = 'Konnte keine gültige Antwort von Gemini extrahieren.';
                        error_log("Gemini API Response konnte nicht geparsed werden: " . $apiResponse); // Fehler-Log bleibt sinnvoll
                        http_response_code(500);
                    }
                } else {
                    $response['success'] = false;
                    $response['message'] = "Fehler von Gemini API (HTTP Code: " . $httpCode . "): " . $apiResponse;
                    error_log("Gemini API Error (HTTP $httpCode): " . $apiResponse); // Fehler-Log bleibt sinnvoll
                    http_response_code($httpCode);
                }
            }
            if (is_resource($ch)) { // Sicherstellen, dass curl geschlossen wird, falls Fehler vor curl_close auftrat
                 curl_close($ch);
            }
        } // Ende else (API Key vorhanden)

    } else {
        // Fallback, wenn kein Topic eingegeben wurde
        try {
            $stmt = $pdo->query("SELECT id, name, description, keywords FROM techniques ORDER BY name ASC");
            $techniques = $stmt->fetchAll();
            $response['success'] = true;
            $response['message'] = "Kein Thema eingegeben, zeige alle Techniken.";
            $response['techniques'] = $techniques ?: []; // Fallback für leere DB sicherstellen
        } catch (PDOException $e) {
             $response['success'] = false;
             $response['message'] = "Datenbankfehler beim Abrufen aller Techniken: " . $e->getMessage();
             http_response_code(500);
        }
    }

    send_response: // Sprungmarke

    // --- BLOCK 4: Antwort als JSON senden ---
    header('Content-Type: application/json');
    echo json_encode($response);

} else {
    // --- BLOCK 5: Falsche Anfrage-Methode ---
    header("HTTP/1.1 405 Method Not Allowed");
    echo json_encode(['success' => false, 'message' => 'Fehler: Nur POST-Anfragen erlaubt.']);
}
?>