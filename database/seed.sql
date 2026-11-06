-- =====================================================================
--  Yappli - Données de démonstration (développement uniquement)
--  Mot de passe des comptes de démo : demo1234
--  (hash bcrypt généré avec password_hash('demo1234', PASSWORD_DEFAULT))
-- =====================================================================
BEGIN;

INSERT INTO utilisateurs (pseudo, mail, mot_de_passe, prenom, nom, avatar, bio) VALUES
 ('alice', 'alice@exemple.fr', '$2y$10$0BXx9k0Mhmi1radr9MSyjOQXe1fUmk4OW.aM9/Tr4DHXayJkC5Cu2', 'Alice', 'Martin', 'avatar2', 'Fan de photo.'),
 ('bob',   'bob@exemple.fr',   '$2y$10$0BXx9k0Mhmi1radr9MSyjOQXe1fUmk4OW.aM9/Tr4DHXayJkC5Cu2', 'Bob',   'Durand', 'avatar5', ''),
 ('chloe', 'chloe@exemple.fr', '$2y$10$0BXx9k0Mhmi1radr9MSyjOQXe1fUmk4OW.aM9/Tr4DHXayJkC5Cu2', 'Chloé', 'Petit',  'avatar7', 'Étudiante à Rouen.');

INSERT INTO posts (auteur_id, texte) VALUES
 (1, 'Premier post sur la version sécurisée de Yappli !'),
 (2, 'Quelqu''un pour réviser les bases de données ce soir ?');

INSERT INTO likes (post_id, utilisateur_id) VALUES (1, 2), (1, 3), (2, 1);
INSERT INTO commentaires (post_id, auteur_id, contenu) VALUES (2, 3, 'Je suis partante !');
INSERT INTO amities (utilisateur_id, ami_id) VALUES (1, 2), (2, 1), (3, 1);
INSERT INTO messages (expediteur_id, destinataire_id, contenu) VALUES
 (1, 2, 'Salut Bob !'), (2, 1, 'Salut Alice, ça va ?');
INSERT INTO notifications (destinataire_id, auteur_id, type, post_id) VALUES
 (1, 2, 'like', 1), (1, 3, 'like', 1), (2, 3, 'commentaire', 2), (2, 1, 'message', NULL);

COMMIT;
