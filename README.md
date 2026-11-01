# Yapply

[![CI](https://github.com/RIZIWYX/Yapply/actions/workflows/ci.yml/badge.svg)](https://github.com/RIZIWYX/Yapply/actions/workflows/ci.yml)
[![Démo](https://img.shields.io/badge/d%C3%A9mo-yapply--riziwyx.vercel.app-1f9d5c)](https://yapply-riziwyx.vercel.app/)
![PHP](https://img.shields.io/badge/PHP-8-777BB4)
[![Licence MIT](https://img.shields.io/badge/licence-MIT-green)](LICENSE)

Petit réseau social développé en **HTML / CSS / PHP**, sans base de données SQL :
les données sont persistées dans des fichiers texte plats.

![Démo : publication, commentaires et navigation dans le réseau social](doc/demo.gif)

Projet réalisé dans le cadre de l'UE **Outils de Développement Web** (2024-2025,
L1 IEEEA ) par **Rayane Graïne** et **Accyl Benadjaoud**.

> Les aspects **sécurité** sont
> volontairement laissés en l'état (voir « Limites connues ») : ce dépôt sert de
> **base** à un second projet d'audit et de sécurisation.

🌐 **Démo en ligne** (version sécurisée, branche [`securite`](https://github.com/RIZIWYX/Yapply/tree/securite)) : https://yapply-riziwyx.vercel.app/

| Branche | Contenu |
|---|---|
| `main` | Version d'origine (L1), stockage en fichiers texte, failles laissées en l'état |
| `securite` | Version en cours de sécurisation : mots de passe hachés, jetons CSRF, échappement HTML, déploiement Vercel et PostgreSQL (Neon) |

---

## Structure du projet

```
Yapply/
├── app/                       # Pages et traitements (tout ce qui est servi)
│   ├── index.html             # Page d'accueil publique
│   ├── inscription.html / connexion.html / modifier.html
│   ├── accueil.php            # Fil d'actualité + recherche, amis, notifications
│   ├── profil.php             # Profil d'un autre membre (recherche)
│   ├── profil_utilisateur.php # Son propre profil
│   ├── personnalisation.php   # Choix d'avatar + bio
│   ├── discussion.php         # Liste des conversations
│   ├── message.php            # Conversation privée
│   └── *_traitement.php, post.php, like.php, commentaire.php, amis.php, ...
│                              #   -> scripts de traitement des formulaires (POST)
├── src/                       # Code commun
│   ├── config.php             # Chemins des données + fonctions partagées
│   └── header.php             # En-tête HTML commun (<head>, fonctions debut_page/fin_page)
├── assets/
│   ├── css/                   # Feuilles de style
│   └── img/                   # logo.png + avatar/avatar1..9.jpg
├── data/                      # « Base de données » en fichiers (contenu NON versionné)
│   ├── comptes.txt            # pseudo;mdp;mdp;mail;prenom;nom
│   ├── personnalisation.txt   # pseudo;avatar;bio
│   ├── posts.txt              # pseudo;contenu|likes|commentaires
│   ├── session.txt            # utilisateur connecté (fichier partagé — limite connue)
│   ├── session_perso.txt
│   ├── images/                # images uploadées dans les posts
│   ├── amis/<pseudo>.txt
│   ├── notifications/<pseudo>.txt
│   └── messagerie/A_B.txt
├── doc/                       # Documentation (sujet du projet, futur write-up de sécurité)
├── .gitignore
└── README.md
```

---

## Installation et lancement

### Prérequis
- PHP 7.4 ou supérieur (testé avec PHP 8.3)
- Un serveur web, ou le serveur intégré de PHP

### Avec le serveur PHP intégré (le plus simple)
Depuis la **racine du projet** :

```bash
php -S localhost:8000
```

Puis ouvrir : `http://localhost:8000/app/index.html`

> Important : lancer le serveur depuis la racine `Yapply/` (et **non** depuis `app/`),
> pour que `assets/`, `src/` et `data/` restent accessibles.

### Avec XAMPP / WAMP / MAMP
Copier le dossier `Yapply/` dans `htdocs/` (XAMPP) ou `www/` (WAMP), démarrer Apache,
puis ouvrir `http://localhost/Yapply/app/index.html`.

### Permissions
Le dossier `data/` et ses sous-dossiers doivent être accessibles en écriture par le serveur.
Les données démarrent **vides** : créez un compte via la page d'inscription.

---

## Limites connues

Cette version contient volontairement les failles analysées dans
[l'audit de sécurité](doc/AUDIT_SECURITE_YAPPLI.md) : 12 failles, dont 5 critiques
(mots de passe en clair, session partagée dans un fichier, injections, XSS, CSRF).
Leur correction se fait sur la branche `securite`.

---

## Licence

[MIT](LICENSE) © Rayane Graïne et Accyl Benadjaoud
