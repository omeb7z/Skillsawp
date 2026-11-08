<?php
declare(strict_types=1);

/**
 * Migrations automatiques et idempotentes de la base SQLite.
 *
 * Le numéro de version est stocké dans PRAGMA user_version :
 *   0 = base ancienne ou vide ;
 *   1 = schéma de base (database/schema.sql) ;
 *   2 = table de limitation des tentatives de connexion ;
 *   3 = garde-fous de finalité des litiges et des réservations.
 *
 * Aucune migration ne supprime de données. Une base ancienne est adoptée
 * telle quelle après vérification de ses tables, puis migrée.
 */
final class Migrateur
{
    public const VERSION = 3;

    /** Tables indispensables pour reconnaître une base SkillSwap existante. */
    private const TABLES_BASE = [
        'utilisateur', 'portefeuille', 'categorie', 'competence', 'etudiant_competence',
        'offre', 'creneau', 'reservation', 'confirmation', 'mouvement', 'litige',
        'element_litige', 'evaluation', 'notification', 'parametre',
    ];

    public static function migrer(Db $db, bool $ecrireIdentifiants = false): void
    {
        $db->transaction(function () use ($db, $ecrireIdentifiants): void {
            $version = self::version($db);
            $existe = self::tableExiste($db, 'utilisateur');

            if (!$existe) {
                self::creerSchemaDeBase($db);
                self::definirVersion($db, 1);
                $version = 1;
            } elseif ($version < 1) {
                self::adopterBaseExistante($db);
                self::definirVersion($db, 1);
                $version = 1;
            }

            if ($version < 2) {
                self::migration2LimitationConnexions($db);
                self::definirVersion($db, 2);
                $version = 2;
            }

            if ($version < 3) {
                self::migration3GardiensLitiges($db);
                self::definirVersion($db, 3);
                $version = 3;
            }

            if ($version > self::VERSION) {
                throw new RuntimeException(
                    'Cette base a été créée par une version plus récente de SkillSwap Campus (version '
                    . $version . '). Mettez l’application à jour avant de continuer.'
                );
            }

            self::creerPremierAdmin($db, $ecrireIdentifiants);
        });
    }

    public static function version(Db $db): int
    {
        return (int) $db->pdo->query('PRAGMA user_version')->fetchColumn();
    }

    private static function definirVersion(Db $db, int $version): void
    {
        $db->pdo->exec('PRAGMA user_version = ' . max(0, $version));
    }

    private static function tableExiste(Db $db, string $nom): bool
    {
        return (bool) $db->valeur(
            "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = ?",
            [$nom]
        );
    }

    private static function creerSchemaDeBase(Db $db): void
    {
        $fichier = __DIR__ . '/../database/schema.sql';
        $sql = @file_get_contents($fichier);
        if ($sql === false) {
            throw new RuntimeException('Le schéma de base est introuvable : ' . $fichier);
        }
        $db->pdo->exec($sql);
    }

    /**
     * Base créée avant l'introduction des migrations : on vérifie qu'elle est
     * complète avant de la déclarer en version 1. On ne recrée jamais une
     * table existante et on ne supprime rien.
     */
    private static function adopterBaseExistante(Db $db): void
    {
        $manquantes = [];
        foreach (self::TABLES_BASE as $table) {
            if (!self::tableExiste($db, $table)) {
                $manquantes[] = $table;
            }
        }
        if ($manquantes !== []) {
            throw new RuntimeException(
                'La base existante est incomplète (tables manquantes : ' . implode(', ', $manquantes)
                . '). Sauvegardez-la puis contactez l’administrateur : aucune donnée n’a été supprimée.'
            );
        }
    }

