<?php
/**
 * Point d'entrée unique pour Vercel (plan Hobby : 12 fonctions max).
 * Toutes les URL /app/<page>.php arrivent ici et sont redirigées vers le bon
 * fichier de app/. Liste blanche stricte : impossible d'inclure autre chose.
 */

// Production : aucune erreur affichée au visiteur (elle révélerait serveur,
// identifiants, chemins...). Les erreurs partent dans les logs Vercel.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);
set_exception_handler(function (Throwable $e): void {
    error_log('[Yapply] ' . get_class($e) . ' : ' . $e->getMessage()
              . ' (' . $e->getFile() . ':' . $e->getLine() . ')');
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    echo '<!DOCTYPE html><html lang="fr"><meta charset="utf-8"><title>Erreur - Yapply</title>'
       . '<p>'
       . 'Une erreur est survenue. Réessayez dans un instant.</p></html>';
});

$page = $_GET['__page'] ?? 'index.php';
unset($_GET['__page']);

$dossierApp = realpath(__DIR__ . '/../app');
if (!is_string($page) || !preg_match('/^[a-z_]+\.php$/', $page)) {   // ?__page[]=x : refusé
    http_response_code(404);
    exit('Page introuvable');
}
$fichier = realpath($dossierApp . '/' . $page);
if ($fichier === false || dirname($fichier) !== $dossierApp) {
    http_response_code(404);
    exit('Page introuvable');
}

chdir($dossierApp);
$_SERVER['SCRIPT_NAME'] = '/app/' . $page;
$_SERVER['PHP_SELF']    = '/app/' . $page;
require $fichier;
