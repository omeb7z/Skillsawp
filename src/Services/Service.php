<?php
declare(strict_types=1);

/**
 * Classe de base de la couche métier.
 *
 * Toutes les règles de gestion (RG01 à RG19) sont appliquées dans les
 * services, côté traitement, jamais seulement dans l'interface. Les
 * contrôleurs se contentent d'appeler ces méthodes et d'afficher le message
 * d'une ErreurMetier.
 */
abstract class Service
{
    public const ETATS_ATTRIBUES = "('ACCEPTEE','EN_LITIGE','REALISEE')";

    public function __construct(protected Db $db) {}

    protected function maintenant(): string
    {
        return Horloge::maintenantTxt($this->db);
    }

    protected function exiger(mixed $condition, string $message, ?string $regle = null): void
    {
        if (!$condition) {
            throw new ErreurMetier($message, $regle);
        }
    }

    /** Valide un champ texte : obligatoire ou non, longueur minimale / maximale. */
    protected function texte(mixed $brut, string $champ, int $mini = 1, int $maxi = 200, bool $obligatoire = true): ?string
    {
        if ($brut !== null && !is_scalar($brut)) {
            throw new ErreurMetier("Le champ « $champ » est invalide.");
        }
        $v = trim((string) ($brut ?? ''));
        if ($v === '') {
            if ($obligatoire) {
                throw new ErreurMetier("Le champ « $champ » est obligatoire.");
            }
            return null;
        }
        $n = mb_strlen($v);
        if ($n < $mini) {
            throw new ErreurMetier("« $champ » doit contenir au moins $mini caractères.");
        }
        if ($n > $maxi) {
            throw new ErreurMetier("« $champ » ne doit pas dépasser $maxi caractères.");
        }
        return $v;
    }

    public function notifier(int $destinataire, string $message, ?string $lien = null): void
    {
        $this->db->executer('INSERT INTO notification(destinataire_id, message, lien, cree_le) VALUES (?,?,?,?)',
            [$destinataire, $message, $lien, $this->maintenant()]);
    }

    /**
     * Applique un mouvement au portefeuille ET l'inscrit dans l'historique (RG16).
     *
     * L'UPDATE est gardé par une condition : si le solde ne suffit pas, aucune
     * ligne n'est modifiée et on lève une erreur, ce qui annule toute la
     * transaction. Le solde ne peut donc jamais devenir négatif.
     */
    protected function mouvement(int $etudiant, string $type, int $quantite, ?int $reservation, string $libelle): void
    {
        if (!isset(Libelles::EFFETS[$type])) {
            throw new ErreurMetier('Type de mouvement de crédits inconnu.');
        }
        [$ed, $eb] = Libelles::EFFETS[$type];
        $ed *= $quantite;
        $eb *= $quantite;
        $n = $this->db->executer(
            'UPDATE portefeuille SET disponible = disponible + ?, bloque = bloque + ?
             WHERE etudiant_id = ? AND disponible + ? >= 0 AND bloque + ? >= 0',
            [$ed, $eb, $etudiant, $ed, $eb]);
        $this->exiger($n === 1, 'Solde insuffisant pour cette opération.', 'RG16');
        try {
            $this->db->executer(
                'INSERT INTO mouvement(etudiant_id, reservation_id, type, quantite, effet_disponible, effet_bloque, libelle, cree_le)
                 VALUES (?,?,?,?,?,?,?,?)',
                [$etudiant, $reservation, $type, $quantite, $ed, $eb, $libelle, $this->maintenant()]);
        } catch (PDOException) {
            // Index unique : ce mouvement a déjà eu lieu pour cette réservation
            throw new ErreurMetier('Cette opération sur les crédits a déjà été effectuée.', 'RG11');
        }
    }

    /** Lecture complète d'une réservation (avec créneau, offre et participants). */
    public function reservation(int $id): ?array
    {
        return $this->db->un(
            "SELECT r.*, c.debut, c.fin, c.lieu, c.lien, c.offre_id, o.titre, o.mode, o.duree,
                    b.prenom || ' ' || b.nom AS beneficiaire, b.actif AS beneficiaire_actif,
                    i.prenom || ' ' || i.nom AS intervenant, i.actif AS intervenant_actif,
                    k.nom AS categorie, k.couleur
             FROM reservation r JOIN creneau c ON c.id = r.creneau_id JOIN offre o ON o.id = c.offre_id
             JOIN utilisateur b ON b.id = r.beneficiaire_id JOIN utilisateur i ON i.id = r.intervenant_id
             LEFT JOIN competence cp ON cp.id = o.competence_id LEFT JOIN categorie k ON k.id = cp.categorie_id
             WHERE r.id = ?", [$id]);
    }

    protected function reservationParticipant(int $id, int $uid): array
    {
        $r = $this->reservation($id);
        $this->exiger($r !== null, 'Réservation introuvable.');
        if (!in_array($uid, [(int) $r['beneficiaire_id'], (int) $r['intervenant_id']], true)) {
            throw new AccesRefuse('Vous ne participez pas à cette séance.');
        }
        return $r;
    }

    /**
     * Transfert unique des crédits bloqués vers l'intervenant (RG11, RG14).
     * Le passage ACCEPTEE/EN_LITIGE → REALISEE est la « porte » : un seul
     * appel peut la franchir, le second trouve 0 ligne modifiée.
     */
    protected function transferer(array $r): void
    {
        $n = $this->db->executer(
            "UPDATE reservation SET etat = 'REALISEE', cloturee_le = ? WHERE id = ? AND etat IN ('ACCEPTEE','EN_LITIGE')",
            [$this->maintenant(), $r['id']]);
        $this->exiger($n === 1, 'Les crédits de cette séance ont déjà été réglés.', 'RG14');
        $this->mouvement((int) $r['beneficiaire_id'], 'TRANSFERT_SORTANT', (int) $r['cout'], (int) $r['id'],
            "Séance « {$r['titre']} » réalisée");
        $this->mouvement((int) $r['intervenant_id'], 'TRANSFERT_ENTRANT', (int) $r['cout'], (int) $r['id'],
            "Séance « {$r['titre']} » animée");
    }

    /** Annule les demandes en attente correspondant au filtre et prévient les bénéficiaires (RG17, RG18). */
    protected function annulerDemandesEnAttente(string $filtre, array $params, string $motif): int
    {
        $lignes = $this->db->tous(
            "SELECT r.id, r.beneficiaire_id, o.titre FROM reservation r
             JOIN creneau c ON c.id = r.creneau_id JOIN offre o ON o.id = c.offre_id
             WHERE r.etat = 'EN_ATTENTE' AND $filtre", $params);
        foreach ($lignes as $l) {
            $this->db->executer("UPDATE reservation SET etat = 'ANNULEE', motif_annulation = ?, decidee_le = ?, cloturee_le = ?
                                 WHERE id = ? AND etat = 'EN_ATTENTE'",
                [$motif, $this->maintenant(), $this->maintenant(), $l['id']]);
            $this->notifier((int) $l['beneficiaire_id'], "Votre demande pour « {$l['titre']} » a été annulée : $motif",
                "/reservations/{$l['id']}");
        }
        return count($lignes);
    }
}
