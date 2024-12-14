<?php
// inc/functions.php

/**
 * Escaped output to prevent XSS
 */
function escape($html) {
    return htmlspecialchars($html, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
}

/**
 * Generiert ein sicheres Token
 */
function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Analysiert die Geschichte und setzt ein Hintergrundbild
 * (Diese Funktion kann in `admin.php` aufgerufen werden)
 */
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
