# SkillSwap Campus — site web

SkillSwap Campus est une banque du temps pour les étudiants de l’EST Béni
Mellal : une heure de savoir donnée vaut une heure de savoir reçue, sans
paiement.

Le site est une application PHP 8.4 sans framework, organisée autour d’un
contrôleur frontal et d’une base SQLite via PDO. Il fonctionne sans Composer,
sans SSH et sans compilation côté serveur.

## Fonctionnalités

- page d’accueil publique avec présentation du concept et téléchargement de
  l’application Android ;
- inscription, connexion et gestion du profil ;
- publication d’offres, créneaux et réservations ;
- portefeuille de temps, transferts et évaluations ;
- espace administrateur : comptes, catégories, modération et litiges ;
- PWA avec page hors-ligne et service worker versionné.

## Démarrage local

Prérequis : PHP 8.4 avec `pdo_sqlite`, `sqlite3`, `mbstring` et `openssl`.

Depuis ce dossier :

```powershell
php -S 127.0.0.1:8080 index.php
```

Ouvrez ensuite `http://127.0.0.1:8080/`. La première requête crée
automatiquement `data/skillswap.db`, applique le schéma et génère
`data/PREMIER_ACCES.txt` avec le compte administrateur initial.

## Tests

La suite de tests utilise une base temporaire et ne modifie pas la base de
démonstration :

```powershell
php tests/run.php
```

Les tests couvrent les règles métier, les migrations, la sécurité HTTP, le
rendu mobile, la PWA et les pages publiques.

## Application Android

Le bouton **Télécharger l’application** de la page d’accueil pointe vers
`apk/SkillSwap-Campus.apk`. Le fichier est inclus dans le paquet de
déploiement et servi avec le type MIME Android.

Les sources Android, le script de construction et l’APK sont maintenus dans le
dépôt séparé :

<https://github.com/omeb7z/Skillswap.app>

## Déploiement

Le guide complet pour InfinityFree est disponible dans
[`GUIDE_DEPLOIEMENT_INFINITYFREE.md`](GUIDE_DEPLOIEMENT_INFINITYFREE.md).

Points importants :

- déposez le contenu du site dans `htdocs/` ;
- conservez les fichiers `.htaccess` ;
- rendez `data/` et `data/logs/` inscriptibles par PHP ;
- ne publiez jamais `data/skillswap.db` ni `data/PREMIER_ACCES.txt`.

## Structure

```text
index.php          contrôleur frontal et routes
src/               bootstrap, contrôleurs, modèles et services
vues/              gabarits et pages PHP
css/               styles du site
js/                thème, navigation, PWA
icons/             icônes PWA et favicon
apk/               APK Android proposé au téléchargement
database/          schéma SQLite et migrations
tests/             suite de tests PHP
scripts/           outil de données de démonstration
data/              base locale et journaux (non versionnés)
```
