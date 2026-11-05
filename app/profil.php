<?php
/** Profil d'un autre membre (recherche par pseudo) : lu en base. */
require_once __DIR__ . '/../src/header.php';
exiger_connexion();

$recherche = trim($_POST['username'] ?? $_GET['username'] ?? '');
$membre = null;
$posts  = [];

if ($recherche !== '') {
    // Le hash du mot de passe n'est jamais sélectionné
    $stmt = db()->prepare('SELECT id, pseudo, mail, prenom, nom, avatar, bio FROM utilisateurs WHERE pseudo = :p');
    $stmt->execute(['p' => $recherche]);
    $membre = $stmt->fetch() ?: null;

    if ($membre) {
        $stmt = db()->prepare('SELECT texte FROM posts WHERE auteur_id = :id ORDER BY cree_le DESC');
        $stmt->execute(['id' => $membre['id']]);
        $posts = $stmt->fetchAll();
    }
}

debut_page('Profil utilisateur', 'profil.css');
barre_navigation();
?>
<main class="page profil">
<?php if ($membre === null): ?>
  <section class="carte notice profil-introuvable">
    <i class='bx bx-search-alt notice-icone'></i>
    <h2>Aucun utilisateur trouvé avec ce nom d'utilisateur.</h2>
    <a class="btn" href="accueil.php">Retour</a>
  </section>
<?php else: ?>
  <section class="carte profil-carte">
    <img class="avatar-rond grand" src="<?= url_avatar($membre['avatar']) ?>" alt="Avatar">
    <div class="profil-infos">
      <h2><?= e($membre['pseudo']) ?></h2>
      <dl>
        <dt>Nom d'utilisateur</dt><dd><?= e($membre['pseudo']) ?></dd>
        <dt>Prénom</dt><dd><?= e($membre['prenom']) ?></dd>
        <dt>Nom</dt><dd><?= e($membre['nom']) ?></dd>
      </dl>
      <div class="bio"><h3>Bio</h3><p><?= e($membre['bio']) ?></p></div>
    </div>
    <div class="profil-actions">
      <form action="amis.php" method="POST"><?= champ_csrf() ?><input type="hidden" name="username" value="<?= e($membre['pseudo']) ?>"><button class="btn" type="submit"><i class='bx bx-user-plus'></i>Ajouter en ami</button></form>
      <form action="message.php" method="POST"><?= champ_csrf() ?><input type="hidden" name="avec" value="<?= e($membre['pseudo']) ?>"><button class="btn btn-secondaire" type="submit"><i class='bx bx-message-rounded-dots'></i>Envoyer un message</button></form>
      <a class="btn btn-discret" href="accueil.php"><i class='bx bx-arrow-back'></i>Retour</a>
    </div>
  </section>

  <section class="profil-posts">
    <h3>Posts de <?= e($membre['pseudo']) ?></h3>
    <?php if (!$posts): ?>
      <p class="carte vide">Aucun post trouvé pour cet utilisateur.</p>
    <?php else: foreach ($posts as $p): ?>
      <p class="carte post"><?= e($p['texte'] ?? '') ?></p>
    <?php endforeach; endif; ?>
  </section>
<?php endif; ?>
</main>
<?php fin_page(); ?>
