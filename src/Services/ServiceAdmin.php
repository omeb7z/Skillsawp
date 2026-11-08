<?php
declare(strict_types=1);

/** Administration : comptes, modération, tableau de bord, contrôle d'intégrité (RG16, RG17, RG18). */
final class ServiceAdmin extends Service
{
    /** Active / désactive un compte (RG18). Retourne « réactivé » ou « désactivé ». */
    public function basculerCompte(int $admin, int $uid): string
    {
        return $this->db->transaction(function () use ($admin, $uid) {
            $u = $this->db->un('SELECT * FROM utilisateur WHERE id = ?', [$uid]);
            $this->exiger($u !== null, 'Compte introuvable.');
            $this->exiger($uid !== $admin, 'Vous ne pouvez pas désactiver votre propre compte.');
            if (!(int) $u['actif']) {
                $this->db->executer('UPDATE utilisateur SET actif = 1 WHERE id = ?', [$uid]);
                return 'réactivé';
            }
            $this->db->executer('UPDATE utilisateur SET actif = 0 WHERE id = ?', [$uid]);
            $this->annulerDemandesEnAttente('(r.beneficiaire_id = ? OR r.intervenant_id = ?)', [$uid, $uid],
                'un compte participant a été désactivé.');
            return 'désactivé';
        });
    }

    public function masquerOffre(int $oid, ?string $motif): void
    {
        $motif = $this->texte($motif, 'Motif de modération', 5, 300);
        $this->db->transaction(function () use ($oid, $motif) {
            $o = $this->db->un('SELECT * FROM offre WHERE id = ?', [$oid]);
            $this->exiger($o !== null, 'Offre introuvable.');
            $this->exiger(!(int) $o['masquee'], "L'offre est déjà masquée.");
            $this->db->executer('UPDATE offre SET masquee = 1, motif_masquage = ? WHERE id = ?', [$motif, $oid]);
            $this->annulerDemandesEnAttente('c.offre_id = ?', [$oid], "l'offre a été retirée par la modération.");
            $this->notifier((int) $o['intervenant_id'], "Votre offre « {$o['titre']} » a été masquée : $motif", "/offres/$oid");
        });
    }

    public function reafficherOffre(int $oid): void
    {
        $this->db->transaction(function () use ($oid) {
            $o = $this->db->un('SELECT * FROM offre WHERE id = ?', [$oid]);
            $this->exiger($o !== null && (int) $o['masquee'], "Cette offre n'est pas masquée.");
            $this->db->executer('UPDATE offre SET masquee = 0, motif_masquage = NULL WHERE id = ?', [$oid]);
            $this->notifier((int) $o['intervenant_id'], "Votre offre « {$o['titre']} » est de nouveau visible.", "/offres/$oid");
        });
    }

