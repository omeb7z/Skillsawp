<?php
declare(strict_types=1);

/**
 * Horloge de l'application.
 *
 * Toutes les règles temporelles (créneau futur, annulation avant le début,
 * confirmation après la fin) lisent l'heure ici, jamais via date() directement :
 *  - l'administrateur peut « avancer le temps » en démonstration
 *    (décalage stocké dans la table `parametre`) ;
 *  - les tests et le jeu de démonstration peuvent figer l'heure.
 */
final class Horloge
{
    public const FORMAT = 'Y-m-d H:i';
    private static ?DateTimeImmutable $figee = null;

    public static function figer(?DateTimeImmutable $moment): void
    {
        self::$figee = $moment;
    }

    public static function decalageMinutes(Db $db): int
    {
        return (int) ($db->valeur("SELECT valeur FROM parametre WHERE cle = 'decalage_minutes'") ?? 0);
    }

    public static function maintenant(?Db $db = null): DateTimeImmutable
    {
        if (self::$figee !== null) {
            return self::$figee;
        }
        $base = new DateTimeImmutable(date('Y-m-d H:i:00'));
        return $db ? $base->modify(sprintf('%+d minutes', self::decalageMinutes($db))) : $base;
    }

    public static function maintenantTxt(?Db $db = null): string
    {
        return self::maintenant($db)->format(self::FORMAT);
    }

    public static function texte(DateTimeImmutable $d): string
    {
        return $d->format(self::FORMAT);
    }

    /** Lit 'Y-m-d H:i' ou 'Y-m-dTH:i' (champ datetime-local). Retourne null si invalide. */
    public static function lire(?string $txt): ?DateTimeImmutable
    {
        $txt = str_replace('T', ' ', trim((string) $txt));
        $d = DateTimeImmutable::createFromFormat('!' . self::FORMAT, $txt);
        return ($d && $d->format(self::FORMAT) === $txt) ? $d : null;
    }
}
