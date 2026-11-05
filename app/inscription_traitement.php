<?php
/**
 * Inscription : crée le compte dans PostgreSQL.
 * Avant : ligne "pseudo;mdp;mdp;mail;..." en clair dans comptes.txt.
 * Maintenant : INSERT préparé + mot de passe haché (password_hash).
 */
require_once __DIR__ . '/../src/config.php';

$user          = trim($_POST['username'] ?? '');
$password      = $_POST['password'] ?? '';
$password_conf = $_POST['password_conf'] ?? '';
$mail          = trim($_POST['mail'] ?? '');
$prenom        = trim($_POST['prenom'] ?? '');
$nom           = trim($_POST['nom'] ?? '');

$erreurs = [];
if (!preg_match('/^[A-Za-z0-9_.-]{2,30}$/', $user)) { $erreurs[] = "Pseudo invalide : 2 à 30 caractères (lettres, chiffres, _ . -)."; }
if (strlen($password) < 8)                          { $erreurs[] = "Le mot de passe doit contenir au moins 8 caractères."; }
if ($password_conf !== $password)                   { $erreurs[] = "Les mots de passe doivent être identiques."; }
if (!filter_var($mail, FILTER_VALIDATE_EMAIL))      { $erreurs[] = "Adresse mail invalide."; }
if (mb_strlen($prenom) < 2 || mb_strlen($prenom) > 50) { $erreurs[] = "Le prénom doit contenir entre 2 et 50 caractères."; }
if (mb_strlen($nom) < 2 || mb_strlen($nom) > 50)       { $erreurs[] = "Le nom doit contenir entre 2 et 50 caractères."; }

if (!$erreurs) {
    try {
        // Requête préparée : les valeurs sont envoyées à part, jamais collées dans le SQL
        $stmt = db()->prepare(
            'INSERT INTO utilisateurs (pseudo, mail, mot_de_passe, prenom, nom)
             VALUES (:pseudo, :mail, :mdp, :prenom, :nom)
             RETURNING id'
        );
        $stmt->execute([
            'pseudo' => $user,
            'mail'   => $mail,
            'mdp'    => password_hash($password, PASSWORD_DEFAULT), // bcrypt, salé automatiquement
            'prenom' => $prenom,
            'nom'    => $nom,
        ]);

        session_regenerate_id(true);               // nouvel ID de session (anti-fixation)
        $_SESSION['user_id'] = (int) $stmt->fetchColumn();
        renouveler_csrf();             // nouveau jeton pour la session connectée
        header('Location: personnalisation.php');
        exit;
    } catch (PDOException $ex) {
        // 23505 = violation d'unicité : c'est la base qui vérifie pseudo et mail
        if ($ex->getCode() !== '23505') { throw $ex; }
        $erreurs[] = str_contains($ex->getMessage(), 'mail')
            ? "Cette adresse mail est déjà utilisée."
            : "Nom d'utilisateur déjà utilisé.";
    }
}

require_once __DIR__ . '/../src/header.php';
page_notice('Inscription impossible', $erreurs, 'inscription.php', 'Retour');
