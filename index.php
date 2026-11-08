<?php
declare(strict_types=1);

/**
 * Contrôleur frontal : toutes les requêtes dynamiques passent ici.
 *
 * L'application est prévue pour être déposée telle quelle dans htdocs/
 * (ou dans un sous-dossier) chez un hébergeur Apache/PHP sans shell.
 */

require __DIR__ . '/src/Erreurs.php';
Erreurs::initialiser(__DIR__);

// Serveur intégré de PHP : seuls les fichiers statiques explicitement
// autorisés sont servis directement. On ne laisse jamais PHP servir un
// fichier source, une base ou un script.
if (PHP_SAPI === 'cli-server') {
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $fichiersPwa = ['/manifest.webmanifest', '/sw.js', '/offline.html'];
    if (is_string($uri)
        && is_file(__DIR__ . $uri)
        && (preg_match('#^/(?:css|js)/[A-Za-z0-9._-]+\.(?:css|js|svg|png|jpe?g|webp|gif|ico|woff2?|ttf|otf)$#i', $uri)
            || in_array($uri, $fichiersPwa, true)
            || preg_match('#^/apk/[A-Za-z0-9._-]+\.apk$#i', $uri)
            || preg_match('#^/icons/[A-Za-z0-9._-]+\.(?:png|svg|ico|webp)$#i', $uri))) {
        // Le serveur intégré ne connaît pas toujours le type MIME du manifeste.
        if ($uri === '/manifest.webmanifest') {
            header('Content-Type: application/manifest+json; charset=utf-8');
        }
        if (preg_match('#\.apk$#i', $uri)) {
            header('Content-Type: application/vnd.android.package-archive');
            header('Content-Disposition: attachment; filename="' . basename($uri) . '"');
        }
        return false;
    }
}

require __DIR__ . '/src/bootstrap.php';

if (!headers_sent()) {
    header_remove('X-Powered-By');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header('Cache-Control: no-store, private');
}

