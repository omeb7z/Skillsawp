<?php
declare(strict_types=1);

final class EtudiantControleur extends Controleur
{
    public function tableau(): never
    {
        $u = Securite::exigerEtudiant();
        $uid = (int) $u['id'];
        $maintenant = Horloge::maintenantTxt($this->db);
        $planning = $this->svcReservations()->planning($uid);
        $recherchees = $this->svcComptes()->competencesDe($uid, 'RECHERCHEE');
        $proposees = $this->svcComptes()->competencesDe($uid, 'PROPOSEE');
        $offres = $this->svcOffres()->offresDe($uid);
        $envoyees = $this->svcReservations()->demandesEnvoyees($uid);
        $etapes = [
            ['titre' => 'Ce que vous voulez apprendre', 'texte' => 'Choisissez quelques compétences pour recevoir des suggestions utiles.',
                'lien' => '/profil#recherchees', 'action' => 'Choisir mes envies', 'fait' => $recherchees !== []],
            ['titre' => 'Ce que vous pouvez transmettre', 'texte' => 'Dites ce que vous savez expliquer : c’est la base de vos futures offres.',
                'lien' => '/profil#proposees', 'action' => 'Indiquer mes talents', 'fait' => $proposees !== []],
            ['titre' => 'Publier une première offre', 'texte' => 'Décrivez une séance, ajoutez un créneau puis publiez-la.',
                'lien' => '/offres/nouvelle', 'action' => 'Créer une offre',
                'fait' => count(array_filter($offres, fn($o) => $o['etat'] === 'PUBLIEE')) > 0],
            ['titre' => 'Réserver une séance', 'texte' => 'Envoyez une demande : vos heures ne sont bloquées qu’une fois acceptée.',
                'lien' => '/catalogue', 'action' => 'Explorer le catalogue', 'fait' => $envoyees !== []],
        ];
        $faites = count(array_filter($etapes, fn($e) => $e['fait']));
        vue('etudiant/tableau', [
            'titre' => 'Accueil',
            'prochaines' => array_slice(array_values(array_filter($planning, fn($s) => $s['fin'] > $maintenant)), 0, 3),
            'aConfirmer' => array_values(array_filter($planning, fn($s) => $s['etat'] === 'ACCEPTEE'
                && $s['fin'] <= $maintenant && !$s['j_ai_confirme'])),
            'enAttente' => array_values(array_filter($this->svcReservations()->demandesRecues($uid), fn($d) => $d['etat'] === 'EN_ATTENTE')),
            'suggestions' => $this->svcOffres()->suggestions($uid, 3),
            'recherchees' => $recherchees,
            'note' => $this->svcComptes()->noteMoyenne($uid),
            'onboarding' => ['etapes' => $etapes, 'faites' => $faites, 'total' => count($etapes), 'termine' => $faites === count($etapes)],
        ]);
    }

