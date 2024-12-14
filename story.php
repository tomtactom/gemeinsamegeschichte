<?php
// story.php

require_once 'inc/db_connect.php';
require_once 'inc/functions.php';

// Initialisiere Variablen
$story_id = isset($_GET['story_id']) ? intval($_GET['story_id']) : 0;
$sentence = "";
$sentence_err = "";
$error_message = "";
$success_message = "";

// Überprüfe, ob die story_id gültig ist
if ($story_id <= 0) {
    header("Location: index.php?error=Ungültige+Geschichte+ID.");
    exit();
}

// Abfrage der Geschichte
$sql_story = "SELECT * FROM stories WHERE id = ?";
if ($stmt_story = $conn->prepare($sql_story)) {
    $stmt_story->bind_param("i", $story_id);
    $stmt_story->execute();
    $result_story = $stmt_story->get_result();

    if ($result_story->num_rows != 1) {
        header("Location: index.php?error=Geschichte+nicht+gefunden.");
        exit();
    }

    $story = $result_story->fetch_assoc();
    $stmt_story->close();
} else {
    header("Location: index.php?error=Fehler+beim+Zugriff+auf+die+Geschichte.");
    exit();
}

// Überprüfe die Authentifizierung via Cookie
$authenticated = false;
if (isset($_COOKIE['story_auth_' . $story_id])) {
    $cookie_hash = $_COOKIE['story_auth_' . $story_id];
    // Vergleiche den Cookie-Hash mit dem Teilnahme-Passwort-Hash
    if (password_verify($_GET['password'] ?? '', $story['participation_password_hash'])) {
        $authenticated = true;
    } else {
        // Optional: Weitere Logik zur Cookie-Authentifizierung
        // Zum Beispiel könnte der Cookie selbst den Passwort-Hash enthalten
        // Hier wird angenommen, dass der Cookie den Hash enthält
        // Stelle sicher, dass dies sicher implementiert ist
        // Für diese Implementierung wird eine neue Authentifizierung benötigt
        $authenticated = false;
    }
}

// Wenn die Geschichte privat ist und der Nutzer nicht authentifiziert ist
if ($story['is_private'] && !$authenticated && $story['status'] !== 'completed') {
    // Verarbeite das Passwort-Formular
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['password'])) {
        $input_password = trim($_POST['password']);

        if (password_verify($input_password, $story['participation_password_hash'])) {
            // Setze den Cookie mit dem Passwort-Hash, gültig für eine Woche
            setcookie('story_auth_' . $story_id, $story['participation_password_hash'], time() + (7 * 24 * 60 * 60), "/");
            header("Location: story.php?story_id=" . $story_id);
            exit();
        } else {
            $error_message = "Falsches Teilnahme-Passwort.";
        }
    }
}

// Wenn die Geschichte abgeschlossen ist und öffentlich ist, oder der Nutzer authentifiziert ist
if (($story['status'] === 'completed') || (!$story['is_private']) || $authenticated) {
    // Verarbeite das Hinzufügen eines neuen Satzes, wenn die Geschichte noch läuft
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['sentence']) && $story['status'] === 'ongoing') {
        $sentence = trim($_POST['sentence']);

        // Validierung
        if (empty($sentence)) {
            $sentence_err = "Bitte geben Sie mindestens einen Satz ein.";
        } elseif (strlen($sentence) > 300) {
            $sentence_err = "Die maximale Zeichenanzahl beträgt 300.";
        } else {
            // Überprüfe, ob zwei Sätze durch einen Punkt getrennt sind
            $sentences = explode('.', $sentence);
            $sentences = array_filter(array_map('trim', $sentences));

            if (count($sentences) != 2) {
                $sentence_err = "Bitte geben Sie genau zwei Sätze ein, getrennt durch einen Punkt.";
            }
        }

        // Wenn keine Fehler vorhanden sind, füge den Satz zur Datenbank hinzu
        if (empty($sentence_err)) {
            $sql_insert = "INSERT INTO sentences (story_id, sentence) VALUES (?, ?)";
            if ($stmt_insert = $conn->prepare($sql_insert)) {
                $stmt_insert->bind_param("is", $param_story_id, $param_sentence);
                $param_story_id = $story_id;
                $param_sentence = $sentence;

                if ($stmt_insert->execute()) {
                    // Aktualisiere den Status, falls nötig (z.B. automatisch abschließen, wenn bestimmte Bedingungen erfüllt sind)
                    $success_message = "Ihr Beitrag wurde erfolgreich hinzugefügt.";

                    // Setze den Cookie neu, um die Gültigkeit zu verlängern
                    setcookie('story_auth_' . $story_id, $story['participation_password_hash'], time() + (7 * 24 * 60 * 60), "/");

                    // Optional: Überprüfe, ob die Geschichte nun abgeschlossen werden soll
                } else {
                    $error_message = "Ein Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.";
                }

                $stmt_insert->close();
            } else {
                $error_message = "Ein Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.";
            }
        }
    }
}

