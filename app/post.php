<?php
/**
 * Publication d'un post (texte).
 * Avant : ligne "pseudo;texte|0" dans posts.txt + upload SANS contrôle.
 * Les images reviendront à l'étape uploads (contrôle du type réel + stockage
 * Vercel Blob) : Vercel n'a pas de disque où les enregistrer.
 */
require_once __DIR__ . '/../src/config.php';
$u = exiger_connexion();

$texte = trim($_POST['post'] ?? '');

if ($texte === '' || mb_strlen($texte) > 2000) {
    require_once __DIR__ . '/../src/header.php';
    page_notice('Publication impossible', ['Le message doit contenir entre 1 et 2000 caractères.'], 'accueil.php', 'Retour');
    exit;
}

$pdo = db();
$stmt = $pdo->prepare('INSERT INTO posts (auteur_id, texte) VALUES (:auteur, :texte) RETURNING id');
$stmt->execute(['auteur' => $u['id'], 'texte' => $texte]);
$postId = (int) $stmt->fetchColumn();

// Notifie les membres qui ont l'auteur dans leur liste d'amis
// (avant : TOUS les utilisateurs recevaient une notification).
$abonnes = $pdo->prepare('SELECT utilisateur_id FROM amities WHERE ami_id = :id');
$abonnes->execute(['id' => $u['id']]);
foreach ($abonnes->fetchAll(PDO::FETCH_COLUMN) as $dest) {
    notifier((int) $dest, 'post', $postId);
}

header('Location: accueil.php');
exit;
