<?php
/** Déconnexion : vide et détruit la session, puis supprime le cookie. */
require_once __DIR__ . '/../src/config.php';

$_SESSION = [];
$p = session_get_cookie_params();
setcookie(session_name(), '', [
    'expires' => time() - 3600, 'path' => $p['path'],
    'secure' => $p['secure'], 'httponly' => $p['httponly'], 'samesite' => $p['samesite'],
]);
session_destroy();

header('Location: index.php');
exit;
