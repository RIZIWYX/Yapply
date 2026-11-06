<?php
require_once __DIR__ . '/../src/header.php';

debut_page('Yapply', 'landing.css');
?>
<main class="landing">
  <section class="landing-marque">
    <img src="<?php echo ASSETS; ?>/img/logo.png" alt="Logo Yapply">
    <h1>Yapply</h1>
    <p class="sous-titre">Le réseau social qui vous ressemble.</p>
  </section>

  <section class="carte landing-texte">
    <p class="bienvenue">Bienvenue sur notre réseau social : <strong>Yapply</strong>.</p>
    <p>
      Rejoignez une communauté dynamique où vous pouvez librement exprimer vos idées,
      partager vos passions, échanger avec vos proches et découvrir de nouvelles personnes.
    </p>
    <p>Connectez-vous dès maintenant et commencez à publier et interagir dans un espace pensé pour vous !</p>
    <div class="landing-actions">
      <a class="btn" href="connexion.php"><i class='bx bx-log-in'></i>Se connecter</a>
      <a class="btn btn-secondaire" href="inscription.php"><i class='bx bx-user-plus'></i>S'inscrire</a>
    </div>
    <p class="landing-note">Première visite ? Qu'attendez-vous pour vous inscrire ?</p>
  </section>
</main>
<?php pied_page(); ?>
<?php fin_page(); ?>