/** Table des routes : [méthode, motif, contrôleur, action] */
const ROUTES = [
    ['GET',  '#^/$#', AuthControleur::class, 'accueil'],
    ['GET',  '#^/inscription$#', AuthControleur::class, 'inscription'],
    ['POST', '#^/inscription$#', AuthControleur::class, 'inscription'],
    ['GET',  '#^/connexion$#', AuthControleur::class, 'connexion'],
    ['POST', '#^/connexion$#', AuthControleur::class, 'connexion'],
    ['POST', '#^/deconnexion$#', AuthControleur::class, 'deconnexion'],

    ['GET',  '#^/tableau-de-bord$#', EtudiantControleur::class, 'tableau'],
    ['GET',  '#^/profil$#', EtudiantControleur::class, 'profil'],
    ['POST', '#^/profil$#', EtudiantControleur::class, 'profil'],
    ['POST', '#^/profil/mot-de-passe$#', EtudiantControleur::class, 'motDePasse'],
    ['GET',  '#^/profil/(\d+)$#', EtudiantControleur::class, 'profilPublic'],
    ['GET',  '#^/portefeuille$#', EtudiantControleur::class, 'portefeuille'],
    ['GET',  '#^/planning$#', EtudiantControleur::class, 'planning'],
    ['GET',  '#^/notifications$#', EtudiantControleur::class, 'notifications'],
    ['POST', '#^/notifications/lire$#', EtudiantControleur::class, 'lireNotifications'],
    ['GET',  '#^/notifications/(\d+)$#', EtudiantControleur::class, 'ouvrirNotification'],

    ['GET',  '#^/catalogue$#', OffresControleur::class, 'catalogue'],
    ['GET',  '#^/suggestions$#', OffresControleur::class, 'suggestions'],
    ['GET',  '#^/mes-offres$#', OffresControleur::class, 'mesOffres'],
    ['GET',  '#^/offres/nouvelle$#', OffresControleur::class, 'nouvelle'],
    ['POST', '#^/offres/nouvelle$#', OffresControleur::class, 'nouvelle'],
    ['GET',  '#^/offres/(\d+)$#', OffresControleur::class, 'fiche'],
    ['GET',  '#^/offres/(\d+)/modifier$#', OffresControleur::class, 'modifier'],
    ['POST', '#^/offres/(\d+)/modifier$#', OffresControleur::class, 'modifier'],
    ['POST', '#^/offres/(\d+)/publier$#', OffresControleur::class, 'publier'],
    ['POST', '#^/offres/(\d+)/archiver$#', OffresControleur::class, 'archiver'],
    ['POST', '#^/offres/(\d+)/creneaux$#', OffresControleur::class, 'ajouterCreneau'],
    ['POST', '#^/creneaux/(\d+)/supprimer$#', OffresControleur::class, 'supprimerCreneau'],

    ['POST', '#^/creneaux/(\d+)/demander$#', ReservationsControleur::class, 'demander'],
    ['GET',  '#^/demandes$#', ReservationsControleur::class, 'demandes'],
    ['GET',  '#^/reservations/(\d+)$#', ReservationsControleur::class, 'detail'],
    ['POST', '#^/reservations/(\d+)/([a-z]+)$#', ReservationsControleur::class, 'agir'],
    ['GET',  '#^/litiges/(\d+)$#', ReservationsControleur::class, 'litige'],
    ['POST', '#^/litiges/(\d+)/elements$#', ReservationsControleur::class, 'ajouterElement'],

    ['GET',  '#^/admin/?$#', AdminControleur::class, 'tableau'],
    ['GET',  '#^/admin/comptes$#', AdminControleur::class, 'comptes'],
    ['POST', '#^/admin/comptes/(\d+)/basculer$#', AdminControleur::class, 'basculerCompte'],
    ['GET',  '#^/admin/categories$#', AdminControleur::class, 'categories'],
    ['POST', '#^/admin/categories$#', AdminControleur::class, 'categories'],
    ['GET',  '#^/admin/offres$#', AdminControleur::class, 'offres'],
    ['POST', '#^/admin/offres/(\d+)/(masquer|reafficher)$#', AdminControleur::class, 'moderer'],
    ['GET',  '#^/admin/litiges$#', AdminControleur::class, 'litiges'],
    ['POST', '#^/admin/litiges/(\d+)/decider$#', AdminControleur::class, 'decider'],
    ['GET',  '#^/admin/mot-de-passe$#', AdminControleur::class, 'motDePasse'],
    ['POST', '#^/admin/mot-de-passe$#', AdminControleur::class, 'motDePasse'],
    ['POST', '#^/admin/reservations/(\d+)/dossier$#', AdminControleur::class, 'ouvrirDossier'],
    ['POST', '#^/admin/horloge$#', AdminControleur::class, 'horloge'],
];

try {
    Securite::demarrerSession();
    Securite::verifierCsrf();

    $chemin = App::cheminDemande();
    $methode = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $methodesAutorisees = [];
    $dispatch = null;

    foreach (ROUTES as [$m, $motif, $classe, $action]) {
        if (preg_match($motif, $chemin, $p) !== 1) {
            continue;
        }
        $methodesAutorisees[$m] = true;
        if ($m === $methode) {
            $dispatch = [$classe, $action, array_slice($p, 1)];
            break;
        }
    }

    if ($dispatch === null) {
        if ($methodesAutorisees !== []) {
            header('Allow: ' . implode(', ', array_keys($methodesAutorisees)));
            throw new ErreurHttp(405);
        }
        throw new ErreurHttp(404);
    }

    [$classe, $action, $parametres] = $dispatch;
    (new $classe())->$action(...$parametres);
    throw new ErreurHttp(404); // un contrôleur doit toujours terminer par vue() ou rediriger()
} catch (ErreurHttp $e) {
    Erreurs::afficher($e->codeHttp);
} catch (AccesRefuse $e) {
    Erreurs::afficher(403);
} catch (ErreurMetier $e) {
    Erreurs::afficher(400, null, $e->getMessage());
} catch (Throwable $e) {
    $reference = Erreurs::journaliser($e);
    Erreurs::afficher(500, $reference);
}
