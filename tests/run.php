<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/**
 * Tests automatisés de SkillSwap Campus — sans dépendance externe.
 * Lancer : php tests/run.php
 *
 * Couvre : les règles RG01 à RG19, les variantes des diagrammes de séquence,
 * la concurrence (processus parallèles) et la sécurité HTTP (CSRF, droits, RG19).
 */
require __DIR__ . '/../src/bootstrap.php';

const MDP = 'Secret123';
$T0 = new DateTimeImmutable('2026-11-02 08:00');

/* ------------------------------------------------------------------ mini-framework */
final class EchecTest extends Exception {}
function verifier(bool $condition, string $message = 'assertion fausse'): void
{
    if (!$condition) throw new EchecTest($message);
}
function egal(mixed $attendu, mixed $obtenu, string $quoi = ''): void
{
    if ($attendu != $obtenu) throw new EchecTest("$quoi : attendu " . json_encode($attendu, JSON_UNESCAPED_UNICODE)
        . ', obtenu ' . json_encode($obtenu, JSON_UNESCAPED_UNICODE));
}
/** Vérifie qu'une ErreurMetier (ou sous-classe) est levée, avec un message contenant $extrait. */
function echoue(callable $f, string $extrait = '', string $classe = ErreurMetier::class): void
{
    try {
        $f();
    } catch (Throwable $e) {
        verifier($e instanceof $classe, 'exception ' . get_class($e) . ' au lieu de ' . $classe . ' : ' . $e->getMessage());
        verifier($extrait === '' || str_contains($e->getMessage(), $extrait), "message « {$e->getMessage()} » sans « $extrait »");
        return;
    }
    throw new EchecTest("aucune exception (attendu : « $extrait »)");
}

/** Commande PHP enfant avec le même php.ini (mbstring, etc.) que le processus courant. */
function argumentsPhp(array $arguments): array
{
    $ini = php_ini_loaded_file();
    return array_merge([PHP_BINARY], $ini ? ['-c', $ini] : [], $arguments);
}

/** Environnement neuf pour chaque test : base temporaire, horloge figée, 4 étudiants, 2 catégories. */
final class Env
{
    public Db $db;
    public string $chemin;
    public array $u = [];
    public int $admin;
    public ServiceComptes $comptes;
    public ServiceCatalogue $cat;
    public ServiceOffres $offres;
    public ServiceReservations $resa;
    public ServiceLitiges $litiges;
    public ServiceEvaluations $eval;
    public ServiceAdmin $adm;

    public function __construct(public DateTimeImmutable $t0)
    {
        $this->chemin = sys_get_temp_dir() . '/skillswap_test_' . bin2hex(random_bytes(6)) . '.db';
        Horloge::figer($t0);
        $this->db = App::utiliserBase($this->chemin);
        foreach (['comptes' => ServiceComptes::class, 'cat' => ServiceCatalogue::class, 'offres' => ServiceOffres::class,
                     'resa' => ServiceReservations::class, 'litiges' => ServiceLitiges::class,
                     'eval' => ServiceEvaluations::class, 'adm' => ServiceAdmin::class] as $p => $c) {
            $this->$p = new $c($this->db);
        }
        $this->cat->creerCategorie('Programmation', '', '#2a6fdb');
        $this->cat->creerCategorie('Langues', '', '#d1495b');
        $this->cat->ajouterCompetence(1, 'Python');
        $this->cat->ajouterCompetence(1, 'Java');
        $this->cat->ajouterCompetence(2, 'Anglais');
        foreach (['Sara', 'Amine', 'Ines', 'Karim'] as $n) {
            $this->u[$n] = $this->comptes->inscrire($n, $n, strtolower($n) . '@test.ma', MDP, MDP, 'DUT GI');
        }
        $this->admin = (int) $this->db->valeur("SELECT id FROM utilisateur WHERE role = 'ADMIN'");
    }

    public function h(float $jours = 0, float $heures = 0): DateTimeImmutable
    {
        return $this->t0->modify(sprintf('+%d minutes', (int) round(($jours * 24 + $heures) * 60)));
    }

    /** Crée et publie une offre avec ses créneaux ; retourne [id offre, ids créneaux]. */
    public function offre(string $qui, array $debuts, int $duree = 1, int $competence = 1, string $mode = 'PRESENTIEL'): array
    {
        $oid = $this->offres->enregistrerOffre($this->u[$qui], ['titre' => "Offre de $qui", 'competence_id' => $competence,
            'description' => 'Une description suffisamment longue.', 'niveau' => 'DEBUTANT', 'duree' => $duree, 'mode' => $mode]);
        $cids = [];
        foreach ($debuts as $d) {
            $cids[] = $this->offres->ajouterCreneau($this->u[$qui], $oid, Horloge::texte($d),
                Horloge::texte($d->modify("+$duree hours")), $mode === 'PRESENTIEL' ? 'Salle 1' : null,
                $mode === 'DISTANCE' ? 'https://meet.test/x' : null);
        }
        $this->offres->publierOffre($this->u[$qui], $oid);
        return [$oid, $cids];
    }

    public function solde(string $qui): array
    {
        $p = $this->comptes->portefeuille($this->u[$qui]);
        return [(int) $p['disponible'], (int) $p['bloque']];
    }

    /** Séance Sara → Amine acceptée, 1 h après T0. */
    public function seanceAcceptee(int $duree = 1): int
    {
        [, $c] = $this->offre('Sara', [$this->h(0, 1)], $duree);
        $r = $this->resa->demander($this->u['Amine'], $c[0]);
        $this->resa->accepter($this->u['Sara'], $r);
        return $r;
    }

    public function nettoyer(): void
    {
        Horloge::figer(null);
        foreach (['', '-wal', '-shm'] as $s) @unlink($this->chemin . $s);
    }
}

$tests = [];
function test(string $nom, callable $f): void { $GLOBALS['tests'][$nom] = $f; }

/* ------------------------------------------------------------------ RG01 et comptes */
test('rg01_courriel_unique_et_bienvenue_unique', function (Env $e) {
    egal([2, 0], $e->solde('Sara'), 'solde initial');
    echoue(fn() => $e->comptes->inscrire('Xavier', 'Sara', 'SARA@test.ma', MDP, MDP, 'GI'), 'déjà utilisée');
    egal(1, (int) $e->db->valeur("SELECT COUNT(*) FROM mouvement WHERE type = 'BIENVENUE' AND etudiant_id = ?", [$e->u['Sara']]));
});
test('authentification_variantes', function (Env $e) {
    egal($e->u['Sara'], (int) $e->comptes->authentifier('sara@test.ma', MDP)['id']);
    echoue(fn() => $e->comptes->authentifier('sara@test.ma', 'mauvais1'), 'Identifiants incorrects');
    echoue(fn() => $e->comptes->authentifier('inconnu@test.ma', MDP), 'Identifiants incorrects');
    $e->adm->basculerCompte($e->admin, $e->u['Sara']);
    echoue(fn() => $e->comptes->authentifier('sara@test.ma', MDP), 'désactivé');
});
test('mot_de_passe_hache', function (Env $e) {
    $h = (string) $e->db->valeur("SELECT mot_de_passe_hash FROM utilisateur WHERE email = 'sara@test.ma'");
    verifier(!str_contains($h, MDP) && password_get_info($h)['algo'] !== null, 'mot de passe non haché');
});

/* ------------------------------------------------------------------ Offres et recherche */
test('publication_offre_incomplete', function (Env $e) {
    $oid = $e->offres->enregistrerOffre($e->u['Sara'], ['titre' => 'Brouillon']);
    echoue(fn() => $e->offres->publierOffre($e->u['Sara'], $oid), 'incomplète');
});
test('recherche_resultats_et_aucune_correspondance', function (Env $e) {
    $e->offre('Sara', [$e->h(2)]);
    egal(1, count($e->offres->rechercher(['q' => 'Python'])));
    egal(0, count($e->offres->rechercher(['q' => 'Cobol'])));
    egal(0, count($e->offres->rechercher(['mode' => 'DISTANCE'])));
});
test('suggestions_exacte_puis_categorie', function (Env $e) {
    $e->offre('Sara', [$e->h(2)]);                     // Python
    $e->offre('Karim', [$e->h(3)], 1, 2);              // Java : même catégorie
    $e->offre('Ines', [$e->h(0, 1)]);                  // Python, mais son créneau va passer
    Horloge::figer($e->h(0, 1.5));
    $e->comptes->modifierProfil($e->u['Amine'], 'Amine', 'Amine', 'GI', null, [], [1]);
    egal([$e->u['Sara'], $e->u['Karim']], array_map(fn($o) => (int) $o['intervenant_id'], $e->offres->suggestions($e->u['Amine'])));
    $e->comptes->modifierProfil($e->u['Sara'], 'Sara', 'Sara', 'GI', null, [], [1]);
    foreach ($e->offres->suggestions($e->u['Sara']) as $o) verifier((int) $o['intervenant_id'] !== $e->u['Sara'], 'sa propre offre suggérée');
});

