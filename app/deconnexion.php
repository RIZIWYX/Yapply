<?php
/**
 * Déconnexion : vide et détruit la session, puis supprime le cookie.
 * Avant : simple lien (GET). N'importe quel site pouvait déconnecter un membre
 * à son insu, par exemple avec <img src="https://.../app/deconnexion.php">.
 * Maintenant : POST uniquement, donc le jeton CSRF est vérifié (src/config.php).
 */
require_once __DIR__ . '/../src/config.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: accueil.php');
    exit;
}

$_SESSION = [];
$p = session_get_cookie_params();
setcookie(session_name(), '', [
    'expires' => time() - 3600, 'path' => $p['path'],
    'secure' => $p['secure'], 'httponly' => $p['httponly'], 'samesite' => $p['samesite'],
]);
session_destroy();

header('Location: index.php');
exit;
