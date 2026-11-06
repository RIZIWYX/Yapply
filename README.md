# Yapply — version sécurisée

[![Démo](https://img.shields.io/badge/d%C3%A9mo-yapply--riziwyx.vercel.app-1f9d5c)](https://yapply-riziwyx.vercel.app/)
[![Security Headers A+](https://img.shields.io/badge/security%20headers-A%2B-1f9d5c)](https://securityheaders.com/?q=https%3A%2F%2Fyapply-riziwyx.vercel.app%2Fapp%2Findex.php&followRedirects=on)
![PHP](https://img.shields.io/badge/PHP-8-777BB4)
![PostgreSQL](https://img.shields.io/badge/PostgreSQL-Neon-4169E1)
[![Licence MIT](https://img.shields.io/badge/licence-MIT-green)](https://github.com/RIZIWYX/Yapply/blob/main/LICENSE)

Petit réseau social en **HTML / CSS / PHP**, réalisé en L1 puis **audité et sécurisé**.
Cette branche (`securite`) remplace le stockage en fichiers texte par **PostgreSQL**
et tourne en ligne sur **Vercel**.

🌐 **Site en ligne :** https://yapply-riziwyx.vercel.app/

![Démo : publication, commentaires et navigation dans le réseau social](doc/demo.gif)

Projet réalisé dans le cadre de l'UE **Outils de Développement Web** (2024-2025,
L1 IEEEA) par **Rayane Graïne** et **Accyl Benadjaoud**.

> ⚠️ **La branche `main` est volontairement vulnérable.** C'est la version d'origine,
> avec les 12 failles décrites dans [l'audit de sécurité](doc/AUDIT_SECURITE_YAPPLY.md).
> Elle sert uniquement de point de comparaison : ne la déployez pas et ne réutilisez
> pas son code.

| Branche | Contenu |
|---|---|
| `main` | Version d'origine (L1) : stockage en fichiers texte, failles laissées en l'état |
| `securite` | Version sécurisée : PostgreSQL, mots de passe hachés, CSRF, CSP… déployée sur Vercel |

---

## Sécurité

Mesures mises en place sur cette branche, en réponse à [l'audit](doc/AUDIT_SECURITE_YAPPLY.md) :

- **Mots de passe** hachés avec `password_hash()`, et vérification bloquée temporairement
  après 5 échecs en 15 minutes pour un même compte (qu'on saisisse le pseudo ou le mail)
  ou une même IP, à la connexion comme à la modification du compte.
- **Compte** : le mot de passe actuel est demandé pour changer l'adresse mail ou le
  mot de passe.
- **Sessions** PHP natives stockées en base, cookie `HttpOnly`, `SameSite=Lax` et
  `Secure` en HTTPS, mode strict (identifiant inconnu ou expiré refusé), expiration
  après 30 minutes d'inactivité vérifiée à chaque requête.
- **CSRF** : jeton vérifié automatiquement sur toutes les requêtes POST, y compris la
  déconnexion (qui n'est plus un simple lien).
- **Injections SQL** : uniquement des requêtes préparées (PDO).
- **XSS** : échappement HTML des données affichées, et CSP stricte (aucun script ni
  style inline autorisé).
- **Upload d'images** : type réel vérifié, image ré-encodée avec GD, nom aléatoire,
  stockage en base.
- **Base de données** : rôle applicatif sans droits sur la structure
  ([`database/roles.sql`](database/roles.sql)).
- **Exposition** : erreurs masquées en production, point d'entrée unique avec liste
  blanche des pages, dossiers `src/`, `database/` et `api/` inaccessibles depuis le web.
- **Entrées inattendues** : un paramètre envoyé sous forme de tableau (`champ[]=…`) est
  refusé d'emblée (erreur 400) au lieu de faire planter la page.
- **En-têtes HTTP** : `Content-Security-Policy`, `Strict-Transport-Security`,
  `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy` et `Permissions-Policy`.

<img src="doc/securityheaders.png" alt="Note A+ sur securityheaders.com avec les six en-têtes de sécurité" width="600">

---

## Structure du projet

```
Yapply/
├── api/index.php              # Point d'entrée unique sur Vercel (liste blanche, erreurs masquées)
├── app/                       # Pages et traitements
│   ├── index.php              # Page d'accueil publique
│   ├── inscription.php / connexion.php / modifier.php
│   ├── accueil.php            # Fil d'actualité, recherche, amis, notifications
│   ├── profil.php             # Profil d'un autre membre
│   ├── profil_utilisateur.php # Son propre profil
│   ├── personnalisation.php   # Choix d'avatar + bio
│   ├── discussion.php         # Liste des conversations
│   ├── message.php            # Conversation privée
│   ├── image.php              # Sert les images des posts depuis la base
│   └── *_traitement.php, post.php, like.php, commentaire.php, amis.php, deconnexion.php
├── src/                       # Code commun
│   ├── config.php             # Fonctions partagées, échappement, jeton CSRF
│   ├── db.php                 # Connexion PostgreSQL (PDO)
│   ├── session.php            # Sessions stockées en base
│   ├── images.php             # Contrôle et ré-encodage des images envoyées
│   └── header.php             # Gabarit commun : <head>, bandeau, pied de page
├── assets/                    # CSS, JavaScript, logo et avatars
├── database/
│   ├── schema.sql             # Tables, contraintes et index
│   ├── roles.sql              # Rôle applicatif à privilèges minimaux
│   ├── seed.sql               # Données de démonstration (développement uniquement)
│   └── migration_*.sql        # Mises à jour d'une base déjà créée (Neon)
├── doc/                       # Audit de sécurité, sujet du projet, captures
├── index.php                  # En local : redirige vers app/index.php
├── vercel.json                # Build, routes et en-têtes de sécurité
└── .env.example               # Modèle de configuration locale (DATABASE_URL)
```

---

## Lancer en local

### Prérequis
- PHP 8 avec les extensions `pdo_pgsql` et `gd`
- PostgreSQL 14 ou plus

Sous Ubuntu / WSL :

```bash
sudo apt install postgresql php-pgsql php-gd
```

### 1. Créer la base

Depuis la **racine du projet** :

```bash
sudo service postgresql start
sudo -u postgres createdb yappli
sudo -u postgres psql -d yappli < database/schema.sql
sudo -u postgres psql -d yappli < database/roles.sql
sudo -u postgres psql -d yappli < database/seed.sql
```

Les fichiers `migration_*.sql` ne sont pas nécessaires : leur contenu est déjà dans
`schema.sql`.

### 2. Donner un mot de passe au rôle de l'application

`roles.sql` crée le rôle `yappli_app` avec un mot de passe provisoire. Remplacez-le
(lettres et chiffres uniquement, pour ne pas casser l'URL de connexion) :

```bash
sudo -u postgres psql -d yappli -c "ALTER ROLE yappli_app PASSWORD 'votre_mot_de_passe'"
```

### 3. Configurer `.env`

```bash
cp .env.example .env
```

Puis remplacer la ligne `DATABASE_URL` par :

```
DATABASE_URL=postgresql://yappli_app:votre_mot_de_passe@localhost:5432/yappli
```

Le fichier `.env` est ignoré par Git : il ne doit jamais être commité.

### 4. Lancer le serveur

```bash
php -S localhost:8000
```

Puis ouvrir http://localhost:8000. Les comptes de démo sont `alice`, `bob` et `chloe` ;
leur mot de passe est indiqué en tête de [`database/seed.sql`](database/seed.sql).

> En local, les en-têtes de sécurité de `vercel.json` (CSP, HSTS…) ne sont pas envoyés :
> ils ne s'appliquent que sur Vercel.

---

## Déploiement

Le projet Vercel est relié au dépôt GitHub : chaque `git push` sur `securite` déclenche
un nouveau déploiement en production. La variable `DATABASE_URL` est fournie par
l'intégration Neon. Pour mettre à jour une base Neon déjà créée, exécuter les fichiers
`database/migration_*.sql` dans l'éditeur SQL de Neon.

---

## Licence

[MIT](https://github.com/RIZIWYX/Yapply/blob/main/LICENSE) © Rayane Graïne et Accyl Benadjaoud
