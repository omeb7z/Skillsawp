<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/**
 * Réinitialise la base et la remplit avec un campus de démonstration.
 *
 * Les données ne sont PAS insérées à la main : on « rejoue » trois semaines de
 * vie du campus en figeant l'horloge à différents moments et en appelant les
 * vraies méthodes métier. Portefeuilles, historique, litiges et évaluations
 * sont donc cohérents par construction.
 *
 * Usage : php scripts/seed.php
 */
require __DIR__ . '/../src/bootstrap.php';

const MDP = 'Campus2026!';
$chemin = App::config()['database'];
foreach (['', '-wal', '-shm'] as $suffixe) {
    @unlink($chemin . $suffixe);
}
$db = App::utiliserBase($chemin);
$adminDemo = (int) $db->valeur("SELECT id FROM utilisateur WHERE role = 'ADMIN' LIMIT 1");
if ($adminDemo > 0) {
    // Le jeu de démonstration a des identifiants documentés ; l'installation
    // normale, elle, génère un mot de passe aléatoire dans PREMIER_ACCES.txt.
    $db->executer('UPDATE utilisateur SET mot_de_passe_hash = ? WHERE id = ?',
        [password_hash(MDP, PASSWORD_DEFAULT), $adminDemo]);
}
$S = [
    'comptes' => new ServiceComptes($db), 'cat' => new ServiceCatalogue($db), 'offres' => new ServiceOffres($db),
    'resa' => new ServiceReservations($db), 'litiges' => new ServiceLitiges($db),
    'eval' => new ServiceEvaluations($db), 'admin' => new ServiceAdmin($db),
];

$N = new DateTimeImmutable(date('Y-m-d H:00'));
$jour = fn(int $j, int $h) => $N->modify("$j days")->setTime($h, 0);
$f = fn(DateTimeImmutable $d) => Horloge::texte($d);
Horloge::figer($N->modify('-21 days'));

// --- Catalogue de compétences ---------------------------------------
$categories = [
    ['Programmation', 'Langages, algorithmique et outils du développeur', '#2a6fdb', ['Python', 'Java', 'Développement web', 'Git', 'SQL']],
    ['Langues', 'Pratique orale et écrite des langues étrangères', '#d1495b', ['Anglais oral', 'Anglais écrit', 'Espagnol', 'Allemand']],
    ['Communication orale', "S'exprimer avec aisance devant un public", '#e07a1f', ['Prise de parole', 'Préparation de soutenance', "Entretien d'embauche"]],
    ['Maths & sciences', 'Comprendre plutôt que réciter', '#1f8a6e', ['Algèbre linéaire', 'Probabilités', 'Analyse']],
    ['Design & outils', 'Créer des interfaces et des visuels', '#8e44ad', ['Figma', 'Canva', 'Montage vidéo']],
    ['Bureautique', 'Être efficace avec les outils du quotidien', '#00838f', ['Excel', 'Word avancé', 'PowerPoint']],
];
$comp = [];
foreach ($categories as [$nom, $desc, $couleur, $liste]) {
    $kid = $S['cat']->creerCategorie($nom, $desc, $couleur);
    foreach ($liste as $c) {
        $comp[$c] = $S['cat']->ajouterCompetence($kid, $c);
    }
}

// --- Étudiants ------------------------------------------------------
$etudiants = [
    ['Sara', 'El Amrani', 'DUT Génie Informatique', ['Python', 'Git'], ['Anglais oral', 'Prise de parole']],
    ['Amine', 'Benali', 'DUT Génie Informatique', ['Algèbre linéaire'], ['Python', 'Java']],
    ['Inès', 'Ouazzani', 'DUT Techniques de Management', ['Anglais oral', 'Anglais écrit'], ['Excel', 'Figma']],
    ['Yassine', 'Tahiri', 'DUT Techniques de Management', ['Excel', 'PowerPoint'], ['Anglais oral', 'Java']],
    ['Salma', 'Idrissi', 'DUT Techniques de Commercialisation', ['Prise de parole', "Entretien d'embauche"], ['Python', 'Canva']],
    ['Omar', 'Chraibi', 'DUT Génie Informatique', ['Figma', 'Canva'], ['Prise de parole', 'SQL']],
    ['Hiba', 'Lahlou', 'DUT Génie Électrique', ['Probabilités'], ['Excel', 'Développement web']],
    ['Mehdi', 'Fassi', 'DUT Génie Informatique', ['Java'], ['Anglais écrit']],
];
$u = [];
foreach ($etudiants as [$prenom, $nom, $filiere, $propose, $recherche]) {
    $email = strtolower(str_replace('è', 'e', $prenom)) . '@etu.skillswap.ma';
    $uid = $S['comptes']->inscrire($nom, $prenom, $email, MDP, MDP, $filiere);
    $bio = ['Sara' => "J'adore expliquer le code avec des exemples ludiques. Patiente avec les débutants !",
        'Inès' => "Bilingue anglais, j'aide à préparer les entretiens et les présentations."][$prenom] ?? null;
    $S['comptes']->modifierProfil($uid, $nom, $prenom, $filiere, $bio,
        array_map(fn($c) => $comp[$c], $propose), array_map(fn($c) => $comp[$c], $recherche));
    $u[$prenom] = $uid;
}