/* ------------------------------------------------------------------ Demande et acceptation */
test('demande_succes_ne_bloque_rien_rg05', function (Env $e) {
    [, $c] = $e->offre('Sara', [$e->h(2)]);
    $rid = $e->resa->demander($e->u['Amine'], $c[0]);
    egal('EN_ATTENTE', $e->resa->reservation($rid)['etat']);
    egal([2, 0], $e->solde('Amine'));
});
test('rg03_propre_offre_et_doublon', function (Env $e) {
    [, $c] = $e->offre('Sara', [$e->h(2)]);
    echoue(fn() => $e->resa->demander($e->u['Sara'], $c[0]), 'propre offre');
    $e->resa->demander($e->u['Amine'], $c[0]);
    echoue(fn() => $e->resa->demander($e->u['Amine'], $c[0]), 'déjà une demande');
});
test('rg05_solde_verifie_a_la_demande_et_a_l_acceptation', function (Env $e) {
    [, $c2] = $e->offre('Sara', [$e->h(2), $e->h(3)], 2);
    [, $c1] = $e->offre('Ines', [$e->h(4)]);
    $r1 = $e->resa->demander($e->u['Amine'], $c2[0]);
    $r2 = $e->resa->demander($e->u['Amine'], $c1[0]);
    $e->resa->accepter($e->u['Sara'], $r1);
    echoue(fn() => $e->resa->accepter($e->u['Ines'], $r2), 'plus assez');
    echoue(fn() => $e->resa->demander($e->u['Amine'], $c2[1]), 'Solde insuffisant');
});
test('rg06_rg07_blocage_et_refus_automatique', function (Env $e) {
    [, $c] = $e->offre('Sara', [$e->h(2)]);
    $ra = $e->resa->demander($e->u['Amine'], $c[0]);
    $rb = $e->resa->demander($e->u['Ines'], $c[0]);
    $e->resa->accepter($e->u['Sara'], $ra);
    egal([1, 1], $e->solde('Amine'));
    $b = $e->resa->reservation($rb);
    verifier($b['etat'] === 'REFUSEE' && str_contains($b['motif_refus'], 'autre demande'), 'refus automatique absent');
    echoue(fn() => $e->resa->demander($e->u['Karim'], $c[0]), 'déjà attribué');
});
test('rg04_offre_archivee', function (Env $e) {
    [$oid, $c] = $e->offre('Sara', [$e->h(2), $e->h(3)]);
    $r = $e->resa->demander($e->u['Amine'], $c[0]);
    $e->offres->archiverOffre($e->u['Sara'], $oid);
    egal('ANNULEE', $e->resa->reservation($r)['etat']);
    echoue(fn() => $e->resa->demander($e->u['Ines'], $c[1]), 'pas publiée');
});
test('rg08_conflit_de_planning_consecutif_autorise', function (Env $e) {
    [, $cs] = $e->offre('Sara', [$e->h(2)]);              // 08:00-09:00
    [, $ci] = $e->offre('Ines', [$e->h(2, 0.5)]);          // 08:30 : chevauche
    [, $ck] = $e->offre('Karim', [$e->h(2, 1)]);           // 09:00 : consécutif
    $e->resa->accepter($e->u['Sara'], $e->resa->demander($e->u['Amine'], $cs[0]));
    $rc = $e->resa->demander($e->u['Amine'], $ci[0]);
    echoue(fn() => $e->resa->accepter($e->u['Ines'], $rc), 'Conflit de planning');
    $e->resa->accepter($e->u['Karim'], $e->resa->demander($e->u['Amine'], $ck[0]));
});

/* ------------------------------------------------------------------ Refus et annulation */
test('rg09_refus_motive_et_retrait', function (Env $e) {
    [, $c] = $e->offre('Sara', [$e->h(2), $e->h(3)]);
    $r1 = $e->resa->demander($e->u['Amine'], $c[0]);
    echoue(fn() => $e->resa->refuser($e->u['Sara'], $r1, ''), 'Motif');
    $e->resa->refuser($e->u['Sara'], $r1, 'Indisponible finalement');
    egal('REFUSEE', $e->resa->reservation($r1)['etat']);
    $r2 = $e->resa->demander($e->u['Amine'], $c[1]);
    $e->resa->retirer($e->u['Amine'], $r2);
    egal('ANNULEE', $e->resa->reservation($r2)['etat']);
});
test('rg10_annulation_avant_debut_libere', function (Env $e) {
    [, $c] = $e->offre('Sara', [$e->h(2)]);
    $r = $e->resa->demander($e->u['Amine'], $c[0]);
    $e->resa->accepter($e->u['Sara'], $r);
    $e->resa->annuler($e->u['Sara'], $r, 'Malade');
    egal([2, 0], $e->solde('Amine'));
    $e->resa->demander($e->u['Ines'], $c[0]);              // créneau de nouveau réservable
});
test('rg10_annulation_interdite_apres_debut', function (Env $e) {
    $r = $e->seanceAcceptee();
    Horloge::figer($e->h(0, 1.5));
    echoue(fn() => $e->resa->annuler($e->u['Amine'], $r), 'signalement');
});

/* ------------------------------------------------------------------ Validation et transfert */
test('rg11_confirmations_puis_transfert_unique', function (Env $e) {
    $r = $e->seanceAcceptee();
    echoue(fn() => $e->resa->confirmer($e->u['Sara'], $r), "après l'heure de fin");
    Horloge::figer($e->h(0, 2));
    verifier($e->resa->confirmer($e->u['Sara'], $r) === false, 'la 1re confirmation ne doit pas transférer');
    echoue(fn() => $e->resa->confirmer($e->u['Sara'], $r), 'déjà confirmé');
    verifier($e->resa->confirmer($e->u['Amine'], $r) === true, 'la 2e confirmation doit transférer');
    egal([1, 0], $e->solde('Amine'));
    egal([3, 0], $e->solde('Sara'));
    egal('REALISEE', $e->resa->reservation($r)['etat']);
    echoue(fn() => $e->resa->confirmer($e->u['Amine'], $r));
    egal(1, (int) $e->db->valeur("SELECT COUNT(*) FROM mouvement WHERE reservation_id = ? AND type = 'TRANSFERT_ENTRANT'", [$r]));
});
test('rg12_litige_empeche_le_transfert', function (Env $e) {
    $r = $e->seanceAcceptee();
    Horloge::figer($e->h(0, 2));
    $e->resa->confirmer($e->u['Sara'], $r);
    $e->litiges->signaler($e->u['Amine'], $r, 'ABSENCE', "L'intervenante n'est jamais venue.");
    echoue(fn() => $e->resa->confirmer($e->u['Amine'], $r), 'litige');
    egal([1, 1], $e->solde('Amine'));
});

/* ------------------------------------------------------------------ Litiges */
foreach ([['TRANSFERT', 'REALISEE', [1, 0], [3, 0]], ['RESTITUTION', 'ANNULEE', [2, 0], [2, 0]]] as [$dec, $etat, $amine, $sara]) {
    test("rg13_rg14_reglement_integral_definitif_$dec", function (Env $e) use ($dec, $etat, $amine, $sara) {
        $r = $e->seanceAcceptee();
        Horloge::figer($e->h(0, 2));
        $lid = $e->litiges->signaler($e->u['Amine'], $r, 'DESACCORD', "La séance n'a duré que dix minutes.");
        $e->litiges->ajouterElement(['id' => $e->u['Sara'], 'role' => 'ETUDIANT'], $lid, 'Il est parti en avance.');
        echoue(fn() => $e->litiges->decider($e->admin, $lid, $dec, ''), 'Motivation');
        $e->litiges->decider($e->admin, $lid, $dec, 'Décision motivée après lecture du dossier.');
        egal($etat, $e->resa->reservation($r)['etat']);
        egal($amine, $e->solde('Amine'), 'Amine');
        egal($sara, $e->solde('Sara'), 'Sara');
        echoue(fn() => $e->litiges->decider($e->admin, $lid, 'TRANSFERT', 'Tentative de second règlement.'), 'second règlement');
    });
}
test('rg13_reglement_partiel_refuse', function (Env $e) {
    $r = $e->seanceAcceptee();
    Horloge::figer($e->h(0, 2));
    $lid = $e->litiges->signaler($e->u['Amine'], $r, 'DESACCORD', 'Partage demandé svp.');
    echoue(fn() => $e->litiges->decider($e->admin, $lid, 'PARTAGE', 'On coupe la poire en deux.'), 'intégral');
});

/* ------------------------------------------------------------------ Évaluations */
test('rg15_evaluation_variantes', function (Env $e) {
    $r = $e->seanceAcceptee();
    echoue(fn() => $e->eval->evaluer($e->u['Amine'], $r, 5, ''), 'réalisée');
    Horloge::figer($e->h(0, 2));
    $e->resa->confirmer($e->u['Sara'], $r);
    $e->resa->confirmer($e->u['Amine'], $r);
    $e->eval->evaluer($e->u['Amine'], $r, 5, 'Parfait');
    echoue(fn() => $e->eval->evaluer($e->u['Amine'], $r, 4, ''), 'déjà évalué');
    echoue(fn() => $e->eval->evaluer($e->u['Sara'], $r, 6, ''), 'entre 1 et 5');
    egal([5.0, 1], $e->comptes->noteMoyenne($e->u['Sara']));
});

/* ------------------------------------------------------------------ RG16 à RG19 */
test('rg16_jamais_negatif_et_coherence', function (Env $e) {
    echoue(fn() => $e->db->executer('UPDATE portefeuille SET disponible = -1 WHERE etudiant_id = ?', [$e->u['Sara']]), '', PDOException::class);
    $r = $e->seanceAcceptee(2);
    Horloge::figer($e->h(0, 4));
    $e->resa->confirmer($e->u['Sara'], $r);
    $e->resa->confirmer($e->u['Amine'], $r);
    egal([], $e->adm->verifierCoherence());
});
test('rg17_masquage_garde_les_seances_acceptees', function (Env $e) {
    [$oid, $c] = $e->offre('Sara', [$e->h(2), $e->h(3)]);
    $ra = $e->resa->demander($e->u['Amine'], $c[0]);
    $e->resa->accepter($e->u['Sara'], $ra);
    $rp = $e->resa->demander($e->u['Ines'], $c[1]);
    $e->adm->masquerOffre($oid, 'Contenu inapproprié');
    egal('ACCEPTEE', $e->resa->reservation($ra)['etat']);
    egal('ANNULEE', $e->resa->reservation($rp)['etat']);
    echoue(fn() => $e->offres->enregistrerOffre($e->u['Sara'], ['titre' => 'Offre de Sara', 'competence_id' => 1, 'duree' => 2,
        'description' => 'Une description suffisamment longue.', 'niveau' => 'DEBUTANT', 'mode' => 'PRESENTIEL'], $oid), 'durée');
});
test('rg18_desactivation', function (Env $e) {
    [, $c] = $e->offre('Sara', [$e->h(2), $e->h(3)]);
    $ra = $e->resa->demander($e->u['Amine'], $c[0]);
    $e->resa->accepter($e->u['Sara'], $ra);
    $rp = $e->resa->demander($e->u['Ines'], $c[1]);
    $e->adm->basculerCompte($e->admin, $e->u['Sara']);
    egal('ANNULEE', $e->resa->reservation($rp)['etat']);
    egal([$ra], array_map(fn($x) => (int) $x['id'], $e->adm->reservationsATraiter()));
    echoue(fn() => $e->resa->demander($e->u['Karim'], $c[1]), 'plus actif');
    $lid = $e->litiges->ouvrirDossierAdmin($e->admin, $ra, 'Compte désactivé, traitement administratif.');
    $e->litiges->decider($e->admin, $lid, 'RESTITUTION', 'Intervenante désactivée : restitution intégrale.');
    egal([2, 0], $e->solde('Amine'), 'aucun crédit perdu');
});
test('rg19_dossier_reserve_aux_participants', function (Env $e) {
    $r = $e->seanceAcceptee();
    Horloge::figer($e->h(0, 2));
    $lid = $e->litiges->signaler($e->u['Amine'], $r, 'ABSENCE', "Personne n'est venu à la séance.");
    echoue(fn() => $e->litiges->verifierAcces($lid, ['id' => $e->u['Karim'], 'role' => 'ETUDIANT']), '', AccesRefuse::class);
    $e->litiges->verifierAcces($lid, ['id' => $e->admin, 'role' => 'ADMIN']);
    echoue(fn() => $e->resa->confirmer($e->u['Karim'], $r), '', AccesRefuse::class);
});

