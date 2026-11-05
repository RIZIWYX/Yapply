<?php
/**
 * Configuration commune + utilisateur connecté.
 * Étape A : la session et le compte viennent maintenant de PostgreSQL.
 * Les constantes FICHIER_* restent le temps de convertir les autres pages
 * (posts, amis, messages...) : elles disparaîtront aux étapes C et D.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/session.php';

define('RACINE', dirname(__DIR__));
define('DOSSIER_DONNEES', RACINE . '/data');

/* --- Anciens fichiers de données (pages pas encore converties) --- */
define('FICHIER_POSTS',    DOSSIER_DONNEES . '/posts.txt');
define('DOSSIER_IMAGES',   DOSSIER_DONNEES . '/images');
define('DOSSIER_AMIS',     DOSSIER_DONNEES . '/amis');
define('DOSSIER_NOTIFS',   DOSSIER_DONNEES . '/notifications');
define('DOSSIER_MESSAGES', DOSSIER_DONNEES . '/messagerie');

/* --- URL relatives (depuis une page de app/) --- */
define('ASSETS',     '../assets');
define('URL_IMAGES', '../data/images');
define('AVATAR_DEFAUT', 'avatar1');

demarrer_session();

/** Échappe un texte avant de l'afficher dans du HTML (anti-XSS). */
function e(?string $texte): string {
    return htmlspecialchars($texte ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Id de l'utilisateur connecté, ou null. */
function utilisateur_id(): ?int {
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
}

/**
 * Ligne complète de l'utilisateur connecté (sans le hash du mot de passe),
 * ou null. Mise en cache pour ne faire qu'une requête par page.
 */
function utilisateur(): ?array {
    static $cache = false;
    if ($cache !== false) { return $cache; }

    $id = utilisateur_id();
    if ($id === null) { return $cache = null; }

    $stmt = db()->prepare(
        'SELECT id, pseudo, mail, prenom, nom, avatar, bio
         FROM utilisateurs WHERE id = :id'
    );
    $stmt->execute(['id' => $id]);
    $u = $stmt->fetch();
    if (!$u) {                       // compte supprimé entre-temps
        $_SESSION = [];
        return $cache = null;
    }
    return $cache = $u;
}

/** Pseudo de l'utilisateur connecté, ou null. */
function utilisateur_courant(): ?string {
    return utilisateur()['pseudo'] ?? null;
}

/** Avatar de l'utilisateur connecté (ex. « avatar3 »). */
function avatar_courant(): string {
    return utilisateur()['avatar'] ?? AVATAR_DEFAUT;
}

/** Redirige vers la connexion si personne n'est connecté. Renvoie l'utilisateur. */
function exiger_connexion(): array {
    $u = utilisateur();
    if ($u === null) {
        header('Location: connexion.php');
        exit;
    }
    return $u;
}

/** Table pseudo => avatar de tous les membres (pour les pages pas encore converties). */
function charger_avatars(): array {
    $avatars = [];
    foreach (db()->query('SELECT pseudo, avatar FROM utilisateurs') as $l) {
        $avatars[$l['pseudo']] = $l['avatar'];
    }
    return $avatars;
}

/** Vrai si $avatar fait partie des 9 avatars proposés (liste blanche). */
function avatar_valide(string $avatar): bool {
    return (bool) preg_match('/^avatar[1-9]$/', $avatar);
}

/** URL de l'image d'un avatar. */
function url_avatar(string $avatar): string {
    if (!avatar_valide($avatar)) { $avatar = AVATAR_DEFAUT; }
    return ASSETS . '/img/avatar/' . $avatar . '.jpg';
}
