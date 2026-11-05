<?php
/**
 * Traitement sécurisé des images envoyées par les membres.
 *
 * Avant : le fichier était enregistré avec SON nom (ex. pirate.php), sans
 * aucun contrôle, dans un dossier public => exécution de code à distance.
 *
 * Maintenant, 5 contrôles :
 *  1. taille maximale ;
 *  2. vrai type lu dans le CONTENU du fichier (finfo), pas dans son nom ;
 *  3. ré-encodage complet avec GD : tout contenu caché (code, EXIF, GPS)
 *     disparaît, et l'image est réduite si elle est trop grande ;
 *  4. nom aléatoire généré par le serveur ;
 *  5. stockage en base, servi plus tard avec le bon Content-Type (image.php).
 */

const IMAGE_TAILLE_MAX = 2 * 1024 * 1024;   // 2 Mo
const IMAGE_COTE_MAX   = 1600;              // px, au-delà on réduit
const IMAGE_PIXELS_MAX = 40_000_000;        // anti « bombe » de décompression
const IMAGE_TYPES_OK   = ['image/jpeg', 'image/png', 'image/webp'];

/**
 * Valide et nettoie l'image envoyée dans $_FILES[$champ].
 * Renvoie null si aucun fichier n'a été envoyé, sinon
 * ['jpeg' => octets, 'largeur' => int, 'hauteur' => int].
 * Lève une RuntimeException avec un message lisible si l'image est refusée.
 */
function traiter_image_envoyee(string $champ): ?array
{
    $f = $_FILES[$champ] ?? null;
    if ($f === null || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (is_array($f['error'])) {                             // envoi multiple : refusé
        throw new RuntimeException('Une seule image par post.');
    }
    if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE) {
        throw new RuntimeException('Image trop lourde (2 Mo maximum).');
    }
    if ($f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
        throw new RuntimeException("L'envoi de l'image a échoué.");
    }

    // 1. Taille
    $taille = filesize($f['tmp_name']);
    if ($taille === false || $taille === 0 || $taille > IMAGE_TAILLE_MAX) {
        throw new RuntimeException('Image trop lourde (2 Mo maximum).');
    }

    // 2. Vrai type, lu dans les premiers octets du fichier
    $type = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    if (!in_array($type, IMAGE_TYPES_OK, true)) {
        throw new RuntimeException('Format refusé : seulement JPEG, PNG ou WebP.');
    }
    $infos = @getimagesize($f['tmp_name']);
    if ($infos === false || $infos[0] < 1 || $infos[1] < 1
        || $infos[0] * $infos[1] > IMAGE_PIXELS_MAX) {
        throw new RuntimeException('Image invalide ou trop grande.');
    }

    // 3. Décodage puis ré-encodage complet
    $source = @imagecreatefromstring((string) file_get_contents($f['tmp_name']));
    if ($source === false) {
        throw new RuntimeException('Image illisible.');
    }
    $source = corriger_orientation($source, $f['tmp_name'], $type);

    $l = imagesx($source);
    $h = imagesy($source);
    $ratio = min(1, IMAGE_COTE_MAX / max($l, $h));
    $nl = max(1, (int) round($l * $ratio));
    $nh = max(1, (int) round($h * $ratio));

    // Nouvelle image vierge, fond blanc (pour les PNG transparents), puis copie des pixels
    $propre = imagecreatetruecolor($nl, $nh);
    imagefill($propre, 0, 0, imagecolorallocate($propre, 255, 255, 255));
    imagecopyresampled($propre, $source, 0, 0, 0, 0, $nl, $nh, $l, $h);
    imagedestroy($source);

    ob_start();
    imagejpeg($propre, null, 85);           // JPEG neuf : aucune métadonnée d'origine
    $jpeg = (string) ob_get_clean();
    imagedestroy($propre);

    return ['jpeg' => $jpeg, 'largeur' => $nl, 'hauteur' => $nh];
}

/** Remet droite une photo de téléphone (l'orientation est dans l'EXIF, qu'on supprime). */
function corriger_orientation(GdImage $img, string $fichier, string $type): GdImage
{
    if ($type !== 'image/jpeg' || !function_exists('exif_read_data')) { return $img; }
    $exif = @exif_read_data($fichier);
    $angle = match ((int) ($exif['Orientation'] ?? 1)) { 3 => 180, 6 => -90, 8 => 90, default => 0 };
    return $angle ? (imagerotate($img, $angle, 0) ?: $img) : $img;
}

/** Enregistre l'image nettoyée et renvoie son nom aléatoire (clé). */
function enregistrer_image(array $image, int $auteurId): string
{
    $nom = bin2hex(random_bytes(16)) . '.jpg';   // 4. le nom d'origine est ignoré
    $stmt = db()->prepare(
        'INSERT INTO images (nom, donnees, taille, largeur, hauteur, auteur_id)
         VALUES (:nom, :donnees, :taille, :l, :h, :auteur)'
    );
    $stmt->bindValue(':nom', $nom);
    $stmt->bindValue(':donnees', $image['jpeg'], PDO::PARAM_LOB);   // binaire
    $stmt->bindValue(':taille', strlen($image['jpeg']), PDO::PARAM_INT);
    $stmt->bindValue(':l', $image['largeur'], PDO::PARAM_INT);
    $stmt->bindValue(':h', $image['hauteur'], PDO::PARAM_INT);
    $stmt->bindValue(':auteur', $auteurId, PDO::PARAM_INT);
    $stmt->execute();
    return $nom;
}