/* ------------------------------------------------------------------ Phase 3 : litiges et restitution */
test('phase3_gardiens_de_finalite_du_litige', function (Env $e) {
    $r = $e->seanceAcceptee();
    Horloge::figer($e->h(0, 2));
    $lid = $e->litiges->signaler($e->u['Amine'], $r, 'DESACCORD', 'La séance a été écourtée après une coupure.');
    $e->litiges->ajouterElement(['id' => $e->u['Sara'], 'role' => 'ETUDIANT'], $lid, 'Je confirme la coupure et la fin anticipée.');

    // Une séance en litige ne peut pas être clôturée tant que le dossier reste ouvert.
    echoue(fn() => $e->db->executer("UPDATE reservation SET etat = 'REALISEE' WHERE id = ?", [$r]), '', PDOException::class);

    $e->litiges->decider($e->admin, $lid, 'RESTITUTION', 'Les deux versions concordent : restitution intégrale.');
    egal('ANNULEE', $e->resa->reservation($r)['etat']);
    egal([2, 0], $e->solde('Amine'));
    egal([2, 0], $e->solde('Sara'));

    echoue(fn() => $e->litiges->ajouterElement(['id' => $e->u['Sara'], 'role' => 'ETUDIANT'], $lid, 'Élément ajouté trop tard.'),
        'clos', ErreurMetier::class);
    echoue(fn() => $e->litiges->decider($e->admin, $lid, 'TRANSFERT', 'Tentative de second règlement.'),
        'déjà été réglé', ErreurMetier::class);

    // Les déclencheurs SQLite protègent aussi les écritures hors service.
    echoue(fn() => $e->db->executer("UPDATE litige SET etat = 'OUVERT' WHERE id = ?", [$lid]), '', PDOException::class);
    echoue(fn() => $e->db->executer("UPDATE litige SET decision = 'TRANSFERT' WHERE id = ?", [$lid]), '', PDOException::class);
    echoue(fn() => $e->db->executer("INSERT INTO element_litige(litige_id, auteur_id, texte, cree_le) VALUES (?,?,?,?)",
        [$lid, $e->u['Amine'], 'Élément tardif', Horloge::maintenantTxt($e->db)]), '', PDOException::class);
    echoue(fn() => $e->db->executer("UPDATE reservation SET etat = 'ACCEPTEE' WHERE id = ?", [$r]), '', PDOException::class);
    egal(1, (int) $e->db->valeur("SELECT COUNT(*) FROM mouvement WHERE reservation_id = ? AND type = 'RESTITUTION'", [$r]),
        'restitution unique');
});

test('phase3_dossier_admin_reserve_aux_comptes_desactives', function (Env $e) {
    $r = $e->seanceAcceptee();
    echoue(fn() => $e->litiges->ouvrirDossierAdmin($e->admin, $r, 'Traitement administratif demandé.'),
        'désactivé', ErreurMetier::class);

    $e->adm->basculerCompte($e->admin, $e->u['Sara']);
    $lid = $e->litiges->ouvrirDossierAdmin($e->admin, $r, 'Compte désactivé : traitement administratif.');
    egal('EN_LITIGE', $e->resa->reservation($r)['etat']);
    $e->litiges->decider($e->admin, $lid, 'RESTITUTION', 'Le compte est désactivé : restitution intégrale.');
    egal([2, 0], $e->solde('Amine'), 'crédits restitués');
});

test('phase3_decision_reservee_a_un_admin_actif', function (Env $e) {
    $r = $e->seanceAcceptee();
    Horloge::figer($e->h(0, 2));
    $lid = $e->litiges->signaler($e->u['Amine'], $r, 'ABSENCE', "L'intervenante n'est jamais venue.");
    echoue(fn() => $e->litiges->decider($e->u['Sara'], $lid, 'TRANSFERT', 'Décision étudiante.'),
        'administrateur actif', ErreurMetier::class);
    echoue(fn() => $e->litiges->ouvrirDossierAdmin($e->u['Sara'], $r, 'Dossier étudiant.'),
        'administrateur actif', ErreurMetier::class);

    $e->db->executer('UPDATE utilisateur SET actif = 0 WHERE id = ?', [$e->admin]);
    echoue(fn() => $e->litiges->decider($e->admin, $lid, 'TRANSFERT', 'Décision administrateur désactivé.'),
        'administrateur actif', ErreurMetier::class);
    egal('OUVERT', $e->litiges->litige($lid)['etat']);
});

test('phase3_http_dossier_litige_et_decision', function (Env $e) {
    $r = $e->seanceAcceptee();
    Horloge::figer($e->h(0, 2));
    $lid = $e->litiges->signaler($e->u['Amine'], $r, 'DESACCORD', 'La séance a été écourtée.');
    $e->db->executer('UPDATE utilisateur SET mot_de_passe_hash = ? WHERE id = ?',
        [password_hash(MDP, PASSWORD_DEFAULT), $e->admin]);
    [$serveur, $B] = demarrerServeur($e);
    try {
        connecterHttp($B, 'sara@test.ma', MDP, $ck);
        [$code, $html] = http('GET', "$B/litiges/$lid", [], $ck);
        egal(200, $code, 'dossier participant');
        verifier(str_contains($html, 'Dossier n°' . $lid), 'dossier non affiché au participant');

        connecterHttp($B, 'karim@test.ma', MDP, $ck2);
        egal(403, http('GET', "$B/litiges/$lid", [], $ck2)[0], 'dossier d’un autre étudiant');

        $jeton = connecterHttp($B, 'admin@skillswap.ma', MDP, $ckA);
        [$code, $html] = http('GET', "$B/litiges/$lid", [], $ckA);
        egal(200, $code, 'dossier administrateur');
        verifier(str_contains($html, 'Décider'), 'formulaire de décision absent');

        [$code, , $lieu] = http('POST', "$B/admin/litiges/$lid/decider", [
            'csrf' => $jeton, 'decision' => 'RESTITUTION', 'justification' => 'Séance écourtée : restitution intégrale.',
        ], $ckA);
        egal(302, $code, 'décision HTTP');
        verifier(str_contains($lieu, "/litiges/$lid"), 'redirection après décision');

        [$code, $html] = http('GET', "$B/litiges/$lid", [], $ckA);
        egal(200, $code, 'dossier réglé');
        verifier(str_contains($html, 'Décision définitive') && str_contains($html, 'Restitution intégrale'), 'décision non affichée');
        verifier(!str_contains($html, 'Appliquer la décision'), 'formulaire encore présent après règlement');
        egal('ANNULEE', $e->resa->reservation($r)['etat'], 'état final HTTP');
    } finally {
        arreterServeur($serveur);
    }
});

/* ------------------------------------------------------------------ Concurrence (processus réels) */
function enParallele(Env $e, int $n, string $action, int $uid, array $rids): array
{
    $depart = sprintf('%.0f', (microtime(true) + 0.6) * 1e6);
    $heure = Horloge::maintenant()->format('Y-m-d H:i');
    $procs = [];
    for ($i = 0; $i < $n; $i++) {
        $cmd = argumentsPhp(array_merge([__DIR__ . '/concurrence_worker.php', $e->chemin, $heure, $depart, $action, (string) $uid],
            array_map('strval', $rids)));
        $p = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $procs[] = [$p, $pipes];
    }
    $sorties = [];
    foreach ($procs as [$p, $pipes]) {
        $sorties[] = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        proc_close($p);
    }
    return $sorties;
}
test('double_clic_sur_confirmation_un_seul_transfert', function (Env $e) {
    $r = $e->seanceAcceptee();
    Horloge::figer($e->h(0, 2));
    $e->resa->confirmer($e->u['Sara'], $r);
    $sorties = enParallele($e, 8, 'confirmer', $e->u['Amine'], [$r]);
    egal(1, count(array_filter($sorties, fn($s) => $s === 'ok')), 'transferts réussis (' . implode('|', $sorties) . ')');
    egal([3, 0], $e->solde('Sara'));
    egal([], $e->adm->verifierCoherence());
});
test('acceptations_simultanees_une_seule_attribution', function (Env $e) {
    [, $c] = $e->offre('Sara', [$e->h(2)]);
    $rids = array_map(fn($n) => $e->resa->demander($e->u[$n], $c[0]), ['Amine', 'Ines', 'Karim']);
    enParallele($e, 6, 'accepter', $e->u['Sara'], $rids);
    egal(1, (int) $e->db->valeur("SELECT COUNT(*) FROM reservation WHERE creneau_id = ? AND etat = 'ACCEPTEE'", [$c[0]]));
    egal(1, array_sum(array_map(fn($n) => $e->solde($n)[1], ['Amine', 'Ines', 'Karim'])), 'crédits bloqués');
    egal([], $e->adm->verifierCoherence());
});
test('phase3_decisions_simultanees_une_seule', function (Env $e) {
    $r = $e->seanceAcceptee();
    Horloge::figer($e->h(0, 2));
    $lid = $e->litiges->signaler($e->u['Amine'], $r, 'ABSENCE', "Personne n'est venu à la séance.");
    $sorties = enParallele($e, 8, 'decider', $e->admin, [$lid]);
    egal(1, count(array_filter($sorties, fn($s) => $s === 'ok')), 'règlements réussis (' . implode('|', $sorties) . ')');
    egal('REALISEE', $e->resa->reservation($r)['etat']);
    egal([1, 0], $e->solde('Amine'));
    egal([3, 0], $e->solde('Sara'));
    egal(1, (int) $e->db->valeur("SELECT COUNT(*) FROM mouvement WHERE reservation_id = ? AND type = 'TRANSFERT_ENTRANT'", [$r]));
    egal([], $e->adm->verifierCoherence());
});