// Abfrage der letzten Satz
$sql_last_sentence = "SELECT sentence FROM sentences WHERE story_id = ? ORDER BY created_at DESC LIMIT 1";
$last_sentence = "";
if ($stmt_last = $conn->prepare($sql_last_sentence)) {
    $stmt_last->bind_param("i", $story_id);
    $stmt_last->execute();
    $result_last = $stmt_last->get_result();
    if ($result_last->num_rows == 1) {
        $row_last = $result_last->fetch_assoc();
        $last_sentence = $row_last['sentence'];
    }
    $stmt_last->close();
}

// Abfrage aller Sätze, falls die Geschichte abgeschlossen ist
$all_sentences = [];
if ($story['status'] === 'completed') {
    $sql_all = "SELECT sentence FROM sentences WHERE story_id = ? ORDER BY created_at ASC";
    if ($stmt_all = $conn->prepare($sql_all)) {
        $stmt_all->bind_param("i", $story_id);
        $stmt_all->execute();
        $result_all = $stmt_all->get_result();
        while ($row_all = $result_all->fetch_assoc()) {
            $all_sentences[] = $row_all['sentence'];
        }
        $stmt_all->close();
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title><?php echo escape($story['title']); ?> - Geschichtenplattform</title>
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
        <h1><?php echo escape($story['title']); ?></h1>

        <?php if ($story['status'] === 'completed'): ?>
            <div class="completed-story">
                <h2>Geschichte abgeschlossen</h2>
                <p>
                    <?php 
                    foreach ($all_sentences as $s) {
                        echo escape($s) . ". ";
                    }
                    ?>
                </p>
            </div>
        <?php else: ?>
            <?php if ($story['is_private'] && !$authenticated): ?>
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Teilnahme-Passwort erforderlich</h5>
                        <?php if (!empty($error_message)): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?php echo escape($error_message); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                        <form action="story.php?story_id=<?php echo $story_id; ?>" method="POST">
                            <div class="mb-3">
                                <label for="password" class="form-label">Teilnahme-Passwort</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <button type="submit" class="btn btn-primary">Anmelden</button>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <!-- Anzeige des letzten Satzes -->
                <?php if (!empty($last_sentence)): ?>
                    <div class="mb-4">
                        <h5>Letzter Satz:</h5>
                        <p><?php echo escape($last_sentence); ?>.</p>
                    </div>
                <?php else: ?>
                    <div class="mb-4">
                        <p>Sei der Erste, der die Geschichte startet!</p>
                    </div>
                <?php endif; ?>

                <!-- Formular zum Hinzufügen eines neuen Satzes -->
                <?php if ($story['status'] === 'ongoing'): ?>
                    <?php if (!empty($success_message)): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?php echo escape($success_message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($sentence_err)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo escape($sentence_err); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <form id="add-sentence-form" action="story.php?story_id=<?php echo $story_id; ?>" method="POST">
                        <div class="mb-3">
                            <label for="sentence-input" class="form-label">Neuen Satz hinzufügen</label>
                            <textarea class="form-control" id="sentence-input" name="sentence" rows="3" maxlength="300" required></textarea>
                            <div class="form-text">Bitte geben Sie genau zwei Sätze ein, getrennt durch einen Punkt. Maximal 300 Zeichen.</div>
                        </div>
                        <button type="submit" class="btn btn-primary">Hinzufügen</button>
                    </form>
                <?php else: ?>
                    <div class="alert alert-info" role="alert">
                        Diese Geschichte ist abgeschlossen.
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Bootstrap JS und Abhängigkeiten über CDN einbinden -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Eigene JS-Datei -->
    <script src="js/main.js"></script>
</body>
</html>
