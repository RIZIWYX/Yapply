<?php
/**
 * Like / unlike d'un post.
 * Avant : compteur incrémentable à l'infini (on pouvait liker 1000 fois).
 * Maintenant : une ligne par (post, utilisateur) ; un 2e clic retire le like.
 */
require_once __DIR__ . '/../src/config.php';
$u = exiger_connexion();

$postId = filter_var($_POST['post_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($postId !== false) {
    $pdo = db();
    // Le post existe-t-il ? (et qui en est l'auteur, pour la notification)
    $stmt = $pdo->prepare('SELECT auteur_id FROM posts WHERE id = :id');
    $stmt->execute(['id' => $postId]);
    $auteur = $stmt->fetchColumn();

    if ($auteur !== false) {
        // Si le like existe déjà, on le retire (bascule)
        $del = $pdo->prepare('DELETE FROM likes WHERE post_id = :p AND utilisateur_id = :u');
        $del->execute(['p' => $postId, 'u' => $u['id']]);

        if ($del->rowCount() === 0) {
            // ON CONFLICT DO NOTHING : deux clics simultanés ne créent pas d'erreur
            $pdo->prepare('INSERT INTO likes (post_id, utilisateur_id) VALUES (:p, :u) ON CONFLICT DO NOTHING')
                ->execute(['p' => $postId, 'u' => $u['id']]);
            notifier((int) $auteur, 'like', $postId);
        }
    }
}

header('Location: accueil.php');
exit;
