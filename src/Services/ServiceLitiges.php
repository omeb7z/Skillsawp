<?php
declare(strict_types=1);

/** Signalements, dossiers de litige et décisions (RG10, RG12, RG13, RG14, RG18, RG19). */
final class ServiceLitiges extends Service
{
    public const DECISIONS = ['TRANSFERT', 'RESTITUTION'];

    /** Contrôle serveur : même appelée hors contrôleur, une décision reste réservée à un administrateur actif. */
    private function exigerAdminActif(int $admin): void
    {
        $a = $this->db->un('SELECT role, actif FROM utilisateur WHERE id = ?', [$admin]);
        $this->exiger($a !== null && $a['role'] === 'ADMIN' && (int) $a['actif'] === 1,
            'Cette action est réservée à un administrateur actif.', 'RG19');
    }

    /** Un participant signale une absence ou un désaccord. */
    public function signaler(int $uid, int $rid, ?string $motif, ?string $description): int
    {
        $this->exiger(in_array($motif, ['ABSENCE', 'DESACCORD', 'CONFIRMATION_MANQUANTE'], true), 'Motif de signalement invalide.');
        $description = $this->texte($description, 'Description', 10, 1000);
        return $this->db->transaction(function () use ($uid, $rid, $motif, $description) {
            $r = $this->reservationParticipant($rid, $uid);
            $this->exiger($r['etat'] === 'ACCEPTEE', 'Un signalement n\'est possible que sur une séance acceptée non réglée.', 'RG12');
            $this->exiger($this->maintenant() >= $r['debut'],
                "La séance n'a pas commencé : annulez-la plutôt que de la signaler.", 'RG10');
            $lid = $this->ouvrir($r, $uid, $motif, $description);
            $autre = $uid === (int) $r['beneficiaire_id'] ? (int) $r['intervenant_id'] : (int) $r['beneficiaire_id'];
            $this->notifier($autre, "Un signalement a été ouvert sur « {$r['titre']} ». Ajoutez votre version des faits.", "/litiges/$lid");
            foreach ($this->db->tous("SELECT id FROM utilisateur WHERE role = 'ADMIN' AND actif = 1") as $a) {
                $this->notifier((int) $a['id'], "Nouveau litige sur « {$r['titre']} » (" . Libelles::MOTIFS_LITIGE[$motif] . ').',
                    "/litiges/$lid");
            }
            return $lid;
        });
    }

    private function ouvrir(array $r, int $auteur, string $motif, string $description): int
    {
        $n = $this->db->executer("UPDATE reservation SET etat = 'EN_LITIGE' WHERE id = ? AND etat = 'ACCEPTEE'", [$r['id']]);
        $this->exiger($n === 1, 'Cette séance a déjà été traitée.');
        try {
            $lid = $this->db->inserer('INSERT INTO litige(reservation_id, ouvert_par, motif, ouvert_le) VALUES (?,?,?,?)',
                [$r['id'], $auteur, $motif, $this->maintenant()]);
        } catch (PDOException) {
            throw new ErreurMetier('Un litige a déjà été ouvert pour cette séance.', 'RG14');
        }
        $this->db->executer('INSERT INTO element_litige(litige_id, auteur_id, texte, cree_le) VALUES (?,?,?,?)',
            [$lid, $auteur, $description, $this->maintenant()]);
        return $lid;
    }

    /** RG18 : traitement par l'administrateur d'une séance acceptée liée à un compte désactivé. */
    public function ouvrirDossierAdmin(int $admin, int $rid, ?string $description): int
    {
        $this->exigerAdminActif($admin);
        $description = $this->texte($description, 'Description', 10, 1000);
        return $this->db->transaction(function () use ($admin, $rid, $description) {
            $r = $this->reservation($rid);
            $this->exiger($r !== null, 'Réservation introuvable.');
            $this->exiger($r['etat'] === 'ACCEPTEE', 'Seule une séance acceptée peut faire l\'objet d\'un dossier.', 'RG18');
            $this->exiger(!(int) $r['beneficiaire_actif'] || !(int) $r['intervenant_actif'],
                'Un dossier administratif ne peut être ouvert que si un participant est désactivé.', 'RG18');
            $lid = $this->ouvrir($r, $admin, 'ADMINISTRATIF', $description);
            foreach ([(int) $r['beneficiaire_id'], (int) $r['intervenant_id']] as $p) {
                $this->notifier($p, "L'administration a ouvert un dossier sur « {$r['titre']} ».", "/litiges/$lid");
            }
            return $lid;
        });
    }