/* ------------------------------------------------------------------ Migrations et installation */
function baseTemporaire(string $prefixe = 'skillswap_mig_'): string
{
    return sys_get_temp_dir() . DIRECTORY_SEPARATOR . $prefixe . bin2hex(random_bytes(6)) . '.db';
}
function supprimerBase(string $chemin): void
{
    foreach (['', '-wal', '-shm'] as $suffixe) @unlink($chemin . $suffixe);
}

test('migration_fraiche_idempotente_et_admin_aleatoire', function (Env $e) {
    $chemin = baseTemporaire();
    try {
        $db = Db::connecter($chemin);
        Migrateur::migrer($db, false);
        egal(Migrateur::VERSION, Migrateur::version($db), 'version après création');
        egal(1, (int) $db->valeur("SELECT COUNT(*) FROM utilisateur WHERE role = 'ADMIN'"), 'administrateur initial');
        egal(1, (int) $db->valeur("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = 'tentative_connexion'"), 'table v2');
        egal(7, (int) $db->valeur("SELECT COUNT(*) FROM sqlite_master WHERE type = 'trigger' AND name LIKE 'trg_%'"), 'gardiens v3');
        $hash = (string) $db->valeur("SELECT mot_de_passe_hash FROM utilisateur WHERE role = 'ADMIN'");
        verifier(!password_verify('Admin2026!', $hash), 'mot de passe administrateur par défaut encore accepté');
        Migrateur::migrer($db, false);
        egal(Migrateur::VERSION, Migrateur::version($db), 'version après seconde migration');
        egal(1, (int) $db->valeur('SELECT COUNT(*) FROM utilisateur'), 'seconde migration non idempotente');
        unset($db);
    } finally {
        supprimerBase($chemin);
    }
});

test('premier_acces_ecrit_un_mot_de_passe_aleatoire', function (Env $e) {
    $chemin = baseTemporaire('skillswap_admin_');
    $fichier = dirname(__DIR__) . '/data/PREMIER_ACCES.txt';
    $existait = is_file($fichier);
    $ancien = $existait ? (string) file_get_contents($fichier) : null;
    try {
        $db = Db::connecter($chemin);
        Migrateur::migrer($db, true);
        verifier(is_file($fichier), 'data/PREMIER_ACCES.txt non créé');
        $contenu = (string) file_get_contents($fichier);
        verifier(str_contains($contenu, 'admin@skillswap.ma'), 'adresse administrateur absente du fichier');
        verifier(!str_contains($contenu, 'Admin2026!'), 'mot de passe par défaut présent dans le fichier');
        preg_match('/Mot de passe : ([^\r\n]+)/', $contenu, $m);
        verifier(isset($m[1]) && trim($m[1]) !== '', 'mot de passe illisible dans le fichier');
        $u = (new ServiceComptes($db))->authentifier('admin@skillswap.ma', trim($m[1]));
        verifier($u['role'] === 'ADMIN', 'le mot de passe du fichier ne connecte pas l’administrateur');
        $avant = (string) file_get_contents($fichier);
        Migrateur::migrer($db, true);
        egal($avant, (string) file_get_contents($fichier), 'fichier réécrit alors que l’admin existe déjà');
        unset($db);
    } finally {
        if ($existait && $ancien !== null) {
            @file_put_contents($fichier, $ancien, LOCK_EX);
        } elseif (!$existait) {
            @unlink($fichier);
        }
        supprimerBase($chemin);
    }
});

test('migration_adopte_une_base_ancienne_sans_perte', function (Env $e) {
    $chemin = baseTemporaire('skillswap_legacy_');
    try {
        $origine = Db::connecter($chemin);
        $origine->creerSchema();
        $origine->executer("INSERT INTO utilisateur(nom, prenom, email, mot_de_passe_hash, role, cree_le)
            VALUES ('Ancien', 'Étudiant', 'ancien@test.ma', ?, 'ETUDIANT', '2025-01-01 00:00')",
            [password_hash(MDP, PASSWORD_DEFAULT)]);
        $id = (int) $origine->valeur('SELECT id FROM utilisateur WHERE email = ?', ['ancien@test.ma']);
        $origine->executer('INSERT INTO portefeuille(etudiant_id) VALUES (?)', [$id]);
        unset($origine);

        $db = Db::connecter($chemin);
        egal(0, Migrateur::version($db), 'version initiale de la base ancienne');
        Migrateur::migrer($db, false);
        egal(Migrateur::VERSION, Migrateur::version($db), 'version après adoption');
        egal(1, (int) $db->valeur('SELECT COUNT(*) FROM utilisateur WHERE email = ?', ['ancien@test.ma']), 'donnée perdue');
        egal(1, (int) $db->valeur("SELECT COUNT(*) FROM utilisateur WHERE role = 'ADMIN'"), 'administrateur absent');
        egal(1, (int) $db->valeur("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = 'tentative_connexion'"), 'migration v2 absente');
        unset($db);
    } finally {
        supprimerBase($chemin);
    }
});

