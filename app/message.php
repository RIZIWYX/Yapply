<?php
/**
 * Conversation privée avec un membre.
 * Avant : un fichier data/messagerie/<a>_<b>.txt par paire, dont le nom venait
 * directement du formulaire (risque de lecture d'autres fichiers), et les
 * messages étaient affichés sans échappement (XSS).
 */
require_once __DIR__ . '/../src/header.php';
$u = exiger_connexion();

$pseudo = trim($_POST['avec'] ?? $_GET['avec'] ?? '');
$autre  = $pseudo !== '' ? membre_par_pseudo($pseudo) : null;
if ($autre === null || $autre['id'] === $u['id']) {
    header('Location: discussion.php');
    exit;
}

$pdo = db();

// Envoi d'un message
$texte = trim($_POST['message'] ?? '');
if ($texte !== '' && mb_strlen($texte) <= 2000) {
    $pdo->prepare('INSERT INTO messages (expediteur_id, destinataire_id, contenu) VALUES (:de, :a, :c)')
        ->execute(['de' => $u['id'], 'a' => $autre['id'], 'c' => $texte]);
    notifier((int) $autre['id'], 'message');
    // Redirection après POST : un rechargement de page ne renvoie pas le message
    header('Location: message.php?avec=' . urlencode($autre['pseudo']));
    exit;
}

// Messages entre les deux membres, dans les deux sens
$stmt = $pdo->prepare(
    'SELECT expediteur_id, contenu, envoye_le FROM messages
     WHERE (expediteur_id = :moi AND destinataire_id = :lui)
        OR (expediteur_id = :lui AND destinataire_id = :moi)
     ORDER BY envoye_le, id
     LIMIT 200'
);
$stmt->execute(['moi' => $u['id'], 'lui' => $autre['id']]);
$messages = $stmt->fetchAll();

// Les messages reçus sont maintenant lus
$pdo->prepare('UPDATE messages SET lu_le = now() WHERE destinataire_id = :moi AND expediteur_id = :lui AND lu_le IS NULL')
    ->execute(['moi' => $u['id'], 'lui' => $autre['id']]);

debut_page('Discussion avec ' . $autre['pseudo'], 'discussion.css');
barre_navigation('discussion');
?>
<main class="page conversation-page">
  <section class="carte-pleine chat">
    <header class="chat-entete">
      <a class="btn btn-icone btn-discret" href="discussion.php" title="Retour"><i class='bx bx-arrow-back'></i><span class="visually-hidden">Retour</span></a>
      <img class="avatar-rond" src="<?= url_avatar($autre['avatar']) ?>" alt="">
      <h2><?= e($autre['pseudo']) ?></h2>
    </header>
    <div class="messages" id="messages">
      <?php if (!$messages): ?>
        <p class="vide">Aucune discussion avec <?= e($autre['pseudo']) ?> pour le moment.</p>
      <?php endif; ?>
      <?php foreach ($messages as $m): $envoye = (int) $m['expediteur_id'] === $u['id']; ?>
        <div class="message <?= $envoye ? 'envoye' : 'recu' ?>">
          <strong><?= e($envoye ? $u['pseudo'] : $autre['pseudo']) ?></strong><?= nl2br(e($m['contenu'])) ?>
        </div>
      <?php endforeach; ?>
    </div>
    <form class="zone-saisie" method="POST" action="message.php"><?= champ_csrf() ?>
      <input type="hidden" name="avec" value="<?= e($autre['pseudo']) ?>">
      <label class="champ">
        <span class="visually-hidden">Votre message</span>
        <input type="text" name="message" placeholder="Écrire un message" autocomplete="off" maxlength="2000" required autofocus>
      </label>
      <button class="btn btn-icone" type="submit" title="Envoyer"><i class='bx bxs-send'></i><span class="visually-hidden">Envoyer</span></button>
    </form>
  </section>
</main>
<script>
  // Fait défiler la conversation jusqu'au dernier message
  const zone = document.getElementById('messages');
  if (zone) { zone.scrollTop = zone.scrollHeight; }
</script>
<?php fin_page(); ?>
