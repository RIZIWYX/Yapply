<?php
/** Modification de l'avatar et/ou de la bio depuis la page « Modifier mon profil ». */
require_once __DIR__ . '/../src/config.php';
$u = exiger_connexion();

// Champ vide => on garde la valeur actuelle
$avatar = $_POST['avatar_number'] ?? '';
$bio    = trim($_POST['biographie'] ?? '');
if ($avatar === '' || !avatar_valide($avatar)) { $avatar = $u['avatar']; }
if ($bio === '') { $bio = $u['bio']; }
$bio = mb_substr($bio, 0, 500);

db()->prepare('UPDATE utilisateurs SET avatar = :a, bio = :b WHERE id = :id')
    ->execute(['a' => $avatar, 'b' => $bio, 'id' => $u['id']]);

header('Location: profil_utilisateur.php');
exit;
