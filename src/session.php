<?php
/**
 * Sessions PHP stockées dans PostgreSQL (table « sessions »).
 * Remplace data/session.txt, qui ne permettait qu'UN utilisateur connecté
 * pour tout le site, et fonctionne sur Vercel (pas de disque partagé).
 */
require_once __DIR__ . '/db.php';

final class SessionEnBase implements SessionHandlerInterface
{
    public function open(string $chemin, string $nom): bool { return true; }
    public function close(): bool { return true; }

    public function read(string $id): string|false
    {
        $stmt = db()->prepare('SELECT donnees FROM sessions WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return (string) ($stmt->fetchColumn() ?: '');
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
    ini_set('session.gc_maxlifetime', '1800'); // 30 min d'inactivité
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
