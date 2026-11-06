-- =====================================================================
--  Yapply - Schéma PostgreSQL (branche securite)
--  Remplace le stockage en fichiers texte de data/ :
--    comptes.txt + personnalisation.txt  -> utilisateurs
--    posts.txt (texte|image|likes|coms)  -> posts, likes, commentaires
--    data/amis/<pseudo>.txt              -> amities
--    data/messagerie/<a>_<b>.txt         -> messages
--    data/notifications/<pseudo>.txt     -> notifications
--    session.txt / session_perso.txt     -> supprimés : sessions PHP natives
--  PostgreSQL 14+   |   psql -d yapply -f database/schema.sql
-- =====================================================================

BEGIN;

CREATE EXTENSION IF NOT EXISTS citext;  -- pseudo et mail insensibles à la casse

DROP TABLE IF EXISTS tentatives_connexion, notifications, messages,
                     amities, commentaires, likes, posts, utilisateurs CASCADE;
DROP TYPE IF EXISTS type_notification;

-- ---------------------------------------------------------------------
-- Utilisateurs (comptes + personnalisation fusionnés)
-- Le mot de passe n'est plus stocké en clair (ni en double) :
-- uniquement le hash produit par password_hash() (Argon2id / bcrypt).
-- ---------------------------------------------------------------------
CREATE TABLE utilisateurs (
    id             BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    pseudo         CITEXT       NOT NULL UNIQUE
                   CHECK (char_length(pseudo) BETWEEN 2 AND 30
                          AND pseudo ~ '^[A-Za-z0-9_.-]+$'),
    mail           CITEXT       NOT NULL UNIQUE
                   CHECK (char_length(mail) <= 254 AND mail ~ '^[^@\s]+@[^@\s]+\.[^@\s]+$'),
    mot_de_passe   TEXT         NOT NULL,              -- hash password_hash()
    prenom         VARCHAR(50)  NOT NULL CHECK (char_length(prenom) >= 2),
    nom            VARCHAR(50)  NOT NULL CHECK (char_length(nom) >= 2),
    avatar         VARCHAR(20)  NOT NULL DEFAULT 'avatar1'
                   CHECK (avatar ~ '^avatar[1-9]$'),   -- liste blanche des avatars
    bio            VARCHAR(500) NOT NULL DEFAULT '',
    cree_le        TIMESTAMPTZ  NOT NULL DEFAULT now(),
    modifie_le     TIMESTAMPTZ  NOT NULL DEFAULT now()
);

-- ---------------------------------------------------------------------
-- Posts : texte et/ou image. Les likes et commentaires ne sont plus
-- concaténés dans la ligne, et l'id remplace l'index de ligne.
-- image = nom de fichier aléatoire généré côté serveur (jamais le nom client).
-- ---------------------------------------------------------------------
CREATE TABLE posts (
    id             BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    auteur_id      BIGINT       NOT NULL REFERENCES utilisateurs(id) ON DELETE CASCADE,
    texte          VARCHAR(2000),
    image          VARCHAR(80)
                   CHECK (image ~ '^[a-f0-9]{32}\.(jpg|png|webp)$'),
    cree_le        TIMESTAMPTZ  NOT NULL DEFAULT now(),
    CHECK (texte IS NOT NULL AND btrim(texte) <> '' OR image IS NOT NULL)
);
CREATE INDEX idx_posts_auteur ON posts (auteur_id, cree_le DESC);
CREATE INDEX idx_posts_date   ON posts (cree_le DESC);

