<?php
/**
 * Connexion PostgreSQL (Neon) via PDO.
 * Lit DATABASE_URL depuis l'environnement (Vercel) ou le fichier .env (local).
 * Usage : $pdo = db();  puis requêtes préparées uniquement.
 */

function charger_env(string $fichier): void {
    if (!is_readable($fichier)) { return; }
    foreach (file($fichier, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $ligne) {
        $ligne = trim($ligne);
        if ($ligne === '' || $ligne[0] === '#' || !str_contains($ligne, '=')) { continue; }
        [$cle, $valeur] = explode('=', $ligne, 2);
        $cle = trim($cle);
        $valeur = trim($valeur, " \t\"'");
        if (getenv($cle) === false) { putenv("$cle=$valeur"); }
    }
}

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) { return $pdo; }

    charger_env(dirname(__DIR__) . '/.env');
    $url = getenv('DATABASE_URL');
    if ($url === false || $url === '') {
        throw new RuntimeException('DATABASE_URL non définie.');
    }

    $p = parse_url($url);
    $hote = $p['host'] ?? 'localhost';
    $estNeon = str_ends_with($hote, '.neon.tech');

    $dsn = sprintf(
        'pgsql:host=%s;port=%d;dbname=%s;sslmode=%s',
        $hote, $p['port'] ?? 5432, ltrim($p['path'] ?? '', '/'),
        $estNeon ? 'require' : 'prefer'
    );

    $motDePasse = urldecode($p['pass'] ?? '');
    if ($estNeon) {
        // Neon : identifiant du endpoint passé dans le mot de passe
        // (méthode officielle Neon pour les libpq sans SNI).
        $endpoint = str_replace('-pooler', '', explode('.', $hote)[0]);
        $motDePasse = 'endpoint=' . $endpoint . ';' . $motDePasse;
    }

    $pdo = new PDO($dsn, urldecode($p['user'] ?? ''), $motDePasse, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    return $pdo;
}
