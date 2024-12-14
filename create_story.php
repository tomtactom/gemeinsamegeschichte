<?php
// create_story.php

require_once 'inc/db_connect.php';
require_once 'inc/functions.php';

// Initialisiere Variablen
$title = $participation_password = $admin_password = "";
$title_err = $participation_password_err = $admin_password_err = "";
$error_message = "";

// Verarbeite das Formular, wenn es abgesendet wurde
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Titel validieren
    if (empty(trim($_POST["title"]))) {
        $title_err = "Bitte geben Sie einen Titel ein.";
    } else {
        $title = trim($_POST["title"]);
    }

    // Teilnahme-Passwort validieren
    if (empty(trim($_POST["participation_password"]))) {
        $participation_password_err = "Bitte geben Sie ein Teilnahme-Passwort ein.";
    } elseif (strlen(trim($_POST["participation_password"])) < 6) {
        $participation_password_err = "Das Teilnahme-Passwort muss mindestens 6 Zeichen lang sein.";
    } else {
        $participation_password = trim($_POST["participation_password"]);
    }

    // Admin-Passwort validieren
    if (empty(trim($_POST["admin_password"]))) {
        $admin_password_err = "Bitte geben Sie ein Admin-Passwort ein.";
    } elseif (strlen(trim($_POST["admin_password"])) < 6) {
        $admin_password_err = "Das Admin-Passwort muss mindestens 6 Zeichen lang sein.";
    } else {
        $admin_password = trim($_POST["admin_password"]);
    }

    // Wenn keine Fehler vorhanden sind, füge die Geschichte zur Datenbank hinzu
    if (empty($title_err) && empty($participation_password_err) && empty($admin_password_err)) {
        // Hash die Passwörter
        $participation_password_hash = password_hash($participation_password, PASSWORD_DEFAULT);
        $admin_password_hash = password_hash($admin_password, PASSWORD_DEFAULT);

        // Entscheide, ob die Geschichte öffentlich oder privat sein soll
        $is_private = isset($_POST['is_private']) ? 1 : 0;

        // Bereite die SQL-Anweisung vor
        $sql = "INSERT INTO stories (title, participation_password_hash, admin_password_hash, is_private) VALUES (?, ?, ?, ?)";

        if ($stmt = $conn->prepare($sql)) {
            // Binde die Parameter
            $stmt->bind_param("sssi", $param_title, $param_participation_password_hash, $param_admin_password_hash, $param_is_private);

            // Setze die Parameter
            $param_title = $title;
            $param_participation_password_hash = $participation_password_hash;
            $param_admin_password_hash = $admin_password_hash;
            $param_is_private = $is_private;

            // Führe die Anweisung aus
            if ($stmt->execute()) {
                // Erfolg: Weiterleitung zur Startseite mit Erfolgsnachricht
                header("Location: index.php?success=Geschichte+erfolgreich+erstellt!");
                exit();
            } else {
                $error_message = "Ein Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.";
            }

            // Schließe das Statement
            $stmt->close();
        } else {
            $error_message = "Ein Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.";
        }
    }

    // Schließe die Verbindung
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Neue Geschichte erstellen</title>
    <!-- Bootstrap CSS über CDN einbinden -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Eigene CSS-Datei -->
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">Geschichtenplattform</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" 
                aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
        </div>
    </nav>

    <!-- Hauptcontainer -->
    <div class="container">
        <h1>Neue Geschichte erstellen</h1>
        <p>Füllen Sie das Formular aus, um eine neue Geschichte zu erstellen.</p>

        <?php 
        // Anzeige von Fehlernachrichten
        if (!empty($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo escape($error_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <form id="create-story-form" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
            <div class="mb-3">
                <label for="story-title" class="form-label">Titel der Geschichte</label>
                <input type="text" class="form-control <?php echo (!empty($title_err)) ? 'is-invalid' : ''; ?>" id="story-title" name="title" value="<?php echo escape($title); ?>" required>
                <div class="invalid-feedback">
                    <?php echo escape($title_err); ?>
                </div>
            </div>
            <div class="mb-3">
                <label for="participation-password" class="form-label">Teilnahme-Passwort</label>
                <input type="password" class="form-control <?php echo (!empty($participation_password_err)) ? 'is-invalid' : ''; ?>" id="participation-password" name="participation_password" required>
                <div class="invalid-feedback">
                    <?php echo escape($participation_password_err); ?>
                </div>
            </div>
            <div class="mb-3">
                <label for="admin-password" class="form-label">Admin-Passwort</label>
                <input type="password" class="form-control <?php echo (!empty($admin_password_err)) ? 'is-invalid' : ''; ?>" id="admin-password" name="admin_password" required>
                <div class="invalid-feedback">
                    <?php echo escape($admin_password_err); ?>
                </div>
            </div>
            <div class="form-check mb-3">
                <input type="checkbox" class="form-check-input" id="is-private" name="is_private">
                <label class="form-check-label" for="is-private">Diese Geschichte privat halten</label>
            </div>
            <button type="submit" class="btn btn-primary">Geschichte erstellen</button>
            <a href="index.php" class="btn btn-secondary">Abbrechen</a>
        </form>
    </div>

    <!-- Bootstrap JS und Abhängigkeiten über CDN einbinden -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Eigene JS-Datei -->
    <script src="js/main.js"></script>
</body>
</html>
