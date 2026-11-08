<?php
declare(strict_types=1);

final class AdminControleur extends Controleur
{
    public function tableau(): never
    {
        Securite::exigerAdmin();
        vue('admin/tableau', ['titre' => 'Administration', 's' => $this->svcAdmin()->tableauDeBord(),
            'litiges' => $this->svcAdmin()->litigesOuverts(), 'aTraiter' => $this->svcAdmin()->reservationsATraiter(),
            'incoherences' => $this->svcAdmin()->verifierCoherence()]);
    }

    public function comptes(): never
    {
        Securite::exigerAdmin();
        vue('admin/comptes', ['titre' => 'Comptes', 'q' => $this->get('q'), 'comptes' => $this->svcAdmin()->comptes($this->get('q'))]);
    }

    public function motDePasse(): never
    {
        $u = Securite::exigerAdmin();
        $erreurs = [];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $valeurs = [
                'actuel' => $this->post('actuel') ?? '',
                'nouveau' => $this->post('nouveau') ?? '',
                'confirmation' => $this->post('confirmation') ?? '',
            ];
            $erreurs = Validation::formulaire($valeurs, [
                'actuel' => ['libelle' => 'Mot de passe actuel'],
                'nouveau' => ['libelle' => 'Nouveau mot de passe', 'mot_de_passe' => true],
                'confirmation' => ['libelle' => 'Confirmation', 'egal_a' => 'nouveau',
                    'message_egal' => 'Les deux mots de passe ne correspondent pas.'],
            ]);
            if ($erreurs === []) {
                try {
                    $this->svcComptes()->changerMotDePasse((int) $u['id'], $this->post('actuel'),
                        $this->post('nouveau'), $this->post('confirmation'));
                    Securite::regenererSession();
                    flash('succes', 'Mot de passe administrateur modifié.');
                    rediriger('/admin');
                } catch (ErreurMetier $e) {
                    $champ = str_contains($e->getMessage(), 'actuel') ? 'actuel' : '_form';
                    $erreurs[$champ] = $e->getMessage();
                }
            }
        }
        vue('admin/mot_de_passe', ['titre' => 'Mot de passe administrateur', 'erreurs' => $erreurs]);
    }

    public function basculerCompte(string $id): never
    {
        $u = Securite::exigerAdmin();
        [$ok, $etat] = $this->tenter(fn() => $this->svcAdmin()->basculerCompte((int) $u['id'], (int) $id));
        if ($ok) {
            flash('succes', "Compte $etat." . ($etat === 'désactivé' ? ' Ses demandes en attente ont été annulées ; '
                . 'vérifiez les séances acceptées à traiter sur le tableau de bord.' : ''));
        }
        rediriger('/admin/comptes');
    }

    public function categories(): never
    {
        Securite::exigerAdmin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = (int) $this->post('id');
            match ($this->post('action')) {
                'creer' => $this->tenter(fn() => $this->svcCatalogue()->creerCategorie($this->post('nom'), $this->post('description'),
                    $this->post('couleur')), 'Catégorie créée.'),
                'modifier' => $this->tenter(fn() => $this->svcCatalogue()->modifierCategorie($id, $this->post('nom'),
                    $this->post('description'), $this->post('couleur')), 'Catégorie modifiée.'),
                'basculer' => $this->tenter(fn() => $this->svcCatalogue()->basculerCategorie($id), 'Visibilité de la catégorie modifiée.'),
                'competence' => $this->tenter(fn() => $this->svcCatalogue()->ajouterCompetence($id, $this->post('nom')), 'Compétence ajoutée.'),
                default => throw new ErreurHttp(400),
            };
            rediriger('/admin/categories');
        }
        vue('admin/categories', ['titre' => 'Catégories', 'categories' => $this->svcCatalogue()->categories(),
            'comps' => $this->svcCatalogue()->competencesIndexees()]);
    }

    public function offres(): never
    {
        Securite::exigerAdmin();
        $filtre = in_array($this->get('filtre'), ['publiees', 'masquees', 'toutes'], true) ? $this->get('filtre') : 'publiees';
        vue('admin/offres', ['titre' => 'Modération', 'filtre' => $filtre, 'offres' => $this->svcAdmin()->offres($filtre)]);
    }

    public function moderer(string $id, string $action): never
    {
        Securite::exigerAdmin();
        match ($action) {
            'masquer' => $this->tenter(fn() => $this->svcAdmin()->masquerOffre((int) $id, $this->post('motif')),
                "Offre masquée : elle n'accepte plus de demandes."),
            'reafficher' => $this->tenter(fn() => $this->svcAdmin()->reafficherOffre((int) $id), 'Offre de nouveau visible.'),
            default => throw new ErreurHttp(404),
        };
        $this->retour('/admin/offres');
    }

    public function litiges(): never
    {
        Securite::exigerAdmin();
        vue('admin/litiges', ['titre' => 'Litiges', 'litiges' => $this->svcLitiges()->tous()]);
    }

    public function decider(string $id): never
    {
        $u = Securite::exigerAdmin();
        $this->tenter(fn() => $this->svcLitiges()->decider((int) $u['id'], (int) $id, $this->post('decision'), $this->post('justification')),
            'Décision enregistrée et appliquée. Le règlement est définitif.');
        rediriger("/litiges/$id");
    }

    public function ouvrirDossier(string $id): never
    {
        $u = Securite::exigerAdmin();
        [$ok, $lid] = $this->tenter(fn() => $this->svcLitiges()->ouvrirDossierAdmin((int) $u['id'], (int) $id, $this->post('description')),
            'Dossier ouvert : vous pouvez maintenant décider du règlement.');
        rediriger($ok ? "/litiges/$lid" : '/admin');
    }

    public function horloge(): never
    {
        Securite::exigerAdmin();
        $this->tenter(fn() => $this->svcAdmin()->reglerDecalageHorloge($this->post('minutes')), 'Horloge de démonstration réglée.');
        $this->retour('/admin');
    }
}