test('migration_v3_ajoute_les_gardiens_sans_perte', function (Env $e) {
    $chemin = baseTemporaire('skillswap_v2_');
    try {
        $db = Db::connecter($chemin);
        $db->creerSchema();
        $db->pdo->exec('DROP TRIGGER IF EXISTS trg_litige_insertion_ouverte;
            DROP TRIGGER IF EXISTS trg_litige_transition_finale;
            DROP TRIGGER IF EXISTS trg_litige_finalisation_complete;
            DROP TRIGGER IF EXISTS trg_litige_decision_figee;
            DROP TRIGGER IF EXISTS trg_element_litige_ouvert;
            DROP TRIGGER IF EXISTS trg_reservation_finalisation_litige;
            DROP TRIGGER IF EXISTS trg_reservation_etat_final;
            PRAGMA user_version = 2;');
        $db->executer("INSERT INTO utilisateur(nom, prenom, email, mot_de_passe_hash, role, cree_le)
            VALUES ('Conservé', 'Étudiant', 'conserve@test.ma', ?, 'ETUDIANT', '2025-01-01 00:00')",
            [password_hash(MDP, PASSWORD_DEFAULT)]);
        $db->executer('INSERT INTO portefeuille(etudiant_id) VALUES ((SELECT id FROM utilisateur WHERE email = ?))', ['conserve@test.ma']);

        Migrateur::migrer($db, false);
        egal(3, Migrateur::version($db), 'version après migration v3');
        egal(7, (int) $db->valeur("SELECT COUNT(*) FROM sqlite_master WHERE type = 'trigger' AND name LIKE 'trg_%'"), 'gardiens v3');
        egal(1, (int) $db->valeur("SELECT COUNT(*) FROM utilisateur WHERE email = 'conserve@test.ma'"), 'donnée perdue');
        Migrateur::migrer($db, false);
        egal(7, (int) $db->valeur("SELECT COUNT(*) FROM sqlite_master WHERE type = 'trigger' AND name LIKE 'trg_%'"), 'seconde migration non idempotente');
        unset($db);
    } finally {
        supprimerBase($chemin);
    }
});

test('migration_refuse_une_base_incomplete_sans_la_modifier', function (Env $e) {
    $chemin = baseTemporaire('skillswap_incomplete_');
    try {
        $db = Db::connecter($chemin);
        $db->pdo->exec('CREATE TABLE utilisateur(id INTEGER PRIMARY KEY)');
        unset($db);
        $db = Db::connecter($chemin);
        try {
            Migrateur::migrer($db, false);
            throw new EchecTest('base incomplète acceptée');
        } catch (RuntimeException $ex) {
            verifier(str_contains($ex->getMessage(), 'incomplète'), 'message : ' . $ex->getMessage());
        }
        egal(0, Migrateur::version($db), 'version modifiée malgré le refus');
        unset($db);
    } finally {
        supprimerBase($chemin);
    }
});

test('limitation_tentatives_connexion', function (Env $e) {
    for ($i = 1; $i <= 3; $i++) {
        echoue(fn() => $e->comptes->authentifier('sara@test.ma', 'Mauvais' . $i), 'Identifiants incorrects');
    }
    egal($e->u['Sara'], (int) $e->comptes->authentifier('sara@test.ma', MDP)['id'], 'connexion après quelques échecs');
    egal(0, (int) $e->db->valeur('SELECT COUNT(*) FROM tentative_connexion'), 'échecs non effacés après succès');

    for ($i = 1; $i <= 5; $i++) {
        echoue(fn() => $e->comptes->authentifier('sara@test.ma', 'Mauvais' . $i), 'Identifiants incorrects');
    }
    echoue(fn() => $e->comptes->authentifier('sara@test.ma', MDP), 'Trop de tentatives');
    Horloge::figer($e->t0->modify('+16 minutes'));
    egal($e->u['Sara'], (int) $e->comptes->authentifier('sara@test.ma', MDP)['id'], 'connexion après expiration de la fenêtre');
});

test('dates_libelles_et_echappement', function (Env $e) {
    egal(null, date_obj('2026-02-30'), 'date inexistante');
    egal(null, date_obj('pas une date'), 'date illisible');
    egal('lundi 2 novembre', date_longue('2026-11-02'), 'date longue');
    egal('02/11/2026 08:05', date_courte('2026-11-02 08:05'), 'date courte');
    egal('08:05', heure('2026-11-02 08:05'), 'heure');
    egal('', heure(null), 'heure vide');
    egal('', date_longue('2026-13-01'), 'mois invalide');
    verifier(Horloge::lire('2026-11-02T08:00')?->format(Horloge::FORMAT) === '2026-11-02 08:00', 'datetime-local');
    egal(null, Horloge::lire('2026-02-30 08:00'), 'heure inexistante');
    egal('', e(['x']), 'tableau échappé');
    egal('', v(['a' => ['x']], 'a'), 'valeur tableau');
    egal('&lt;b&gt;', v(['a' => '<b>'], 'a'), 'échappement formulaire');
    verifier(str_contains(etat_resa('ANNULEE'), 'Annulée'), 'libellé annulée');
    verifier(str_contains(etat_resa('INCONNU'), 'Inconnu'), 'libellé inconnu');
    verifier(str_contains(etat_offre(['etat' => 'PUBLIEE', 'masquee' => 0]), 'Publiée'), 'libellé offre publiée');
    verifier(str_contains(etat_offre(['etat' => 'PUBLIEE', 'masquee' => 1]), 'Masquée'), 'libellé offre masquée');
});

test('redirections_internes_sures', function (Env $e) {
    egal('/portefeuille?x=1', chemin_interne('/portefeuille?x=1'), 'chemin interne');
    egal('/', chemin_interne('//pirate.example'), 'chemin réseau');
    egal('/', chemin_interne('https://pirate.example/x'), 'URL absolue');
    egal('/', chemin_interne('javascript:alert(1)'), 'protocole');
    egal('/', chemin_interne('/\\pirate.example'), 'antislash');
    egal('/', chemin_interne('/chemin%00injecte'), 'octet nul');
    egal('/profil', chemin_interne(' /profil '), 'espaces');
    egal('/catalogue?q=python', chemin_interne('/catalogue?q=python'), 'requête conservée');
    egal('/defaut', chemin_interne('', '/defaut'), 'valeur par défaut');
    egal('/defaut', chemin_interne('relatif', '/defaut'), 'chemin relatif');
});

test('planning_accepte_les_seances_au_dela_de_30_jours', function (Env $e) {
    [, $c] = $e->offre('Sara', [$e->h(40)]);
    $r = $e->resa->demander($e->u['Amine'], $c[0]);
    $e->resa->accepter($e->u['Sara'], $r);
    $ids = array_map(fn($x) => (int) $x['id'], $e->resa->planning($e->u['Amine']));
    verifier(in_array($r, $ids, true), 'séance à 40 jours absente du planning');
});

test('annulation_et_archivage_renseignent_les_dates', function (Env $e) {
    [$oid, $c] = $e->offre('Sara', [$e->h(2), $e->h(3)]);
    $r1 = $e->resa->demander($e->u['Amine'], $c[0]);
    $e->offres->archiverOffre($e->u['Sara'], $oid);
    $a = $e->resa->reservation($r1);
    egal('ANNULEE', $a['etat'], 'état après archivage');
    verifier($a['decidee_le'] !== null && $a['cloturee_le'] !== null, 'dates de clôture manquantes après archivage');
    verifier(str_contains((string) $a['motif_annulation'], 'archivée'), 'motif d’archivage absent');

    [, $c2] = $e->offre('Ines', [$e->h(4)]);
    $r2 = $e->resa->demander($e->u['Amine'], $c2[0]);
    $e->resa->accepter($e->u['Ines'], $r2);
    $e->resa->annuler($e->u['Amine'], $r2, 'Empêchement');
    $b = $e->resa->reservation($r2);
    egal('ANNULEE', $b['etat'], 'état après annulation');
    verifier($b['decidee_le'] !== null && $b['cloturee_le'] !== null, 'dates de clôture manquantes après annulation');
    egal($e->u['Amine'], (int) $b['annulee_par'], 'auteur de l’annulation');
    verifier(str_contains(etat_resa($b['etat']), 'Annulée'), 'libellé annulée');
});

test('entrees_tableaux_ne_provoquent_pas_d_avertissement', function (Env $e) {
    $e->offre('Sara', [$e->h(2)]);
    egal(1, count($e->offres->rechercher(['q' => ['Python']])), 'filtre tableau');
    echoue(fn() => $e->offres->enregistrerOffre($e->u['Sara'], ['titre' => ['x']]), 'Titre');
    echoue(fn() => $e->offres->enregistrerOffre($e->u['Sara'], ['titre' => 'Titre valide', 'competence_id' => ['1']]), 'Compétence invalide');
    echoue(fn() => $e->offres->enregistrerOffre($e->u['Sara'], ['titre' => 'Titre valide', 'duree' => ['1']]), 'Durée invalide');
});

test('journalisation_et_pages_d_erreur', function (Env $e) {
    $racine = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'skillswap_logs_' . bin2hex(random_bytes(4));
    try {
        $ref = Erreurs::journaliser(new RuntimeException('panne simulée'), $racine);
        verifier(str_starts_with($ref, 'ERR-'), 'référence invalide');
        $fichiers = glob($racine . '/data/logs/erreurs-*.log') ?: [];
        egal(1, count($fichiers), 'fichier journal absent');
        $contenu = (string) file_get_contents($fichiers[0]);
        verifier(str_contains($contenu, $ref) && str_contains($contenu, 'panne simulée'), 'journal incomplet');
        $html = Erreurs::rendre(500, $ref);
        verifier(str_contains($html, 'Erreur interne') && str_contains($html, $ref), 'page 500 incomplète');
        verifier(str_contains(Erreurs::rendre(404), 'Page introuvable'), 'page 404 incomplète');
        verifier(str_contains(Erreurs::rendre(400, null, 'Message métier de test'), 'Message métier de test'), 'message personnalisé absent');
    } finally {
        if (is_dir($racine)) {
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($racine, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($it as $f) {
                $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
            }
            @rmdir($racine);
        }
    }
});

test('fichiers_php_sans_bom_ni_balise_fermante', function (Env $e) {
    $racine = dirname(__DIR__);
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($racine, FilesystemIterator::SKIP_DOTS));
    $controles = 0;
    foreach ($it as $f) {
        if (!$f->isFile() || strtolower($f->getExtension()) !== 'php') continue;
        $chemin = $f->getPathname();
        $contenu = (string) file_get_contents($chemin);
        verifier(!str_starts_with($contenu, "\xEF\xBB\xBF"), 'BOM dans ' . $chemin);
        verifier(!preg_match('/\?>\s*$/', $contenu), 'balise fermante finale dans ' . $chemin);
        $controles++;
    }
    verifier($controles > 20, 'trop peu de fichiers PHP contrôlés');
});

test('phase2_design_system_local_et_reperes_accessibles', function (Env $e) {
    $racine = dirname(__DIR__);
    $css = (string) file_get_contents($racine . '/css/skillswap-v4.css');
    $js = (string) file_get_contents($racine . '/js/skillswap.js');
    $gabarit = (string) file_get_contents($racine . '/vues/gabarit.php');
    $helpers = (string) file_get_contents($racine . '/src/helpers.php');

    verifier(str_contains($css, ':root') && str_contains($css, '[data-theme="dark"]'), 'jetons de thème absents');
    verifier(str_contains($css, '@media (prefers-color-scheme: dark)'), 'thème système absent');
    verifier(str_contains($css, '@media (max-width: 900px)'), 'point de rupture mobile absent');
    verifier(str_contains($css, ':focus-visible'), 'indicateur de focus absent');
    verifier(str_contains($css, 'prefers-reduced-motion'), 'préférence de mouvement réduit absente');
    verifier(str_contains($css, '.saut-contenu') && str_contains($css, '.nav-mobile'), 'navigation accessible incomplète');
    verifier(str_contains($css, 'inset-inline-start: 0 !important'), 'texte hors écran susceptible de déborder');
    verifier(!str_contains($css, 'color-mix('), 'color-mix() encore utilisé');
    verifier(preg_match('#(?:@import|fonts\.googleapis|fonts\.gstatic)#i', $css) !== 1, 'ressource distante dans le CSS');
    verifier(preg_match('/url\(\s*["\']?https?:/i', $css) !== 1, 'URL distante dans le CSS');

    verifier(str_contains($js, 'skillswap-theme') && str_contains($js, 'localStorage.setItem'), 'persistance du thème absente');
    verifier(str_contains($js, '"Escape"') && str_contains($js, 'inert'), 'gestion clavier du menu mobile absente');
    verifier(str_contains($js, 'aria-expanded'), 'état du menu mobile non exposé');
    verifier(str_contains($js, 'data-meme-que') && str_contains($js, 'noValidate'), 'validation champ par champ incomplète');
    verifier(str_contains($js, 'data-fermer-onboarding') && str_contains($js, 'data-onboarding'), 'mémorisation du guide absente');

    verifier(str_contains($gabarit, '<html lang="fr">'), 'langue française absente');
    verifier(str_contains($gabarit, 'class="saut-contenu"') && str_contains($gabarit, 'id="contenu"'), 'lien d’évitement absent');
    verifier(str_contains($gabarit, 'aria-label="Navigation principale"'), 'navigation principale non nommée');
    verifier(str_contains($gabarit, 'class="nav-mobile"') && str_contains($gabarit, 'aria-label="Navigation rapide"'), 'navigation mobile non nommée');
    verifier(str_contains($gabarit, 'data-menu-ouvrir') && str_contains($gabarit, 'aria-expanded="false"'), 'bouton de menu mobile incomplet');
    verifier(str_contains($gabarit, '<noscript>'), 'repli sans JavaScript absent');
    verifier(str_contains($helpers, 'data-theme-toggle') && str_contains($helpers, 'aria-pressed'), 'bouton de thème inaccessible');
    verifier(preg_match('/<(?:script|link)[^>]+(?:src|href)=["\']https?:/i', $gabarit) !== 1, 'ressource distante dans le gabarit');
});

test('phase4_pwa_offline_et_page_bienvenue', function (Env $e) {
    $racine = dirname(__DIR__);
    $fichiers = ['manifest.webmanifest', 'sw.js', 'offline.html',
        'icons/icon-192.png', 'icons/icon-512.png', 'icons/icon-maskable-512.png',
        'icons/apple-touch-icon.png', 'icons/favicon-32.png',
        'apk/SkillSwap-Campus.apk'];
    foreach ($fichiers as $fichier) {
        verifier(is_file($racine . '/' . $fichier), 'fichier PWA absent : ' . $fichier);
    }

    $manifest = json_decode((string) file_get_contents($racine . '/manifest.webmanifest'), true);
    verifier(is_array($manifest), 'manifeste PWA invalide');
    egal('SkillSwap Campus', $manifest['name'] ?? null, 'nom du manifeste');
    egal('standalone', $manifest['display'] ?? null, 'mode d’affichage du manifeste');
    egal('./', $manifest['start_url'] ?? null, 'URL de démarrage du manifeste');
    egal('./', $manifest['scope'] ?? null, 'portée du manifeste');
    egal('fr', $manifest['lang'] ?? null, 'langue du manifeste');
    $icones = $manifest['icons'] ?? [];
    verifier(is_array($icones) && count($icones) >= 3, 'icônes du manifeste incomplètes');
    $maskable = false;
    foreach ($icones as $icone) {
        $chemin = $racine . '/' . ltrim((string) ($icone['src'] ?? ''), '/');
        verifier(is_file($chemin), 'icône du manifeste absente : ' . ($icone['src'] ?? '?'));
        $taille = @getimagesize($chemin);
        verifier($taille !== false, 'icône illisible : ' . ($icone['sizes'] ?? '?'));
        [$largeur, $hauteur] = [(int) $taille[0], (int) $taille[1]];
        egal((string) $largeur . 'x' . $hauteur, (string) ($icone['sizes'] ?? ''), 'taille d’icône');
        if (($icone['purpose'] ?? '') === 'maskable') $maskable = true;
    }
    verifier($maskable, 'icône maskable absente');

    $sw = (string) file_get_contents($racine . '/sw.js');
    verifier(str_contains($sw, 'offline.html') && str_contains($sw, 'estRessourcePublique'), 'repli hors ligne absent du service worker');
    verifier(str_contains($sw, 'requete.mode === "navigate"') && str_contains($sw, 'fetch(requete)'), 'stratégie de navigation absente');
    verifier(!str_contains($sw, 'tableau-de-bord') && !str_contains($sw, '/reservations/'), 'le service worker cite une page authentifiée');
    verifier(str_contains($sw, 'cache.put') && str_contains($sw, 'PAGE_HORS_LIGNE'), 'cache public incomplet');

    $horsLigne = (string) file_get_contents($racine . '/offline.html');
    verifier(str_contains($horsLigne, 'hors ligne') && str_contains($horsLigne, 'Réessayer'), 'page hors ligne incomplète');
    verifier(preg_match('#(?:src|href)=["\']https?://#i', $horsLigne) !== 1, 'ressource distante dans la page hors ligne');
    verifier(str_contains($horsLigne, 'aucune donnée personnelle'), 'avertissement de données absent');

    $gabarit = (string) file_get_contents($racine . '/vues/gabarit.php');
    $bienvenue = (string) file_get_contents($racine . '/vues/auth/bienvenue.php');
    $titre = preg_replace('/\s+/', ' ', strip_tags($bienvenue)) ?? '';
    verifier(str_contains($titre, 'Bienvenue sur votre SkillSwap Campus'), 'titre français de la page d’accueil absent');
    verifier(!str_contains($bienvenue, 'Welcome to your'), 'titre anglais encore présent');
    verifier(str_contains($bienvenue, 'class="accueil-bienvenue"'), 'page d’accueil 3D absente');
    verifier(!str_contains($bienvenue, 'role="dialog"') && !str_contains($bienvenue, 'aria-modal="true"'), 'la page d’accueil est encore un dialogue modal');
    verifier(str_contains($bienvenue, 'Passer à la connexion'), 'destination du bouton Passer absente');
    verifier(str_contains($bienvenue, 'Télécharger l’application') && str_contains($bienvenue, "url('/apk/SkillSwap-Campus.apk')") && str_contains($bienvenue, 'download'), 'bouton de téléchargement de l’application absent');
    verifier(str_contains($bienvenue, 'Créer un compte') && str_contains($bienvenue, 'Se connecter'), 'actions de connexion absentes de la page d’accueil');
    verifier(str_contains($bienvenue, "url('/inscription')") && str_contains($bienvenue, "url('/connexion')"), 'liens d’inscription ou de connexion absents');
    verifier(!str_contains($bienvenue, 'data-fermer-bienvenue') && !str_contains($bienvenue, 'data-bienvenue-entrer'), 'logique de fermeture de la première visite encore présente');
    verifier(!str_contains($bienvenue, ' hidden'), 'la page d’accueil est encore masquée par défaut');
    verifier(str_contains($bienvenue, 'Un échange, simplement'), 'bloc de présentation de l’échange absent');
    verifier(str_contains($bienvenue, 'Proposez') && str_contains($bienvenue, 'Échangez') && str_contains($bienvenue, 'Progressez'), 'étapes de la page d’accueil absentes');
    verifier(str_contains($bienvenue, 'de bienvenue.') && str_contains($bienvenue, 'jeton(2)'), 'mention des deux heures de bienvenue absente');
    verifier(str_contains($bienvenue, 'accueil-statistiques') && str_contains($bienvenue, 'credits_transferes'), 'statistiques dynamiques absentes');
    verifier(str_contains($bienvenue, 'class="accueil-principes"') && str_contains($bienvenue, 'class="accueil-parcours"') && str_contains($bienvenue, 'class="accueil-chiffres"'), 'nouvelle hiérarchie de la page d’accueil absente');
    verifier(!is_file($racine . '/vues/auth/accueil.php'), 'ancienne page d’accueil encore présente');
    verifier(str_contains($gabarit, '$pleinEcran'), 'mise en page pleine largeur absente du gabarit');
    verifier(!str_contains($gabarit, 'partiels/ecran-bienvenue.php'), 'ancien écran de bienvenue encore inclus dans le gabarit');
    verifier(str_contains($gabarit, 'rel="manifest"') && str_contains($gabarit, 'apple-touch-icon'), 'liens PWA absents du gabarit');
    verifier(!str_contains($gabarit, 'skillswap-welcome-vu') && !str_contains($gabarit, 'premiere-visite'), 'logique de première visite encore présente dans le gabarit');
    verifier(str_contains($gabarit, 'data-base='), 'base PWA absente du gabarit');

    $js = (string) file_get_contents($racine . '/js/skillswap.js');
    verifier(!str_contains($js, 'skillswap-welcome-vu') && !str_contains($js, 'focusablesBienvenue'), 'logique de bienvenue modale encore présente dans le JavaScript');
    verifier(str_contains($js, 'serviceWorker.register'), 'enregistrement du service worker absent');

    $css = (string) file_get_contents($racine . '/css/skillswap-v4.css');
    verifier(str_contains($css, '.accueil-bienvenue') && str_contains($css, '.accueil-suite'), 'styles de la page d’accueil absents');
    verifier(str_contains($css, '.accueil-statistiques') && str_contains($css, '.accueil-etapes'), 'styles des contenus ajoutés absents');
    verifier(str_contains($css, '.accueil-principes') && str_contains($css, '.accueil-parcours') && str_contains($css, '.accueil-bonus'), 'styles de la nouvelle hiérarchie absents');
    verifier(!str_contains($css, '.accueil-public') && !str_contains($css, '.hero-public') && !str_contains($css, '.carte-echange') && !str_contains($css, '.etapes-accueil'), 'styles de l’ancienne page encore présents');
    verifier(!str_contains($css, 'html.premiere-visite'), 'styles de première visite encore présents');
    verifier(str_contains($css, '@keyframes apparaitre-bienvenue') && str_contains($css, 'prefers-reduced-motion'), 'animation ou mouvement réduit absent');

    $htaccess = (string) file_get_contents($racine . '/.htaccess');
    verifier(str_contains($htaccess, 'application/manifest+json') && str_contains($htaccess, 'no-cache'), 'en-têtes PWA absents du .htaccess');
});

test('phase2_http_theme_mobile_onboarding_et_validation_inline', function (Env $e) {
    [$serveur, $B] = demarrerServeur($e);
    try {
        $cookie = null;
        [$code, $html] = http('GET', "$B/", [], $cookie);
        egal(200, $code, 'accueil public');
        verifier(str_contains($html, 'data-theme-toggle'), 'bouton de thème absent en public');
        verifier(str_contains($html, 'Aller au contenu principal'), 'lien d’évitement absent');
        verifier(str_contains($html, 'name="color-scheme"'), 'métadonnée color-scheme absente');
        verifier(str_contains($html, '/css/skillswap-v4.css') && str_contains($html, '/js/skillswap.js'), 'assets locaux absents');
        verifier(preg_match('/fonts\.googleapis|fonts\.gstatic|cdn\.jsdelivr|unpkg\.com/i', $html) !== 1, 'ressource distante dans le HTML');

        [$code, $css] = http('GET', "$B/css/skillswap-v4.css");
        egal(200, $code, 'CSS local');
        verifier(str_contains($css, '@media (max-width: 900px)') && str_contains($css, '.nav-mobile'), 'mise en page mobile absente');
        verifier(!str_contains($css, 'color-mix('), 'CSS incompatible avec les anciens navigateurs');

        [$code, $html] = http('GET', "$B/inscription", [], $cookie);
        egal(200, $code, 'formulaire d’inscription');
        $jeton = jetonCsrf($html);
        [$code, $html] = http('POST', "$B/inscription", [
            'csrf' => $jeton, 'prenom' => 'Ada', 'nom' => 'Lovelace', 'email' => 'adresse-invalide',
            'filiere' => 'DUT GI', 'mot_de_passe' => 'Secret123', 'confirmation' => 'Secret123',
        ], $cookie);
        egal(200, $code, 'inscription invalide rendue avec les champs');
        verifier(str_contains($html, 'aria-invalid="true"'), 'champ invalide non signalé par aria-invalid');
        verifier(str_contains($html, 'id="email-erreur"') && str_contains($html, 'role="alert"'), 'erreur de champ non reliée au champ');
        verifier(str_contains($html, 'Saisissez une adresse électronique valide.'), 'message de validation serveur absent');

        connecterHttp($B, 'amine@test.ma', MDP, $cookie);
        [$code, $html] = http('GET', "$B/tableau-de-bord", [], $cookie);
        egal(200, $code, 'tableau de bord');
        verifier(str_contains($html, 'data-onboarding="' . (int) $e->u['Amine'] . '"'), 'guide de premiers pas absent');
        verifier(str_contains($html, 'Premiers pas') && str_contains($html, 'data-fermer-onboarding'), 'guide non actionable');
        verifier(str_contains($html, 'aria-label="Navigation rapide"'), 'navigation mobile secondaire absente');
        verifier(substr_count($html, 'class="nav-mobile-lien') >= 5, 'navigation mobile incomplète');
        verifier(str_contains($html, 'data-menu-ouvrir') && str_contains($html, 'aria-expanded="false"'), 'menu mobile non exposé');
        verifier(str_contains($html, 'data-theme-toggle'), 'bouton de thème absent dans l’application');
    } finally {
        arreterServeur($serveur);
    }
});

/* ------------------------------------------------------------------ Sécurité HTTP (serveur réel) */
function http(string $methode, string $url, array $donnees = [], ?string &$cookie = null): array
{
    $entetesRequete = "Content-Type: application/x-www-form-urlencoded\r\n";
    if ($cookie !== null && $cookie !== '') {
        $entetesRequete .= "Cookie: $cookie\r\n";
    }
    $ctx = stream_context_create(['http' => [
        'method' => $methode,
        'ignore_errors' => true,
        'follow_location' => 0,
        'timeout' => 15,
        'header' => $entetesRequete,
        'content' => $methode === 'GET' || $methode === 'HEAD' ? '' : http_build_query($donnees),
    ]]);
    $corps = @file_get_contents($url, false, $ctx);
    $entetes = $http_response_header ?? [];
    $code = 0;
    if (isset($entetes[0]) && preg_match('#^HTTP/\S+\s+(\d{3})#i', $entetes[0], $m)) {
        $code = (int) $m[1];
    }
    $lieu = '';
    foreach ($entetes as $h) {
        if (preg_match('/^Set-Cookie:\s*(skillswap=[^;]*)/i', $h, $m)) {
            $cookie = in_array($m[1], ['skillswap=', 'skillswap=deleted'], true) ? null : $m[1];
        }
        if (preg_match('/^Location:\s*(.+)$/i', $h, $m)) {
            $lieu = trim($m[1]);
        }
    }
    return [$code, (string) $corps, $lieu, $entetes];
}

function jetonCsrf(string $html): string
{
    if (preg_match('/name="csrf" value="([^"]+)"/', $html, $m) !== 1) {
        throw new EchecTest('jeton CSRF absent de la page');
    }
    return $m[1];
}

function demarrerServeur(Env $e): array
{
    $nul = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
    $racine = dirname(__DIR__);
    for ($essai = 0; $essai < 10; $essai++) {
        $port = random_int(8800, 8999);
        $s = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.1);
        if (is_resource($s)) { fclose($s); continue; }
        $processus = proc_open(
            argumentsPhp(['-S', "127.0.0.1:$port", '-t', $racine, $racine . '/index.php']),
            [1 => ['file', $nul, 'w'], 2 => ['file', $nul, 'w']],
            $pipes,
            $racine,
            ['SKILLSWAP_DB' => $e->chemin] + getenv()
        );
        if (!is_resource($processus)) continue;
        for ($i = 0; $i < 100; $i++) {
            $s = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.2);
            if (is_resource($s)) {
                fclose($s);
                return [$processus, "http://127.0.0.1:$port"];
            }
            usleep(50000);
        }
        proc_terminate($processus);
        proc_close($processus);
    }
    throw new EchecTest('impossible de démarrer le serveur HTTP de test');
}