    public function litige(int $lid): ?array
    {
        return $this->db->un("SELECT l.*, r.beneficiaire_id, r.intervenant_id, r.cout, r.etat AS etat_reservation, c.debut, c.fin, o.titre,
                b.prenom || ' ' || b.nom AS beneficiaire, i.prenom || ' ' || i.nom AS intervenant,
                a.prenom || ' ' || a.nom AS auteur, ad.prenom || ' ' || ad.nom AS admin
            FROM litige l JOIN reservation r ON r.id = l.reservation_id JOIN creneau c ON c.id = r.creneau_id
            JOIN offre o ON o.id = c.offre_id JOIN utilisateur b ON b.id = r.beneficiaire_id
            JOIN utilisateur i ON i.id = r.intervenant_id JOIN utilisateur a ON a.id = l.ouvert_par
            LEFT JOIN utilisateur ad ON ad.id = l.admin_id WHERE l.id = ?", [$lid]);
    }

    /** RG19 : dossier visible des participants et de l'administrateur uniquement. */
    public function verifierAcces(int $lid, array $utilisateur): array
    {
        $l = $this->litige($lid);
        $this->exiger($l !== null, 'Litige introuvable.');
        if ($utilisateur['role'] !== 'ADMIN'
            && !in_array((int) $utilisateur['id'], [(int) $l['beneficiaire_id'], (int) $l['intervenant_id']], true)) {
            throw new AccesRefuse("Ce dossier est réservé aux participants et à l'administration.");
        }
        return $l;
    }

    public function elements(int $lid): array
    {
        return $this->db->tous("SELECT e.*, u.prenom || ' ' || u.nom AS auteur, u.role FROM element_litige e
            JOIN utilisateur u ON u.id = e.auteur_id WHERE e.litige_id = ? ORDER BY e.cree_le, e.id", [$lid]);
    }

    public function ajouterElement(array $utilisateur, int $lid, ?string $texte): void
    {
        $texte = $this->texte($texte, 'Votre version des faits', 5, 1000);
        $this->db->transaction(function () use ($utilisateur, $lid, $texte) {
            $l = $this->verifierAcces($lid, $utilisateur);
            $this->exiger($l['etat'] === 'OUVERT', 'Ce litige est clos.', 'RG14');
            $this->exiger($l['etat_reservation'] === 'EN_LITIGE', 'La séance liée à ce dossier n\'est plus en litige.', 'RG14');
            $this->db->executer('INSERT INTO element_litige(litige_id, auteur_id, texte, cree_le) VALUES (?,?,?,?)',
                [$lid, $utilisateur['id'], $texte, $this->maintenant()]);
        });
    }

    /** Règlement intégral et définitif (RG13, RG14). */
    public function decider(int $admin, int $lid, ?string $decision, ?string $justification): void
    {
        $this->exigerAdminActif($admin);
        $this->exiger(in_array($decision, self::DECISIONS, true),
            'Décision invalide : transfert intégral ou restitution intégrale uniquement.', 'RG13');
        $justification = $this->texte($justification, 'Motivation de la décision', 10, 1000);
        $this->db->transaction(function () use ($admin, $lid, $decision, $justification) {
            $l = $this->litige($lid);
            $this->exiger($l !== null, 'Litige introuvable.');
            $this->exiger($l['etat'] === 'OUVERT', 'Ce litige a déjà été réglé : aucun second règlement n\'est autorisé.', 'RG14');
            $this->exiger($l['etat_reservation'] === 'EN_LITIGE',
                'La séance liée à ce litige n\'est plus dans l\'état attendu.', 'RG14');
            $n = $this->db->executer("UPDATE litige SET etat = 'REGLE', decision = ?, justification = ?, admin_id = ?, regle_le = ?
                                      WHERE id = ? AND etat = 'OUVERT'", [$decision, $justification, $admin, $this->maintenant(), $lid]);
            $this->exiger($n === 1, 'Ce litige a déjà été réglé : aucun second règlement n\'est autorisé.', 'RG14');
            $r = $this->reservation((int) $l['reservation_id']);
            if ($decision === 'TRANSFERT') {
                $this->transferer($r);
                $texte = "transfert de {$r['cout']} crédit(s) à l'intervenant";
            } else {
                $n = $this->db->executer("UPDATE reservation SET etat = 'ANNULEE', cloturee_le = ?,
                    decidee_le = COALESCE(decidee_le, ?),
                    motif_annulation = 'Litige réglé par restitution.' WHERE id = ? AND etat = 'EN_LITIGE'",
                    [$this->maintenant(), $this->maintenant(), $r['id']]);
                $this->exiger($n === 1, 'Les crédits de cette séance ont déjà été réglés.', 'RG14');
                $this->mouvement((int) $r['beneficiaire_id'], 'RESTITUTION', (int) $r['cout'], (int) $r['id'],
                    "Restitution après litige « {$r['titre']} »");
                $texte = "restitution de {$r['cout']} crédit(s) au bénéficiaire";
            }
            foreach ([(int) $r['beneficiaire_id'], (int) $r['intervenant_id']] as $p) {
                $this->notifier($p, "Litige « {$r['titre']} » réglé : $texte.", "/litiges/$lid");
            }
        });
    }

    public function tous(): array
    {
        return $this->db->tous("SELECT l.*, o.titre, r.cout, b.prenom || ' ' || b.nom AS beneficiaire, i.prenom || ' ' || i.nom AS intervenant,
                (SELECT COUNT(*) FROM element_litige e WHERE e.litige_id = l.id) AS nb_elements
            FROM litige l JOIN reservation r ON r.id = l.reservation_id JOIN creneau c ON c.id = r.creneau_id
            JOIN offre o ON o.id = c.offre_id JOIN utilisateur b ON b.id = r.beneficiaire_id JOIN utilisateur i ON i.id = r.intervenant_id
            ORDER BY l.etat = 'REGLE', l.ouvert_le DESC");
    }
}