    /** RG18 : séances acceptées dont un participant est désactivé. */
    public function reservationsATraiter(): array
    {
        return $this->db->tous("SELECT r.id, o.titre, c.debut, b.prenom || ' ' || b.nom AS beneficiaire,
                i.prenom || ' ' || i.nom AS intervenant, b.actif AS b_actif, i.actif AS i_actif
            FROM reservation r JOIN creneau c ON c.id = r.creneau_id JOIN offre o ON o.id = c.offre_id
            JOIN utilisateur b ON b.id = r.beneficiaire_id JOIN utilisateur i ON i.id = r.intervenant_id
            WHERE r.etat = 'ACCEPTEE' AND (b.actif = 0 OR i.actif = 0) ORDER BY c.debut");
    }

    public function litigesOuverts(): array
    {
        return $this->db->tous("SELECT l.id, l.motif, l.ouvert_le, o.titre FROM litige l JOIN reservation r ON r.id = l.reservation_id
            JOIN creneau c ON c.id = r.creneau_id JOIN offre o ON o.id = c.offre_id WHERE l.etat = 'OUVERT' ORDER BY l.ouvert_le");
    }

    public function tableauDeBord(): array
    {
        $s = fn(string $sql) => (int) ($this->db->valeur($sql) ?? 0);
        return [
            'comptes_actifs' => $s("SELECT COUNT(*) FROM utilisateur WHERE actif = 1 AND role = 'ETUDIANT'"),
            'comptes_inactifs' => $s('SELECT COUNT(*) FROM utilisateur WHERE actif = 0'),
            'offres_publiees' => $s("SELECT COUNT(*) FROM offre WHERE etat = 'PUBLIEE' AND masquee = 0"),
            'offres_masquees' => $s('SELECT COUNT(*) FROM offre WHERE masquee = 1'),
            'seances_acceptees' => $s("SELECT COUNT(*) FROM reservation WHERE etat = 'ACCEPTEE'"),
            'seances_realisees' => $s("SELECT COUNT(*) FROM reservation WHERE etat = 'REALISEE'"),
            'credits_transferes' => $s("SELECT SUM(quantite) FROM mouvement WHERE type = 'TRANSFERT_ENTRANT'"),
            'credits_bloques' => $s('SELECT SUM(bloque) FROM portefeuille'),
            'credits_en_circulation' => $s('SELECT SUM(disponible + bloque) FROM portefeuille'),
            'litiges_ouverts' => $s("SELECT COUNT(*) FROM litige WHERE etat = 'OUVERT'"),
            'litiges_regles' => $s("SELECT COUNT(*) FROM litige WHERE etat = 'REGLE'"),
            'par_categorie' => $this->db->tous("SELECT k.nom, k.couleur, COUNT(r.id) AS n FROM categorie k
                LEFT JOIN competence cp ON cp.categorie_id = k.id LEFT JOIN offre o ON o.competence_id = cp.id
                LEFT JOIN creneau c ON c.offre_id = o.id LEFT JOIN reservation r ON r.creneau_id = c.id AND r.etat = 'REALISEE'
                GROUP BY k.id ORDER BY n DESC"),
            'par_etat' => $this->db->tous('SELECT etat, COUNT(*) AS n FROM reservation GROUP BY etat'),
        ];
    }

    /** RG16 : chaque portefeuille doit être égal à la somme des effets de son historique. */
    public function verifierCoherence(): array
    {
        return $this->db->tous('SELECT p.etudiant_id, p.disponible, p.bloque,
                COALESCE(SUM(m.effet_disponible), 0) AS somme_dispo, COALESCE(SUM(m.effet_bloque), 0) AS somme_bloque
            FROM portefeuille p LEFT JOIN mouvement m ON m.etudiant_id = p.etudiant_id GROUP BY p.etudiant_id
            HAVING p.disponible <> somme_dispo OR p.bloque <> somme_bloque');
    }

    public function comptes(string $q = ''): array
    {
        $sql = "SELECT u.*, p.disponible, p.bloque,
                (SELECT ROUND(AVG(note), 1) FROM evaluation e WHERE e.cible_id = u.id) AS note,
                (SELECT COUNT(*) FROM reservation r WHERE (r.beneficiaire_id = u.id OR r.intervenant_id = u.id) AND r.etat = 'ACCEPTEE') AS acceptees
            FROM utilisateur u LEFT JOIN portefeuille p ON p.etudiant_id = u.id";
        $p = [];
        if ($q !== '') {
            $sql .= ' WHERE u.nom LIKE ? OR u.prenom LIKE ? OR u.email LIKE ?';
            $p = array_fill(0, 3, "%$q%");
        }
        return $this->db->tous($sql . ' ORDER BY u.role, u.nom', $p);
    }

    public function offres(string $filtre): array
    {
        $condition = ['publiees' => "o.etat = 'PUBLIEE' AND o.masquee = 0", 'masquees' => 'o.masquee = 1'][$filtre] ?? '1 = 1';
        return $this->db->tous("SELECT o.*, u.prenom || ' ' || u.nom AS intervenant, c.nom AS competence, k.nom AS categorie
            FROM offre o JOIN utilisateur u ON u.id = o.intervenant_id LEFT JOIN competence c ON c.id = o.competence_id
            LEFT JOIN categorie k ON k.id = c.categorie_id WHERE $condition ORDER BY o.modifie_le DESC");
    }

    public function reglerDecalageHorloge(mixed $minutes): void
    {
        $m = filter_var($minutes, FILTER_VALIDATE_INT);
        $this->exiger($m !== false, 'Décalage invalide.');
        $this->exiger(abs($m) <= 60 * 24 * 30, 'Décalage limité à ±30 jours.');
        $this->db->transaction(fn() => $this->db->executer("INSERT INTO parametre(cle, valeur) VALUES ('decalage_minutes', ?)
            ON CONFLICT(cle) DO UPDATE SET valeur = excluded.valeur", [(string) $m]));
    }
}