function arreterServeur($processus): void
{
    if (is_resource($processus)) {
        proc_terminate($processus);
        proc_close($processus);
    }
}

function connecterHttp(string $base, string $email, string $motDePasse = MDP, ?string &$cookie = null, ?array &$entetes = null): string
{
    $cookie = null;
    [$code, $html] = http('GET', "$base/connexion", [], $cookie);
    egal(200, $code, 'formulaire de connexion');
    [$code, , $lieu, $entetes] = http('POST', "$base/connexion", [
        'email' => $email,
        'mot_de_passe' => $motDePasse,
        'csrf' => jetonCsrf($html),
    ], $cookie);
    egal(302, $code, "connexion $email");
    verifier($lieu !== '' && !str_contains($lieu, '/connexion'), "connexion refusée pour $email");
    [$code, $html] = http('GET', "$base/notifications", [], $cookie);
    egal(200, $code, 'page authentifiée');
    return jetonCsrf($html);
}

test('http_racine_assets_et_pages_erreur', function (Env $e) {
    [$serveur, $B] = demarrerServeur($e);
    try {
        [$code, $html] = http('GET', "$B/");
        egal(200, $code, 'racine');
        verifier(str_contains($html, 'SkillSwap'), 'accueil sans marque');
        verifier(str_contains($html, 'href="/css/skillswap-v4.css"'), 'lien CSS racine incorrect');
        egal(200, http('GET', "$B/index.php")[0], 'index.php');

        [$code, $css, , $entetes] = http('GET', "$B/css/skillswap-v4.css");
        egal(200, $code, 'CSS');
        verifier(strlen($css) > 500, 'CSS vide');
        $type = '';
        foreach ($entetes as $h) {
            if (preg_match('/^Content-Type:\s*(.+)$/i', $h, $m)) $type = strtolower(trim($m[1]));
        }
        verifier(str_contains($type, 'text/css'), 'type CSS : ' . $type);
        egal(200, http('GET', "$B/js/skillswap.js")[0], 'JS');

        [$code, $html] = http('GET', "$B/page-inexistante");
        egal(404, $code, 'page inconnue');
        verifier(str_contains($html, 'Page introuvable'), 'page 404 incomplète');

        [$code, $html, , $entetes] = http('GET', "$B/deconnexion");
        egal(405, $code, 'méthode non autorisée');
        verifier(str_contains($html, 'Méthode non autorisée'), 'page 405 incomplète');
        $allow = '';
        foreach ($entetes as $h) {
            if (preg_match('/^Allow:\s*(.+)$/i', $h, $m)) $allow = strtoupper(trim($m[1]));
        }
        egal('POST', $allow, 'en-tête Allow');

        foreach (['/config.php', '/src/bootstrap.php', '/data/skillswap.db', '/data/PREMIER_ACCES.txt',
                     '/database/schema.sql', '/tests/run.php', '/vues/gabarit.php', '/scripts/seed.php', '/.htaccess'] as $sensible) {
            egal(404, http('GET', $B . $sensible)[0], 'chemin sensible ' . $sensible);
        }
    } finally {
        arreterServeur($serveur);
    }
});

