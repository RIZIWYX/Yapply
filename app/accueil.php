<?php
/**
 * Accueil : fil d'actualité, commentaires, notifications, amis.
 * Tout est lu en base ; tout ce qui vient d'un utilisateur est échappé avec e().
 */
require_once __DIR__ . '/../src/header.php';
$u   = exiger_connexion();
$pdo = db();

// Quelle fenêtre ouvrir ? (POST depuis les boutons, GET après un commentaire)
$fermer          = isset($_POST['fermer']);
$afficher_notifs = isset($_POST['afficher']) && !$fermer;
$post_commente   = null;
if (!$fermer && isset($_POST['commentaire'])) {
    $post_commente = filter_var($_POST['post_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
} elseif (!$fermer && isset($_GET['coms'])) {
    $post_commente = filter_var($_GET['coms'], FILTER_VALIDATE_INT) ?: null;
}

// --- Fil d'actualité : la vue fil_actualite donne déjà nb_likes et nb_commentaires
$stmt = $pdo->prepare(
    'SELECT f.*, EXISTS (SELECT 1 FROM likes l WHERE l.post_id = f.id AND l.utilisateur_id = :moi) AS aime
     FROM fil_actualite f
     ORDER BY f.cree_le DESC
     LIMIT 50'
);
$stmt->execute(['moi' => $u['id']]);
$posts = $stmt->fetchAll();

// --- Amis (relation orientée : ceux que J'AI ajoutés)
$stmt = $pdo->prepare(
    'SELECT v.pseudo, v.avatar FROM amities a
     JOIN utilisateurs v ON v.id = a.ami_id
     WHERE a.utilisateur_id = :moi ORDER BY v.pseudo'
);
$stmt->execute(['moi' => $u['id']]);
$amis = $stmt->fetchAll();

// --- Commentaires du post sélectionné
$commentaires = [];
if ($post_commente !== null) {
    $stmt = $pdo->prepare(
        'SELECT c.contenu, v.pseudo FROM commentaires c
         JOIN utilisateurs v ON v.id = c.auteur_id
         WHERE c.post_id = :p ORDER BY c.cree_le'
    );
    $stmt->execute(['p' => $post_commente]);
    $commentaires = $stmt->fetchAll();
}

// --- Notifications (le texte est construit ici, à partir de données structurées)
$notifications = [];
if ($afficher_notifs) {
    $stmt = $pdo->prepare(
        'SELECT n.type, n.lu, v.pseudo FROM notifications n
         JOIN utilisateurs v ON v.id = n.auteur_id
         WHERE n.destinataire_id = :moi ORDER BY n.cree_le DESC LIMIT 30'
    );
    $stmt->execute(['moi' => $u['id']]);
    $notifications = $stmt->fetchAll();
    $pdo->prepare('UPDATE notifications SET lu = true WHERE destinataire_id = :moi AND lu = false')
        ->execute(['moi' => $u['id']]);
}
$phrases = [
    'post'        => 'a publié un post',
    'like'        => 'a aimé votre post',
    'commentaire' => 'a commenté votre post',
    'ami'         => "vous a ajouté à sa liste d'amis",
    'message'     => 'vous a envoyé un message',
];

debut_page('Yapply', 'accueil.css');
barre_navigation($afficher_notifs ? 'notifications' : 'accueil');
?>
<main class="page accueil">
  <!-- Colonne gauche : publier un post -->
  <aside class="colonne colonne-gauche">
    <section class="carte composer">
      <h3>Quoi de neuf ?</h3>
      <form action="post.php" method="post">
        <label class="champ">
          <span class="visually-hidden">Votre publication</span>
          <textarea name="post" placeholder="Écrivez quelque chose..." maxlength="2000" required></textarea>
        </label>
        <div class="composer-actions">
          <button type="submit" class="btn">Publier</button>
        </div>
      </form>
    </section>
    <?php pied_page(); ?>
  </aside>

  <!-- Colonne centrale : fil des publications -->
  <section class="colonne fil" aria-label="Fil d'actualité">
    <?php if (!$posts): ?>
      <p class="carte vide">Aucune publication pour le moment.</p>
    <?php endif; ?>
    <?php foreach ($posts as $p): ?>
      <article class="carte publication">
        <header class="publication-auteur">
          <img class="avatar-rond" src="<?= url_avatar($p['avatar']) ?>" alt="">
          <strong><?= e($p['pseudo']) ?></strong>
        </header>
        <div class="publication-contenu">
          <p><?= nl2br(e($p['texte'])) ?></p>
        </div>
        <footer class="publication-actions">
          <form action="like.php" method="post">
            <input type="hidden" name="post_id" value="<?= (int) $p['id'] ?>">
            <button class="btn btn-discret" type="submit" title="<?= $p['aime'] ? "Je n'aime plus" : "J'aime" ?>">
              <i class='bx <?= $p['aime'] ? 'bxs-heart' : 'bx-heart' ?>'></i><?= (int) $p['nb_likes'] ?>
            </button>
          </form>
          <form method="post">
            <input type="hidden" name="post_id" value="<?= (int) $p['id'] ?>">
            <button class="btn btn-discret" name="commentaire" type="submit" title="Commentaires">
              <i class='bx bx-comment-detail'></i><?= (int) $p['nb_commentaires'] ?>
            </button>
          </form>
        </footer>
      </article>
    <?php endforeach; ?>
  </section>

  <!-- Colonne droite : recherche + amis -->
  <aside class="colonne colonne-droite">
    <section class="carte">
      <form class="recherche" action="profil.php" method="post">
        <label class="champ">
          <span class="visually-hidden">Rechercher des utilisateurs</span>
          <input type="text" name="username" placeholder="Rechercher...">
          <i class='bx bx-search-alt-2'></i>
        </label>
        <button class="btn btn-icone" type="submit" title="Rechercher"><i class='bx bx-right-arrow-alt'></i><span class="visually-hidden">Rechercher</span></button>
      </form>
    </section>
    <section class="carte amis">
      <h3>Mes amis</h3>
      <?php if (!$amis): ?>
        <p class="vide">Aucun ami trouvé</p>
      <?php else: ?>
        <ul class="liste-amis">
          <?php foreach ($amis as $a): ?>
            <li><form action="message.php" method="post">
              <input type="hidden" name="avec" value="<?= e($a['pseudo']) ?>">
              <button class="ami" type="submit" title="Écrire à <?= e($a['pseudo']) ?>">
                <img class="avatar-rond" src="<?= url_avatar($a['avatar']) ?>" alt="">
                <span><?= e($a['pseudo']) ?></span><i class='bx bx-message-rounded'></i>
              </button>
            </form></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
  </aside>
</main>

<?php if ($post_commente !== null): ?>
  <div class="fenetre-fond">
    <section class="carte-pleine fenetre" aria-label="Commentaires">
      <header class="fenetre-entete">
        <h3>Commentaires</h3>
        <form method="post"><button type="submit" name="fermer" class="btn btn-icone btn-discret" title="Fermer"><i class='bx bx-x'></i><span class="visually-hidden">Fermer</span></button></form>
      </header>
      <div class="fenetre-corps">
        <?php if (!$commentaires): ?>
          <p class="vide">Aucun commentaire pour le moment.</p>
        <?php endif; ?>
        <?php foreach ($commentaires as $c): ?>
          <p class="commentaire"><strong><?= e($c['pseudo']) ?></strong> : <?= e($c['contenu']) ?></p>
        <?php endforeach; ?>
      </div>
      <form class="fenetre-saisie" action="commentaire.php" method="post">
        <input type="hidden" name="post_id" value="<?= (int) $post_commente ?>">
        <label class="champ">
          <span class="visually-hidden">Votre commentaire</span>
          <input type="text" name="commentaire" placeholder="Écrivez un commentaire..." maxlength="1000" required>
        </label>
        <button class="btn btn-icone" type="submit" title="Envoyer"><i class='bx bx-send'></i><span class="visually-hidden">Envoyer</span></button>
      </form>
    </section>
  </div>
<?php endif; ?>

<?php if ($afficher_notifs): ?>
  <div class="fenetre-fond">
    <section class="carte-pleine fenetre" aria-label="Notifications">
      <header class="fenetre-entete">
        <h3>Notifications</h3>
        <form method="post"><button type="submit" name="fermer" class="btn btn-icone btn-discret" title="Fermer"><i class='bx bx-x'></i><span class="visually-hidden">Fermer</span></button></form>
      </header>
      <div class="fenetre-corps">
        <?php if (!$notifications): ?>
          <p class="vide">Aucune notification pour le moment.</p>
        <?php endif; ?>
        <?php foreach ($notifications as $n): ?>
          <p class="notif"><i class='bx <?= $n['lu'] ? 'bx-bell' : 'bxs-bell' ?>'></i><?= e($n['pseudo']) ?> <?= e($phrases[$n['type']] ?? '') ?></p>
        <?php endforeach; ?>
      </div>
    </section>
  </div>
<?php endif; ?>
<?php fin_page(); ?>
