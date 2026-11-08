<?php
declare(strict_types=1);

/** Catégories et compétences (gérées par l'administrateur). */
final class ServiceCatalogue extends Service
{
    public function categories(bool $activesSeulement = false): array
    {
        $sql = 'SELECT k.*, (SELECT COUNT(*) FROM competence c WHERE c.categorie_id = k.id) AS nb_competences FROM categorie k';
        if ($activesSeulement) {
            $sql .= ' WHERE k.active = 1';
        }
        return $this->db->tous($sql . ' ORDER BY k.nom');
    }

    /** Compétences des catégories actives, groupées par catégorie (pour les formulaires). */
    public function competencesParCategorie(): array
    {
        $groupes = [];
        foreach ($this->db->tous('SELECT c.id, c.nom, k.id AS categorie_id, k.nom AS categorie, k.couleur
                                  FROM competence c JOIN categorie k ON k.id = c.categorie_id
                                  WHERE k.active = 1 ORDER BY k.nom, c.nom') as $l) {
            $groupes[$l['categorie_id']] ??= ['nom' => $l['categorie'], 'couleur' => $l['couleur'], 'competences' => []];
            $groupes[$l['categorie_id']]['competences'][] = $l;
        }
        return $groupes;
    }

    private function couleur(?string $c): string
    {
        return preg_match('/^#[0-9a-fA-F]{6}$/', (string) $c) ? (string) $c : '#c8553d';
    }

    public function creerCategorie(?string $nom, ?string $description, ?string $couleur): int
    {
        $nom = $this->texte($nom, 'Nom de la catégorie', 2, 40);
        $description = $this->texte($description, 'Description', 0, 200, false);
        try {
            return $this->db->transaction(fn() => $this->db->inserer(
                'INSERT INTO categorie(nom, description, couleur) VALUES (?,?,?)', [$nom, $description, $this->couleur($couleur)]));
        } catch (PDOException) {
            throw new ErreurMetier('Une catégorie porte déjà ce nom.');
        }
    }

    public function modifierCategorie(int $id, ?string $nom, ?string $description, ?string $couleur): void
    {
        $nom = $this->texte($nom, 'Nom de la catégorie', 2, 40);
        $description = $this->texte($description, 'Description', 0, 200, false);
        try {
            $this->db->transaction(fn() => $this->db->executer('UPDATE categorie SET nom=?, description=?, couleur=? WHERE id=?',
                [$nom, $description, $this->couleur($couleur), $id]));
        } catch (PDOException) {
            throw new ErreurMetier('Une catégorie porte déjà ce nom.');
        }
    }

    public function basculerCategorie(int $id): void
    {
        $this->db->transaction(fn() => $this->db->executer('UPDATE categorie SET active = 1 - active WHERE id = ?', [$id]));
    }

    public function ajouterCompetence(int $categorie, ?string $nom): int
    {
        $nom = $this->texte($nom, 'Nom de la compétence', 2, 40);
        $this->exiger($this->db->valeur('SELECT 1 FROM categorie WHERE id = ?', [$categorie]), 'Catégorie inconnue.');
        try {
            return $this->db->transaction(fn() => $this->db->inserer(
                'INSERT INTO competence(nom, categorie_id) VALUES (?,?)', [$nom, $categorie]));
        } catch (PDOException) {
            throw new ErreurMetier('Cette compétence existe déjà dans la catégorie.');
        }
    }

    /** @return array<int, array> compétences indexées par catégorie (écran admin) */
    public function competencesIndexees(): array
    {
        $r = [];
        foreach ($this->db->tous('SELECT * FROM competence ORDER BY nom') as $c) {
            $r[(int) $c['categorie_id']][] = $c;
        }
        return $r;
    }
}
