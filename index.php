<?php
// index.php

// Inkludiere die Datenbankverbindung und Hilfsfunktionen
require_once 'inc/db_connect.php';
require_once 'inc/functions.php';

// Abfrage, um alle Geschichten abzurufen
$sql = "SELECT id, title, status, is_private FROM stories ORDER BY created_at DESC";
$result = $conn->query($sql);

// Schließen der Datenbankverbindung ist optional hier
// $conn->close();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Kooperative Geschichtenplattform</title>
    <!-- Bootstrap CSS über CDN einbinden -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Eigene CSS-Datei -->
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">Geschichtenplattform</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" 
                aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <!-- Optional: Weitere Navigationselemente -->
        </div>
    </nav>

    <!-- Hauptcontainer -->
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1>Alle Geschichten</h1>
            <a href="create_story.php" class="btn btn-success">Neue Geschichte erstellen</a>
        </div>

        <?php if ($result->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-primary">
                        <tr>
                            <th>Titel</th>
                            <th>Status</th>
                            <th>Privat</th>
                            <th>Aktion</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo escape($row['title']); ?></td>
                                <td>
                                    <?php 
                                        switch ($row['status']) {
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
                                </td>
                                <td>
                                    <?php 
                                        echo $row['is_private'] ? 
                                        '<span class="badge bg-danger">Privat</span>' : 
                                        '<span class="badge bg-info text-dark">Öffentlich</span>'; 
                                    ?>
                                </td>
                                <td>
                                    <a href="story.php?story_id=<?php echo $row['id']; ?>" class="btn btn-primary btn-sm">
                                        <?php echo $row['status'] === 'completed' ? 'Ansehen' : 'Teilnehmen'; ?>
                                    </a>
                                    <?php if ($row['status'] === 'locked'): ?>
                                        <span class="text-muted">Keine Aktion möglich</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info" role="alert">
                Es gibt noch keine Geschichten. Sei der Erste und erstelle eine neue Geschichte!
            </div>
        <?php endif; ?>
    </div>

    <!-- Bootstrap JS und Abhängigkeiten über CDN einbinden -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
