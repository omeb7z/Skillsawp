<?php
declare(strict_types=1);

/**
 * Gestion autonome des erreurs : pages publiques et journalisation.
 *
 * Ce fichier ne dépend ni de la base de données, ni de la session, ni du
 * reste de l'application : il doit pouvoir afficher une page propre même si
 * la configuration ou SQLite est indisponible.
 */
final class Erreurs
{
    private const MESSAGES = [
        400 => ['Requête invalide', 'Le formulaire a expiré, contient des données invalides ou est incomplet. Rechargez la page et réessayez.'],
        403 => ['Accès refusé', "Vous n'avez pas les droits nécessaires pour consulter cette page."],
        404 => ['Page introuvable', "Cette page s'est perdue dans le sablier. Vérifiez l'adresse ou revenez à l'accueil."],
        405 => ['Méthode non autorisée', "Cette action n'est pas disponible de cette manière. Revenez à la page précédente et réessayez."],
        500 => ['Erreur interne', "Une erreur inattendue s'est produite. Réessayez dans un instant ; si le problème persiste, transmettez la référence ci-dessous à l'administration."],
    ];

    private static string $racine = '';
    private static bool $initialise = false;
    private static bool $erreurFataleAffichee = false;

    /** Prépare le report des erreurs PHP et la journalisation dans data/logs/. */
    public static function initialiser(string $racine): void
    {
        self::$racine = rtrim(str_replace('\\', '/', $racine), '/');
        if (self::$initialise) {
            return;
        }
        self::$initialise = true;
        date_default_timezone_set('Africa/Casablanca');
        error_reporting(E_ALL);
        ini_set('display_errors', PHP_SAPI === 'cli' ? '1' : '0');
        ini_set('log_errors', '1');

        $dossier = self::dossierLogs();
        self::creerDossier($dossier);
        ini_set('error_log', $dossier . '/php-errors.log');

        set_error_handler(static function (int $severite, string $message, string $fichier, int $ligne): bool {
            if (!(error_reporting() & $severite)) {
                return false;
            }
            // En ligne de commande, on laisse PHP afficher l'avertissement :
            // les tests doivent échouer bruyamment plutôt que masquer un bug.
            if (PHP_SAPI === 'cli') {
                return false;
            }
            self::journaliserTexte('Avertissement PHP', $message . ' — ' . $fichier . ':' . $ligne);
            return true;
        });

        register_shutdown_function([self::class, 'arreter']);
    }

    /** Dernier filet de sécurité pour les erreurs fatales (parse, mémoire, etc.). */
    public static function arreter(): void
    {
        $erreur = error_get_last();
        if ($erreur === null || self::$erreurFataleAffichee
            || !in_array($erreur['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR], true)) {
            return;
        }
        self::$erreurFataleAffichee = true;
        $reference = self::journaliserTexte('Erreur fatale',
            $erreur['message'] . ' — ' . $erreur['file'] . ':' . $erreur['line']);
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=utf-8');
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: DENY');
            echo self::rendre(500, $reference);
        }
    }

    /** Enregistre une exception et retourne une référence courte pour l'utilisateur. */
    public static function journaliser(Throwable $e, ?string $racine = null): string
    {
        $detail = get_class($e) . ': ' . $e->getMessage() . "\n"
            . 'Fichier : ' . $e->getFile() . ':' . $e->getLine() . "\n"
            . $e->getTraceAsString();
        return self::journaliserTexte('Exception non gérée', $detail, $racine);
    }

    public static function journaliserTexte(string $contexte, string $detail, ?string $racine = null): string
    {
        $reference = 'ERR-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3));
        try {
            $base = $racine !== null ? rtrim(str_replace('\\', '/', $racine), '/') : self::$racine;
            $dossier = $base . '/data/logs';
            self::creerDossier($dossier);
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';
            $methode = $_SERVER['REQUEST_METHOD'] ?? 'cli';
            $uri = $_SERVER['REQUEST_URI'] ?? '';
            $ligne = '[' . date('Y-m-d H:i:s') . '] ' . $reference . ' ' . $contexte . "\n"
                . 'Requête : ' . $methode . ' ' . $uri . ' depuis ' . $ip . "\n"
                . $detail . "\n" . str_repeat('-', 80) . "\n";
            @file_put_contents($dossier . '/erreurs-' . date('Y-m-d') . '.log', $ligne, FILE_APPEND | LOCK_EX);
        } catch (Throwable) {
            // Une panne de journalisation ne doit jamais masquer l'erreur d'origine.
        }
        return $reference;
    }

    /** Construit la page d'erreur complète, sans dépendre de la base ni du gabarit. */
    public static function rendre(int $code, ?string $reference = null, ?string $messagePersonnalise = null): string
    {
        [$titre, $message] = self::MESSAGES[$code] ?? ['Erreur', 'Une erreur est survenue.'];
        if ($messagePersonnalise !== null && $messagePersonnalise !== '') {
            $message = $messagePersonnalise;
        }
        $base = self::base();
        $css = $base . '/css/skillswap-v5.css';
        $accueil = $base . '/' ?: '/';
        ob_start();
        try {
            require __DIR__ . '/../vues/erreur.php';
        } catch (Throwable) {
            ob_end_clean();
            return '<!doctype html><html lang="fr"><meta charset="utf-8"><title>Erreur</title>'
                . '<body style="font-family:system-ui,sans-serif;padding:40px;background:#f3f4f8;color:#1a1d3a">'
                . '<h1>' . (int) $code . ' — Erreur</h1><p>Une erreur est survenue.</p>'
                . '<p><a href="/">Revenir à l’accueil</a></p></body></html>';
        }
        return (string) ob_get_clean();
    }

    /** Affiche la page et termine la requête. */
    public static function afficher(int $code, ?string $reference = null, ?string $messagePersonnalise = null): never
    {
        if (!headers_sent()) {
            http_response_code($code);
            header('Content-Type: text/html; charset=utf-8');
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: DENY');
            header('Referrer-Policy: same-origin');
            header('Cache-Control: no-store, private');
        }
        echo self::rendre($code, $reference, $messagePersonnalise);
        exit;
    }

    /** Base d'URL, calculée sans dépendre de la classe App. */
    public static function base(): string
    {
        if (PHP_SAPI === 'cli') {
            return '';
        }
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $base = rtrim(str_replace('\\', '/', dirname($script)), '/');
        return ($base === '/' || $base === '.') ? '' : $base;
    }

    private static function dossierLogs(): string
    {
        return self::$racine . '/data/logs';
    }

    private static function creerDossier(string $dossier): void
    {
        if (!is_dir($dossier)) {
            @mkdir($dossier, 0775, true);
        }
    }
}