test('phase4_http_pwa_et_page_bienvenue', function (Env $e) {
    [$serveur, $B] = demarrerServeur($e);
    try {
        [$code, $html] = http('GET', "$B/");
        egal(200, $code, 'accueil PWA');
        verifier(str_contains($html, 'Bienvenue sur votre') && str_contains($html, 'SkillSwap Campus'), 'titre français de la page d’accueil absent en HTTP');
        verifier(!str_contains($html, 'Welcome to your'), 'titre anglais encore rendu en HTTP');
        verifier(str_contains($html, 'class="accueil-bienvenue"') && str_contains($html, 'id="bienvenue-titre"'), 'page d’accueil 3D absente en HTTP');
        verifier(!str_contains($html, 'aria-modal="true"') && !str_contains($html, 'role="dialog"'), 'ancien dialogue de bienvenue encore rendu en HTTP');
        verifier(!str_contains($html, 'Une heure donnée, une heure reçue.'), 'ancienne page d’accueil encore rendue en HTTP');
        verifier(!str_contains($html, 'accueil-public') && !str_contains($html, 'hero-public'), 'styles ou structure de l’ancienne page encore rendus');
        verifier(str_contains($html, 'Un échange, simplement') && str_contains($html, 'Proposez') && str_contains($html, 'Échangez') && str_contains($html, 'Progressez'), 'contenus de présentation absents en HTTP');
        verifier(str_contains($html, 'accueil-statistiques') && str_contains($html, 'heures échangées'), 'statistiques dynamiques absentes en HTTP');
        verifier(str_contains($html, 'de bienvenue.'), 'mention des heures de bienvenue absente en HTTP');
        verifier(str_contains($html, 'Passer à la connexion'), 'destination du bouton Passer absente en HTTP');
        verifier(str_contains($html, 'Télécharger l’application') && str_contains($html, 'class="bienvenue-apk"') && str_contains($html, 'download'), 'bouton de téléchargement de l’application absent en HTTP');
        [$code, $apk, , $entetesApk] = http('GET', "$B/apk/SkillSwap-Campus.apk");
        egal(200, $code, 'APK non téléchargeable');
        verifier(str_starts_with($apk, 'PK') && strlen($apk) > 50000, 'contenu APK invalide');
        verifier(str_contains(strtolower(implode("\n", $entetesApk)), 'content-type: application/vnd.android.package-archive'), 'type MIME APK incorrect');
        verifier(str_contains($html, 'rel="manifest" href="/manifest.webmanifest"'), 'manifeste non lié');
        verifier(!str_contains($html, 'premiere-visite') && !str_contains($html, 'skillswap-welcome-vu'), 'logique de première visite encore rendue');
        verifier(str_contains($html, 'data-base="/"'), 'base du service worker absente');
        verifier(str_contains($html, 'data-theme-toggle'), 'bouton de thème absent de la page d’accueil');
        verifier(str_contains($html, 'Créer un compte') && str_contains($html, 'Se connecter'), 'actions d’authentification absentes de la page d’accueil HTTP');
        verifier(str_contains($html, 'class="bienvenue-passer" href="/connexion"'), 'le bouton Passer ne mène pas vers l’authentification');

        [$code] = http('GET', "$B/accueil");
        egal(404, $code, 'ancienne route /accueil encore disponible');

        $cookie = null;
        connecterHttp($B, 'sara@test.ma', MDP, $cookie);
        [$code, , $lieu] = http('GET', "$B/", [], $cookie);
        egal(302, $code, 'racine pour un étudiant connecté');
        egal('/tableau-de-bord', $lieu, 'redirection d’un étudiant connecté');
        $cookieAdmin = null;
        $emailAdmin = (string) $e->db->valeur('SELECT email FROM utilisateur WHERE id = ?', [$e->admin]);
        $e->db->executer('UPDATE utilisateur SET mot_de_passe_hash = ? WHERE id = ?',
            [password_hash(MDP, PASSWORD_DEFAULT), $e->admin]);
        connecterHttp($B, $emailAdmin, MDP, $cookieAdmin);
        [$code, , $lieu] = http('GET', "$B/", [], $cookieAdmin);
        egal(302, $code, 'racine pour un administrateur connecté');
        egal('/admin', $lieu, 'redirection d’un administrateur connecté');

        [$code, $manifest, , $entetes] = http('GET', "$B/manifest.webmanifest");
        egal(200, $code, 'manifeste HTTP');
        $type = '';
        foreach ($entetes as $h) {
            if (preg_match('/^Content-Type:\s*(.+)$/i', $h, $m)) $type = strtolower(trim($m[1]));
        }
        verifier(str_contains($type, 'application/manifest+json'), 'type MIME du manifeste : ' . $type);
        $json = json_decode($manifest, true);
        verifier(is_array($json) && ($json['name'] ?? '') === 'SkillSwap Campus', 'manifeste HTTP illisible');
        verifier(count($json['icons'] ?? []) >= 3, 'icônes du manifeste HTTP absentes');

        [$code, $sw] = http('GET', "$B/sw.js");
        egal(200, $code, 'service worker HTTP');
        verifier(str_contains($sw, 'skillswap-public-v4') && str_contains($sw, 'offline.html'), 'service worker HTTP incomplet');

        [$code, $offline] = http('GET', "$B/offline.html");
        egal(200, $code, 'page hors ligne HTTP');
        verifier(str_contains($offline, 'hors ligne') && str_contains($offline, 'Réessayer'), 'page hors ligne HTTP incomplète');

        [$code, $png, , $entetes] = http('GET', "$B/icons/icon-192.png");
        egal(200, $code, 'icône HTTP');
        verifier(strlen($png) > 1000 && substr($png, 1, 3) === 'PNG', 'icône PNG invalide');
        $type = '';
        foreach ($entetes as $h) {
            if (preg_match('/^Content-Type:\s*(.+)$/i', $h, $m)) $type = strtolower(trim($m[1]));
        }
        verifier(str_contains($type, 'image/png'), 'type MIME de l’icône : ' . $type);
    } finally {
        arreterServeur($serveur);
    }
});

