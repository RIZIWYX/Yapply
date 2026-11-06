<?php
/** Première personnalisation (avatar + bio) juste après l'inscription. */
require_once __DIR__ . '/../src/config.php';
$u = exiger_connexion();

$avatar = $_POST['avatar'] ?? AVATAR_DEFAUT;
$bio    = trim($_POST['text'] ?? '');

if (!avatar_valide($avatar)) { $avatar = AVATAR_DEFAUT; }  // liste blanche
$bio = mb_substr($bio, 0, 500);                            // longueur max de la colonne

db()->prepare('UPDATE utilisateurs SET avatar = :a, bio = :b WHERE id = :id')
    ->execute(['a' => $avatar, 'b' => $bio, 'id' => $u['id']]);

header('Location: accueil.php');
exit;
