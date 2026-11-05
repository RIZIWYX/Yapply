-- =====================================================================
--  Migration 02 - Images des posts stockées en base
--  Vercel n'a pas de disque : les images (déjà nettoyées et ré-encodées
--  en JPEG par src/images.php) sont gardées ici, en binaire (BYTEA).
--  posts.image contient le nom aléatoire qui sert de clé.
--  À exécuter une fois dans l'éditeur SQL de Neon.
-- =====================================================================
CREATE TABLE IF NOT EXISTS images (
    nom        VARCHAR(80)  PRIMARY KEY
               CHECK (nom ~ '^[a-f0-9]{32}\.jpg$'),   -- nom aléatoire imposé
    donnees    BYTEA        NOT NULL,
    taille     INTEGER      NOT NULL CHECK (taille BETWEEN 1 AND 3000000),
    largeur    INTEGER      NOT NULL,
    hauteur    INTEGER      NOT NULL,
    auteur_id  BIGINT       NOT NULL REFERENCES utilisateurs(id) ON DELETE CASCADE,
    cree_le    TIMESTAMPTZ  NOT NULL DEFAULT now()
);

-- Un post ne peut pointer que vers une image qui existe vraiment
ALTER TABLE posts DROP CONSTRAINT IF EXISTS posts_image_fkey;
ALTER TABLE posts ADD CONSTRAINT posts_image_fkey
    FOREIGN KEY (image) REFERENCES images(nom) ON DELETE SET NULL;
