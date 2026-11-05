-- =====================================================================
--  Migration 01 - Sessions PHP stockées en base
--  Sur Vercel, chaque requête peut tourner sur une instance différente :
--  les sessions fichiers (/tmp) se perdraient. On les garde donc ici.
--  À exécuter une fois dans l'éditeur SQL de Neon.
-- =====================================================================
CREATE TABLE IF NOT EXISTS sessions (
    id        VARCHAR(128) PRIMARY KEY,         -- identifiant de session PHP
    donnees   TEXT         NOT NULL DEFAULT '', -- contenu sérialisé de $_SESSION
    maj_le    TIMESTAMPTZ  NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_sessions_maj ON sessions (maj_le);