test('http_csrf_droits_et_rg19', function (Env $e) {
    [$oid, $c] = $e->offre('Sara', [$e->h(2)]);
    $e->db->executer("UPDATE creneau SET lieu = 'Salle secrète B12' WHERE id = ?", [$c[0]]);
    $rid = $e->resa->demander($e->u['Amine'], $c[0]);
    $e->resa->accepter($e->u['Sara'], $rid);
    [$serveur, $B] = demarrerServeur($e);
    try {
        $jeton = connecterHttp($B, 'amine@test.ma', MDP, $ck);
        [$code, $html] = http('POST', "$B/reservations/$rid/annuler", [], $ck);
        egal(400, $code, 'POST sans jeton CSRF');
        verifier(str_contains($html, 'Requête invalide'), 'page 400 incomplète');
        egal(400, http('POST', "$B/reservations/$rid/annuler", ['csrf' => 'faux'], $ck)[0], 'jeton CSRF faux');

        [$code, $html] = http('GET', "$B/admin", [], $ck);
        egal(403, $code, 'étudiant sur /admin');
        verifier(str_contains($html, 'Accès refusé'), 'page 403 incomplète');

        [$code, $html] = http('GET', "$B/reservations/$rid", [], $ck);
        egal(200, $code, 'détail participant');
        verifier(str_contains($html, 'Salle secrète B12'), 'lieu visible du participant');

        $j2 = connecterHttp($B, 'karim@test.ma', MDP, $ck2);
        egal(404, http('GET', "$B/reservations/$rid", [], $ck2)[0], 'séance d’un autre');
        [$code, $html] = http('GET', "$B/offres/$oid", [], $ck2);
        egal(200, $code, 'fiche visible');
        verifier(!str_contains($html, 'Salle secrète B12'), 'lieu caché sur la fiche (RG19)');
        verifier(in_array(http('POST', "$B/reservations/$rid/accepter", ['csrf' => $j2], $ck2)[0], [403, 404], true), 'accepter à la place');

        $anonyme = null;
        [$code, , $lieu] = http('GET', "$B/portefeuille", [], $anonyme);
        verifier($code === 302 && str_contains($lieu, '/connexion'), 'visiteur redirigé');
        [, $html] = http('GET', "$B/connexion", [], $anonyme);
        [, , $lieu] = http('POST', "$B/connexion?suivant=//pirate.example", [
            'email' => 'amine@test.ma', 'mot_de_passe' => MDP, 'csrf' => jetonCsrf($html),
        ], $anonyme);
        verifier(!str_contains($lieu, 'pirate'), 'redirection ouverte');
    } finally {
        arreterServeur($serveur);
    }
});

test('http_deconnexion_detruit_la_session', function (Env $e) {
    [$serveur, $B] = demarrerServeur($e);
    try {
        $jeton = connecterHttp($B, 'amine@test.ma', MDP, $ck, $entetesConnexion);
        $cookieConnexion = '';
        foreach ($entetesConnexion as $h) {
            if (preg_match('/^Set-Cookie:\s*(.+)$/i', $h, $m)) $cookieConnexion .= $m[1] . ' ';
        }
        verifier(stripos($cookieConnexion, 'HttpOnly') !== false, 'cookie sans HttpOnly');
        verifier(stripos($cookieConnexion, 'SameSite=Lax') !== false, 'cookie sans SameSite=Lax');
        verifier(stripos($cookieConnexion, 'Secure') === false, 'cookie Secure sur HTTP local');
        [$code, , $lieu] = http('GET', "$B/", [], $ck);
        verifier($code === 302 && str_contains($lieu, '/tableau-de-bord'), 'racine connectée');

        $avant = $ck;
        [$code, , $lieu, $entetes] = http('POST', "$B/deconnexion", ['csrf' => $jeton], $ck);
        egal(302, $code, 'déconnexion');
        verifier(str_contains($lieu, '/connexion'), 'redirection après déconnexion');
        $supprime = false;
        foreach ($entetes as $h) {
            if (preg_match('/^Set-Cookie:\s*skillswap=deleted/i', $h)
                || preg_match('/^Set-Cookie:\s*skillswap=;.*Max-Age=0/i', $h)) {
                $supprime = true;
            }
        }
        verifier($supprime, 'cookie de session non supprimé');
        verifier($ck !== $avant, 'session non renouvelée après déconnexion');
        [$code, , $lieu] = http('GET', "$B/tableau-de-bord", [], $ck);
        verifier($code === 302 && str_contains($lieu, '/connexion'), 'session encore active après déconnexion');
    } finally {
        arreterServeur($serveur);
    }
});

test('http_changement_mot_de_passe_admin', function (Env $e) {
    $e->db->executer('UPDATE utilisateur SET mot_de_passe_hash = ? WHERE id = ?',
        [password_hash(MDP, PASSWORD_DEFAULT), $e->admin]);
    [$serveur, $B] = demarrerServeur($e);
    try {
        $jeton = connecterHttp($B, 'admin@skillswap.ma', MDP, $ck);
        egal(200, http('GET', "$B/admin", [], $ck)[0], 'tableau administrateur');
        [$code, $html] = http('GET', "$B/admin/mot-de-passe", [], $ck);
        egal(200, $code, 'écran mot de passe');
        verifier(str_contains($html, 'Mot de passe administrateur'), 'écran mot de passe absent');
        $csrf = jetonCsrf($html);

        [$code, $html] = http('POST', "$B/admin/mot-de-passe", [
            'csrf' => $csrf, 'actuel' => 'Mauvais123', 'nouveau' => 'Nouveau123', 'confirmation' => 'Nouveau123',
        ], $ck);
        egal(200, $code, 'mauvais mot de passe actuel');
        verifier(str_contains($html, 'Mot de passe actuel incorrect'), 'message mot de passe actuel absent');

        $ancienCookie = $ck;
        [$code, , $lieu] = http('POST', "$B/admin/mot-de-passe", [
            'csrf' => $csrf, 'actuel' => MDP, 'nouveau' => 'Nouveau123', 'confirmation' => 'Nouveau123',
        ], $ck);
        egal(302, $code, 'changement de mot de passe');
        verifier(str_contains($lieu, '/admin'), 'redirection après changement');
        verifier($ck !== $ancienCookie, 'session non régénérée après changement de mot de passe');

        $anonyme = null;
        [, $html] = http('GET', "$B/connexion", [], $anonyme);
        [$code, $html] = http('POST', "$B/connexion", [
            'email' => 'admin@skillswap.ma', 'mot_de_passe' => MDP, 'csrf' => jetonCsrf($html),
        ], $anonyme);
        egal(200, $code, 'ancien mot de passe encore accepté');
        verifier(str_contains($html, 'Identifiants incorrects'), 'message ancien mot de passe absent');

        $anonyme = null;
        $jeton = connecterHttp($B, 'admin@skillswap.ma', 'Nouveau123', $anonyme);
        verifier($jeton !== '', 'connexion avec le nouveau mot de passe');
    } finally {
        arreterServeur($serveur);
    }
});

/* ------------------------------------------------------------------ Exécution */
$filtre = $argv[1] ?? '';
$ok = $ko = 0;
$debut = microtime(true);
foreach ($tests as $nom => $f) {
    if ($filtre && !str_contains($nom, $filtre)) continue;
    $env = new Env($T0);
    try {
        $f($env);
        $ok++;
        echo "  \033[32m✓\033[0m $nom\n";
    } catch (Throwable $e) {
        $ko++;
        echo "  \033[31m✗ $nom\033[0m\n      " . get_class($e) . ' : ' . $e->getMessage() . "\n";
    } finally {
        $env->nettoyer();
    }
}
printf("\n%d test(s) réussi(s), %d échec(s) en %.1f s\n", $ok, $ko, microtime(true) - $debut);
exit($ko ? 1 : 0);
