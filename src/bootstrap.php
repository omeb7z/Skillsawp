<?php
declare(strict_types=1);

/**
 * Point de démarrage commun (web, scripts, tests) :
 * chargement automatique des classes, configuration, migrations, accès base.
 */
date_default_timezone_set('Africa/Casablanca');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/Exceptions.php';
require_once __DIR__ . '/Erreurs.php';
require_once __DIR__ . '/helpers.php';

Erreurs::initialiser(dirname(__DIR__));

spl_autoload_register(function (string $classe): void {
    foreach (['', 'Services/', 'Controleurs/'] as $dossier) {
        $f = __DIR__ . "/$dossier$classe.php";
        if (is_file($f)) {
            require_once $f;
            return;
        }
    }
});

final class App
{
    private static ?Db $db = null;
    private static ?array $config = null;

    public static function racine(): string
    {
        return dirname(__DIR__);
    }

    public static function config(): array
    {
        return self::$config ??= require __DIR__ . '/../config.php';
    }

    public static function db(): Db
    {
        if (self::$db === null) {
            $chemin = (string) self::config()['database'];
            self::preparerDossier($chemin);
            $db = Db::connecter($chemin);
            Migrateur::migrer($db, true);
            self::$db = $db;
        }
        return self::$db;
    }

    /** Pour les tests et les scripts : utiliser une autre base. */
    public static function utiliserBase(string $chemin): Db
    {
        self::preparerDossier($chemin);
        $db = Db::connecter($chemin);
        Migrateur::migrer($db, false);
        return self::$db = $db;
    }

    /** Préfixe d'URL quand l'application est dans un sous-dossier (XAMPP, htdocs/skillswap). */
    public static function base(): string
    {
        if (PHP_SAPI === 'cli') {
            return '';
        }
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $base = rtrim(str_replace('\\', '/', dirname($script)), '/');
        return ($base === '/' || $base === '.') ? '' : $base;
    }

    /**
     * Chemin de la requête, débarrassé du préfixe de sous-dossier et de
     * index.php. Sert au routeur, aux redirections et au menu actif.
     */
    public static function cheminDemande(): string
    {
        $chemin = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        if (!is_string($chemin) || $chemin === '') {
            $chemin = '/';
        }
        $chemin = rawurldecode($chemin);
        $base = self::base();
        if ($base !== '' && ($chemin === $base || str_starts_with($chemin, $base . '/'))) {
            $chemin = substr($chemin, strlen($base));
            if ($chemin === '' || $chemin === false) {
                $chemin = '/';
            }
        }
        if ($chemin === '/index.php' || $chemin === '/index.php/') {
            $chemin = '/';
        }
        return $chemin !== '' && $chemin[0] === '/' ? $chemin : '/' . $chemin;
    }

    private static function preparerDossier(string $chemin): void
    {
        $dossier = dirname($chemin);
        if (!is_dir($dossier) && !@mkdir($dossier, 0775, true) && !is_dir($dossier)) {
            throw new RuntimeException('Le dossier de la base de données est introuvable : ' . $dossier);
        }
    }
}