$offre = function (string $qui, string $titre, string $competence, string $desc, string $niveau, int $duree, string $mode,
                   array $creneaux) use ($S, $u, $comp, $f): array {
    $oid = $S['offres']->enregistrerOffre($u[$qui], ['titre' => $titre, 'competence_id' => $comp[$competence],
        'description' => $desc, 'niveau' => $niveau, 'duree' => $duree, 'mode' => $mode]);
    $cids = [];
    foreach ($creneaux as $d) {
        $cids[] = $S['offres']->ajouterCreneau($u[$qui], $oid, $f($d), $f($d->modify("+$duree hours")),
            $mode === 'DISTANCE' ? null : "Bibliothèque de l'EST, salle de travail 2",
            $mode === 'DISTANCE' ? "https://meet.jit.si/skillswap-$oid" : null);
    }
    $S['offres']->publierOffre($u[$qui], $oid);
    return [$oid, $cids];
};

[, $cPy] = $offre('Sara', 'Python pour débuter : variables, boucles et mini-jeux', 'Python',
    'On écrit ensemble un petit jeu du pendu pour comprendre variables, conditions, boucles et fonctions. Apportez votre ordinateur avec Python 3 installé.',
    'DEBUTANT', 1, 'PRESENTIEL', [$jour(-14, 14), $jour(-7, 14), $jour(2, 10), $jour(2, 14), $jour(5, 16)]);
[, $cEn] = $offre('Inès', 'Conversation anglaise : préparer un entretien de stage', 'Anglais oral',
    'Simulation d\'entretien en anglais : se présenter, parler de ses projets, répondre aux questions pièges. Retour personnalisé sur la prononciation.',
    'INTERMEDIAIRE', 1, 'DISTANCE', [$jour(-10, 18), $jour(1, 18), $jour(3, 18)]);
[, $cXl] = $offre('Yassine', 'Excel : tableaux croisés dynamiques et graphiques', 'Excel',
    'À partir d\'un vrai fichier de ventes, on construit un tableau croisé dynamique, des segments et un graphique propre pour un rapport.',
    'INTERMEDIAIRE', 2, 'PRESENTIEL', [$jour(-6, 10), $jour(4, 10)]);
[, $cPp] = $offre('Salma', 'Prise de parole : réussir sa soutenance de stage', 'Préparation de soutenance',
    'Structurer sa présentation, gérer le trac, répondre au jury. Vous présentez 5 minutes, je vous filme et on débriefe ensemble.',
    'AVANCE', 1, 'PRESENTIEL', [$jour(-5, 16), $jour(1, 16), $jour(6, 16)]);
[, $cFg] = $offre('Omar', 'Figma : maquetter une application mobile', 'Figma',
    'De la feuille blanche à un prototype cliquable : frames, composants, auto-layout et partage du prototype. Créez un compte Figma gratuit avant la séance.',
    'DEBUTANT', 2, 'DISTANCE', [$N->modify('-3 hours'), $jour(3, 14)]);
[, $cAl] = $offre('Amine', 'Algèbre linéaire : les matrices sans douleur', 'Algèbre linéaire',
    'Produit matriciel, déterminant et inverse expliqués avec des schémas. Idéal avant le contrôle de maths du semestre.',
    'DEBUTANT', 1, 'PRESENTIEL', [$jour(3, 11), $jour(7, 11)]);
[, $cJa] = $offre('Mehdi', 'Java POO : héritage et interfaces', 'Java',
    'Classes abstraites, interfaces et polymorphisme à travers un mini-projet de gestion de bibliothèque.',
    'INTERMEDIAIRE', 1, 'PRESENTIEL', [$jour(2, 9)]);
[$oSpam] = $offre('Mehdi', "Vends corrigés des examens de l'an dernier", 'Java',
    'Tous les corrigés des examens, contactez-moi en privé pour les obtenir rapidement.', 'AVANCE', 1, 'DISTANCE', [$jour(8, 20)]);
$S['offres']->enregistrerOffre($u['Sara'], ['titre' => 'Git et GitHub pour travailler en équipe', 'competence_id' => $comp['Git']]);

// --- Trois semaines d'échanges --------------------------------------
$r1 = $S['resa']->demander($u['Amine'], $cPy[0], 'Je bloque sur les boucles, merci !');
$r5 = $S['resa']->demander($u['Amine'], $cPy[1], 'Je voudrais voir les fonctions.');
$S['resa']->accepter($u['Sara'], $r1);
$S['resa']->accepter($u['Sara'], $r5);
$r2 = $S['resa']->demander($u['Sara'], $cEn[0]);
$S['resa']->accepter($u['Inès'], $r2);
$r3 = $S['resa']->demander($u['Hiba'], $cXl[0], 'Pour mon rapport de stage.');
$r10 = $S['resa']->demander($u['Hiba'], $cPp[1]);
$S['resa']->accepter($u['Yassine'], $r3);
$S['resa']->refuser($u['Salma'], $r10, 'Ce créneau est réservé à un groupe de révision, désolée ! Essayez le suivant.');
$r4 = $S['resa']->demander($u['Omar'], $cPp[0]);
$S['resa']->accepter($u['Salma'], $r4);
$r6 = $S['resa']->demander($u['Inès'], $cFg[0], 'Pour un projet de maquette en marketing.');
$S['resa']->accepter($u['Omar'], $r6);