    /** Version 2 : historisation des échecs de connexion pour le anti-bruteforce. */
    private static function migration2LimitationConnexions(Db $db): void
    {
        $db->pdo->exec(
            "CREATE TABLE IF NOT EXISTS tentative_connexion (
                id      INTEGER PRIMARY KEY,
                email   TEXT    NOT NULL COLLATE NOCASE,
                ip      TEXT    NOT NULL,
                reussi  INTEGER NOT NULL DEFAULT 0 CHECK (reussi IN (0,1)),
                cree_le TEXT    NOT NULL
            );
            CREATE INDEX IF NOT EXISTS ix_tentative_email ON tentative_connexion(email, cree_le);
            CREATE INDEX IF NOT EXISTS ix_tentative_ip    ON tentative_connexion(ip, cree_le);"
        );
    }

    /**
     * Version 3 : la machine d'état des litiges est également protégée dans
     * SQLite. Les services restent la première barrière ; ces déclencheurs
     * empêchent une écriture directe de rouvrir un dossier réglé, de modifier
     * sa décision, d'ajouter une pièce après clôture ou de finaliser une
     * réservation alors que son litige est encore ouvert.
     */
    private static function migration3GardiensLitiges(Db $db): void
    {
        $sql = <<<'SQL'
DROP TRIGGER IF EXISTS trg_litige_insertion_ouverte;
DROP TRIGGER IF EXISTS trg_litige_transition_finale;
DROP TRIGGER IF EXISTS trg_litige_finalisation_complete;
DROP TRIGGER IF EXISTS trg_litige_decision_figee;
DROP TRIGGER IF EXISTS trg_element_litige_ouvert;
DROP TRIGGER IF EXISTS trg_reservation_finalisation_litige;
DROP TRIGGER IF EXISTS trg_reservation_etat_final;

CREATE INDEX IF NOT EXISTS ix_litige_etat     ON litige(etat, ouvert_le);
CREATE INDEX IF NOT EXISTS ix_element_litige  ON element_litige(litige_id, cree_le);

CREATE TRIGGER trg_litige_insertion_ouverte
BEFORE INSERT ON litige
FOR EACH ROW
WHEN NEW.etat <> 'OUVERT'
  OR (SELECT etat FROM reservation WHERE id = NEW.reservation_id) <> 'EN_LITIGE'
BEGIN
    SELECT RAISE(ABORT, 'Un litige doit être ouvert sur une séance en litige.');
END;

CREATE TRIGGER trg_litige_transition_finale
BEFORE UPDATE OF etat ON litige
FOR EACH ROW
WHEN NEW.etat <> OLD.etat
  AND NOT (OLD.etat = 'OUVERT' AND NEW.etat = 'REGLE')
BEGIN
    SELECT RAISE(ABORT, 'Transition de litige invalide.');
END;

CREATE TRIGGER trg_litige_finalisation_complete
BEFORE UPDATE OF etat ON litige
FOR EACH ROW
WHEN NEW.etat = 'REGLE'
  AND (NEW.decision IS NULL OR NEW.justification IS NULL
       OR NEW.admin_id IS NULL OR NEW.regle_le IS NULL)
BEGIN
    SELECT RAISE(ABORT, 'Finalisation de litige incomplète.');
END;

CREATE TRIGGER trg_litige_decision_figee
BEFORE UPDATE OF decision, justification, admin_id, regle_le ON litige
FOR EACH ROW
WHEN OLD.etat = 'REGLE'
BEGIN
    SELECT RAISE(ABORT, 'La décision d''un litige réglé est définitive.');
END;

CREATE TRIGGER trg_element_litige_ouvert
BEFORE INSERT ON element_litige
FOR EACH ROW
WHEN (SELECT etat FROM litige WHERE id = NEW.litige_id) <> 'OUVERT'
BEGIN
    SELECT RAISE(ABORT, 'Ce litige est clos : aucun nouvel élément ne peut être ajouté.');
END;

CREATE TRIGGER trg_reservation_finalisation_litige
BEFORE UPDATE OF etat ON reservation
FOR EACH ROW
WHEN OLD.etat = 'EN_LITIGE'
  AND NEW.etat IN ('REALISEE', 'ANNULEE')
  AND EXISTS (SELECT 1 FROM litige WHERE reservation_id = OLD.id AND etat = 'OUVERT')
BEGIN
    SELECT RAISE(ABORT, 'Le litige doit être réglé avant de clôturer la séance.');
END;

CREATE TRIGGER trg_reservation_etat_final
BEFORE UPDATE OF etat ON reservation
FOR EACH ROW
WHEN OLD.etat IN ('REALISEE', 'ANNULEE') AND NEW.etat <> OLD.etat
BEGIN
    SELECT RAISE(ABORT, 'Une réservation clôturée est définitive.');
END;
SQL;
        $db->pdo->exec($sql);
    }

    /**
     * Crée un administrateur si aucun n'existe. Le mot de passe est aléatoire
     * et n'est écrit que dans data/PREMIER_ACCES.txt (hors dépôt, protégé par
     * .htaccess). Il n'y a donc aucun mot de passe administrateur en source.
     */
    private static function creerPremierAdmin(Db $db, bool $ecrireIdentifiants): void
    {
        $admin = (int) ($db->valeur("SELECT COUNT(*) FROM utilisateur WHERE role = 'ADMIN'") ?? 0);
        if ($admin > 0) {
            return;
        }

        $motDePasse = self::motDePasseAleatoire();
        $service = new ServiceComptes($db);
        $service->inscrire('Admin', 'Système', 'admin@skillswap.ma', $motDePasse, $motDePasse, null, 'ADMIN');

        if ($ecrireIdentifiants) {
            self::ecrirePremierAcces($motDePasse);
        }
    }

    private static function motDePasseAleatoire(): string
    {
        // 20 caractères, avec au moins une lettre et un chiffre.
        return 'Sk' . bin2hex(random_bytes(9)) . '9a';
    }

    private static function ecrirePremierAcces(string $motDePasse): void
    {
        $dossier = dirname(__DIR__) . '/data';
        if (!is_dir($dossier) && !@mkdir($dossier, 0775, true) && !is_dir($dossier)) {
            throw new RuntimeException('Le dossier data/ est introuvable et ne peut pas être créé.');
        }
        $fichier = $dossier . '/PREMIER_ACCES.txt';
        $contenu = "SkillSwap Campus — accès administrateur initial\n"
            . "Créé le : " . date('Y-m-d H:i:s') . "\n\n"
            . "Adresse électronique : admin@skillswap.ma\n"
            . "Mot de passe : " . $motDePasse . "\n\n"
            . "Conservez ce mot de passe dans un gestionnaire de mots de passe.\n"
            . "Connectez-vous, changez-le immédiatement depuis Administration → Mot de passe,\n"
            . "puis supprimez ce fichier du serveur.\n";
        if (@file_put_contents($fichier, $contenu, LOCK_EX) === false) {
            throw new RuntimeException(
                'Impossible d’écrire data/PREMIER_ACCES.txt. Vérifiez les droits du dossier data/.'
            );
        }
        @chmod($fichier, 0600);
    }
}
