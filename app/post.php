<?php
/**
 * Publication d'un post : texte et/ou image.
 * L'image passe par les 5 contrôles de src/images.php avant d'être stockée.
 */
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/images.php';
$u = exiger_connexion();

$texte  = trim($_POST['post'] ?? '');
$erreur = null;
$image  = null;

try {
    $image = traiter_image_envoyee('photo');
} catch (RuntimeException $ex) {
    $erreur = $ex->getMessage();
}

if ($erreur === null && $texte === '' && $image === null) {
    $erreur = 'Écrivez un message ou ajoutez une image.';
}
if ($erreur === null && mb_strlen($texte) > 2000) {
    $erreur = 'Le message ne doit pas dépasser 2000 caractères.';
}
if ($erreur !== null) {
    require_once __DIR__ . '/../src/header.php';
    page_notice('Publication impossible', [$erreur], 'accueil.php', 'Retour');
    exit;
}

$pdo = db();
$pdo->beginTransaction();   // image + post enregistrés ensemble, ou pas du tout
$nomImage = $image !== null ? enregistrer_image($image, $u['id']) : null;
$stmt = $pdo->prepare('INSERT INTO posts (auteur_id, texte, image) VALUES (:auteur, :texte, :image) RETURNING id');
$stmt->execute(['auteur' => $u['id'], 'texte' => $texte !== '' ? $texte : null, 'image' => $nomImage]);
$postId = (int) $stmt->fetchColumn();
$pdo->commit();

// Notifie les membres qui ont l'auteur dans leur liste d'amis
$abonnes = $pdo->prepare('SELECT utilisateur_id FROM amities WHERE ami_id = :id');
$abonnes->execute(['id' => $u['id']]);
foreach ($abonnes->fetchAll(PDO::FETCH_COLUMN) as $dest) {
    notifier((int) $dest, 'post', $postId);
}

header('Location: accueil.php');
exit;
