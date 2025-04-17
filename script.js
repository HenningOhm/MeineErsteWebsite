// Strikten Modus aktivieren
'use strict';

// jQuery-Weg, um zu warten, bis das Dokument bereit ist
// Entspricht document.addEventListener('DOMContentLoaded', ...)
$(document).ready(function() {

    // 1. Elemente auswählen mit jQuery
    // Statt document.getElementById('id') nutzen wir $('#id')
    // Das '$' ist das Haupt-Werkzeug von jQuery.
    const $promptForm = $('#prompt-form'); // Konvention: jQuery-Objekte mit $ benennen
    const $topicInput = $('#topic-input');
    const $resultArea = $('#result-area');

    // 2. Event Listener hinzufügen mit jQuery
    // Statt promptForm.addEventListener('submit', ...) nutzen wir .on('submit', ...)
        // BLOCK: Submit-Handler (angepasst für AJAX)
        $promptForm.on('submit', function(event) {
            // 3. Standard-Formularverhalten verhindern (bleibt gleich)
            event.preventDefault();
    
            // 4. Wert aus dem Eingabefeld auslesen mit jQuery (bleibt gleich)
            const topic = $topicInput.val().trim();
    
            // 5. Prüfen, ob ein Thema eingegeben wurde (Frontend-Validierung)
            if (!topic) {
                $resultArea.html(`<p style="color: #ffcc80;">Bitte gib zuerst ein Thema ein.</p>`); // Hinweis direkt anzeigen
                return; // Funktion hier beenden, keine Anfrage senden
            }
    
            // 6. Daten an das Backend (api.php) senden (NEU: AJAX mit jQuery)
            // $.ajax() ist die Hauptfunktion von jQuery für Anfragen an den Server.
            $.ajax({
                url: 'api.php', // Die PHP-Datei, die wir ansprechen wollen
                type: 'POST',   // Die HTTP-Methode, die wir verwenden (passend zu PHP Block 1)
                dataType: 'json', // Welchen Datentyp erwarten wir als Antwort? (passend zu PHP Block 4)
                data: {         // Die Daten, die wir senden wollen
                    topic: topic  // Schlüssel 'topic' mit dem Wert aus dem Input-Feld
                },
                            // 7. Funktion, die bei ERFOLGREICHER Antwort vom Server ausgeführt wird
                            success: function(response) {
                                console.log('Antwort vom Server:', response); // Beibehalten!
                
                                $resultArea.empty(); // Leeren
                
                                // Zeige die Nachricht vom Server (Status)
                                $resultArea.append(`<p><em>${response.message}</em></p>`);
                
                                if (response.success) {
                                    if (response.ai_response) {
                                        // Wenn eine KI-Antwort vorhanden ist, zeige sie an
                                        // Das nl2br() in PHP hat Zeilenumbrüche in <br> umgewandelt
                                        $resultArea.append('<h3>KI Vorschlag:</h3>');
                                        // Füge einen Div hinzu, um die Antwort zu stylen (optional)
                                        $resultArea.append(`<div class="ai-response">${response.ai_response}</div>`);
                
                                    } else if (response.techniques && response.techniques.length > 0) {
                                        // Fallback: Wenn keine KI-Antwort, aber Techniken gefunden wurden (z.B. bei leerer Eingabe)
                                        $resultArea.append('<h3>Gefundene Techniken (keine KI-Anfrage gestellt):</h3>');
                                        const $list = $('<ul></ul>');
                                        response.techniques.forEach(function(technik) {
                                            const $listItem = $('<li></li>');
                                            $listItem.html(`<strong>${technik.name}:</strong> ${technik.description} ${technik.keywords ? '('+technik.keywords+')' : ''}`);
                                            $list.append($listItem);
                                        });
                                        $resultArea.append($list);
                                    }
                                } else {
                                    // Wenn response.success false war (Fehlermeldung wird schon oben angezeigt)
                                    // Nichts extra hinzufügen, die Fehlermeldung reicht.
                                }
                            },
                // 8. Funktion, die bei einem FEHLER während der Anfrage ausgeführt wird
                error: function(jqXHR, textStatus, errorThrown) {
                    // Wird ausgeführt, wenn der Server nicht erreichbar ist,
                    // einen Serverfehler (500) zurückgibt oder die Antwort kein gültiges JSON ist.
                    console.error('AJAX Fehler:', textStatus, errorThrown, jqXHR); // Details in die Konsole
                    $resultArea.html(`<p style="color: #ef9a9a;">Ein Fehler ist aufgetreten. Konnte den Server nicht erreichen oder Antwort nicht verarbeiten.</p>`);
                },
                // 9. (Optional) Etwas anzeigen, während wir warten
                beforeSend: function() {
                    $resultArea.html('<p>Sende Anfrage an Server...</p>'); // Feedback für den Nutzer
                }
            }); // Ende von $.ajax()
    
            // Das alte Anzeigen im Frontend (vor AJAX) wird entfernt.
            // $resultArea.html(...); // Diese Zeilen von vorher sind jetzt weg!
    
            // Optional: Eingabefeld leeren mit jQuery
            // $topicInput.val('');
        }); // Ende des Submit-Handlers

}); // Ende des $(document).ready