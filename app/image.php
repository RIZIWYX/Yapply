<?php
/**
 * Affiche une image de post stockée en base : image.php?nom=<32 hex>.jpg
 * Réservé aux membres connectés, comme le fil d'actualité.
 */
require_once __DIR__ . '/../src/config.php';

if (utilisateur() === null) {
    http_response_code(403);
    exit;
}

$nom = $_GET['nom'] ?? '';
if (!is_string($nom) || !preg_match('/^[a-f0-9]{32}\.jpg$/', $nom)) {   // liste blanche du format
    http_response_code(404);
    exit;
}

$stmt = db()->prepare('SELECT donnees FROM images WHERE nom = :nom');
$stmt->execute(['nom' => $nom]);
$donnees = $stmt->fetchColumn();
if ($donnees === false) {
    http_response_code(404);
    exit;
}
if (is_resource($donnees)) { $donnees = stream_get_contents($donnees); }   // BYTEA -> flux PDO

// Le navigateur doit la traiter comme une image JPEG, et rien d'autre
header('Content-Type: image/jpeg');
header('Content-Length: ' . strlen($donnees));
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; sandbox");
header('Cache-Control: private, max-age=86400');   // cache du navigateur seulement (contenu réservé aux membres)
echo $donnees;
