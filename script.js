// Strikten Modus aktivieren
'use strict';

// jQuery-Weg, um zu warten, bis das Dokument bereit ist
$(document).ready(function() {

    // 1. Elemente auswählen mit jQuery
    const $promptForm = $('#prompt-form');
    const $topicInput = $('#topic-input');
    const $resultArea = $('#result-area');
    const $submitButton = $promptForm.find('button[type="submit"]');
    
    // 2. Animation für Sektionen beim Laden
    $('.fade-in').each(function(index) {
        $(this).css({
            'opacity': '0',
            'transform': 'translateY(20px)',
            'transition': 'opacity 0.5s ease, transform 0.5s ease',
            'transition-delay': (index * 0.2) + 's'
        });
        
        setTimeout(() => {
            $(this).css({
                'opacity': '1',
                'transform': 'translateY(0)'
            });
        }, 100);
    });
    
    // 3. Fokus auf das Eingabefeld setzen
    $topicInput.focus();
    
    // Funktion zum Formatieren der KI-Antwort
    function formatAIResponse(text) {
        // Ersetze Markdown-ähnliche Formatierung mit HTML
        return text
            // Entferne "Antwort von Gemini erhalten"
            .replace(/^Antwort von Gemini erhalten\.\s*/i, '')
            // Ersetze **text** mit <strong>text</strong>
            .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
            // Ersetze *text* mit <em>text</em>
            .replace(/\*(.*?)\*/g, '<em>$1</em>')
            // Ersetze `text` mit <code>text</code>
            .replace(/`(.*?)`/g, '<code>$1</code>')
            // Ersetze ```text``` mit <pre><code>text</code></pre>
            .replace(/```(.*?)```/g, '<pre><code>$1</code></pre>')
            // Ersetze Zeilenumbrüche mit <br>
            .replace(/\n/g, '<br>');
    }

    // 4. Event Listener für das Formular
    $promptForm.on('submit', function(event) {
        // Standard-Formularverhalten verhindern
        event.preventDefault();
    
        // Wert aus dem Eingabefeld auslesen
        const topic = $topicInput.val().trim();
    
        // Prüfen, ob ein Thema eingegeben wurde
        if (!topic) {
            $resultArea.html(`
                <div class="feedback error">
                    <i class="fas fa-exclamation-triangle"></i> 
                    Bitte gib zuerst ein Thema ein.
                </div>
            `);
            $topicInput.focus();
            return;
        }
        
        // Button-Status ändern während der Anfrage
        $submitButton.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Suche...');
    
        // Daten an das Backend senden
        $.ajax({
            url: 'api.php',
            type: 'POST',
            dataType: 'json',
            data: {
                topic: topic
            },
            success: function(response) {
                console.log('Antwort vom Server:', response);
                
                // Button-Status zurücksetzen
                $submitButton.prop('disabled', false).html('<i class="fas fa-search"></i> Technik vorschlagen');
                
                // Ergebnisbereich leeren und Animation vorbereiten
                $resultArea.empty().css({
                    'opacity': '0',
                    'transform': 'translateY(10px)'
                });
                
                if (response.success) {
                    if (response.ai_response) {
                        // KI-Antwort formatieren und anzeigen
                        const formattedResponse = formatAIResponse(response.ai_response);
                        $resultArea.append('<h3><i class="fas fa-robot"></i> KI Vorschlag:</h3>');
                        $resultArea.append(`<div class="ai-response">${formattedResponse}</div>`);
                    } else if (response.techniques && response.techniques.length > 0) {
                        // Gefundene Techniken anzeigen
                        $resultArea.append('<h3><i class="fas fa-list-check"></i> Gefundene Techniken:</h3>');
                        const $list = $('<ul class="techniques-list"></ul>');
                        
                        response.techniques.forEach(function(technik) {
                            const $listItem = $('<li></li>');
                            $listItem.html(`
                                <strong><i class="fas fa-star"></i> ${technik.name}:</strong> 
                                <p>${technik.description}</p>
                                ${technik.keywords ? `<div class="keywords"><i class="fas fa-tags"></i> ${technik.keywords}</div>` : ''}
                            `);
                            $list.append($listItem);
                        });
                        
                        $resultArea.append($list);
                    }
                } else {
                    // Zeige Fehlermeldung
                    $resultArea.append(`
                        <div class="feedback error">
                            <i class="fas fa-exclamation-circle"></i> 
                            ${response.message}
                        </div>
                    `);
                }
                
                // Animation für das Ergebnis
                setTimeout(() => {
                    $resultArea.css({
                        'opacity': '1',
                        'transform': 'translateY(0)',
                        'transition': 'opacity 0.5s ease, transform 0.5s ease'
                    });
                }, 50);
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('AJAX Fehler:', textStatus, errorThrown, jqXHR);
                
                // Button-Status zurücksetzen
                $submitButton.prop('disabled', false).html('<i class="fas fa-search"></i> Technik vorschlagen');
                
                $resultArea.html(`
                    <div class="feedback error">
                        <i class="fas fa-exclamation-circle"></i> 
                        Ein Fehler ist aufgetreten. Konnte den Server nicht erreichen oder Antwort nicht verarbeiten.
                    </div>
                `);
            },
            beforeSend: function() {
                $resultArea.html(`
                    <div class="loading">
                        <i class="fas fa-spinner fa-spin"></i> 
                        Sende Anfrage an Server...
                    </div>
                `);
            }
        });
    });
    
    // 5. Event Listener für Tastatureingaben (Enter-Taste)
    $topicInput.on('keypress', function(e) {
        if (e.which === 13) { // Enter-Taste
            $promptForm.submit();
        }
    });
    
    // 6. Hover-Effekt für Buttons
    $('button').hover(
        function() {
            $(this).addClass('button-hover');
        },
        function() {
            $(this).removeClass('button-hover');
        }
    );
    
    // 7. Tooltip für das Eingabefeld
    $topicInput.attr('title', 'Gib hier dein Thema ein, zu dem du eine passende Prompt-Technik suchst.');
    
    // 8. Responsive Anpassungen
    function adjustForMobile() {
        if ($(window).width() < 768) {
            $('section').css('padding', '1.5rem');
        } else {
            $('section').css('padding', '2rem');
        }
    }
    
    // Beim Laden und bei Größenänderung des Fensters
    adjustForMobile();
    $(window).resize(adjustForMobile);
});