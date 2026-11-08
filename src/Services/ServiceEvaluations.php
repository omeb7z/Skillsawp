<?php
declare(strict_types=1);

/** Évaluations entre participants (RG15). */
final class ServiceEvaluations extends Service
{
    public function evaluer(int $uid, int $rid, mixed $note, ?string $commentaire): void
    {
        $note = filter_var($note, FILTER_VALIDATE_INT);
        $this->exiger($note !== false && $note >= 1 && $note <= 5, 'La note doit être comprise entre 1 et 5.');
        $commentaire = $this->texte($commentaire, 'Commentaire', 0, 500, false);
        $this->db->transaction(function () use ($uid, $rid, $note, $commentaire) {
            $r = $this->reservationParticipant($rid, $uid);
            $this->exiger($r['etat'] === 'REALISEE', 'Seule une séance réalisée peut être évaluée.', 'RG15');
            $cible = $uid === (int) $r['beneficiaire_id'] ? (int) $r['intervenant_id'] : (int) $r['beneficiaire_id'];
            try {
                $this->db->executer('INSERT INTO evaluation(reservation_id, auteur_id, cible_id, note, commentaire, cree_le)
                                     VALUES (?,?,?,?,?,?)', [$rid, $uid, $cible, $note, $commentaire, $this->maintenant()]);
            } catch (PDOException) {
                throw new ErreurMetier('Vous avez déjà évalué cette séance.', 'RG15');
            }
            $this->notifier($cible, "Vous avez reçu une évaluation ($note/5) pour « {$r['titre']} ».", "/profil/$cible");
        });
    }

    public function recues(int $uid): array
    {
        return $this->db->tous("SELECT e.*, a.prenom || ' ' || a.nom AS auteur, o.titre,
                CASE WHEN r.intervenant_id = e.cible_id THEN 'intervenant' ELSE 'bénéficiaire' END AS role
            FROM evaluation e JOIN utilisateur a ON a.id = e.auteur_id JOIN reservation r ON r.id = e.reservation_id
            JOIN creneau c ON c.id = r.creneau_id JOIN offre o ON o.id = c.offre_id
            WHERE e.cible_id = ? ORDER BY e.cree_le DESC", [$uid]);
    }

    public function de(int $rid, int $auteur): ?array
    {
        return $this->db->un('SELECT * FROM evaluation WHERE reservation_id = ? AND auteur_id = ?', [$rid, $auteur]);
    }
}