-- ---------------------------------------------------------------------
-- Likes : un seul like par utilisateur et par post
-- (avant : simple compteur incrémentable à l'infini).
-- ---------------------------------------------------------------------
CREATE TABLE likes (
    post_id        BIGINT       NOT NULL REFERENCES posts(id)        ON DELETE CASCADE,
    utilisateur_id BIGINT       NOT NULL REFERENCES utilisateurs(id) ON DELETE CASCADE,
    cree_le        TIMESTAMPTZ  NOT NULL DEFAULT now(),
    PRIMARY KEY (post_id, utilisateur_id)
);

-- ---------------------------------------------------------------------
-- Commentaires
-- ---------------------------------------------------------------------
CREATE TABLE commentaires (
    id             BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    post_id        BIGINT       NOT NULL REFERENCES posts(id)        ON DELETE CASCADE,
    auteur_id      BIGINT       NOT NULL REFERENCES utilisateurs(id) ON DELETE CASCADE,
    contenu        VARCHAR(1000) NOT NULL CHECK (btrim(contenu) <> ''),
    cree_le        TIMESTAMPTZ  NOT NULL DEFAULT now()
);
CREATE INDEX idx_commentaires_post ON commentaires (post_id, cree_le);

-- ---------------------------------------------------------------------
-- Amitiés : relation orientée, comme dans l'appli
-- (« X ajoute Y à sa liste d'amis »).
-- ---------------------------------------------------------------------
CREATE TABLE amities (
    utilisateur_id BIGINT       NOT NULL REFERENCES utilisateurs(id) ON DELETE CASCADE,
    ami_id         BIGINT       NOT NULL REFERENCES utilisateurs(id) ON DELETE CASCADE,
    cree_le        TIMESTAMPTZ  NOT NULL DEFAULT now(),
    PRIMARY KEY (utilisateur_id, ami_id),
    CHECK (utilisateur_id <> ami_id)
);
CREATE INDEX idx_amities_ami ON amities (ami_id);

-- ---------------------------------------------------------------------
-- Messages privés (remplace un fichier par paire de pseudos).
-- Une conversation = l'ensemble des messages entre deux utilisateurs.
-- ---------------------------------------------------------------------
CREATE TABLE messages (
    id               BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    expediteur_id    BIGINT       NOT NULL REFERENCES utilisateurs(id) ON DELETE CASCADE,
    destinataire_id  BIGINT       NOT NULL REFERENCES utilisateurs(id) ON DELETE CASCADE,
    contenu          VARCHAR(2000) NOT NULL CHECK (btrim(contenu) <> ''),
    envoye_le        TIMESTAMPTZ  NOT NULL DEFAULT now(),
    lu_le            TIMESTAMPTZ,
    CHECK (expediteur_id <> destinataire_id)
);
-- Index sur la paire non orientée : retrouve une conversation quel que soit le sens
CREATE INDEX idx_messages_conversation
    ON messages (LEAST(expediteur_id, destinataire_id),
                 GREATEST(expediteur_id, destinataire_id), envoye_le);

-- ---------------------------------------------------------------------
-- Notifications : données structurées au lieu de phrases toutes faites.
-- Le texte est construit à l'affichage (et échappé).
-- ---------------------------------------------------------------------
CREATE TYPE type_notification AS ENUM ('post', 'like', 'commentaire', 'ami', 'message');

CREATE TABLE notifications (
    id               BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    destinataire_id  BIGINT       NOT NULL REFERENCES utilisateurs(id) ON DELETE CASCADE,
    auteur_id        BIGINT       NOT NULL REFERENCES utilisateurs(id) ON DELETE CASCADE,
    type             type_notification NOT NULL,
    post_id          BIGINT       REFERENCES posts(id) ON DELETE CASCADE,
    cree_le          TIMESTAMPTZ  NOT NULL DEFAULT now(),
    lu               BOOLEAN      NOT NULL DEFAULT false
);
CREATE INDEX idx_notifications_dest ON notifications (destinataire_id, lu, cree_le DESC);

-- ---------------------------------------------------------------------
-- Anti brute force (Phase 3 de la roadmap)
-- ---------------------------------------------------------------------
CREATE TABLE tentatives_connexion (
    id             BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    identifiant    CITEXT       NOT NULL,           -- « compte:<id> » si le compte existe, sinon le texte saisi
    ip             INET         NOT NULL,
    reussie        BOOLEAN      NOT NULL,
    tente_le       TIMESTAMPTZ  NOT NULL DEFAULT now()
);
CREATE INDEX idx_tentatives ON tentatives_connexion (identifiant, tente_le DESC);
CREATE INDEX idx_tentatives_ip ON tentatives_connexion (ip, tente_le DESC);

-- ---------------------------------------------------------------------
-- Mise à jour automatique de modifie_le
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION maj_modifie_le() RETURNS trigger AS $$
BEGIN
    NEW.modifie_le := now();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_utilisateurs_modifie
    BEFORE UPDATE ON utilisateurs
    FOR EACH ROW EXECUTE FUNCTION maj_modifie_le();

-- ---------------------------------------------------------------------
-- Vue pratique pour le fil d'actualité
-- ---------------------------------------------------------------------
CREATE OR REPLACE VIEW fil_actualite AS
SELECT p.id, p.texte, p.image, p.cree_le,
       u.id AS auteur_id, u.pseudo, u.avatar,
       (SELECT count(*) FROM likes l        WHERE l.post_id = p.id) AS nb_likes,
       (SELECT count(*) FROM commentaires c WHERE c.post_id = p.id) AS nb_commentaires
FROM posts p
JOIN utilisateurs u ON u.id = p.auteur_id;

COMMIT;

-- ---------------------------------------------------------------------
-- Sessions PHP stockées en base (voir migration_01_sessions.sql)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS sessions (
    id        VARCHAR(128) PRIMARY KEY,
    donnees   TEXT         NOT NULL DEFAULT '',
    maj_le    TIMESTAMPTZ  NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_sessions_maj ON sessions (maj_le);

-- ---------------------------------------------------------------------
-- Images des posts stockées en base (voir migration_02_images.sql)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS images (
    nom        VARCHAR(80)  PRIMARY KEY
               CHECK (nom ~ '^[a-f0-9]{32}\.jpg$'),
    donnees    BYTEA        NOT NULL,
    taille     INTEGER      NOT NULL CHECK (taille BETWEEN 1 AND 3000000),
    largeur    INTEGER      NOT NULL,
    hauteur    INTEGER      NOT NULL,
    auteur_id  BIGINT       NOT NULL REFERENCES utilisateurs(id) ON DELETE CASCADE,
    cree_le    TIMESTAMPTZ  NOT NULL DEFAULT now()
);
ALTER TABLE posts DROP CONSTRAINT IF EXISTS posts_image_fkey;
ALTER TABLE posts ADD CONSTRAINT posts_image_fkey
    FOREIGN KEY (image) REFERENCES images(nom) ON DELETE SET NULL;
