<?php
declare(strict_types=1);

/** Comptes, profils, portefeuille (RG01, RG16, RG18). */
final class ServiceComptes extends Service
{
    public const CREDITS_BIENVENUE = 2;
    private const MAX_ECHECS_EMAIL = 5;
    private const MAX_ECHECS_IP = 20;
    private const FENETRE_MINUTES = 15;
    private static ?string $hashFactice = null;

    private function validerMotDePasse(?string $mdp, ?string $confirmation = null, bool $verifierConf = true): void
    {
        $mdp = (string) $mdp;
        if (strlen($mdp) < 8 || !preg_match('/[A-Za-z]/', $mdp) || !preg_match('/\d/', $mdp)) {
            throw new ErreurMetier('Le mot de passe doit contenir au moins 8 caractères, dont une lettre et un chiffre.');
        }
        if ($verifierConf && $mdp !== (string) $confirmation) {
            throw new ErreurMetier('Les deux mots de passe ne correspondent pas.');
        }
    }

    /** Adresse IP tronquée : suffisante pour le quota, jamais affichée. */
    private function adresseIp(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';
        return substr(is_string($ip) ? $ip : 'cli', 0, 45);
    }

    private static function hashFactice(): string
    {
        // Un vrai hash pour que la vérification d'un compte inconnu coûte le
        // même temps qu'un compte existant (limite l'énumération par chronométrage).
        return self::$hashFactice ??= password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
    }

    private function verifierLimiteConnexion(string $email, string $ip): void
    {
        $depuis = Horloge::texte(Horloge::maintenant()->modify('-' . self::FENETRE_MINUTES . ' minutes'));
        $parEmail = (int) ($this->db->valeur(
            'SELECT COUNT(*) FROM tentative_connexion WHERE email = ? AND reussi = 0 AND cree_le >= ?',
            [$email, $depuis]
        ) ?? 0);
        $parIp = (int) ($this->db->valeur(
            'SELECT COUNT(*) FROM tentative_connexion WHERE ip = ? AND reussi = 0 AND cree_le >= ?',
            [$ip, $depuis]
        ) ?? 0);
        if ($parEmail >= self::MAX_ECHECS_EMAIL || $parIp >= self::MAX_ECHECS_IP) {
            throw new ErreurMetier(
                'Trop de tentatives de connexion. Réessayez dans ' . self::FENETRE_MINUTES . ' minutes.',
                'RG01'
            );
        }
    }

    private function enregistrerEchecConnexion(string $email, string $ip): void
    {
        $this->db->transaction(function () use ($email, $ip): void {
            $maintenant = Horloge::maintenant();
            $this->db->executer('DELETE FROM tentative_connexion WHERE cree_le < ?', [
                Horloge::texte($maintenant->modify('-1 day')),
            ]);
            $this->db->executer(
                'INSERT INTO tentative_connexion(email, ip, reussi, cree_le) VALUES (?, ?, 0, ?)',
                [$email, $ip, Horloge::texte($maintenant)]
            );
        });
    }

    /** Crée un compte et attribue les 2 crédits de bienvenue une seule fois (RG01). */
    public function inscrire(?string $nom, ?string $prenom, ?string $email, ?string $mdp, ?string $confirmation,
                             ?string $filiere, string $role = 'ETUDIANT'): int
    {
        $nom = $this->texte($nom, 'Nom', 2, 50);
        $prenom = $this->texte($prenom, 'Prénom', 2, 50);
        $email = mb_strtolower(trim((string) $email));
        $this->exiger(filter_var($email, FILTER_VALIDATE_EMAIL) !== false, 'Adresse électronique invalide.');
        $filiere = $this->texte($filiere, 'Filière', 2, 60, $role === 'ETUDIANT');
        $this->validerMotDePasse($mdp, $confirmation);

        return $this->db->transaction(function () use ($nom, $prenom, $email, $mdp, $filiere, $role) {
            $this->exiger(!$this->db->valeur('SELECT 1 FROM utilisateur WHERE email = ?', [$email]),
                'Cette adresse électronique est déjà utilisée.', 'RG01');
            try {
                $uid = $this->db->inserer(
                    'INSERT INTO utilisateur(nom, prenom, email, mot_de_passe_hash, role, filiere, cree_le) VALUES (?,?,?,?,?,?,?)',
                    [$nom, $prenom, $email, password_hash((string) $mdp, PASSWORD_DEFAULT), $role, $filiere, $this->maintenant()]);
            } catch (PDOException) {
                throw new ErreurMetier('Cette adresse électronique est déjà utilisée.', 'RG01');
            }
            if ($role === 'ETUDIANT') {
                $this->db->executer('INSERT INTO portefeuille(etudiant_id) VALUES (?)', [$uid]);
                $this->mouvement($uid, 'BIENVENUE', self::CREDITS_BIENVENUE, null, 'Crédits de bienvenue');
                $this->db->executer('UPDATE portefeuille SET bienvenue_attribuee = 1 WHERE etudiant_id = ?', [$uid]);
                $this->notifier($uid, 'Bienvenue sur SkillSwap Campus ! ' . self::CREDITS_BIENVENUE
                    . ' heures vous ont été offertes.', '/portefeuille');
            }
            return $uid;
        });
    }

