# Déploiement de SkillSwap Campus sur InfinityFree

Ce guide décrit l’installation du paquet `skillswap_final/` sur un compte
InfinityFree gratuit. Le paquet est déjà prêt : il ne nécessite ni SSH, ni
Composer, ni cron, ni étape de compilation. Il fonctionne avec PHP 8.4 et
SQLite via PDO.

> Le dossier `skillswap_final/` est l’archive du site à déployer. Le présent
> guide et l’APK Android sont fournis dans le paquet pour commodité ; ils ne
> sont pas nécessaires au fonctionnement du site. Les sources Android sont
> dans le dossier `skillswap-android/` du projet, hors du paquet web.

---

## 1. Ce qui est fourni

Le paquet contient :

```text
htdocs/
├── .htaccess
├── index.php
├── config.php
├── manifest.webmanifest
├── sw.js
├── offline.html
├── css/
├── js/
├── icons/
├── src/
├── vues/
├── database/
│   ├── .htaccess
│   └── schema.sql
├── data/
│   ├── .htaccess
│   └── logs/
│       └── .htaccess
├── apk/
│   ├── SkillSwap-Campus.apk
│   └── LISEZ-MOI.txt
└── GUIDE_DEPLOIEMENT_INFINITYFREE.md
```

Le paquet ne contient volontairement :

- aucune base de démonstration (`data/skillswap.db` est absent) ;
- aucun identifiant administrateur ;
- aucun fichier `data/PREMIER_ACCES.txt` ;
- aucun dossier `tests/` ni `scripts/` ;
- aucun secret, aucune clé privée et aucun mot de passe.

La base SQLite et le premier compte administrateur sont créés automatiquement
à la première requête. Les migrations sont idempotentes et suivies par
`PRAGMA user_version` : une base existante n’est jamais supprimée.

---

## 2. Créer le compte et le domaine

