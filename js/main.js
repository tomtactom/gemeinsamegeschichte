/* js/main.js */

// DOM Content Loaded Event, um sicherzustellen, dass das Skript erst ausgeführt wird, wenn das Dokument vollständig geladen ist
document.addEventListener('DOMContentLoaded', function () {

    // Automatisches Schließen von Alerts nach 5 Sekunden
    const alerts = document.querySelectorAll('.alert-dismissible');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            // Bootstrap's alert 'close' method
            let closeButton = alert.querySelector('.btn-close');
            if (closeButton) {
                closeButton.click();
            }
        }, 5000); // 5000 Millisekunden = 5 Sekunden
    });

    // Formularvalidierung für das Erstellen einer neuen Geschichte
    const createStoryForm = document.getElementById('create-story-form');
    if (createStoryForm) {
        createStoryForm.addEventListener('submit', function(event) {
            const title = document.getElementById('story-title').value.trim();
            const participationPassword = document.getElementById('participation-password').value.trim();
            const adminPassword = document.getElementById('admin-password').value.trim();

            // Einfache Validierung: Alle Felder müssen ausgefüllt sein
            if (!title || !participationPassword || !adminPassword) {
                event.preventDefault(); // Verhindert das Absenden des Formulars
                alert('Bitte füllen Sie alle Felder aus.');
            }

            // Überprüfung der Passwortlänge
            if (participationPassword.length < 6 || adminPassword.length < 6) {
                event.preventDefault();
                alert('Passwörter müssen mindestens 6 Zeichen lang sein.');
            }
        });
    }

    // Formularvalidierung für das Hinzufügen eines neuen Satzes zur Geschichte
    const addSentenceForm = document.getElementById('add-sentence-form');
    if (addSentenceForm) {
        addSentenceForm.addEventListener('submit', function(event) {
            const sentence = document.getElementById('sentence-input').value.trim();

            // Überprüfung, ob die Eingabe nicht leer ist
            if (!sentence) {
                event.preventDefault();
                alert('Bitte geben Sie mindestens einen Satz ein.');
                return;
            }

            // Überprüfung der maximalen Zeichenanzahl (300 Zeichen)
            if (sentence.length > 300) {
                event.preventDefault();
                alert('Die maximale Zeichenanzahl beträgt 300.');
            }

            // Überprüfung, ob zwei Sätze durch einen Punkt getrennt sind
            const sentenceCount = sentence.split('.').filter(function(s) {
                return s.trim().length > 0;
            }).length;

            if (sentenceCount != 2) {
                event.preventDefault();
                alert('Bitte geben Sie genau zwei Sätze ein, getrennt durch einen Punkt.');
            }
        });
    }

    // Formularvalidierung für das Admin-Passwort
    const adminForm = document.querySelector('form[action^="admin.php"]');
    if (adminForm && !adminForm.querySelector('input[name="action"]')) { // Nur das Authentifizierungsformular
        adminForm.addEventListener('submit', function(event) {
            const adminPassword = document.getElementById('admin-password').value.trim();

            // Überprüfung der Passwortlänge
            if (adminPassword.length < 6) {
                event.preventDefault();
                alert('Das Admin-Passwort muss mindestens 6 Zeichen lang sein.');
            }
        });
    }
});