    /** Message identique pour un courriel inconnu et un mauvais mot de passe. */
    public function authentifier(?string $email, ?string $mdp): array
    {
        $email = mb_strtolower(trim((string) $email));
        $ip = $this->adresseIp();
        $this->verifierLimiteConnexion($email, $ip);

        $u = $this->db->un('SELECT * FROM utilisateur WHERE email = ?', [$email]);
        $hash = $u['mot_de_passe_hash'] ?? self::hashFactice();
        $correct = password_verify((string) $mdp, (string) $hash);
        if ($u === null || !$correct) {
            $this->enregistrerEchecConnexion($email, $ip);
            throw new ErreurMetier('Identifiants incorrects.');
        }
        if (!(int) $u['actif']) {
            throw new ErreurMetier("Ce compte est désactivé. Contactez l'administration.", 'RG18');
        }
        if (password_needs_rehash((string) $u['mot_de_passe_hash'], PASSWORD_DEFAULT)) {
            $this->db->executer('UPDATE utilisateur SET mot_de_passe_hash = ? WHERE id = ?',
                [password_hash((string) $mdp, PASSWORD_DEFAULT), $u['id']]);
        }
        // Une connexion réussie efface les échecs récents de ce compte et de cette IP.
        $this->db->executer('DELETE FROM tentative_connexion WHERE email = ? OR ip = ?', [$email, $ip]);
        return $u;
    }

    public function modifierProfil(int $uid, ?string $nom, ?string $prenom, ?string $filiere, ?string $bio,
                                   array $proposees, array $recherchees): void
    {
        $nom = $this->texte($nom, 'Nom', 2, 50);
        $prenom = $this->texte($prenom, 'Prénom', 2, 50);
        $filiere = $this->texte($filiere, 'Filière', 2, 60);
        $bio = $this->texte($bio, 'Présentation', 0, 400, false);
        $valides = array_map('intval', array_column($this->db->tous('SELECT id FROM competence'), 'id'));
        $filtrer = function (array $ids) use ($valides): array {
            $resultat = [];
            foreach ($ids as $id) {
                if (!is_int($id) && !(is_string($id) && ctype_digit($id))) {
                    continue;
                }
                $id = (int) $id;
                if (in_array($id, $valides, true)) {
                    $resultat[] = $id;
                }
            }
            return array_values(array_unique($resultat));
        };
        $proposees = $filtrer($proposees);
        $recherchees = $filtrer($recherchees);
        $this->db->transaction(function () use ($uid, $nom, $prenom, $filiere, $bio, $proposees, $recherchees) {
            $this->db->executer('UPDATE utilisateur SET nom=?, prenom=?, filiere=?, bio=? WHERE id=?',
                [$nom, $prenom, $filiere, $bio, $uid]);
            $this->db->executer('DELETE FROM etudiant_competence WHERE etudiant_id = ?', [$uid]);
            foreach ($proposees as $c) {
                $this->db->executer("INSERT INTO etudiant_competence VALUES (?, ?, 'PROPOSEE')", [$uid, $c]);
            }
            foreach ($recherchees as $c) {
                $this->db->executer("INSERT INTO etudiant_competence VALUES (?, ?, 'RECHERCHEE')", [$uid, $c]);
            }
        });
    }

    public function changerMotDePasse(int $uid, ?string $actuel, ?string $nouveau, ?string $confirmation): void
    {
        $hash = $this->db->valeur('SELECT mot_de_passe_hash FROM utilisateur WHERE id = ?', [$uid]);
        $this->exiger(password_verify((string) $actuel, (string) $hash), 'Mot de passe actuel incorrect.');
        $this->validerMotDePasse($nouveau, $confirmation);
        $this->db->transaction(fn() => $this->db->executer('UPDATE utilisateur SET mot_de_passe_hash = ? WHERE id = ?',
            [password_hash((string) $nouveau, PASSWORD_DEFAULT), $uid]));
    }

    public function competencesDe(int $uid, string $sens): array
    {
        return $this->db->tous(
            'SELECT c.*, k.nom AS categorie, k.couleur FROM etudiant_competence ec
             JOIN competence c ON c.id = ec.competence_id JOIN categorie k ON k.id = c.categorie_id
             WHERE ec.etudiant_id = ? AND ec.sens = ? ORDER BY k.nom, c.nom', [$uid, $sens]);
    }

    public function portefeuille(int $uid): ?array
    {
        return $this->db->un('SELECT * FROM portefeuille WHERE etudiant_id = ?', [$uid]);
    }

    public function historique(int $uid): array
    {
        return $this->db->tous(
            'SELECT m.*, o.titre AS offre_titre, cr.debut AS seance_debut FROM mouvement m
             LEFT JOIN reservation r ON r.id = m.reservation_id LEFT JOIN creneau cr ON cr.id = r.creneau_id
             LEFT JOIN offre o ON o.id = cr.offre_id
             WHERE m.etudiant_id = ? ORDER BY m.cree_le DESC, m.id DESC', [$uid]);
    }

    /** @return array{0: ?float, 1: int} note moyenne et nombre d'avis */
    public function noteMoyenne(int $uid): array
    {
        $r = $this->db->un('SELECT ROUND(AVG(note), 1) AS moyenne, COUNT(*) AS nombre FROM evaluation WHERE cible_id = ?', [$uid]);
        return [$r['moyenne'] !== null ? (float) $r['moyenne'] : null, (int) $r['nombre']];
    }

    public function utilisateur(int $uid): ?array
    {
        return $this->db->un('SELECT * FROM utilisateur WHERE id = ?', [$uid]);
    }
}
