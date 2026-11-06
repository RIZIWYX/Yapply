<?php
/**
 * En-tête / pied de page HTML communs.
 * Évite de recopier le <head> (CDN, CSS, favicon) et le bandeau dans chaque page.
 */
require_once __DIR__ . '/config.php';

/**
 * Ouvre la page : <head> commun + base.css + la feuille propre à la page.
 * $css est le nom d'un fichier de assets/css/pages/ (ex. « accueil.css »), ou '' pour aucun.
 */
function debut_page(string $titre, string $css = '', string $classe_body = ''): void {
    $titre_complet = ($titre === 'Yapply') ? 'Yapply' : $titre . ' · Yapply';
    echo '<!DOCTYPE html>' . "\n";
    echo '<html lang="fr">' . "\n";
    echo '<head>' . "\n";
    echo '  <meta charset="UTF-8">' . "\n";
    echo '  <meta name="viewport" content="width=device-width, initial-scale=1">' . "\n";
    echo '  <title>' . htmlspecialchars($titre_complet) . '</title>' . "\n";
    echo "  <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>\n";
    echo '  <link rel="stylesheet" href="' . ASSETS . '/css/base.css">' . "\n";
    if ($css !== '') {
        echo '  <link rel="stylesheet" href="' . ASSETS . '/css/pages/' . $css . '">' . "\n";
    }
    echo '  <link rel="icon" type="image/png" href="' . ASSETS . '/img/logo.png">' . "\n";
    echo '</head>' . "\n";
    echo '<body' . ($classe_body !== '' ? ' class="' . $classe_body . '"' : '') . '>' . "\n";
}

/**
 * Bandeau des pages connectées : logo, navigation et accès au profil.
 * $actif : « accueil », « discussion », « notifications » ou « profil ».
 */
function barre_navigation(string $actif = ''): void {
    $pseudo = utilisateur_courant() ?? '';
    $classe = fn(string $nom) => 'btn btn-icone' . ($nom === $actif ? ' actif' : '');
    ?>
<header class="bandeau">
  <a class="bandeau-marque" href="accueil.php">
    <img src="<?php echo ASSETS; ?>/img/logo.png" alt="">
    <span>Yapply</span>
  </a>
  <nav class="bandeau-nav" aria-label="Navigation principale">
    <a class="<?php echo $classe('accueil'); ?>" href="accueil.php" title="Accueil"><i class='bx bx-home-alt-2'></i><span class="visually-hidden">Accueil</span></a>
    <form action="accueil.php" method="post"><?= champ_csrf() ?>
      <button class="<?php echo $classe('notifications'); ?>" name="afficher" type="submit" title="Notifications"><i class='bx bx-bell'></i><span class="visually-hidden">Notifications</span></button>
    </form>
    <a class="<?php echo $classe('discussion'); ?>" href="discussion.php" title="Discussions"><i class='bx bx-message-rounded-dots'></i><span class="visually-hidden">Discussions</span></a>
    <form action="deconnexion.php" method="post"><?= champ_csrf() ?>
      <button class="<?php echo $classe('deconnexion'); ?>" type="submit" title="Déconnexion"><i class='bx bx-log-out'></i><span class="visually-hidden">Déconnexion</span></button>
    </form>
    <a class="bandeau-profil<?php echo $actif === 'profil' ? ' actif' : ''; ?>" href="profil_utilisateur.php" title="Mon profil">
      <img src="<?php echo url_avatar(avatar_courant()); ?>" alt="">
      <span><?php echo e($pseudo); ?></span>
    </a>
  </nav>
</header>
<?php
}

/** Petit pied de page commun (mentions du projet). */
function pied_page(): void {
    echo '<footer class="pied">Projet Outils de développement Web · Benadjaoud Accyl &amp; Rayane Graine · 2025</footer>' . "\n";
}

function fin_page(): void {
    echo "\n</body>\n</html>\n";
}

/**
 * Page de résultat simple (erreur ou confirmation) pour les scripts de traitement.
 * $messages : lignes à afficher ; $lien / $libelle : bouton de retour.
 */
function page_notice(string $titre, array $messages, string $lien, string $libelle, bool $erreur = true): void {
    debut_page($titre, '');
    echo '<main class="page-centree">' . "\n";
    echo '  <div class="carte notice">' . "\n";
    echo "    <i class='bx " . ($erreur ? 'bx-error-circle' : 'bx-check-circle') . " notice-icone'></i>\n";
    echo '    <h2>' . htmlspecialchars($titre) . '</h2>' . "\n";
    if ($messages) {
        echo $erreur ? '    <ul>' : '    <ul class="succes">';
        foreach ($messages as $m) { echo '<li>' . e($m) . '</li>'; }
        echo '</ul>' . "\n";
    }
    echo '    <a class="btn" href="' . $lien . '">' . $libelle . '</a>' . "\n";
    echo '  </div>' . "\n";
    echo '</main>' . "\n";
    fin_page();
}
