<?php
require_once __DIR__ . '/../src/header.php';

if (utilisateur_courant() === null) {
    header('Location: connexion.php');
    exit;
}

// Avatars proposés (fichier assets/img/avatar/avatarN.jpg)
$avatars_proposes = [
    'avatar1' => 'Tralaléro tralala',
    'avatar2' => 'Bonbardilo crocodilo',
    'avatar3' => 'Tung tung tung sahur',
    'avatar4' => 'Lirili larila',
    'avatar5' => 'Brr brr patapim',
    'avatar6' => 'Espressona serona',
    'avatar7' => 'Bobrito bandito',
    'avatar8' => 'Balerina capuchina',
    'avatar9' => 'Capuchino assasino',
];
$avatar_actuel = avatar_courant();

debut_page('Modifier mon profil', 'modifier.css');
barre_navigation('profil');
?>
<main class="page modifier">
  <section class="carte">
    <h2>Modifier vos informations</h2>
    <p class="sous-titre">Laissez un champ vide pour conserver la valeur actuelle.</p>
    <form class="auth-form grille-2" action="modifier_traitement.php" method="post"><?= champ_csrf() ?>
      <label class="champ">
        <span class="visually-hidden">Nouveau nom</span>
        <input type="text" name="nom" placeholder="Nouveau nom" autocomplete="family-name">
        <i class='bx bxs-user'></i>
      </label>
      <label class="champ">
        <span class="visually-hidden">Nouveau prénom</span>
        <input type="text" name="prenom" placeholder="Nouveau prénom" autocomplete="given-name">
        <i class='bx bxs-user'></i>
      </label>
      <label class="champ">
        <span class="visually-hidden">Nouveau pseudo</span>
        <input type="text" name="username" placeholder="Nouveau pseudo" autocomplete="username">
        <i class='bx bxs-user-circle'></i>
      </label>
      <label class="champ">
        <span class="visually-hidden">Nouvelle adresse électronique</span>
        <input type="email" name="mail" placeholder="Nouvelle adresse électronique" autocomplete="email">
        <i class='bx bxs-envelope'></i>
      </label>
      <label class="champ">
        <span class="visually-hidden">Nouveau mot de passe</span>
        <input type="password" name="password" placeholder="Nouveau mot de passe" autocomplete="new-password">
        <i class='bx bxs-lock-alt'></i>
      </label>
      <label class="champ">
        <span class="visually-hidden">Confirmation du nouveau mot de passe</span>
        <input type="password" name="password_conf" placeholder="Confirmez le nouveau mot de passe" autocomplete="new-password">
        <i class='bx bxs-lock-alt'></i>
      </label>
      <label class="champ pleine-largeur">
        <span class="visually-hidden">Mot de passe actuel (obligatoire pour changer l'adresse mail ou le mot de passe)</span>
        <input type="password" name="password_actuel" placeholder="Mot de passe actuel (obligatoire pour changer le mail ou le mot de passe)" autocomplete="current-password">
        <i class='bx bxs-key'></i>
      </label>
      <button class="btn btn-bloc pleine-largeur" type="submit">Valider</button>
    </form>
  </section>

  <section class="carte">
    <h2>Personnaliser votre profil</h2>
    <form class="perso-form" action="modifier_personnalisation.php" method="post"><?= champ_csrf() ?>
      <label class="champ">
        <span class="visually-hidden">Biographie</span>
        <textarea name="biographie" placeholder="Modifier votre biographie"></textarea>
      </label>

      <fieldset class="choix-avatars">
        <legend>Choisissez un avatar</legend>
        <?php foreach ($avatars_proposes as $id => $nom): ?>
          <label class="choix-avatar">
            <input type="radio" name="avatar_number" value="<?php echo $id; ?>"<?php echo $id === $avatar_actuel ? ' checked' : ''; ?>>
            <img src="<?php echo url_avatar($id); ?>" alt="">
            <span><?php echo $nom; ?></span>
          </label>
        <?php endforeach; ?>
      </fieldset>

      <button class="btn btn-bloc" type="submit">Valider</button>
    </form>
  </section>

  <div class="modifier-actions">
    <a class="btn btn-secondaire" href="profil_utilisateur.php"><i class='bx bx-arrow-back'></i>Annuler</a>
  </div>
</main>
<?php fin_page(); ?>
