<?php
declare(strict_types=1);

/**
 * Accès à la base SQLite (PDO) et gestion des transactions.
 *
 * Chaque opération métier s'exécute dans transaction(), qui ouvre un
 * « BEGIN IMMEDIATE » : SQLite pose le verrou d'écriture dès le début.
 * Deux clics simultanés sur « Accepter » ou « Confirmer » sont donc
 * sérialisés : le second voit l'état laissé par le premier et échoue
 * proprement (ni double transfert, ni double attribution).
 */
final class Db
{
    private int $profondeur = 0;

    private function __construct(public readonly PDO $pdo) {}

    public static function connecter(string $chemin): self
    {
        $pdo = new PDO('sqlite:' . $chemin, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 15,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA busy_timeout = 15000');
        try {
            $pdo->exec('PRAGMA journal_mode = WAL');
        } catch (PDOException) {
            // Hébergement sans WAL (système de fichiers restreint) : le mode
            // rollback classique reste parfaitement valide.
            try {
                $pdo->exec('PRAGMA journal_mode = DELETE');
            } catch (PDOException) {
                // Certains hébergeurs refusent même de changer ce pragma ;
                // la base fonctionne alors avec son mode par défaut.
            }
        }
        $verif = $pdo->query('PRAGMA foreign_keys');
        if ($verif !== false && (int) $verif->fetchColumn() !== 1) {
            throw new RuntimeException('SQLite refuse d’activer les clés étrangères.');
        }
        return new self($pdo);
    }

    public function creerSchema(): void
    {
        $sql = file_get_contents(__DIR__ . '/../database/schema.sql');
        if ($sql === false) {
            throw new RuntimeException('Schéma de base introuvable.');
        }
        $this->pdo->exec($sql);
    }

    /** Exécute $operation dans une transaction exclusive : tout ou rien. */
    public function transaction(callable $operation): mixed
    {
        if ($this->profondeur > 0) {           // transaction imbriquée : on réutilise l'englobante
            return $operation();
        }
        $this->pdo->exec('BEGIN IMMEDIATE');
        $this->profondeur++;
        try {
            $resultat = $operation();
            $this->pdo->exec('COMMIT');
            return $resultat;
        } catch (Throwable $e) {
            try {
                $this->pdo->exec('ROLLBACK');
            } catch (Throwable) {
                // La transaction a déjà été annulée par SQLite.
            }
            throw $e;
        } finally {
            $this->profondeur--;
        }
    }

    /** Exécute une requête d'écriture et retourne le nombre de lignes modifiées. */
    public function executer(string $sql, array $params = []): int
    {
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        return $st->rowCount();
    }

    public function inserer(string $sql, array $params = []): int
    {
        $this->executer($sql, $params);
        return (int) $this->pdo->lastInsertId();
    }

    public function un(string $sql, array $params = []): ?array
    {
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        $l = $st->fetch();
        return $l === false ? null : $l;
    }

    public function tous(string $sql, array $params = []): array
    {
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }

    public function valeur(string $sql, array $params = []): mixed
    {
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        $v = $st->fetchColumn();
        return $v === false ? null : $v;
    }
}
