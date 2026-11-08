<?php
declare(strict_types=1);

final class AuthControleur extends Controleur
{
    public function accueil(): never
    {
        $u = Securite::utilisateur();
        if ($u) {
            rediriger($u['role'] === 'ADMIN' ? '/admin' : '/tableau-de-bord');
        }
        $this->afficherBienvenue();
    }

    /** Page d’accueil publique consultable même lorsqu’une session est ouverte. */
    public function bienvenue(): never
    {
        $this->afficherBienvenue();
    }

    private function afficherBienvenue(): never
    {
        vue('auth/bienvenue', ['titre' => 'Bienvenue', 'pleinEcran' => true,
            'stats' => $this->svcAdmin()->tableauDeBord(),
            'vitrine' => array_slice($this->svcOffres()->rechercher(), 0, 3)]);
    }

    public function inscription(): never
    {
        if (Securite::utilisateur()) {
            rediriger('/');
        }
        $valeurs = [
            'prenom' => $this->post('prenom') ?? '',
            'nom' => $this->post('nom') ?? '',
            'email' => $this->post('email') ?? '',
            'filiere' => $this->post('filiere') ?? '',
            'mot_de_passe' => $this->post('mot_de_passe') ?? '',
            'confirmation' => $this->post('confirmation') ?? '',
        ];
        $erreurs = [];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $erreurs = Validation::formulaire($valeurs, [
                'prenom' => ['libelle' => 'Prénom', 'min' => 2, 'max' => 50],
                'nom' => ['libelle' => 'Nom', 'min' => 2, 'max' => 50],
                'email' => ['libelle' => 'Adresse électronique', 'email' => true, 'max' => 190],
                'filiere' => ['libelle' => 'Filière', 'min' => 2, 'max' => 60],
                'mot_de_passe' => ['libelle' => 'Mot de passe', 'mot_de_passe' => true],
                'confirmation' => ['libelle' => 'Confirmation', 'egal_a' => 'mot_de_passe',
                    'message_egal' => 'Les deux mots de passe ne correspondent pas.'],
            ]);
            if ($erreurs === []) {
                [$ok, $uid] = $this->tenter(fn() => $this->svcComptes()->inscrire($this->post('nom'), $this->post('prenom'),
                    $this->post('email'), $this->post('mot_de_passe'), $this->post('confirmation'), $this->post('filiere')));
                if ($ok) {
                    Securite::connecter($this->svcComptes()->utilisateur($uid));
                    flash('succes', 'Compte créé ! Deux heures de crédit vous attendent. Suivez les étapes pour démarrer.');
                    rediriger('/tableau-de-bord');
                }
            }
        }
        vue('auth/inscription', ['titre' => 'Inscription', 'f' => $valeurs, 'erreurs' => $erreurs]);
    }

    public function connexion(): never
    {
        if (Securite::utilisateur()) {
            rediriger('/');
        }
        $email = $this->post('email') ?? '';
        $erreurs = [];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $erreurs = Validation::formulaire(
                ['email' => $email, 'mot_de_passe' => $this->post('mot_de_passe') ?? ''],
                [
                    'email' => ['libelle' => 'Adresse électronique', 'email' => true, 'max' => 190],
                    'mot_de_passe' => ['libelle' => 'Mot de passe'],
                ]
            );
            if ($erreurs === []) {
                [$ok, $u] = $this->tenter(fn() => $this->svcComptes()->authentifier($this->post('email'), $this->post('mot_de_passe')));
                if ($ok) {
                    Securite::connecter($u);
                    flash('succes', "Bonjour {$u['prenom']} !");
                    $suivant = chemin_interne($this->get('suivant'), '/');
                    rediriger($suivant);
                }
            }
        }
        vue('auth/connexion', ['titre' => 'Connexion', 'email' => $email, 'erreurs' => $erreurs]);
    }

    public function deconnexion(): never
    {
        Securite::deconnecter();
        rediriger('/connexion?deconnecte=1');
    }
}
