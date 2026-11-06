-- =====================================================================
--  Yappli - Rôle applicatif à privilèges minimaux
--  À exécuter en superutilisateur (postgres), APRÈS schema.sql.
--  Remplacer le mot de passe et le garder dans .env (jamais dans Git).
-- =====================================================================

DO $$
BEGIN
    IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'yappli_app') THEN
        CREATE ROLE yappli_app LOGIN PASSWORD 'A_CHANGER';
    END IF;
END $$;

REVOKE ALL ON SCHEMA public FROM PUBLIC;
GRANT USAGE ON SCHEMA public TO yappli_app;

-- Lecture / écriture des données uniquement : ni DROP, ni ALTER, ni TRUNCATE
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO yappli_app;
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO yappli_app;