1. Créez un compte sur [InfinityFree](https://www.infinityfree.com/).
2. Créez un compte d’hébergement gratuit et choisissez un sous-domaine
   (par exemple `skillswap-campus.infinityfreeapp.com`) ou un domaine
   personnel.
3. Attendez que le domaine soit actif dans le panneau de contrôle.
4. Dans le panneau de contrôle, vérifiez que le compte utilise bien **PHP 8.4**
   (InfinityFree a migré ses serveurs gratuits vers PHP 8.4.27 en 2026).
   L’application fonctionne aussi avec PHP 8.3, mais PHP 8.4 est la version
   recommandée.
5. Vérifiez que l’extension **PDO SQLite** est disponible. Elle l’est sur les
   comptes InfinityFree ; si un message « could not find driver » apparaît,
   ouvrez un ticket au support avant de poursuivre.
6. Activez **Free SSL** pour le domaine. Le certificat Let’s Encrypt peut mettre
   quelques minutes à quelques heures à être émis. Activez aussi l’option
   **Force HTTPS** si elle est proposée.

### Pourquoi HTTPS est obligatoire

- Le service worker et l’installation PWA n’exigent une origine sécurisée
  (HTTPS), sauf sur `localhost`.
- Les cookies de session et les formulaires doivent circuler en HTTPS.
- L’APK Android refuse volontairement les adresses `http://`.

Sans HTTPS, le site peut encore s’afficher, mais l’installation PWA et le mode
hors-ligne ne fonctionneront pas correctement.

---

## 3. Transférer les fichiers

### Méthode A — FileZilla (recommandée)

1. Dans le panneau InfinityFree, ouvrez les informations FTP : hôte, nom
   d’utilisateur, mot de passe et port.
2. Connectez FileZilla au compte.
3. **Affichez les fichiers cachés** : menu `Serveur` →
   `Forcer l’affichage des fichiers cachés`. Cette étape est indispensable pour
   envoyer les fichiers `.htaccess`, qui protègent `data/`, `src/`, `vues/`,
   `database/` et les fichiers sensibles.
4. Ouvrez le dossier **`htdocs`** du serveur (et non `www`, `public_html` ou un
   sous-dossier).
5. Sélectionnez **le contenu** de `skillswap_final/` et envoyez-le dans
   `htdocs/`. Vous devez obtenir `htdocs/index.php`, `htdocs/.htaccess`, etc.
   N’envoyez pas le dossier `skillswap_final` lui-même, sinon le site serait
   accessible sous `/skillswap_final/`.
6. Attendez la fin du transfert et vérifiez que `data/` ne contient que
   `.htaccess` et `logs/.htaccess`.

### Méthode B — Gestionnaire de fichiers web

1. Dans le panneau de contrôle, ouvrez le gestionnaire de fichiers.
2. Ouvrez `htdocs`.
3. Téléversez le contenu du dossier `skillswap_final/` dans `htdocs/`.
4. Vérifiez que les fichiers commençant par un point (`.htaccess`) ont bien été
   transférés. Si le gestionnaire les masque, activez l’affichage des fichiers
   cachés ou utilisez FileZilla pour ces fichiers.

### Vérification de l’arborescence

Après transfert, l’URL `https://votre-domaine/` doit charger
`htdocs/index.php`. Si vous obtenez une page de liste de fichiers ou une erreur
404, le contenu n’a pas été placé au bon endroit.

---

## 4. Permissions

Le serveur PHP doit pouvoir écrire dans le dossier de données pour créer la
base, les journaux et le fichier de premier accès.

| Élément | Permission conseillée |
|---|---|
| `data/` | `755`, puis `775` si PHP ne peut pas écrire |
| `data/logs/` | `755`, puis `775` si PHP ne peut pas écrire |
| Fichiers `.htaccess` | `644` |
| `src/`, `vues/`, `database/` | `755` (lecture seule pour PHP) |

Si l’installation affiche « Impossible d’écrire data/PREMIER_ACCES.txt » ou
« dossier non inscriptible » :

1. passez `data/` et `data/logs/` en `775` ;
2. si le problème persiste, utilisez temporairement `777` ;
3. après l’installation, remettez si possible `755` ou `775`.

Ne rendez jamais `src/`, `vues/` ou `database/` inscriptibles par tout le
monde. Le fichier `.htaccess` de `data/` interdit l’accès web à la base même
si son dossier est lisible par PHP.

---

## 5. Première installation automatique

1. Ouvrez `https://votre-domaine/` dans un navigateur.
2. La première requête :
   - crée `data/skillswap.db` ;
   - applique le schéma et les migrations ;
   - crée le compte administrateur initial ;
   - écrit les identifiants dans `data/PREMIER_ACCES.txt` ;
   - affiche la page d’accueil publique.
3. Téléchargez ou ouvrez `data/PREMIER_ACCES.txt` depuis FileZilla ou le
   gestionnaire de fichiers. Il contient :
   - l’adresse e-mail : `admin@skillswap.ma` ;
   - un mot de passe aléatoire.
4. Connectez-vous avec ces identifiants.
5. Allez dans **Administration → Mot de passe** et remplacez immédiatement le
   mot de passe aléatoire par un mot de passe fort et unique.
6. **Supprimez `data/PREMIER_ACCES.txt`** après avoir changé le mot de passe.
   Ne le renvoyez jamais sur le serveur et ne le partagez pas.
7. Vérifiez que `data/skillswap.db` existe et que `data/PREMIER_ACCES.txt`
   n’existe plus.

> Le fichier `PREMIER_ACCES.txt` est protégé par `data/.htaccess`, mais il ne
> doit pas rester sur le serveur : c’est une bonne pratique de le supprimer.

### Si l’installation échoue

- **Erreur 500** : permissions de `data/`, version PHP ou extension PDO SQLite.
  Consultez `data/logs/` et le journal d’erreurs proposé par InfinityFree.
- **Le fichier `PREMIER_ACCES.txt` n’apparaît pas** : le dossier `data/` n’est
  probablement pas inscriptible, ou un administrateur existe déjà. Ne supprimez
  pas la base pour « réinstaller » sans sauvegarde.
- **La page d’accueil fonctionne mais la connexion échoue** : vérifiez que le
  fichier `config.php` pointe bien vers `data/skillswap.db` et que le dossier
  `data/logs/` est inscriptible.
- **Erreur de rewrite / 404 sur les liens** : vérifiez que `.htaccess` a été
  envoyé, que `mod_rewrite` est actif et que le site est bien dans `htdocs`.
  InfinityFree annonce le support complet de `.htaccess`.

### Première configuration métier

Une base fraîche ne contient aucune catégorie ni compétence. Avant d’ouvrir le
site aux étudiants :

1. Connectez-vous en administrateur.
2. Ouvrez **Administration → Catégories**.
3. Dans **Nouvelle catégorie**, créez au moins une catégorie active, par exemple
   « Informatique ».
4. Dans chaque catégorie, ajoutez les compétences avec le champ
   **Nouvelle compétence** (par exemple « Python », « HTML/CSS », « Anglais »).
5. Vérifiez qu’au moins une catégorie et une compétence sont actives : sans
   cela, un étudiant ne peut pas publier d’offre.
6. Ouvrez **Administration → Comptes** pour vérifier les comptes et
   **Administration → Modération** pour les offres.

---

## 6. Page d’accueil publique

La page d’accueil `/` est une page publique permanente, sans écran
intermédiaire ni suivi de première visite :

- elle présente la banque du temps et son fonctionnement ;
- elle propose **Créer un compte** et **Se connecter** ;
- le bouton **Passer à la connexion** mène à `/connexion` ;
- sous le hero 3D, elle affiche les étapes **Proposez / Échangez /
  Progressez**, la mention des **2 h de bienvenue**, les statistiques
  dynamiques et les offres récentes ;
- les utilisateurs déjà connectés sont redirigés vers `/tableau-de-bord` ou
  `/admin` selon leur rôle.

Aucune clé locale de première visite n’est utilisée. La page est rendue côté
serveur et reste accessible si JavaScript est désactivé.

---

## 7. Installation PWA

La PWA est installable une fois le site servi en HTTPS.

### Vérifications préalables

Dans un navigateur, vérifiez les URL suivantes (adaptez le domaine) :

```text
https://votre-domaine/manifest.webmanifest
https://votre-domaine/sw.js
https://votre-domaine/offline.html
https://votre-domaine/icons/icon-192.png
https://votre-domaine/icons/icon-512.png
```

Résultats attendus :

- `manifest.webmanifest` : HTTP 200, type `application/manifest+json` ;
- `sw.js` : HTTP 200, type JavaScript, sans cache HTML ;
- `offline.html` : HTTP 200 ;
- les icônes : HTTP 200, type `image/png`.

Si le manifeste est servi en `text/plain` ou `text/html`, vérifiez que
`.htaccess` est bien présent et que `mod_mime` est actif. InfinityFree prend en
charge `.htaccess` et les types MIME.

### Installer

- **Android / Chrome** : menu `⋮` → `Installer l’application` ou
  `Ajouter à l’écran d’accueil`.
- **Windows / Chrome ou Edge** : icône d’installation dans la barre d’adresse,
  ou menu → `Installer SkillSwap Campus`.
- **iPhone / Safari** : bouton Partager → `Sur l’écran d’accueil`.

L’application installée démarre sur l’URL du site et utilise le thème clair ou
sombre du système.

### Ce que met en cache le service worker

Le service worker met uniquement en cache :

- la page `offline.html` ;
- le manifeste ;
- le CSS et le JavaScript publics ;
- les icônes.

Les pages PHP authentifiées et les réponses personnelles ne sont **jamais**
placées dans le cache. Une navigation hors-ligne renvoie la page de secours
`offline.html`, jamais une page de compte. Il ne s’agit donc pas d’un mode
hors-ligne complet : les offres, réservations et messages nécessitent une
connexion réseau.

### Mettre à jour la PWA

Lorsque vous modifiez le CSS, le JavaScript, les icônes ou la page de secours :

1. modifiez les fichiers sur le serveur ;
2. incrémentez `VERSION_CACHE` dans `sw.js` (par exemple
   `skillswap-public-v2`) pour forcer le renouvellement du cache ;
3. rechargez le site et vérifiez dans les outils de développement →
   Application → Service Workers que le nouveau worker est actif.

Le code du service worker ne doit jamais mettre en cache une page authentifiée.

---

## 8. APK Android

Le fichier `apk/SkillSwap-Campus.apk` est une enveloppe WebView directe. Il ne
contient pas une copie du site : il charge l’URL HTTPS de votre campus.

Au premier lancement :

1. saisissez l’adresse du site, par exemple
   `https://votre-campus.infinityfreeapp.com/` ;
2. l’adresse est mémorisée sur le téléphone ;
3. l’application ouvre ensuite directement le campus.

Pour changer l’adresse : appuyez longuement sur la page du campus, puis
choisissez « Changer l’adresse du site ». L’APK refuse les URL `http://` et
affiche un écran « Campus injoignable » avec un bouton Réessayer en cas de
problème réseau.

L’APK fourni est signé avec une clé de test pour l’installation directe. Pour
une publication sur le Play Store ou pour contrôler les mises à jour, signez
une version avec votre propre clé de production. Les sources et la procédure
de construction se trouvent dans le dossier `skillswap-android/` du projet.

Le fichier `apk/SkillSwap-Campus.apk` est inclus dans le paquet et le bouton
**Télécharger l’application** de la page d’accueil pointe vers ce fichier.
L’extension `.apk` est autorisée par le `.htaccess` et servie avec le type
`application/vnd.android.package-archive`.

---

## 9. Sauvegardes et mises à jour

### Sauvegarder la base

La base SQLite est un simple fichier :

```text
data/skillswap.db
```

Pour une sauvegarde fiable :

1. téléchargez `data/skillswap.db` par FTP ;
2. téléchargez aussi, s’ils existent, `data/skillswap.db-wal` et
   `data/skillswap.db-shm` ;
3. conservez une copie datée hors du serveur.

Ne supprimez jamais la base pour appliquer une mise à jour. Le code exécute
les migrations manquantes au démarrage et conserve les données existantes.

### Mettre à jour l’application

1. Sauvegardez `data/` (surtout `skillswap.db`).
2. Remplacez uniquement les fichiers de code : `index.php`, `config.php`,
   `src/`, `vues/`, `css/`, `js/`, `icons/`, `manifest.webmanifest`, `sw.js`,
   `offline.html`, `database/schema.sql` et les `.htaccess`.
3. **Ne remplacez pas et ne supprimez pas `data/`.**
4. Si le CSS/JS public change, incrémentez `VERSION_CACHE` dans `sw.js`.
5. Rechargez le site et vérifiez la connexion, le catalogue et une réservation.

### Restaurer une sauvegarde

1. Mettez le site en maintenance ou prévenez les utilisateurs.
2. Renommez la base actuelle en `data/skillswap.db.avant-restauration`.
3. Renvoyez la sauvegarde sous le nom `data/skillswap.db`.
4. Vérifiez les permissions de `data/`.
5. Rechargez le site et contrôlez les comptes, les soldes et les réservations.

---

## 10. Liste de vérification finale

### Fichiers

- [ ] `htdocs/index.php` existe.
- [ ] `htdocs/.htaccess` a été envoyé (fichier caché).
- [ ] `htdocs/data/.htaccess` et `htdocs/data/logs/.htaccess` existent.
- [ ] `htdocs/data/skillswap.db` est absent avant la première visite.
- [ ] `htdocs/data/PREMIER_ACCES.txt` est absent du paquet.
- [ ] Aucun fichier `tests/`, `scripts/` ou base de démonstration n’a été
      envoyé.

### PHP et permissions

- [ ] PHP 8.4 (ou 8.3) actif.
- [ ] PDO SQLite disponible.
- [ ] `data/` et `data/logs/` inscriptibles par PHP.
- [ ] `src/`, `vues/` et `database/` ne sont pas inscriptibles par tous.

### Sécurité et HTTPS

- [ ] Le certificat SSL est actif.
- [ ] `https://votre-domaine/` affiche le cadenas.
- [ ] `http://votre-domaine/` redirige vers HTTPS (ou HTTPS est forcé).
- [ ] `.htaccess` protège `src/`, `vues/`, `database/` et `data/`.
- [ ] `data/PREMIER_ACCES.txt` a été supprimé après changement du mot de passe.

### Application

- [ ] La page d’accueil 3D s’affiche à l’ouverture de `/`.
- [ ] Les sections « Un échange, simplement », les étapes et les statistiques
      sont visibles sous le hero.
- [ ] **Créer un compte** mène à l’inscription.
- [ ] **Se connecter** et **Passer à la connexion** mènent à la connexion.
- [ ] Le bouton **Télécharger l’application** télécharge
      `apk/SkillSwap-Campus.apk`.
- [ ] Un utilisateur connecté est redirigé vers son tableau de bord.
- [ ] L’inscription et la connexion fonctionnent.
- [ ] L’administrateur a créé au moins une catégorie et une compétence.
- [ ] Une offre peut être publiée et apparaît dans le catalogue.
- [ ] Une réservation de test peut être créée.

### PWA et APK

- [ ] Le manifeste, le service worker et `offline.html` répondent en HTTP 200.
- [ ] Le manifeste a le bon type MIME.
- [ ] L’installation PWA est proposée en HTTPS.
- [ ] Le mode hors-ligne affiche la page de secours, jamais une page connectée.
- [ ] L’APK s’installe et accepte l’URL HTTPS du site.
- [ ] L’APK affiche l’écran « Campus injoignable » lorsque le réseau est coupé.

---

## 11. Dépannage rapide

| Symptôme | Piste |
|---|---|
| Erreur 500 à la première visite | Permissions de `data/`, version PHP, PDO SQLite, journal d’erreurs |
| Page blanche | Erreur PHP masquée ; activez temporairement l’affichage des erreurs ou consultez les logs |
| 404 sur toutes les pages sauf l’accueil | `.htaccess` absent, `mod_rewrite` inactif ou site hors de `htdocs` |
| La page d’accueil affiche une liste de fichiers | `DirectoryIndex index.php` absent ou `.htaccess` non envoyé |
| Le manifeste est téléchargé au lieu d’être lu | Type MIME `.webmanifest` absent ; vérifiez `.htaccess` et `mod_mime` |
| La PWA ne s’installe pas | HTTPS absent, manifeste/icônes invalides, navigation privée ou cache ancien |
| Le service worker reste ancien | Incrémentez `VERSION_CACHE` dans `sw.js`, videz le cache du site |
| `PREMIER_ACCES.txt` absent | `data/` non inscriptible, ou administrateur déjà créé |
| Impossible de se connecter après restauration | Base incomplète ou mot de passe différent ; vérifiez la sauvegarde |
| L’APK refuse l’adresse | Utilisez une URL `https://` avec un certificat valide |
| L’APK ne charge rien | Vérifiez Internet, le domaine et le certificat ; utilisez la page « Campus injoignable » |

---

## 12. Notes techniques

- Front controller : `index.php` ; MVC dans `src/` et `vues/`.
- Base : SQLite via PDO, dans `data/skillswap.db`.
- Migrations : `src/Migrateur.php`, versionnées par `PRAGMA user_version`.
- Les fichiers `.htaccess` sont une protection de premier niveau ; ne les
  supprimez pas lors des mises à jour.
- Les polices et icônes sont locales ; aucun CDN ni service externe n’est
  requis pour l’interface.
- Le premier administrateur est créé avec un mot de passe aléatoire ; aucun
  mot de passe n’est présent dans le code source.
- `data/PREMIER_ACCES.txt` n’est jamais inclus dans le paquet de déploiement.

Pour toute erreur persistante, relevez le message exact et vérifiez d’abord :
la version de PHP, les permissions de `data/`, la présence de `.htaccess`, puis
le journal `data/logs/`.
