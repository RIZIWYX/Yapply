<?php
/** Mon profil : infos du compte connecté + ses posts (lus en base). */
require_once __DIR__ . '/../src/header.php';
$u = exiger_connexion();

$stmt = db()->prepare('SELECT texte, image FROM posts WHERE auteur_id = :id ORDER BY cree_le DESC');
$stmt->execute(['id' => $u['id']]);
$posts = $stmt->fetchAll();

debut_page('Mon profil', 'profil.css');
barre_navigation('profil');
?>
<main class="page profil">
  <section class="carte profil-carte">
    <img class="avatar-rond grand" src="<?= url_avatar($u['avatar']) ?>" alt="Avatar">
    <div class="profil-infos">
      <h2><?= e($u['pseudo']) ?></h2>
      <dl>
        <dt>Nom d'utilisateur</dt><dd><?= e($u['pseudo']) ?></dd>
        <dt>Adresse mail</dt><dd><?= e($u['mail']) ?></dd>
        <dt>Prénom</dt><dd><?= e($u['prenom']) ?></dd>
        <dt>Nom</dt><dd><?= e($u['nom']) ?></dd>
      </dl>
      <div class="bio"><h3>Bio</h3><p><?= e($u['bio']) ?></p></div>
    </div>
    <div class="profil-actions">
      <a class="btn" href="modifier.php"><i class='bx bx-edit-alt'></i>Modifier</a>
      <a class="btn btn-discret" href="accueil.php"><i class='bx bx-arrow-back'></i>Retour</a>
    </div>
  </section>

  <section class="profil-posts">
    <h3>Posts de <?= e($u['pseudo']) ?></h3>
    <?php if (!$posts): ?>
      <p class="carte vide">Aucun post trouvé pour cet utilisateur.</p>
    <?php else: foreach ($posts as $p): ?>
      <p class="carte post"><?= e($p['texte'] ?? '') ?></p>
    <?php endforeach; endif; ?>
  </section>
</main>
<?php fin_page(); ?>