    public function profil(): never
    {
        $u = Securite::exigerEtudiant();
        $uid = (int) $u['id'];
        $proposees = array_map('intval', array_column($this->svcComptes()->competencesDe($uid, 'PROPOSEE'), 'id'));
        $recherchees = array_map('intval', array_column($this->svcComptes()->competencesDe($uid, 'RECHERCHEE'), 'id'));
        $erreurs = [];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $valeurs = [
                'prenom' => $this->post('prenom') ?? '',
                'nom' => $this->post('nom') ?? '',
                'filiere' => $this->post('filiere') ?? '',
                'bio' => $this->post('bio') ?? '',
            ];
            $erreurs = Validation::formulaire($valeurs, [
                'prenom' => ['libelle' => 'Prénom', 'min' => 2, 'max' => 50],
                'nom' => ['libelle' => 'Nom', 'min' => 2, 'max' => 50],
                'filiere' => ['libelle' => 'Filière', 'min' => 2, 'max' => 60],
                'bio' => ['libelle' => 'Présentation', 'obligatoire' => false, 'max' => 400],
            ]);
            if ($erreurs === []) {
                [$ok] = $this->tenter(fn() => $this->svcComptes()->modifierProfil($uid, $this->post('nom'), $this->post('prenom'),
                    $this->post('filiere'), $this->post('bio'), $this->postListe('proposees'), $this->postListe('recherchees')),
                    'Profil mis à jour.');
                if ($ok) {
                    rediriger('/profil');
                }
            }
            $u = array_merge($u, $valeurs);
            $filtrerIds = fn(string $cle): array => array_values(array_filter(array_map(
                fn($id) => is_scalar($id) ? (int) $id : 0,
                $this->postListe($cle)
            ), fn(int $id) => $id > 0));
            $proposees = $filtrerIds('proposees');
            $recherchees = $filtrerIds('recherchees');
        }
        vue('etudiant/profil', [
            'titre' => 'Mon profil',
            'u' => $u,
            'groupes' => $this->svcCatalogue()->competencesParCategorie(),
            'proposees' => $proposees,
            'recherchees' => $recherchees,
            'erreurs' => $erreurs,
        ]);
    }

    public function motDePasse(): never
    {
        $u = Securite::exigerConnexion();
        [$ok] = $this->tenter(fn() => $this->svcComptes()->changerMotDePasse((int) $u['id'], $this->post('actuel'),
            $this->post('nouveau'), $this->post('confirmation')), 'Mot de passe modifié.');
        if ($ok) {
            Securite::regenererSession();
        }
        $this->retour('/');
    }

    public function profilPublic(string $id): never
    {
        Securite::exigerConnexion();
        $uid = (int) $id;
        $p = $this->db->un("SELECT id, nom, prenom, filiere, bio, cree_le, actif FROM utilisateur WHERE id = ? AND role = 'ETUDIANT'", [$uid]);
        if ($p === null) {
            throw new ErreurHttp(404);
        }
        vue('etudiant/profil_public', [
            'titre' => $p['prenom'] . ' ' . $p['nom'], 'p' => $p,
            'offres' => array_values(array_filter($this->svcOffres()->rechercher(), fn($o) => (int) $o['intervenant_id'] === $uid)),
            'note' => $this->svcComptes()->noteMoyenne($uid),
            'evaluations' => $this->svcEvaluations()->recues($uid),
            'proposees' => $this->svcComptes()->competencesDe($uid, 'PROPOSEE'),
            'recherchees' => $this->svcComptes()->competencesDe($uid, 'RECHERCHEE'),
        ]);
    }

    public function portefeuille(): never
    {
        $u = Securite::exigerEtudiant();
        $mouvements = $this->svcComptes()->historique((int) $u['id']);
        // Solde « après opération » ligne par ligne, du plus ancien au plus récent (effet livret)
        $total = 0;
        $soldes = [];
        foreach (array_reverse($mouvements) as $m) {
            $total += (int) $m['effet_disponible'];
            $soldes[$m['id']] = $total;
        }
        $somme = fn(string $type) => array_sum(array_map(fn($m) => $m['type'] === $type ? (int) $m['quantite'] : 0, $mouvements));
        vue('etudiant/portefeuille', ['titre' => 'Portefeuille', 'mouvements' => $mouvements, 'soldes' => $soldes,
            'gagnes' => $somme('TRANSFERT_ENTRANT'), 'depenses' => $somme('TRANSFERT_SORTANT')]);
    }

    public function planning(): never
    {
        $u = Securite::exigerEtudiant();
        $jours = [];
        foreach ($this->svcReservations()->planning((int) $u['id']) as $s) {
            $jours[substr($s['debut'], 0, 10)][] = $s;
        }
        vue('etudiant/planning', ['titre' => 'Planning', 'jours' => $jours]);
    }

    public function notifications(): never
    {
        $u = Securite::exigerConnexion();
        vue('etudiant/notifications', ['titre' => 'Notifications', 'liste' => $this->db->tous(
            'SELECT * FROM notification WHERE destinataire_id = ? ORDER BY id DESC LIMIT 100', [$u['id']])]);
    }

    public function lireNotifications(): never
    {
        $u = Securite::exigerConnexion();
        $this->db->transaction(fn() => $this->db->executer('UPDATE notification SET lue = 1 WHERE destinataire_id = ?', [$u['id']]));
        rediriger('/notifications');
    }

    public function ouvrirNotification(string $id): never
    {
        $u = Securite::exigerConnexion();
        $n = $this->db->un('SELECT * FROM notification WHERE id = ? AND destinataire_id = ?', [(int) $id, $u['id']]);
        if ($n === null) {
            throw new ErreurHttp(404);
        }
        $this->db->transaction(fn() => $this->db->executer('UPDATE notification SET lue = 1 WHERE id = ?', [(int) $id]));
        rediriger(chemin_interne(is_string($n['lien'] ?? null) ? $n['lien'] : '', '/notifications'));
    }
}
