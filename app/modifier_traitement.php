<?php
/**
 * Modification du compte (pseudo, mail, nom, prénom, mot de passe).
 * Avant : réécriture de comptes.txt, posts.txt et renommage des fichiers d'amis.
 * Maintenant : un seul UPDATE ; les posts/amis sont liés à l'id, pas au pseudo.
 */
require_once __DIR__ . '/../src/config.php';
$u = exiger_connexion();

// Champ vide => on garde l'ancienne valeur
$user   = trim($_POST['username'] ?? '') ?: $u['pseudo'];
$mail   = trim($_POST['mail'] ?? '')     ?: $u['mail'];
$prenom = trim($_POST['prenom'] ?? '')   ?: $u['prenom'];
$nom    = trim($_POST['nom'] ?? '')      ?: $u['nom'];
$password      = $_POST['password'] ?? '';
$password_conf = $_POST['password_conf'] ?? '';

$erreurs = [];
if (!preg_match('/^[A-Za-z0-9_.-]{2,30}$/', $user))    { $erreurs[] = "Pseudo invalide : 2 à 30 caractères (lettres, chiffres, _ . -)."; }
if (!filter_var($mail, FILTER_VALIDATE_EMAIL))         { $erreurs[] = "Adresse mail invalide."; }
if (mb_strlen($prenom) < 2 || mb_strlen($prenom) > 50) { $erreurs[] = "Le prénom doit contenir entre 2 et 50 caractères."; }
if (mb_strlen($nom) < 2 || mb_strlen($nom) > 50)       { $erreurs[] = "Le nom doit contenir entre 2 et 50 caractères."; }
if ($password !== '' || $password_conf !== '') {
    if (strlen($password) < 8)        { $erreurs[] = "Le mot de passe doit contenir au moins 8 caractères."; }
    if ($password_conf !== $password) { $erreurs[] = "Les mots de passe doivent être identiques."; }
}

if (!$erreurs) {
    try {
        $sql    = 'UPDATE utilisateurs SET pseudo = :pseudo, mail = :mail, prenom = :prenom, nom = :nom';
        $params = ['pseudo' => $user, 'mail' => $mail, 'prenom' => $prenom, 'nom' => $nom, 'id' => $u['id']];
        if ($password !== '') {
            $sql .= ', mot_de_passe = :mdp';
            $params['mdp'] = password_hash($password, PASSWORD_DEFAULT);
        }
        // WHERE id = id de la SESSION : impossible de modifier le compte de quelqu'un d'autre
        db()->prepare($sql . ' WHERE id = :id')->execute($params);

        if ($password !== '') { session_regenerate_id(true); }
        header('Location: profil_utilisateur.php');
        exit;
    } catch (PDOException $ex) {
        if ($ex->getCode() !== '23505') { throw $ex; }
        $erreurs[] = str_contains($ex->getMessage(), 'mail')
            ? "Cette adresse mail est déjà utilisée."
            : "Nom d'utilisateur déjà utilisé.";
    }
}

require_once __DIR__ . '/../src/header.php';
page_notice('Modification impossible', $erreurs, 'modifier.php', 'Retour');