Horloge::figer($jour(-14, 15));
$S['resa']->confirmer($u['Sara'], $r1);
$S['resa']->confirmer($u['Amine'], $r1);
$S['eval']->evaluer($u['Amine'], $r1, 5, "Super pédagogue, le jeu du pendu m'a enfin fait comprendre les boucles.");
$S['eval']->evaluer($u['Sara'], $r1, 4, 'Très motivé, pense à revoir les bases avant la séance.');

Horloge::figer($jour(-10, 19));
$S['resa']->confirmer($u['Inès'], $r2);
$S['resa']->confirmer($u['Sara'], $r2);
$S['eval']->evaluer($u['Sara'], $r2, 5, 'Inès met tout de suite à l\'aise. Simulation très réaliste.');

Horloge::figer($jour(-7, 15)->modify('+30 minutes'));
$S['resa']->confirmer($u['Sara'], $r5);
$l5 = $S['litiges']->signaler($u['Amine'], $r5, 'DESACCORD',
    'La séance a été écourtée à 20 minutes à cause d\'une coupure de courant à la bibliothèque.');
$S['litiges']->ajouterElement(['id' => $u['Sara'], 'role' => 'ETUDIANT'], $l5,
    'C\'est exact, la coupure nous a obligés à arrêter. Je suis d\'accord pour rendre l\'heure.');

Horloge::figer($jour(-6, 13));
$S['resa']->confirmer($u['Yassine'], $r3);
$S['resa']->confirmer($u['Hiba'], $r3);
$S['eval']->evaluer($u['Hiba'], $r3, 4, 'Clair et efficace, fichier d\'exemple très utile.');
$S['eval']->evaluer($u['Yassine'], $r3, 5, 'Hiba avait préparé ses questions, séance très productive.');

Horloge::figer($jour(-6, 15));
$admin = (int) $db->valeur("SELECT id FROM utilisateur WHERE role = 'ADMIN'");
$S['litiges']->decider($admin, $l5, 'RESTITUTION', 'Les deux parties confirment que la séance a été interrompue au bout de '
    . '20 minutes pour une raison extérieure. L\'heure est intégralement restituée au bénéficiaire.');

Horloge::figer($jour(-5, 17)->modify('+20 minutes'));
$S['resa']->confirmer($u['Salma'], $r4);
$l4 = $S['litiges']->signaler($u['Omar'], $r4, 'ABSENCE',
    'Je suis arrivé à 16 h en salle de travail 2 et j\'ai attendu 25 minutes, personne n\'est venu.');
$S['litiges']->ajouterElement(['id' => $u['Salma'], 'role' => 'ETUDIANT'], $l4,
    'J\'étais en salle de travail 3 suite à un changement affiché à l\'accueil. J\'ai attendu Omar.');

// --- Situation actuelle ---------------------------------------------
Horloge::figer($N->modify('-30 minutes'));
$S['resa']->confirmer($u['Inès'], $r6);                         // Omar doit encore confirmer
$S['resa']->demander($u['Salma'], $cPy[2], 'Je voudrais automatiser mes tableaux de prospection.');
$r7 = $S['resa']->demander($u['Amine'], $cPy[2], 'Suite de notre première séance !');
$S['resa']->accepter($u['Sara'], $r7);                          // → la demande de Salma est refusée automatiquement
$S['resa']->demander($u['Yassine'], $cEn[1], 'Entretien pour un stage à Casablanca la semaine prochaine.');
$S['resa']->demander($u['Salma'], $cPy[3], 'Je débute totalement.');
$S['resa']->demander($u['Omar'], $cAl[0]);
$r9 = $S['resa']->demander($u['Yassine'], $cJa[0]);
$S['resa']->accepter($u['Mehdi'], $r9);
$S['admin']->masquerOffre($oSpam, "Vente de corrigés d'examens : contraire à la charte d'intégrité académique.");
$S['admin']->basculerCompte($admin, $u['Mehdi']);

Horloge::figer(null);
$S['admin']->reglerDecalageHorloge(0);
if ($S['admin']->verifierCoherence()) {
    fwrite(STDERR, "Incohérence de portefeuille !\n");
    exit(1);
}
$db->pdo->exec('PRAGMA wal_checkpoint(TRUNCATE)');
echo "Base de démonstration créée : $chemin\n";
echo "  Administrateur  : admin@skillswap.ma / " . MDP . "\n";
echo "  Étudiants       : sara@, amine@, ines@, yassine@, salma@, omar@, hiba@ (…@etu.skillswap.ma) / " . MDP . "\n";
echo "  Compte désactivé : mehdi@etu.skillswap.ma\n";
