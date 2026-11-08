<?php
declare(strict_types=1);

/**
 * Validation de surface des formulaires.
 *
 * Elle complète la validation métier, qui reste la seule autorité : les
 * services revérifient toujours les règles. Son intérêt est de produire des
 * messages champ par champ, accessibles et affichés au bon endroit.
 */
final class Validation
{
    /**
     * @param array<string, mixed> $valeurs
     * @param array<string, array{
     *     libelle?: string,
     *     obligatoire?: bool,
     *     min?: int,
     *     max?: int,
     *     email?: bool,
     *     mot_de_passe?: bool,
     *     url?: bool,
     *     egal_a?: string,
     *     message_egal?: string
     * }> $regles
     * @return array<string, string>
     */
    public static function formulaire(array $valeurs, array $regles): array
    {
        $erreurs = [];
        foreach ($regles as $cle => $regle) {
            $libelle = $regle['libelle'] ?? $cle;
            $brut = $valeurs[$cle] ?? null;
            if ($brut !== null && !is_scalar($brut)) {
                $erreurs[$cle] = "Le champ « {$libelle} » est invalide.";
                continue;
            }

            $valeur = trim((string) ($brut ?? ''));
            $obligatoire = $regle['obligatoire'] ?? true;
            if ($valeur === '') {
                if ($obligatoire) {
                    $erreurs[$cle] = "Le champ « {$libelle} » est obligatoire.";
                }
                continue;
            }

            $longueur = mb_strlen($valeur);
            $minimum = $regle['min'] ?? 1;
            if ($longueur < $minimum) {
                $erreurs[$cle] = "« {$libelle} » doit contenir au moins {$minimum} caractères.";
                continue;
            }
            if (isset($regle['max']) && $longueur > $regle['max']) {
                $erreurs[$cle] = "« {$libelle} » ne doit pas dépasser {$regle['max']} caractères.";
                continue;
            }
            if (($regle['email'] ?? false) && filter_var($valeur, FILTER_VALIDATE_EMAIL) === false) {
                $erreurs[$cle] = 'Saisissez une adresse électronique valide.';
                continue;
            }
            if (($regle['mot_de_passe'] ?? false)
                && (strlen($valeur) < 8 || !preg_match('/[A-Za-z]/', $valeur) || !preg_match('/\d/', $valeur))) {
                $erreurs[$cle] = 'Le mot de passe doit contenir au moins 8 caractères, dont une lettre et un chiffre.';
                continue;
            }
            if (($regle['url'] ?? false) && preg_match('#^https?://\S+$#u', $valeur) !== 1) {
                $erreurs[$cle] = 'Indiquez une adresse commençant par http:// ou https://.';
                continue;
            }
            if (isset($regle['egal_a'])) {
                $attendu = trim((string) ($valeurs[$regle['egal_a']] ?? ''));
                if ($valeur !== $attendu) {
                    $erreurs[$cle] = $regle['message_egal'] ?? "Les deux valeurs ne correspondent pas.";
                }
            }
        }
        return $erreurs;
    }
}
