<?php
require_once __DIR__ . '/../src/header.php';

debut_page('Connexion', 'auth.css');
?>
<main class="page-centree">
  <div class="carte auth">
    <a class="auth-logo" href="index.php"><img src="<?php echo ASSETS; ?>/img/logo.png" alt="Accueil Yapply"></a>
    <h2>Connexion</h2>
    <form class="auth-form" action="connexion_traitement.php" method="post"><?= champ_csrf() ?>
      <label class="champ">
        <span class="visually-hidden">Pseudo ou adresse mail</span>
        <input type="text" name="userinfo" placeholder="Pseudo ou adresse mail" autocomplete="username" required>
        <i class='bx bxs-user'></i>
      </label>
      <label class="champ">
        <span class="visually-hidden">Mot de passe</span>
        <input type="password" name="password" placeholder="Mot de passe" autocomplete="current-password" required>
        <i class='bx bxs-lock-alt'></i>
      </label>
      <button class="btn btn-bloc" type="submit">Se connecter</button>
    </form>
    <p class="auth-lien"><a href="inscription.php">Première fois ? Inscris-toi</a></p>
  </div>
</main>
<?php fin_page(); ?>
