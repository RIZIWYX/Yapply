<?php
require_once __DIR__ . '/../src/header.php';

debut_page('Inscription', 'auth.css');
?>
<main class="page-centree">
  <div class="carte auth auth-large">
    <a class="auth-logo" href="index.php"><img src="<?php echo ASSETS; ?>/img/logo.png" alt="Accueil Yapply"></a>
    <h2>Inscription</h2>
    <form class="auth-form grille-2" action="inscription_traitement.php" method="post"><?= champ_csrf() ?>
      <label class="champ">
        <span class="visually-hidden">Nom</span>
        <input type="text" name="nom" placeholder="Nom" autocomplete="family-name">
        <i class='bx bxs-user'></i>
      </label>
      <label class="champ">
        <span class="visually-hidden">Prénom</span>
        <input type="text" name="prenom" placeholder="Prénom" autocomplete="given-name">
        <i class='bx bxs-user'></i>
      </label>
      <label class="champ">
        <span class="visually-hidden">Pseudo</span>
        <input type="text" name="username" placeholder="Pseudo" autocomplete="username">
        <i class='bx bxs-user-circle'></i>
      </label>
      <label class="champ">
        <span class="visually-hidden">Adresse électronique</span>
        <input type="email" name="mail" placeholder="Adresse électronique" autocomplete="email">
        <i class='bx bxs-envelope'></i>
      </label>
      <label class="champ">
        <span class="visually-hidden">Mot de passe</span>
        <input type="password" name="password" placeholder="Mot de passe" autocomplete="new-password">
        <i class='bx bxs-lock-alt'></i>
      </label>
      <label class="champ">
        <span class="visually-hidden">Confirmation du mot de passe</span>
        <input type="password" name="password_conf" placeholder="Confirmez le mot de passe" autocomplete="new-password">
        <i class='bx bxs-lock-alt'></i>
      </label>
      <button class="btn btn-bloc pleine-largeur" type="submit">S'inscrire</button>
    </form>
    <p class="auth-lien"><a href="connexion.php">Déjà inscrit ? Connecte-toi</a></p>
  </div>
</main>
<?php fin_page(); ?>
