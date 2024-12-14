<?php
// admin.php

require_once 'inc/db_connect.php';
require_once 'inc/functions.php';
session_start();

// Initialisiere Variablen
$story_id = isset($_GET['story_id']) ? intval($_GET['story_id']) : 0;
$admin_password = "";
$admin_password_err = "";
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

// Verarbeite das Passwort-Formular zur Authentifizierung des Admins
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['admin_password']) && !isset($_POST['action'])) {
    $input_password = trim($_POST['admin_password']);

    if (password_verify($input_password, $story['admin_password_hash'])) {
        // Authentifizierung erfolgreich, setze einen Admin-Session-Token
        $_SESSION['admin_authenticated_' . $story_id] = true;
        header("Location: admin.php?story_id=" . $story_id);
        exit();
    } else {
        $error_message = "Falsches Admin-Passwort.";
    }
}

// Überprüfe, ob der Admin authentifiziert ist
$admin_authenticated = isset($_SESSION['admin_authenticated_' . $story_id]) && $_SESSION['admin_authenticated_' . $story_id];

// Verarbeite das Einstellungs-Formular, wenn der Admin authentifiziert ist
if ($admin_authenticated && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_settings') {
        // Sammle und validiere die Eingaben
        $new_title = trim($_POST['title']);
        $new_max_sentences = trim($_POST['max_sentences']);
        $complete_story = isset($_POST['complete_story']) ? true : false;

        // Validierung
        if (empty($new_title)) {
            $error_message = "Der Titel darf nicht leer sein.";
        } elseif (!empty($new_max_sentences) && (!ctype_digit($new_max_sentences) || intval($new_max_sentences) <= 0)) {
            $error_message = "Die maximale Anzahl der Sätze muss eine positive Ganzzahl sein.";
        }

        if (empty($error_message)) {
            // Bereite die SQL-Anweisung vor
            if (!empty($new_max_sentences)) {
                $sql_update = "UPDATE stories SET title = ?, max_sentences = ? WHERE id = ?";
            } else {
                $sql_update = "UPDATE stories SET title = ?, max_sentences = NULL WHERE id = ?";
            }

            if ($stmt_update = $conn->prepare($sql_update)) {
                if (!empty($new_max_sentences)) {
                    $stmt_update->bind_param("sii", $new_title, $new_max_sentences, $story_id);
                } else {
                    $stmt_update->bind_param("si", $new_title, $story_id);
                }

                if ($stmt_update->execute()) {
                    // Optional: Automatisches Abschließen, wenn die Bedingung erfüllt ist
                    if ($complete_story && $story['status'] !== 'completed') {
                        $stmt_close = $conn->prepare("UPDATE stories SET status = 'completed' WHERE id = ?");
                        $stmt_close->bind_param("i", $story_id);
                        $stmt_close->execute();
                        $stmt_close->close();

                        // Analysiere die Geschichte und füge ein Hintergrundbild hinzu
                        // (Diese Funktion wird später definiert)
                        analyze_and_set_background($conn, $story_id);
                    }

                    $success_message = "Einstellungen wurden erfolgreich aktualisiert.";
                    
                    // Aktualisiere den lokalen $story-Array
                    $story['title'] = $new_title;
                    if (!empty($new_max_sentences)) {
                        $story['max_sentences'] = intval($new_max_sentences);
                    } else {
                        $story['max_sentences'] = NULL;
                    }
                    if ($complete_story) {
                        $story['status'] = 'completed';
                    }
                } else {
                    $error_message = "Ein Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.";
                }

                $stmt_update->close();
            } else {
                $error_message = "Ein Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.";
            }
        }
    }
}

