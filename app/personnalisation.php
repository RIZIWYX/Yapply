<?php
require_once __DIR__ . '/../src/header.php';

$user = utilisateur_courant();
if ($user === null) {
    header('Location: connexion.php');
    exit;
}

debut_page('Personnalisation', 'personnalisation.css');
?>
<main class="page personnalisation">
  <h1 class="bienvenue">Bienvenue, <?php echo e($user); ?> !</h1>
  <p class="sous-titre">Choisissez un avatar et présentez-vous en quelques mots.</p>

  <form class="perso-grille" action="personnalisation_traitement.php" method="post"><?= champ_csrf() ?>
    <fieldset class="carte choix-avatars">
      <legend><h2>Choisissez votre avatar</h2></legend>
      <div class="grille-avatars">
        <?php for ($i = 1; $i <= 9; $i++): ?>
          <label class="choix-avatar">
            <input type="radio" name="avatar" value="avatar<?php echo $i; ?>"<?php echo $i === 1 ? ' checked' : ''; ?>>
            <img src="<?php echo url_avatar('avatar' . $i); ?>" alt="Avatar <?php echo $i; ?>">
          </label>
        <?php endfor; ?>
      </div>
    </fieldset>

    <section class="carte bio-zone">
      <h2>Biographie</h2>
      <label class="champ">
        <span class="visually-hidden">Biographie</span>
        <textarea name="text" placeholder="Écrivez quelque chose..."></textarea>
      </label>
      <button class="btn btn-bloc" type="submit">Valider</button>
    </section>
  </form>
</main>
<?php fin_page(); ?>
