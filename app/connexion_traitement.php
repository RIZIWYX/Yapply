<?php
/**
 * Connexion : vérifie le mot de passe haché + limite les essais (anti brute force).
 * Avant : comparaison en clair avec comptes.txt, essais illimités.
 */
require_once __DIR__ . '/../src/config.php';

// ESSAIS_MAX, FENETRE_MIN et les fonctions anti brute force sont dans src/config.php
$identifiant = trim($_POST['userinfo'] ?? '');
$password    = $_POST['password'] ?? '';
$ip          = ip_visiteur();

$pdo = db();

// 1. Trop d'échecs récents pour ce compte ou cette IP ? On bloque avant même de vérifier.
if (trop_de_tentatives($identifiant, $ip)) {
    require_once __DIR__ . '/../src/header.php';
    page_notice('Trop de tentatives', ['Trop d\'essais de connexion. Réessayez dans ' . FENETRE_MIN . ' minutes.'], 'connexion.php', 'Retour');
    exit;
}

// 2. Recherche du compte par pseudo OU mail (citext : insensible à la casse)
$stmt = $pdo->prepare('SELECT id, mot_de_passe FROM utilisateurs WHERE pseudo = :v OR mail = :v');
$stmt->execute(['v' => $identifiant]);
$compte = $stmt->fetch();

// password_verify compare au hash ; si le compte n'existe pas on vérifie quand même
// un faux hash, pour que le temps de réponse ne révèle pas si le pseudo existe.
$hashFactice = '$2y$10$AyRBKAcwomnnUhdj52JSgeYkBmHJpUs9ymasRgIPkOnKaEN08Rm76';
$ok = password_verify($password, $compte['mot_de_passe'] ?? $hashFactice) && $compte !== false;

// 3. On enregistre la tentative (réussie ou non)
noter_tentative($identifiant, $ip, $ok);

if ($ok) {
    // Si l'algorithme de hachage par défaut a évolué, on met le hash à jour
    if (password_needs_rehash($compte['mot_de_passe'], PASSWORD_DEFAULT)) {
        $pdo->prepare('UPDATE utilisateurs SET mot_de_passe = :h WHERE id = :id')
            ->execute(['h' => password_hash($password, PASSWORD_DEFAULT), 'id' => $compte['id']]);
    }
    session_regenerate_id(true);   // anti fixation de session
    $_SESSION['user_id'] = (int) $compte['id'];
    renouveler_csrf();             // nouveau jeton pour la session connectée
    header('Location: accueil.php');
    exit;
}

// Même message que le pseudo existe ou non (pas d'énumération des comptes)
require_once __DIR__ . '/../src/header.php';
page_notice('Information incorrecte', ['Pseudo, adresse mail ou mot de passe invalide.'], 'connexion.php', 'Retour');
