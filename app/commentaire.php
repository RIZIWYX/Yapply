<?php
/**
 * Ajout d'un commentaire.
 * Avant : texte collé au bout de la ligne du post dans posts.txt (avec "|").
 */
require_once __DIR__ . '/../src/config.php';
$u = exiger_connexion();

$contenu = trim($_POST['commentaire'] ?? '');
$postId  = filter_var($_POST['post_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

if ($contenu !== '' && mb_strlen($contenu) <= 1000 && $postId !== false) {
    $pdo = db();
    $stmt = $pdo->prepare('SELECT auteur_id FROM posts WHERE id = :id');
    $stmt->execute(['id' => $postId]);
    $auteur = $stmt->fetchColumn();

    if ($auteur !== false) {
        $pdo->prepare('INSERT INTO commentaires (post_id, auteur_id, contenu) VALUES (:p, :a, :c)')
            ->execute(['p' => $postId, 'a' => $u['id'], 'c' => $contenu]);
        notifier((int) $auteur, 'commentaire', $postId);
    }
}

// On rouvre la fenêtre des commentaires du même post
header('Location: accueil.php?coms=' . ($postId ?: ''));
exit;
