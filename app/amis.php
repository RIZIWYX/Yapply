<?php
/**
 * Ajout d'un ami.
 * Avant : pseudo ajouté dans data/amis/<pseudo>.txt, SANS vérifier que le
 * membre existe (on pouvait « ajouter » n'importe quel texte).
 */
require_once __DIR__ . '/../src/header.php';
$u = exiger_connexion();

$pseudo = trim($_POST['username'] ?? '');
$ami    = $pseudo !== '' ? membre_par_pseudo($pseudo) : null;

if ($ami === null) {
    page_notice('Utilisateur introuvable.', [], 'accueil.php', "Retour à l'accueil");
    exit;
}
if ($ami['id'] === $u['id']) {
    page_notice('Vous ne pouvez pas vous ajouter vous-même.', [], 'accueil.php', "Retour à l'accueil");
    exit;
}

// ON CONFLICT DO NOTHING : la clé primaire (utilisateur_id, ami_id) empêche les doublons
$stmt = db()->prepare(
    'INSERT INTO amities (utilisateur_id, ami_id) VALUES (:moi, :ami) ON CONFLICT DO NOTHING'
);
$stmt->execute(['moi' => $u['id'], 'ami' => $ami['id']]);

if ($stmt->rowCount() === 0) {
    page_notice($ami['pseudo'] . ' est déjà dans votre liste d\'amis.', [], 'accueil.php', "Retour à l'accueil");
} else {
    notifier((int) $ami['id'], 'ami');
    page_notice($ami['pseudo'] . ' a été ajouté à vos amis !', [], 'accueil.php', "Retour à l'accueil", false);
}
