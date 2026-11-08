<?php
declare(strict_types=1);

final class OffresControleur extends Controleur
{
    public function catalogue(): never
    {
        Securite::exigerConnexion();
        $f = ['q' => $this->get('q'), 'categorie' => (int) $this->get('categorie') ?: null,
            'niveau' => $this->get('niveau'), 'mode' => $this->get('mode'), 'date' => $this->get('date')];
        vue('offres/catalogue', ['titre' => 'Catalogue', 'resultats' => $this->svcOffres()->rechercher($f), 'f' => $f,
            'categories' => $this->svcCatalogue()->categories(true), 'rechercheActive' => (bool) array_filter($f)]);
    }

    public function suggestions(): never
    {
        $u = Securite::exigerEtudiant();
        vue('offres/suggestions', ['titre' => 'Pour moi', 'suggestions' => $this->svcOffres()->suggestions((int) $u['id'], 30),
            'recherchees' => $this->svcComptes()->competencesDe((int) $u['id'], 'RECHERCHEE')]);
    }

    public function fiche(string $id): never
    {
        $u = Securite::exigerConnexion();
        $o = $this->svcOffres()->offre((int) $id);
        if ($o === null) {
            throw new ErreurHttp(404);
        }
        $proprietaire = (int) $o['intervenant_id'] === (int) $u['id'];
        $visible = $o['etat'] === 'PUBLIEE' && !(int) $o['masquee'] && (int) $o['intervenant_actif'];
        if (!($visible || $proprietaire || $u['role'] === 'ADMIN')) {
            throw new ErreurHttp(404);
        }
        $mesDemandes = [];
        if ($u['role'] === 'ETUDIANT' && !$proprietaire) {
            foreach ($this->svcReservations()->demandesEnvoyees((int) $u['id']) as $r) {
                if (in_array($r['etat'], ['EN_ATTENTE', 'ACCEPTEE'], true)) {
                    $mesDemandes[(int) $r['creneau_id']] = $r;
                }
            }
        }
        vue('offres/fiche', [
            'titre' => $o['titre'], 'o' => $o, 'proprietaire' => $proprietaire, 'visible' => $visible,
            'creneaux' => $this->svcOffres()->creneauxOffre((int) $id), 'mesDemandes' => $mesDemandes,
            'note' => $this->svcComptes()->noteMoyenne((int) $o['intervenant_id']),
            'avis' => array_slice($this->svcEvaluations()->recues((int) $o['intervenant_id']), 0, 3),
            'manque' => ServiceOffres::champsManquants($o, $this->svcOffres()->creneauxFutursLibres((int) $id)),
            'maintenant' => Horloge::maintenantTxt($this->db),
            'solde' => $u['role'] === 'ETUDIANT' ? $this->svcComptes()->portefeuille((int) $u['id']) : null,
        ]);
    }

    public function mesOffres(): never
    {
        $u = Securite::exigerEtudiant();
        vue('offres/mes_offres', ['titre' => 'Mes offres', 'offres' => $this->svcOffres()->offresDe((int) $u['id'])]);
    }

    public function nouvelle(): never
    {
        $this->formulaire(null);
    }

    public function modifier(string $id): never
    {
        $this->formulaire((int) $id);
    }

    private function formulaire(?int $id): never
    {
        $u = Securite::exigerEtudiant();
        $o = $id ? $this->svcOffres()->offre($id) : [];
        if ($id && ($o === null || (int) $o['intervenant_id'] !== (int) $u['id'])) {
            throw new ErreurHttp(404);
        }
        $erreurs = [];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $valeurs = [];
            foreach (['titre', 'competence_id', 'description', 'niveau', 'duree', 'mode'] as $cle) {
                $valeur = $_POST[$cle] ?? '';
                $valeurs[$cle] = is_scalar($valeur) ? (string) $valeur : '';
            }
            $erreurs = Validation::formulaire($valeurs, [
                'titre' => ['libelle' => 'Titre', 'min' => 3, 'max' => 80],
                'description' => ['libelle' => 'Description', 'obligatoire' => false, 'max' => 1500],
            ]);
            if (array_key_exists('competence_id', $_POST) && $valeurs['competence_id'] !== ''
                && !ctype_digit($valeurs['competence_id'])) {
                $erreurs['competence_id'] = 'Choisissez une compétence dans la liste.';
            }
            if ($valeurs['niveau'] !== '' && !isset(Libelles::NIVEAUX[$valeurs['niveau']])) {
                $erreurs['niveau'] = 'Choisissez un niveau valide.';
            }
            if ($valeurs['duree'] !== '' && !in_array($valeurs['duree'], ['1', '2'], true)) {
                $erreurs['duree'] = 'Choisissez une durée d’une ou deux heures.';
            }
            if ($valeurs['mode'] !== '' && !isset(Libelles::MODES[$valeurs['mode']])) {
                $erreurs['mode'] = 'Choisissez un mode de déroulement valide.';
            }
            if ($erreurs === []) {
                [$ok, $nid] = $this->tenter(fn() => $this->svcOffres()->enregistrerOffre((int) $u['id'], $_POST, $id), 'Offre enregistrée.');
                if ($ok) {
                    rediriger("/offres/$nid");
                }
            }
            $o = array_merge($o ?: [], $valeurs);
        }
        vue('offres/formulaire', ['titre' => $id ? "Modifier l'offre" : 'Nouvelle offre', 'o' => $o, 'id' => $id,
            'groupes' => $this->svcCatalogue()->competencesParCategorie(), 'erreurs' => $erreurs]);
    }

    public function publier(string $id): never
    {
        $u = Securite::exigerEtudiant();
        $this->tenter(fn() => $this->svcOffres()->publierOffre((int) $u['id'], (int) $id),
            'Offre publiée : elle apparaît maintenant dans le catalogue.');
        rediriger("/offres/$id");
    }

    public function archiver(string $id): never
    {
        $u = Securite::exigerEtudiant();
        $this->tenter(fn() => $this->svcOffres()->archiverOffre((int) $u['id'], (int) $id),
            'Offre archivée. Les demandes en attente ont été annulées ; les séances acceptées sont maintenues.');
        rediriger("/offres/$id");
    }

    public function ajouterCreneau(string $id): never
    {
        $u = Securite::exigerEtudiant();
        $this->tenter(fn() => $this->svcOffres()->ajouterCreneau((int) $u['id'], (int) $id, $this->post('debut'), $this->post('fin'),
            $this->post('lieu'), $this->post('lien')), 'Créneau ajouté.');
        rediriger("/offres/$id#creneaux");
    }

    public function supprimerCreneau(string $id): never
    {
        $u = Securite::exigerEtudiant();
        $offre = $this->db->valeur('SELECT offre_id FROM creneau WHERE id = ?', [(int) $id]);
        $this->tenter(fn() => $this->svcOffres()->supprimerCreneau((int) $u['id'], (int) $id), 'Créneau retiré.');
        rediriger($offre ? "/offres/$offre#creneaux" : '/mes-offres');
    }
}
