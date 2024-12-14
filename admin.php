<?php
// admin.php

require_once 'inc/db_connect.php';
require_once 'inc/functions.php';

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
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['admin_password'])) {
    $admin_password_input = trim($_POST['admin_password']);

    if (password_verify($admin_password_input, $story['admin_password_hash'])) {
        // Authentifizierung erfolgreich, setze einen Admin-Session-Token
        session_start();
        $_SESSION['admin_authenticated_' . $story_id] = true;
        header("Location: admin.php?story_id=" . $story_id);
        exit();
    } else {
        $error_message = "Falsches Admin-Passwort.";
    }
}

// Überprüfe, ob der Admin authentifiziert ist
session_start();
$admin_authenticated = isset($_SESSION['admin_authenticated_' . $story_id]) && $_SESSION['admin_authenticated_' . $story_id];

// Verarbeite das Einstellungs-Formular, wenn der Admin authentifiziert ist
if ($admin_authenticated && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    if ($_POST['action'] === 'complete_story') {
        // Markiere die Geschichte als abgeschlossen
        $sql_update = "UPDATE stories SET status = 'completed' WHERE id = ?";
        if ($stmt_update = $conn->prepare($sql_update)) {
            $stmt_update->bind_param("i", $param_story_id);
            $param_story_id = $story_id;

            if ($stmt_update->execute()) {
                $success_message = "Die Geschichte wurde erfolgreich als abgeschlossen markiert.";
                // Aktualisiere den lokalen $story-Array
                $story['status'] = 'completed';
            } else {
                $error_message = "Ein Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.";
            }

            $stmt_update->close();
        } else {
            $error_message = "Ein Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.";
        }
    }

    // Weitere Aktionen können hier hinzugefügt werden (z.B. Bearbeiten des Titels)
}

$conn->close();
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
            </div>

            <?php if ($story['status'] === 'ongoing'): ?>
                <form action="admin.php?story_id=<?php echo $story_id; ?>" method="POST">
                    <input type="hidden" name="action" value="complete_story">
                    <button type="submit" class="btn btn-danger">Geschichte als abgeschlossen markieren</button>
                </form>
            <?php else: ?>
                <div class="alert alert-info" role="alert">
                    Diese Geschichte ist bereits abgeschlossen.
                </div>
            <?php endif; ?>

            <!-- Weitere Admin-Funktionen können hier hinzugefügt werden -->
        <?php endif; ?>
    </div>

    <!-- Bootstrap JS und Abhängigkeiten über CDN einbinden -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Eigene JS-Datei -->
    <script src="js/main.js"></script>
</body>
</html>
