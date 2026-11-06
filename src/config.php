<?php
/**
 * Configuration commune + utilisateur connecté.
 * Toutes les données (comptes, sessions, posts, amis, messages, notifications)
 * sont dans PostgreSQL : plus aucun fichier texte n'est lu ni écrit.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/session.php';

define('RACINE', dirname(__DIR__));

/* --- URL relatives (depuis une page de app/) --- */
define('ASSETS',     '../assets');
define('AVATAR_DEFAUT', 'avatar1');

demarrer_session();

/* ------------------------------------------------------------------
 * Protection CSRF (jeton synchronisé)
 * Un site tiers peut faire envoyer un formulaire à Yapply par le
 * navigateur d'un membre connecté (le cookie part tout seul). Il ne peut
 * pas, en revanche, lire le jeton secret caché dans nos formulaires :
 * toute requête POST sans le bon jeton est donc refusée.
 * ------------------------------------------------------------------ */

/** Jeton CSRF de la session (créé à la première utilisation). */
function jeton_csrf(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));   // 256 bits imprévisibles
    }
    return $_SESSION['csrf'];
}

/** Champ caché à placer dans CHAQUE <form method="post">. */
function champ_csrf(): string {
    return '<input type="hidden" name="csrf" value="' . jeton_csrf() . '">';
}

/** Nouveau jeton (à appeler après une connexion / inscription). */
function renouveler_csrf(): void {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

/** Refuse la requête POST si le jeton est absent ou faux. */
function verifier_csrf(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { return; }

    $recu = $_POST['csrf'] ?? '';
    // hash_equals : comparaison en temps constant (pas de fuite par le chronométrage)
    if (!is_string($recu) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $recu)) {
        http_response_code(403);
        require_once __DIR__ . '/header.php';
        page_notice('Requête refusée', ['Le formulaire a expiré ou ne vient pas de Yapply. Rechargez la page et réessayez.'], 'accueil.php', "Retour à l'accueil");
        exit;
    }
}

// Vérification automatique : TOUTES les pages chargent config.php,
// donc toutes les requêtes POST du site sont contrôlées ici, d'un coup.
verifier_csrf();

/* ------------------------------------------------------------------
 * Anti brute force (table tentatives_connexion)
 * Partagé par la connexion ET par la modification du compte, qui demande
 * le mot de passe actuel : sans limite, ce formulaire permettrait de
 * deviner le mot de passe d'un compte dont on aurait volé la session.
 * ------------------------------------------------------------------ */
const ESSAIS_MAX  = 5;   // échecs autorisés...
const FENETRE_MIN = 15;  // ...par tranche de 15 minutes

/** IP du visiteur (Vercel la transmet dans X-Forwarded-For). */
function ip_visiteur(): string {
    $ip = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '')[0]);
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

/** Vrai si ce compte ou cette IP a trop d'échecs récents. */
function trop_de_tentatives(string $identifiant, string $ip): bool {
    $stmt = db()->prepare(
        "SELECT count(*) FROM tentatives_connexion
         WHERE reussie = false
           AND tente_le > now() - make_interval(mins => :fenetre)
           AND (identifiant = :id OR ip = CAST(:ip AS inet))"
    );
    $stmt->execute(['fenetre' => FENETRE_MIN, 'id' => $identifiant, 'ip' => $ip]);
    return (int) $stmt->fetchColumn() >= ESSAIS_MAX;
}

/** Enregistre une vérification de mot de passe, réussie ou non. */
function noter_tentative(string $identifiant, string $ip, bool $reussie): void {
    db()->prepare('INSERT INTO tentatives_connexion (identifiant, ip, reussie) VALUES (:id, CAST(:ip AS inet), :ok)')
        ->execute(['id' => $identifiant, 'ip' => $ip, 'ok' => $reussie ? 'true' : 'false']);
}

/**
 * Crée une notification pour $destinataire de la part de l'utilisateur connecté.
 * $type : post | like | commentaire | ami | message (type ENUM en base).
 * On ne se notifie jamais soi-même.
 */
function notifier(int $destinataire, string $type, ?int $postId = null): void {
    $auteur = utilisateur_id();
    if ($auteur === null || $auteur === $destinataire) { return; }
    db()->prepare(
        'INSERT INTO notifications (destinataire_id, auteur_id, type, post_id)
         VALUES (:dest, :auteur, CAST(:type AS type_notification), :post)'
    )->execute(['dest' => $destinataire, 'auteur' => $auteur, 'type' => $type, 'post' => $postId]);
}

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

/** Cherche un membre par pseudo (sans le hash du mot de passe), ou null. */
function membre_par_pseudo(string $pseudo): ?array {
    $stmt = db()->prepare('SELECT id, pseudo, avatar FROM utilisateurs WHERE pseudo = :p');
    $stmt->execute(['p' => $pseudo]);
    return $stmt->fetch() ?: null;
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
