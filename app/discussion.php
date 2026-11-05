<?php
/**
 * Liste des conversations du membre connecté.
 * Avant : parcours de tous les fichiers de data/messagerie/.
 * Maintenant : une requête qui regroupe les messages par interlocuteur.
 */
require_once __DIR__ . '/../src/header.php';
$u = exiger_connexion();

$stmt = db()->prepare(
    "SELECT v.pseudo, v.avatar,
            max(m.envoye_le) AS dernier,
            count(*) FILTER (WHERE m.destinataire_id = :moi AND m.lu_le IS NULL) AS non_lus
     FROM messages m
     JOIN utilisateurs v
       ON v.id = CASE WHEN m.expediteur_id = :moi THEN m.destinataire_id ELSE m.expediteur_id END
     WHERE :moi IN (m.expediteur_id, m.destinataire_id)
     GROUP BY v.id, v.pseudo, v.avatar
     ORDER BY dernier DESC"
);
$stmt->execute(['moi' => $u['id']]);
$conversations = $stmt->fetchAll();

debut_page('Discussions', 'discussion.css');
barre_navigation('discussion');
?>
<main class="page discussions">
  <h1>Vos discussions</h1>
  <section class="carte">
    <?php if (!$conversations): ?>
      <p class="vide">Aucune discussion trouvée</p>
    <?php else: ?>
      <ul class="liste-discussions">
        <?php foreach ($conversations as $c): ?>
          <li><form action="message.php" method="post">
            <input type="hidden" name="avec" value="<?= e($c['pseudo']) ?>">
            <button class="conversation" type="submit">
              <img class="avatar-rond" src="<?= url_avatar($c['avatar']) ?>" alt="">
              <span><?= e($c['pseudo']) ?><?= $c['non_lus'] > 0 ? ' (' . (int) $c['non_lus'] . ')' : '' ?></span>
              <i class='bx bx-chevron-right'></i>
            </button>
          </form></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
  <a class="btn btn-secondaire" href="accueil.php"><i class='bx bx-arrow-back'></i>Retour</a>
</main>
<?php fin_page(); ?>