// Funktion zur Analyse der Geschichte und Setzen eines Hintergrundbildes
function analyze_and_set_background($conn, $story_id) {
    // Abfrage aller Sätze der Geschichte
    $sql_sentences = "SELECT sentence FROM sentences WHERE story_id = ? ORDER BY created_at ASC";
    $sentences = [];
    if ($stmt = $conn->prepare($sql_sentences)) {
        $stmt->bind_param("i", $story_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $sentences[] = $row['sentence'];
        }
        $stmt->close();
    }

    // Kombiniere alle Sätze zu einem Text
    $full_text = implode(' ', $sentences);

    // Extrahiere Wörter und zähle ihre Häufigkeit
    $words = str_word_count(strtolower($full_text), 1);
    $word_counts = array_count_values($words);

    // Entferne häufige Stoppwörter (optional)
    $stop_words = ['und', 'der', 'die', 'das', 'ist', 'ein', 'eine', 'als', 'in', 'zu', 'den', 'mit', 'auf', 'für', 'von', 'aus', 'an', 'auch', 'sich', 'dass', 'er', 'sie', 'es', 'wir', 'ihr', 'ich', 'du', 'mein', 'dein', 'sein', 'haben', 'werden', 'nach'];
    foreach ($stop_words as $stop_word) {
        unset($word_counts[$stop_word]);
    }

    // Sortiere die Wörter nach Häufigkeit absteigend
    arsort($word_counts);

    // Nimm die Top 3 häufigsten Wörter
    $top_words = array_slice(array_keys($word_counts), 0, 3);

    // Pixabay API Schlüssel (ersetze 'DEIN_PIXABAY_API_SCHLÜSSEL' mit deinem tatsächlichen Schlüssel)
    $pixabay_api_key = 'DEIN_PIXABAY_API_SCHLÜSSEL';

    // Funktion zur Suche nach einem Bild basierend auf einem Wort
    function search_pixabay_image($word, $api_key) {
        $query = urlencode($word);
        $url = "https://pixabay.com/api/?key={$api_key}&q={$query}&image_type=photo&per_page=3&safesearch=true";

        $response = file_get_contents($url);
        if ($response === FALSE) {
            return NULL;
        }

        $data = json_decode($response, true);
        if (isset($data['hits'][0]['largeImageURL'])) {
            return $data['hits'][0]['largeImageURL'];
        }

        return NULL;
    }

    // Suche nach Bildern für die Top-Wörter
    $image_url = NULL;
    foreach ($top_words as $word) {
        $url = search_pixabay_image($word, $pixabay_api_key);
        if ($url) {
            $image_url = $url;
            break;
        }
    }

    // Wenn kein Bild gefunden wurde, setze ein Standardbild oder lasse das Feld leer
    if (!$image_url) {
        $image_url = NULL; // Optional: Setze auf eine Standardbild-URL
    }

    // Aktualisiere die `background_image_url` in der Datenbank
    if ($image_url) {
        $sql_update_image = "UPDATE stories SET background_image_url = ? WHERE id = ?";
        if ($stmt_image = $conn->prepare($sql_update_image)) {
            $stmt_image->bind_param("si", $image_url, $story_id);
            $stmt_image->execute();
            $stmt_image->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Admin - <?php echo escape($story['title']); ?></title>
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
        <h1>Admin-Bereich: <?php echo escape($story['title']); ?></h1>

        <?php if (!$admin_authenticated): ?>
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Admin-Authentifizierung</h5>
                    <?php if (!empty($error_message)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo escape($error_message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    <form action="admin.php?story_id=<?php echo $story_id; ?>" method="POST">
                        <div class="mb-3">
                            <label for="admin-password" class="form-label">Admin-Passwort</label>
                            <input type="password" class="form-control" id="admin-password" name="admin_password" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Anmelden</button>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo escape($success_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo escape($error_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="mb-4">
                <h5>Geschichte-Details</h5>
                <p><strong>Titel:</strong> <?php echo escape($story['title']); ?></p>
                <p><strong>Status:</strong> 
                    <?php 
                        switch ($story['status']) {
                            case 'ongoing':
                                echo '<span class="badge bg-warning text-dark">Laufend</span>';
                                break;
                            case 'completed':
                                echo '<span class="badge bg-success">Abgeschlossen</span>';
                                break;
                            case 'locked':
                                echo '<span class="badge bg-secondary">Gesperrt</span>';
                                break;
                            default:
                                echo '<span class="badge bg-light text-dark">Unbekannt</span>';
                        }
                    ?>
                </p>
                <p><strong>Privat:</strong> 
                    <?php 
                        echo $story['is_private'] ? 
                        '<span class="badge bg-danger">Privat</span>' : 
                        '<span class="badge bg-info text-dark">Öffentlich</span>'; 
                    ?>
                </p>
                <p><strong>Maximale Anzahl von Sätzen:</strong> 
                    <?php 
                        echo !is_null($story['max_sentences']) ? $story['max_sentences'] : 'Keine Begrenzung';
                    ?>
                </p>
                <?php if (!empty($story['background_image_url'])): ?>
                    <p><strong>Hintergrundbild:</strong> 
                        <a href="<?php echo escape($story['background_image_url']); ?>" target="_blank">Bild ansehen</a>
                    </p>
                <?php endif; ?>
            </div>

            <!-- Einstellungsformular -->
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Einstellungen bearbeiten</h5>
                    <form action="admin.php?story_id=<?php echo $story_id; ?>" method="POST">
                        <input type="hidden" name="action" value="update_settings">
                        <div class="mb-3">
                            <label for="title" class="form-label">Titel der Geschichte</label>
                            <input type="text" class="form-control" id="title" name="title" value="<?php echo escape($story['title']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="max_sentences" class="form-label">Maximale Anzahl von Sätzen</label>
                            <input type="number" class="form-control" id="max_sentences" name="max_sentences" value="<?php echo !is_null($story['max_sentences']) ? intval($story['max_sentences']) : ''; ?>" min="1">
                            <div class="form-text">Lasse dieses Feld leer, um keine Begrenzung festzulegen.</div>
                        </div>
                        <div class="form-check mb-3">
                            <input type="checkbox" class="form-check-input" id="complete_story" name="complete_story">
                            <label class="form-check-label" for="complete_story">Geschichte als abgeschlossen markieren</label>
                        </div>
                        <button type="submit" class="btn btn-primary">Einstellungen aktualisieren</button>
                        <a href="story.php?story_id=<?php echo $story_id; ?>" class="btn btn-secondary">Zurück zur Geschichte</a>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Bootstrap JS und Abhängigkeiten über CDN einbinden -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Eigene JS-Datei -->
    <script src="js/main.js"></script>
</body>
</html>
