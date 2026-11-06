<?php
/**
 * Sessions PHP stockées dans PostgreSQL (table « sessions »).
 * Remplace data/session.txt, qui ne permettait qu'UN utilisateur connecté
 * pour tout le site, et fonctionne sur Vercel (pas de disque partagé).
 */
require_once __DIR__ . '/db.php';

/** Durée maximale d'inactivité d'une session, en secondes (30 min). */
const SESSION_DUREE_MAX = 1800;

/**
 * SessionUpdateTimestampHandlerInterface ajoute validateId() et updateTimestamp().
 * Sans validateId(), PHP acceptait n'importe quel identifiant de session, même
 * inventé ou expiré : l'option use_strict_mode n'avait donc aucun effet.
 */
final class SessionEnBase implements SessionHandlerInterface, SessionUpdateTimestampHandlerInterface
{
    public function open(string $chemin, string $nom): bool { return true; }
    public function close(): bool { return true; }

    /**
     * Avant : la session était renvoyée quel que soit son âge. L'expiration
     * dépendait uniquement du ramasse-miettes (gc), qui peut ne jamais tourner
     * (désactivé par défaut sur Ubuntu) : un cookie volé restait valable à vie.
     * Maintenant, une session inactive depuis plus de 30 min est ignorée.
     */
    public function read(string $id): string|false
    {
        $stmt = db()->prepare(
            'SELECT donnees FROM sessions
             WHERE id = :id AND maj_le > now() - make_interval(secs => :duree)'
        );
        $stmt->execute(['id' => $id, 'duree' => SESSION_DUREE_MAX]);
        return (string) ($stmt->fetchColumn() ?: '');
    }

    /**
     * Appelée par PHP (mode strict) quand le navigateur envoie un identifiant :
     * s'il est inconnu ou expiré, PHP en génère un nouveau au lieu de l'adopter.
     */
    public function validateId(string $id): bool
    {
        $stmt = db()->prepare(
            'SELECT 1 FROM sessions
             WHERE id = :id AND maj_le > now() - make_interval(secs => :duree)'
        );
        $stmt->execute(['id' => $id, 'duree' => SESSION_DUREE_MAX]);
        return $stmt->fetchColumn() !== false;
    }

    /** Session lue mais pas modifiée : on repousse seulement son expiration. */
    public function updateTimestamp(string $id, string $donnees): bool
    {
        return db()->prepare('UPDATE sessions SET maj_le = now() WHERE id = :id')
            ->execute(['id' => $id]);
    }

    public function write(string $id, string $donnees): bool
    {
        // INSERT ... ON CONFLICT = « crée ou met à jour » en une seule requête
        $stmt = db()->prepare(
            'INSERT INTO sessions (id, donnees, maj_le) VALUES (:id, :d, now())
             ON CONFLICT (id) DO UPDATE SET donnees = EXCLUDED.donnees, maj_le = now()'
        );
        return $stmt->execute(['id' => $id, 'd' => $donnees]);
    }

    public function destroy(string $id): bool
    {
        return db()->prepare('DELETE FROM sessions WHERE id = :id')->execute(['id' => $id]);
    }

    public function gc(int $dureeMax): int|false
    {
        $stmt = db()->prepare("DELETE FROM sessions WHERE maj_le < now() - make_interval(secs => :s)");
        $stmt->execute(['s' => $dureeMax]);
        return $stmt->rowCount();
    }
}

function demarrer_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) { return; }

    $https = ($_SERVER['HTTPS'] ?? '') === 'on'
          || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

    ini_set('session.use_strict_mode', '1');   // refuse les ID de session inventés
    ini_set('session.use_only_cookies', '1');  // jamais d'ID dans l'URL
    ini_set('session.gc_maxlifetime', (string) SESSION_DUREE_MAX); // 30 min d'inactivité
    // Ménage des sessions expirées dans 1 % des requêtes (désactivé par défaut sur Ubuntu)
    ini_set('session.gc_probability', '1');
    ini_set('session.gc_divisor', '100');
    session_name('yapply_sid');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $https,  // cookie envoyé seulement en HTTPS (en ligne)
        'httponly' => true,    // inaccessible au JavaScript (protège d'une XSS)
        'samesite' => 'Lax',   // première protection contre le CSRF
    ]);
    session_set_save_handler(new SessionEnBase(), true);
    session_start();
}
