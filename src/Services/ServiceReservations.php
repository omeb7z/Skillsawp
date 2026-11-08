<?php
declare(strict_types=1);

/** Demandes, acceptation, refus, retrait, annulation, confirmation (RG03 à RG12). */
final class ServiceReservations extends Service
{
    /** Demande d'une séance sur un créneau (RG03, RG04, RG05). Aucun crédit n'est bloqué. */
    public function demander(int $beneficiaire, int $creneau, ?string $message = null): int
    {
        $message = $this->texte($message, 'Message', 0, 300, false);
        return $this->db->transaction(function () use ($beneficiaire, $creneau, $message) {
            $c = $this->db->un('SELECT c.*, o.intervenant_id, o.etat, o.masquee, o.duree, o.titre, u.actif
                FROM creneau c JOIN offre o ON o.id = c.offre_id JOIN utilisateur u ON u.id = o.intervenant_id
                WHERE c.id = ? AND c.supprime = 0', [$creneau]);
            $this->exiger($c !== null, 'Créneau introuvable.');
            $this->exiger((int) $c['intervenant_id'] !== $beneficiaire, 'Vous ne pouvez pas réserver votre propre offre.', 'RG03');
            $this->exiger($c['etat'] === 'PUBLIEE' && !(int) $c['masquee'], "Cette offre n'est pas publiée.", 'RG04');
            $this->exiger((int) $c['actif'], "L'intervenant de cette offre n'est plus actif.", 'RG04');
            $this->exiger($c['debut'] > $this->maintenant(), 'Ce créneau est déjà passé.', 'RG04');
            $this->exiger(!$this->db->valeur('SELECT 1 FROM reservation WHERE creneau_id = ? AND etat IN ' . self::ETATS_ATTRIBUES,
                [$creneau]), 'Ce créneau est déjà attribué.', 'RG04');
            $this->exiger(!$this->db->valeur("SELECT 1 FROM reservation WHERE creneau_id = ? AND beneficiaire_id = ?
                AND etat IN ('EN_ATTENTE','ACCEPTEE')", [$creneau, $beneficiaire]),
                'Vous avez déjà une demande en cours pour ce créneau.', 'RG03');
            $solde = (int) ($this->db->valeur('SELECT disponible FROM portefeuille WHERE etudiant_id = ?', [$beneficiaire]) ?? 0);
            $this->exiger($solde >= (int) $c['duree'],
                "Solde insuffisant : cette séance coûte {$c['duree']} crédit(s), vous en avez $solde.", 'RG05');
            try {
                $rid = $this->db->inserer('INSERT INTO reservation(creneau_id, beneficiaire_id, intervenant_id, cout, message, cree_le)
                    VALUES (?,?,?,?,?,?)', [$creneau, $beneficiaire, $c['intervenant_id'], $c['duree'], $message, $this->maintenant()]);
            } catch (PDOException) {
                throw new ErreurMetier('Vous avez déjà une demande en cours pour ce créneau.', 'RG03');
            }
            $prenom = $this->db->valeur('SELECT prenom FROM utilisateur WHERE id = ?', [$beneficiaire]);
            $this->notifier((int) $c['intervenant_id'], "$prenom demande une séance « {$c['titre']} ».", "/reservations/$rid");
            return $rid;
        });
    }

    /** RG08 : chevauchement strict (des séances consécutives sont permises). */
    private function conflitPlanning(int $uid, string $debut, string $fin, int $sauf): ?array
    {
        return $this->db->un("SELECT r.id, o.titre, c.debut, c.fin FROM reservation r
            JOIN creneau c ON c.id = r.creneau_id JOIN offre o ON o.id = c.offre_id
            WHERE r.etat IN ('ACCEPTEE','EN_LITIGE') AND r.id <> ? AND (r.beneficiaire_id = ? OR r.intervenant_id = ?)
              AND c.debut < ? AND c.fin > ?", [$sauf, $uid, $uid, $fin, $debut]);
    }

    /** Acceptation : RG05, RG07, RG08 puis blocage (RG06) et refus automatique des autres demandes (RG07). */
    public function accepter(int $intervenant, int $rid): void
    {
        $this->db->transaction(function () use ($intervenant, $rid) {
            $r = $this->reservationParticipant($rid, $intervenant);
            if ((int) $r['intervenant_id'] !== $intervenant) {
                throw new AccesRefuse("Seul l'intervenant peut accepter cette demande.");
            }
            $this->exiger($r['etat'] === 'EN_ATTENTE', "Cette demande n'est plus en attente.", 'RG09');
            $this->exiger($r['debut'] > $this->maintenant(), 'Le créneau a déjà commencé.', 'RG04');
            $this->exiger((int) $r['beneficiaire_actif'], 'Le compte du bénéficiaire est désactivé.', 'RG18');
            $this->exiger(!$this->db->valeur('SELECT 1 FROM reservation WHERE creneau_id = ? AND etat IN ' . self::ETATS_ATTRIBUES,
                [$r['creneau_id']]), 'Ce créneau est déjà attribué.', 'RG07');
            $solde = (int) $this->db->valeur('SELECT disponible FROM portefeuille WHERE etudiant_id = ?', [$r['beneficiaire_id']]);
            $this->exiger($solde >= (int) $r['cout'], "Le bénéficiaire n'a plus assez de crédits disponibles.", 'RG05');
            foreach ([[$intervenant, 'vous avez'], [(int) $r['beneficiaire_id'], 'le bénéficiaire a']] as [$uid, $qui]) {
                $conflit = $this->conflitPlanning($uid, $r['debut'], $r['fin'], $rid);
                if ($conflit) {
                    throw new ErreurMetier("Conflit de planning : $qui déjà « {$conflit['titre']} » de "
                        . substr($conflit['debut'], 11) . ' à ' . substr($conflit['fin'], 11) . ' ce jour-là.', 'RG08');
                }
            }
            try {
                $n = $this->db->executer("UPDATE reservation SET etat = 'ACCEPTEE', decidee_le = ?
                                          WHERE id = ? AND etat = 'EN_ATTENTE'", [$this->maintenant(), $rid]);
            } catch (PDOException) {
                throw new ErreurMetier('Ce créneau est déjà attribué.', 'RG07');
            }
            $this->exiger($n === 1, 'Cette demande a déjà été traitée.');
            $this->mouvement((int) $r['beneficiaire_id'], 'BLOCAGE', (int) $r['cout'], $rid, "Réservation « {$r['titre']} »");
            $this->notifier((int) $r['beneficiaire_id'], "Bonne nouvelle : votre séance « {$r['titre']} » est acceptée !",
                "/reservations/$rid");
            foreach ($this->db->tous("SELECT id, beneficiaire_id FROM reservation WHERE creneau_id = ? AND etat = 'EN_ATTENTE'",
                [$r['creneau_id']]) as $a) {
                $this->db->executer("UPDATE reservation SET etat = 'REFUSEE', decidee_le = ?,
                    motif_refus = 'Créneau attribué à une autre demande.' WHERE id = ?", [$this->maintenant(), $a['id']]);
                $this->notifier((int) $a['beneficiaire_id'],
                    "Votre demande « {$r['titre']} » est refusée : créneau attribué à une autre demande.", "/reservations/{$a['id']}");
            }
        });
    }

    public function refuser(int $intervenant, int $rid, ?string $motif): void
    {
        $motif = $this->texte($motif, 'Motif du refus', 5, 300);
        $this->db->transaction(function () use ($intervenant, $rid, $motif) {
            $r = $this->reservationParticipant($rid, $intervenant);
            if ((int) $r['intervenant_id'] !== $intervenant) {
                throw new AccesRefuse("Seul l'intervenant peut refuser cette demande.");
            }
            $n = $this->db->executer("UPDATE reservation SET etat = 'REFUSEE', motif_refus = ?, decidee_le = ?
                                      WHERE id = ? AND etat = 'EN_ATTENTE'", [$motif, $this->maintenant(), $rid]);
            $this->exiger($n === 1, "Cette demande n'est plus en attente.", 'RG09');
            $this->notifier((int) $r['beneficiaire_id'], "Votre demande « {$r['titre']} » a été refusée : $motif", "/reservations/$rid");
        });
    }

    /** Le bénéficiaire retire sa demande en attente (RG09). */
    public function retirer(int $beneficiaire, int $rid): void
    {
        $this->db->transaction(function () use ($beneficiaire, $rid) {
            $r = $this->reservationParticipant($rid, $beneficiaire);
            if ((int) $r['beneficiaire_id'] !== $beneficiaire) {
                throw new AccesRefuse('Seul le bénéficiaire peut retirer sa demande.');
            }
            $n = $this->db->executer("UPDATE reservation SET etat = 'ANNULEE', annulee_par = ?, cloturee_le = ?,
                decidee_le = ?, motif_annulation = 'Demande retirée par le bénéficiaire.'
                WHERE id = ? AND etat = 'EN_ATTENTE'",
                [$beneficiaire, $this->maintenant(), $this->maintenant(), $rid]);
            $this->exiger($n === 1, 'Seule une demande en attente peut être retirée.', 'RG09');
            $this->notifier((int) $r['intervenant_id'], "{$r['beneficiaire']} a retiré sa demande « {$r['titre']} ».",
                "/reservations/$rid");
        });
    }

    /** Annulation d'une séance acceptée avant son début : libération des crédits (RG10). */
    public function annuler(int $uid, int $rid, ?string $motif = null): void
    {
        $motif = $this->texte($motif, 'Motif', 0, 300, false) ?? 'Sans motif précisé.';
        $this->db->transaction(function () use ($uid, $rid, $motif) {
            $r = $this->reservationParticipant($rid, $uid);
            $this->exiger($r['etat'] === 'ACCEPTEE', 'Seule une séance acceptée peut être annulée.', 'RG10');
            $this->exiger($this->maintenant() < $r['debut'],
                "La séance a commencé : l'annulation directe n'est plus possible. Ouvrez un signalement.", 'RG10');
            $n = $this->db->executer("UPDATE reservation SET etat = 'ANNULEE', annulee_par = ?, motif_annulation = ?,
                                      decidee_le = ?, cloturee_le = ?
                                      WHERE id = ? AND etat = 'ACCEPTEE'",
                [$uid, $motif, $this->maintenant(), $this->maintenant(), $rid]);
            $this->exiger($n === 1, 'Cette séance a déjà été traitée.');
            $this->mouvement((int) $r['beneficiaire_id'], 'LIBERATION', (int) $r['cout'], $rid, "Annulation « {$r['titre']} »");
            $autre = $uid === (int) $r['beneficiaire_id'] ? (int) $r['intervenant_id'] : (int) $r['beneficiaire_id'];
            $this->notifier($autre, "La séance « {$r['titre']} » du {$r['debut']} a été annulée : $motif", "/reservations/$rid");
        });
    }

    /** Confirmation de réalisation. Retourne true si le transfert a eu lieu (RG11, RG12). */
    public function confirmer(int $uid, int $rid): bool
    {
        return $this->db->transaction(function () use ($uid, $rid) {
            $r = $this->reservationParticipant($rid, $uid);
            $this->exiger($r['etat'] !== 'EN_LITIGE',
                "Un litige est ouvert : les crédits restent bloqués jusqu'à la décision de l'administration.", 'RG13');
            $this->exiger($r['etat'] === 'ACCEPTEE', 'Seule une séance acceptée peut être confirmée.', 'RG11');
            $this->exiger($this->maintenant() >= $r['fin'], "Vous pourrez confirmer après l'heure de fin de la séance.", 'RG11');
            try {
                $this->db->executer('INSERT INTO confirmation(reservation_id, etudiant_id, confirme_le) VALUES (?,?,?)',
                    [$rid, $uid, $this->maintenant()]);
            } catch (PDOException) {
                throw new ErreurMetier('Vous avez déjà confirmé cette séance.', 'RG11');
            }
            $nb = (int) $this->db->valeur('SELECT COUNT(*) FROM confirmation WHERE reservation_id = ?', [$rid]);
            $autre = $uid === (int) $r['beneficiaire_id'] ? (int) $r['intervenant_id'] : (int) $r['beneficiaire_id'];
            if ($nb < 2) {
                $this->notifier($autre, "Votre partenaire a confirmé la séance « {$r['titre']} ». À vous de confirmer !",
                    "/reservations/$rid");
                return false;
            }
            $this->transferer($r);
            foreach ([(int) $r['beneficiaire_id'], (int) $r['intervenant_id']] as $p) {
                $this->notifier($p, "Séance « {$r['titre']} » validée : {$r['cout']} crédit(s) transféré(s). Pensez à évaluer !",
                    "/reservations/$rid");
            }
            return true;
        });
    }

    /** @return array<int, string> date de confirmation par étudiant */
    public function confirmations(int $rid): array
    {
        $r = [];
        foreach ($this->db->tous('SELECT * FROM confirmation WHERE reservation_id = ?', [$rid]) as $c) {
            $r[(int) $c['etudiant_id']] = $c['confirme_le'];
        }
        return $r;
    }

    public function demandesRecues(int $uid): array
    {
        return $this->db->tous("SELECT r.*, c.debut, c.fin, o.titre, b.prenom || ' ' || b.nom AS autre, b.id AS autre_id
            FROM reservation r JOIN creneau c ON c.id = r.creneau_id JOIN offre o ON o.id = c.offre_id
            JOIN utilisateur b ON b.id = r.beneficiaire_id
            WHERE r.intervenant_id = ? ORDER BY (r.etat = 'EN_ATTENTE') DESC, c.debut DESC", [$uid]);
    }

    public function demandesEnvoyees(int $uid): array
    {
        return $this->db->tous("SELECT r.*, c.debut, c.fin, o.titre, i.prenom || ' ' || i.nom AS autre, i.id AS autre_id
            FROM reservation r JOIN creneau c ON c.id = r.creneau_id JOIN offre o ON o.id = c.offre_id
            JOIN utilisateur i ON i.id = r.intervenant_id
            WHERE r.beneficiaire_id = ? ORDER BY (r.etat = 'EN_ATTENTE') DESC, c.debut DESC", [$uid]);
    }

    public function planning(int $uid): array
    {
        return $this->db->tous("SELECT r.*, c.debut, c.fin, c.lieu, c.lien, o.titre, o.mode, k.couleur, k.nom AS categorie,
                CASE WHEN r.intervenant_id = :u THEN 'ANIME' ELSE 'APPREND' END AS role,
                CASE WHEN r.intervenant_id = :u THEN b.prenom || ' ' || b.nom ELSE i.prenom || ' ' || i.nom END AS autre,
                (SELECT COUNT(*) FROM confirmation cf WHERE cf.reservation_id = r.id) AS nb_confirmations,
                (SELECT 1 FROM confirmation cf WHERE cf.reservation_id = r.id AND cf.etudiant_id = :u) AS j_ai_confirme
            FROM reservation r JOIN creneau c ON c.id = r.creneau_id JOIN offre o ON o.id = c.offre_id
            JOIN utilisateur b ON b.id = r.beneficiaire_id JOIN utilisateur i ON i.id = r.intervenant_id
            LEFT JOIN competence cp ON cp.id = o.competence_id LEFT JOIN categorie k ON k.id = cp.categorie_id
            WHERE (r.beneficiaire_id = :u OR r.intervenant_id = :u) AND r.etat IN ('ACCEPTEE','EN_LITIGE')
            ORDER BY c.debut", ['u' => $uid]);
    }

    public function litigeDe(int $rid): ?array
    {
        return $this->db->un('SELECT id, etat, decision FROM litige WHERE reservation_id = ?', [$rid]);
    }
}
