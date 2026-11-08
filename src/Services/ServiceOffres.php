<?php
declare(strict_types=1);

/** Offres, créneaux, recherche et suggestions (RG02, RG04, RG17). */
final class ServiceOffres extends Service
{
    public function offre(int $id): ?array
    {
        return $this->db->un(
            "SELECT o.*, c.nom AS competence, c.categorie_id, k.nom AS categorie, k.couleur,
                    u.prenom || ' ' || u.nom AS intervenant, u.actif AS intervenant_actif, u.filiere AS intervenant_filiere
             FROM offre o JOIN utilisateur u ON u.id = o.intervenant_id
             LEFT JOIN competence c ON c.id = o.competence_id LEFT JOIN categorie k ON k.id = c.categorie_id
             WHERE o.id = ?", [$id]);
    }

    private function offreDe(int $id, int $uid): array
    {
        $o = $this->offre($id);
        $this->exiger($o !== null, 'Offre introuvable.');
        if ((int) $o['intervenant_id'] !== $uid) {
            throw new AccesRefuse('Cette offre ne vous appartient pas.');
        }
        return $o;
    }

    private function aSeancesAttribuees(int $id): bool
    {
        return (bool) $this->db->valeur('SELECT 1 FROM reservation r JOIN creneau c ON c.id = r.creneau_id
            WHERE c.offre_id = ? AND r.etat IN ' . self::ETATS_ATTRIBUES, [$id]);
    }

    /** Ce qui empêche la publication d'une offre (offre « incomplète »). */
    public static function champsManquants(array $o, int $creneauxFuturs): array
    {
        $manque = [];
        if (empty($o['competence_id'])) $manque[] = 'compétence';
        if (empty($o['description']) || mb_strlen((string) $o['description']) < 20) $manque[] = 'description (20 caractères min.)';
        if (empty($o['niveau'])) $manque[] = 'niveau';
        if (empty($o['duree'])) $manque[] = 'durée';
        if (empty($o['mode'])) $manque[] = 'mode de déroulement';
        if ($creneauxFuturs === 0) $manque[] = 'au moins un créneau futur';
        return $manque;
    }

    /** Crée ou modifie une offre. Un brouillon peut être incomplet ; seule la publication exige une offre complète. */
    public function enregistrerOffre(int $uid, array $d, ?int $id = null): int
    {
        $titre = $this->texte($d['titre'] ?? null, 'Titre', 3, 80);
        $description = $this->texte($d['description'] ?? null, 'Description', 0, 1500, false);

        $competenceBrute = $d['competence_id'] ?? null;
        $this->exiger($competenceBrute === null || is_scalar($competenceBrute), 'Compétence invalide.');
        $competence = ($competenceBrute !== null && (string) $competenceBrute !== '') ? (int) $competenceBrute : null;
        if ($competence !== null) {
            $this->exiger($this->db->valeur('SELECT 1 FROM competence c JOIN categorie k ON k.id = c.categorie_id
                WHERE c.id = ? AND k.active = 1', [$competence]), 'Compétence inconnue.');
        }

        $niveauBrut = $d['niveau'] ?? null;
        $this->exiger($niveauBrut === null || is_scalar($niveauBrut), 'Niveau invalide.');
        $niveau = ($niveauBrut !== null && (string) $niveauBrut !== '') ? (string) $niveauBrut : null;
        $this->exiger($niveau === null || isset(Libelles::NIVEAUX[$niveau]), 'Niveau invalide.');

        $modeBrut = $d['mode'] ?? null;
        $this->exiger($modeBrut === null || is_scalar($modeBrut), 'Mode de déroulement invalide.');
        $mode = ($modeBrut !== null && (string) $modeBrut !== '') ? (string) $modeBrut : null;
        $this->exiger($mode === null || isset(Libelles::MODES[$mode]), 'Mode de déroulement invalide.');

        $dureeBrute = $d['duree'] ?? null;
        $this->exiger($dureeBrute === null || is_scalar($dureeBrute), 'Durée invalide.');
        $duree = ($dureeBrute !== null && (string) $dureeBrute !== '') ? (string) $dureeBrute : null;
        if ($duree !== null) {
            $this->exiger(in_array($duree, ['1', '2'], true), "La durée doit être d'une ou deux heures.", 'RG02');
            $duree = (int) $duree;
        }

        return $this->db->transaction(function () use ($uid, $id, $titre, $description, $competence, $niveau, $mode, $duree) {
            $t = $this->maintenant();
            if ($id === null) {
                return $this->db->inserer(
                    'INSERT INTO offre(intervenant_id, titre, competence_id, description, niveau, duree, mode, cree_le, modifie_le)
                     VALUES (?,?,?,?,?,?,?,?,?)', [$uid, $titre, $competence, $description, $niveau, $duree, $mode, $t, $t]);
            }
            $o = $this->offreDe($id, $uid);
            $ancienne = $o['duree'] !== null ? (int) $o['duree'] : null;
            if ($ancienne !== $duree && $this->aSeancesAttribuees($id)) {
                throw new ErreurMetier('La durée ne peut plus être modifiée : des séances ont déjà été acceptées.', 'RG17');
            }
            if ($ancienne !== $duree && $this->db->valeur('SELECT 1 FROM creneau WHERE offre_id = ? AND supprime = 0', [$id])) {
                throw new ErreurMetier('Supprimez d\'abord les créneaux existants pour changer la durée.', 'RG02');
            }
            $this->db->executer('UPDATE offre SET titre=?, competence_id=?, description=?, niveau=?, duree=?, mode=?, modifie_le=?
                                 WHERE id=?', [$titre, $competence, $description, $niveau, $duree, $mode, $t, $id]);
            if ($o['etat'] === 'PUBLIEE') {
                $manque = self::champsManquants($this->offre($id), 1);
                $this->exiger(!$manque, 'Une offre publiée doit rester complète. Il manque : ' . implode(', ', $manque) . '.');
            }
            return $id;
        });
    }

    public function creneauxFutursLibres(int $id): int
    {
        return (int) $this->db->valeur(
            'SELECT COUNT(*) FROM creneau c WHERE c.offre_id = ? AND c.supprime = 0 AND c.debut > ?
             AND NOT EXISTS (SELECT 1 FROM reservation r WHERE r.creneau_id = c.id AND r.etat IN ' . self::ETATS_ATTRIBUES . ')',
            [$id, $this->maintenant()]);
    }

    public function publierOffre(int $uid, int $id): void
    {
        $this->db->transaction(function () use ($uid, $id) {
            $o = $this->offreDe($id, $uid);
            $this->exiger(!(int) $o['masquee'], "Cette offre a été masquée par l'administration.");
            $this->exiger($o['etat'] !== 'PUBLIEE', "L'offre est déjà publiée.");
            $manque = self::champsManquants($o, $this->creneauxFutursLibres($id));
            $this->exiger(!$manque, 'Offre incomplète. Il manque : ' . implode(', ', $manque) . '.', 'RG04');
            $this->db->executer("UPDATE offre SET etat = 'PUBLIEE', modifie_le = ? WHERE id = ?", [$this->maintenant(), $id]);
        });
    }

    /** RG17 : plus de nouvelles demandes, attentes annulées, séances acceptées maintenues. */
    public function archiverOffre(int $uid, int $id): void
    {
        $this->db->transaction(function () use ($uid, $id) {
            $o = $this->offreDe($id, $uid);
            $this->exiger($o['etat'] !== 'ARCHIVEE', "L'offre est déjà archivée.");
            $this->db->executer("UPDATE offre SET etat = 'ARCHIVEE', modifie_le = ? WHERE id = ?", [$this->maintenant(), $id]);
            $this->annulerDemandesEnAttente('c.offre_id = ?', [$id], "l'offre a été archivée par l'intervenant.");
        });
    }

    public function ajouterCreneau(int $uid, int $id, ?string $debutTxt, ?string $finTxt, ?string $lieu, ?string $lien): int
    {
        $o = $this->offreDe($id, $uid);
        $this->exiger($o['etat'] !== 'ARCHIVEE', "Impossible d'ajouter un créneau à une offre archivée.");
        $this->exiger($o['duree'] && $o['mode'], "Choisissez d'abord la durée et le mode de l'offre.");
        $debut = Horloge::lire($debutTxt);
        $fin = Horloge::lire($finTxt);
        $this->exiger($debut && $fin, 'Dates du créneau invalides.');
        $this->exiger($debut > Horloge::maintenant($this->db), 'Le créneau doit être dans le futur.', 'RG04');
        $this->exiger($debut->modify('+' . (int) $o['duree'] . ' hours') == $fin,
            "Le créneau doit durer exactement {$o['duree']} h, comme l'offre.", 'RG02');
        $lieu = $this->texte($lieu, 'Lieu', 2, 120, false);
        $lien = trim((string) $lien) ?: null;
        if ($o['mode'] === 'PRESENTIEL') {
            $this->exiger($lieu, 'Précisez le lieu de la séance en présentiel.');
        } else {
            $this->exiger($lien && preg_match('#^https?://\S+$#', $lien), 'Indiquez un lien de réunion valide (http:// ou https://).');
        }
        $d = Horloge::texte($debut);
        $f = Horloge::texte($fin);
        return $this->db->transaction(function () use ($uid, $id, $d, $f, $lieu, $lien) {
            $chevauche = $this->db->valeur('SELECT 1 FROM creneau c JOIN offre o ON o.id = c.offre_id
                WHERE o.intervenant_id = ? AND c.supprime = 0 AND c.debut < ? AND c.fin > ?', [$uid, $f, $d]);
            $this->exiger(!$chevauche, 'Vous avez déjà un créneau qui chevauche cet horaire.');
            return $this->db->inserer('INSERT INTO creneau(offre_id, debut, fin, lieu, lien) VALUES (?,?,?,?,?)',
                [$id, $d, $f, $lieu, $lien]);
        });
    }

    public function supprimerCreneau(int $uid, int $creneau): void
    {
        $this->db->transaction(function () use ($uid, $creneau) {
            $c = $this->db->un('SELECT * FROM creneau WHERE id = ? AND supprime = 0', [$creneau]);
            $this->exiger($c !== null, 'Créneau introuvable.');
            $this->offreDe((int) $c['offre_id'], $uid);
            $this->exiger(!$this->db->valeur('SELECT 1 FROM reservation WHERE creneau_id = ? AND etat IN ' . self::ETATS_ATTRIBUES,
                [$creneau]), 'Ce créneau est attribué à une séance acceptée : il n\'est plus modifiable.', 'RG17');
            $this->annulerDemandesEnAttente('r.creneau_id = ?', [$creneau], 'le créneau a été retiré.');
            $this->db->executer('UPDATE creneau SET supprime = 1 WHERE id = ?', [$creneau]);
        });
    }

    public function creneauxOffre(int $id): array
    {
        return $this->db->tous(
            'SELECT c.*,
               (SELECT r.id FROM reservation r WHERE r.creneau_id = c.id AND r.etat IN ' . self::ETATS_ATTRIBUES . ') AS attribue,
               (SELECT COUNT(*) FROM reservation r WHERE r.creneau_id = c.id AND r.etat = \'EN_ATTENTE\') AS en_attente
             FROM creneau c WHERE c.offre_id = ? AND c.supprime = 0 ORDER BY c.debut', [$id]);
    }

    public function offresDe(int $uid): array
    {
        return $this->db->tous(
            "SELECT o.*, c.nom AS competence, k.nom AS categorie, k.couleur,
               (SELECT COUNT(*) FROM creneau cr WHERE cr.offre_id = o.id AND cr.supprime = 0 AND cr.debut > ?) AS futurs,
               (SELECT COUNT(*) FROM reservation r JOIN creneau cr ON cr.id = r.creneau_id
                  WHERE cr.offre_id = o.id AND r.etat = 'EN_ATTENTE') AS demandes
             FROM offre o LEFT JOIN competence c ON c.id = o.competence_id LEFT JOIN categorie k ON k.id = c.categorie_id
             WHERE o.intervenant_id = ? ORDER BY o.modifie_le DESC", [$this->maintenant(), $uid]);
    }

    private function sqlCatalogue(): string
    {
        return "SELECT o.*, c.nom AS competence, k.id AS categorie_id, k.nom AS categorie, k.couleur,
                   u.prenom || ' ' || u.nom AS intervenant,
                   (SELECT ROUND(AVG(e.note), 1) FROM evaluation e WHERE e.cible_id = o.intervenant_id) AS note,
                   (SELECT MIN(cr.debut) FROM creneau cr WHERE cr.offre_id = o.id AND cr.supprime = 0 AND cr.debut > :now
                      AND NOT EXISTS (SELECT 1 FROM reservation r WHERE r.creneau_id = cr.id
                                      AND r.etat IN " . self::ETATS_ATTRIBUES . ")) AS prochain
                FROM offre o JOIN utilisateur u ON u.id = o.intervenant_id
                JOIN competence c ON c.id = o.competence_id JOIN categorie k ON k.id = c.categorie_id
                WHERE o.etat = 'PUBLIEE' AND o.masquee = 0 AND u.actif = 1 AND k.active = 1";
    }

    /** Recherche multicritère dans les offres publiées (RG04). */
    public function rechercher(array $f = []): array
    {
        $sql = $this->sqlCatalogue();
        $p = ['now' => $this->maintenant()];
        $q = is_scalar($f['q'] ?? null) ? trim((string) $f['q']) : '';
        $categorie = is_scalar($f['categorie'] ?? null) ? (int) $f['categorie'] : 0;
        $niveau = is_scalar($f['niveau'] ?? null) ? (string) $f['niveau'] : '';
        $mode = is_scalar($f['mode'] ?? null) ? (string) $f['mode'] : '';
        $date = is_scalar($f['date'] ?? null) ? (string) $f['date'] : '';
        if ($q !== '') {
            $sql .= ' AND (o.titre LIKE :q OR c.nom LIKE :q OR o.description LIKE :q)';
            $p['q'] = '%' . $q . '%';
        }
        if ($categorie > 0) {
            $sql .= ' AND k.id = :cat';
            $p['cat'] = $categorie;
        }
        if ($niveau !== '' && isset(Libelles::NIVEAUX[$niveau])) {
            $sql .= ' AND o.niveau = :niv';
            $p['niv'] = $niveau;
        }
        if ($mode !== '' && isset(Libelles::MODES[$mode])) {
            $sql .= ' AND o.mode = :mode';
            $p['mode'] = $mode;
        }
        if ($date !== '') {
            $sql .= ' AND EXISTS (SELECT 1 FROM creneau cr WHERE cr.offre_id = o.id AND cr.supprime = 0
                        AND substr(cr.debut, 1, 10) = :date AND cr.debut > :now
                        AND NOT EXISTS (SELECT 1 FROM reservation r WHERE r.creneau_id = cr.id
                                        AND r.etat IN ' . self::ETATS_ATTRIBUES . '))';
            $p['date'] = $date;
        }
        return $this->db->tous($sql . ' ORDER BY (prochain IS NULL), prochain', $p);
    }

    /**
     * Offres correspondant aux compétences recherchées du profil.
     * Score explicable (sans IA) : +3 compétence exacte, +1 même catégorie,
     * + note moyenne / 5 pour départager. Seules les offres d'autres étudiants
     * avec au moins un créneau futur libre sont retenues.
     */
    public function suggestions(int $uid, int $limite = 12): array
    {
        $sql = 'SELECT * FROM (' . $this->sqlCatalogue() . ') AS o2
                WHERE o2.intervenant_id <> :uid AND o2.prochain IS NOT NULL
                  AND (o2.competence_id IN (SELECT competence_id FROM etudiant_competence
                                            WHERE etudiant_id = :uid AND sens = \'RECHERCHEE\')
                    OR o2.categorie_id IN (SELECT c.categorie_id FROM etudiant_competence ec
                                           JOIN competence c ON c.id = ec.competence_id
                                           WHERE ec.etudiant_id = :uid AND ec.sens = \'RECHERCHEE\'))';
        $lignes = $this->db->tous($sql, ['now' => $this->maintenant(), 'uid' => $uid]);
        $recherchees = array_map('intval', array_column(
            (new ServiceComptes($this->db))->competencesDe($uid, 'RECHERCHEE'), 'id'));
        $resultat = [];
        foreach ($lignes as $l) {
            $exacte = in_array((int) $l['competence_id'], $recherchees, true);
            $l['exacte'] = $exacte;
            $l['score'] = round(($exacte ? 3 : 1) + ((float) ($l['note'] ?? 0)) / 5, 2);
            $l['raison'] = $exacte ? "Vous cherchez « {$l['competence']} »" : "Même catégorie : {$l['categorie']}";
            $resultat[] = $l;
        }
        usort($resultat, fn($a, $b) => [$b['score'], $a['prochain']] <=> [$a['score'], $b['prochain']]);
        return array_slice($resultat, 0, $limite);
    }
}
