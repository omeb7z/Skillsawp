<?php
declare(strict_types=1);

/**
 * Sécurité : session, contrôle des droits, protection CSRF, messages flash.
 *
 * Les droits sont vérifiés côté serveur à deux niveaux :
 *  1. ici (être connecté, être étudiant, être administrateur) ;
 *  2. dans la couche métier (être participant de la séance, propriétaire de l'offre…).
 */
final class Securite
{
    private static ?array $utilisateur = null;

    public static function demarrerSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            self::chargerUtilisateur();
            return;
        }
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        session_name('skillswap');
        session_set_cookie_params(self::parametresCookie());
        session_start();
        self::chargerUtilisateur();
    }

    private static function parametresCookie(): array
    {
        $https = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
            || (string) ($_SERVER['SERVER_PORT'] ?? '') === '443';
        return [
            'lifetime' => 0,
            'path' => (App::base() ?: '') . '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => $https,
        ];
    }

    private static function chargerUtilisateur(): void
    {
        self::$utilisateur = null;
        $uid = $_SESSION['uid'] ?? null;
        if ($uid === null || (!is_int($uid) && !(is_string($uid) && ctype_digit($uid)))) {
            return;
        }
        $u = App::db()->un('SELECT id, nom, prenom, email, role, filiere, actif FROM utilisateur WHERE id = ?', [(int) $uid]);
        if ($u === null || !(int) $u['actif']) {
            // RG18 : un compte désactivé est déconnecté immédiatement.
            $desactive = $u !== null;
            $_SESSION = [];
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_regenerate_id(true);
            }
            if ($desactive) {
                flash('erreur', 'Votre compte a été désactivé.');
            }
            return;
        }
        self::$utilisateur = $u;
    }

    public static function utilisateur(): ?array
    {
        return self::$utilisateur;
    }

    public static function connecter(array $u): void
    {
        session_regenerate_id(true);                // nouvel identifiant : anti-fixation de session
        $_SESSION = [
            'uid' => (int) $u['id'],
            'csrf' => bin2hex(random_bytes(32)),
            'connecte_le' => time(),
        ];
        self::$utilisateur = $u;
    }

    /** Nouvel identifiant après un changement de mot de passe. */
    public static function regenererSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function deconnecter(): void
    {
        $_SESSION = [];
        $params = session_get_cookie_params();
        if (ini_get('session.use_cookies')) {
            $options = [
                'expires' => time() - 42000,
                'path' => $params['path'] ?: '/',
                'secure' => (bool) ($params['secure'] ?? false),
                'httponly' => (bool) ($params['httponly'] ?? true),
                'samesite' => $params['samesite'] ?: 'Lax',
            ];
            if (!empty($params['domain'])) {
                $options['domain'] = $params['domain'];
            }
            setcookie(session_name(), '', $options);
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        self::$utilisateur = null;
        // On ne redémarre pas de session ici : cela remplacerait l'en-tête
        // de suppression du cookie. La prochaine requête créera une session
        // anonyme neuve ; le message est affiché via /connexion?deconnecte=1.
    }

    public static function exigerConnexion(): array
    {
        if (self::$utilisateur === null) {
            flash('info', 'Connectez-vous pour accéder à cette page.');
            $chemin = chemin_interne(App::cheminDemande(), '/');
            rediriger('/connexion?suivant=' . rawurlencode($chemin));
        }
        return self::$utilisateur;
    }

    public static function exigerEtudiant(): array
    {
        $u = self::exigerConnexion();
        if ($u['role'] !== 'ETUDIANT') {
            throw new ErreurHttp(403);
        }
        return $u;
    }

    public static function exigerAdmin(): array
    {
        $u = self::exigerConnexion();
        if ($u['role'] !== 'ADMIN') {
            throw new ErreurHttp(403);
        }
        return $u;
    }

    public static function jetonCsrf(): string
    {
        return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
    }

    /** Tout formulaire POST doit renvoyer le jeton de la session. */
    public static function verifierCsrf(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $jeton = $_POST['csrf'] ?? '';
            if (!is_string($jeton) || $jeton === '' || !hash_equals((string) ($_SESSION['csrf'] ?? ''), $jeton)) {
                throw new ErreurHttp(400);
            }
        }
    }
}
