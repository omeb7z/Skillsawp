<?php
declare(strict_types=1);

/* ==================================================================
   Fonctions d'aide pour les contrôleurs et les vues.
   Toute donnée affichée passe par e() : protection contre le XSS.
   ================================================================== */

const JOURS = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];
const MOIS = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août',
    'septembre', 'octobre', 'novembre', 'décembre'];

/** Échappement HTML. Les tableaux et objets ne sont jamais affichés. */
function e(mixed $v): string
{
    if ($v === null) {
        return '';
    }
    if (!is_scalar($v)) {
        return '';
    }
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** URL interne (tient compte d'un éventuel sous-dossier). */
function url(string $chemin): string
{
    $chemin = '/' . ltrim($chemin, '/');
    if ($chemin === '/') {
        return App::base() . '/';
    }
    return App::base() . $chemin;
}

/**
 * Ne conserve qu'un chemin interne sûr. Bloque les URL absolues, les
 * protocoles, les chemins réseau // et les retours Windows.
 */
function chemin_interne(?string $chemin, string $defaut = '/'): string
{
    $brut = trim((string) $chemin);
    if ($brut === '') {
        return $defaut;
    }
    $parties = parse_url($brut);
    if ($parties === false || !is_array($parties)) {
        return $defaut;
    }
    if (isset($parties['scheme']) || isset($parties['host'])) {
        return $defaut;
    }
    $path = isset($parties['path']) && is_string($parties['path']) ? rawurldecode($parties['path']) : '';
    if ($path === '' || $path[0] !== '/' || str_starts_with($path, '//') || str_contains($path, '\\')
        || preg_match('/[\x00-\x1F\x7F]/', $path) === 1) {
        return $defaut;
    }
    $query = isset($parties['query']) && is_string($parties['query']) ? '?' . $parties['query'] : '';
    return $path . $query;
}

function rediriger(string $chemin): never
{
    header('Location: ' . url(chemin_interne($chemin)), true, 302);
    exit;
}

function flash(string $categorie, string $message): void
{
    $_SESSION['flash'][] = [$categorie, $message];
}

function messages_flash(): string
{
    $liste = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    if (!is_array($liste) || !$liste) {
        return '';
    }
    $h = '<div class="messages">';
    foreach ($liste as $item) {
        if (!is_array($item) || count($item) !== 2) {
            continue;
        }
        [$cat, $msg] = $item;
        $classe = in_array($cat, ['succes', 'erreur', 'info'], true) ? $cat : 'info';
        $role = $classe === 'erreur' ? 'alert' : 'status';
        $icone = $classe === 'erreur' ? 'alerte' : ($classe === 'succes' ? 'check' : 'info');
        $h .= '<div class="message ' . $classe . '" role="' . $role . '">'
            . '<span class="message-icone" aria-hidden="true">' . icone($icone, 20) . '</span>'
            . '<span>' . e($msg) . '</span></div>';
    }
    return $h . '</div>';
}

function csrf_champ(): string
{
    return '<input type="hidden" name="csrf" value="' . e(Securite::jetonCsrf()) . '">';
}

/** Rend une vue dans le gabarit commun. */
function vue(string $nom, array $donnees = [], int $code = 200): never
{
    if ($code !== 200) {
        http_response_code($code);
    }
    $donnees['utilisateur'] ??= Securite::utilisateur();
    $fichier = __DIR__ . "/../vues/$nom.php";
    if (!is_file($fichier)) {
        throw new RuntimeException('Vue introuvable : ' . $nom);
    }
    extract($donnees, EXTR_SKIP);
    ob_start();
    try {
        require $fichier;
        $contenu = (string) ob_get_clean();
    } catch (Throwable $e) {
        ob_end_clean();
        throw $e;
    }
    $titre = $donnees['titre'] ?? 'SkillSwap Campus';
    require __DIR__ . '/../vues/gabarit.php';
    exit;
}

/* ---------- Dates en français ---------- */
function date_obj(?string $txt): ?DateTimeImmutable
{
    $txt = trim((string) $txt);
    if ($txt === '') {
        return null;
    }
    if (strlen($txt) > 10) {
        return Horloge::lire(substr($txt, 0, 16));
    }
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $txt);
    return ($d instanceof DateTimeImmutable && $d->format('Y-m-d') === $txt) ? $d : null;
}

function date_longue(?string $txt): string
{
    $d = date_obj($txt);
    return $d ? JOURS[(int) $d->format('N') - 1] . ' ' . (int) $d->format('j') . ' ' . MOIS[(int) $d->format('n') - 1] : '';
}

function date_courte(?string $txt): string
{
    $d = date_obj($txt);
    return $d ? $d->format('d/m/Y H:i') : '';
}

function heure(?string $txt): string
{
    $txt = (string) $txt;
    return strlen($txt) >= 16 ? substr($txt, 11, 5) : '';
}

function initiales(?string $nom): string
{
    $parts = preg_split('/\s+/', trim((string) $nom)) ?: ['?'];
    return mb_strtoupper(implode('', array_map(fn($p) => mb_substr($p, 0, 1), array_slice($parts, 0, 2))));
}

/* ---------- Icônes locales (aucune police ou bibliothèque externe) ---------- */
function icone(string $nom, int $taille = 20): string
{
    static $dessins = [
        'accueil' => '<path d="M3 10.8 12 3l9 7.8"/><path d="M5.5 9.5V21h13V9.5"/><path d="M9.5 21v-6h5v6"/>',
        'explorer' => '<circle cx="12" cy="12" r="9"/><path d="m15.5 8.5-2 5-5 2 2-5z"/>',
        'etincelle' => '<path d="M12 3l1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8z"/><path d="M19 16l.7 2.3L22 19l-2.3.7L19 22l-.7-2.3L16 19l2.3-.7z"/>',
        'offre' => '<path d="M4 8h16v12H4z"/><path d="M8 8V5h8v3"/><path d="M4 13h16"/><path d="M10 13v2h4v-2"/>',
        'demandes' => '<path d="M4 5h16v14H4z"/><path d="M4 13h4l1.5 2h5L16 13h4"/>',
        'calendrier' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/>',
        'portefeuille' => '<path d="M4 6.5A2.5 2.5 0 0 1 6.5 4H19v16H6.5A2.5 2.5 0 0 1 4 17.5z"/><path d="M4 7h15"/><path d="M15 12h5v4h-5a2 2 0 0 1 0-4z"/>',
        'cloche' => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/>',
        'profil' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'menu' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'fermer' => '<path d="m6 6 12 12M18 6 6 18"/>',
        'soleil' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
        'lune' => '<path d="M20 14.5A8.5 8.5 0 0 1 9.5 4 8.5 8.5 0 1 0 20 14.5z"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'recherche' => '<circle cx="11" cy="11" r="7"/><path d="m16.5 16.5 4 4"/>',
        'admin' => '<path d="M12 3 4.5 6v5c0 4.6 3.1 8.4 7.5 10 4.4-1.6 7.5-5.4 7.5-10V6z"/><path d="m9 12 2 2 4-4"/>',
        'comptes' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><circle cx="17.5" cy="9" r="2.5"/><path d="M15.5 14.5A5 5 0 0 1 22 19"/>',
        'categories' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
        'moderation' => '<path d="M3 3l18 18"/><path d="M10.6 5.1A10.9 10.9 0 0 1 12 5c5.5 0 9 7 9 7a16.8 16.8 0 0 1-2.2 3.1M6.2 6.2C3.8 8 2 12 2 12s3.5 7 10 7a10 10 0 0 0 4.1-.9"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/>',
        'litiges' => '<path d="M5 21V4h11l-2 4 2 4H5"/><path d="M5 21v-5"/>',
        'cle' => '<circle cx="8" cy="15" r="4"/><path d="m11 12 8-8M16 4l4 4M14 6l2 2"/>',
        'deconnexion' => '<path d="M10 4H5v16h5"/><path d="M14 8l4 4-4 4M8 12h10"/>',
        'fleche' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'telecharger' => '<path d="M12 3v12"/><path d="m7 11 5 5 5-5"/><path d="M5 21h14"/>',
        'check' => '<path d="m5 12 4 4L19 6"/>',
        'alerte' => '<path d="M12 3 2.5 20h19z"/><path d="M12 9v5M12 17.5v.1"/>',
        'info' => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8v.1"/>',
        'horloge' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/>',
        'etoile' => '<path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2-5.6-2.9-5.6 2.9 1.1-6.2L3 9.6l6.2-.9z" fill="currentColor" stroke="none"/>',
        'sablier' => '<path d="M6 3h12M6 21h12M7 3c0 5 5 6 5 9s-5 4-5 9M17 3c0 5-5 6-5 9s5 4 5 9"/>',
        'reglages' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-2.8 2.8-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6v.2h-4v-.2a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1L4.2 17l.1-.1a1.7 1.7 0 0 0 .3-1.9 1.7 1.7 0 0 0-1.6-1H2.8v-4H3a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9L4.2 7 7 4.2l.1.1A1.7 1.7 0 0 0 9 4.6a1.7 1.7 0 0 0 1-1.6v-.2h4V3a1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.9-.3l.1-.1L19.8 7l-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.6 1h.2v4H21a1.7 1.7 0 0 0-1.6 1z"/>',
    ];
    $dessin = $dessins[$nom] ?? $dessins['info'];
    return '<svg class="icone" width="' . max(12, $taille) . '" height="' . max(12, $taille)
        . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"'
        . ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $dessin . '</svg>';
}

/** Bouton de thème partagé par la barre latérale et les en-têtes. */
function bouton_theme(bool $avecTexte = false, string $classe = 'bouton-icone'): string
{
    $texte = '<span class="theme-libelle' . ($avecTexte ? '' : ' sr-only') . '">Thème sombre</span>';
    return '<button type="button" class="' . e($classe) . ' theme-toggle" data-theme-toggle'
        . ' aria-pressed="false" aria-label="Passer au thème sombre">'
        . '<span class="theme-icone theme-icone-lune" aria-hidden="true">' . icone('lune', 20) . '</span>'
        . '<span class="theme-icone theme-icone-soleil" aria-hidden="true">' . icone('soleil', 20) . '</span>'
        . $texte . '</button>';
}

/* ---------- Erreurs de formulaire affichées au bon endroit ---------- */
function erreur_de(array $erreurs, string $champ): ?string
{
    $erreur = $erreurs[$champ] ?? null;
    return is_string($erreur) && $erreur !== '' ? $erreur : null;
}

function erreur_html(array $erreurs, string $champ, string $id): string
{
    $erreur = erreur_de($erreurs, $champ);
    if ($erreur === null) {
        return '';
    }
    return '<p class="erreur-champ" id="' . e($id) . '" role="alert">'
        . '<span class="erreur-champ-icone" aria-hidden="true">' . icone('alerte', 16) . '</span>'
        . '<span>' . e($erreur) . '</span></p>';
}

/** Attributs aria-describedby / aria-invalid d'un champ en erreur. */
function attrs_erreur(array $erreurs, string $champ, string $idErreur, string $idAide = ''): string
{
    $aide = trim($idAide);
    if (erreur_de($erreurs, $champ) === null) {
        return $aide !== '' ? ' aria-describedby="' . e($aide) . '"' : '';
    }
    $descriptions = trim($aide . ' ' . $idErreur);
    return ' aria-invalid="true" aria-describedby="' . e($descriptions) . '"';
}

function resume_erreurs(array $erreurs, string $intro = 'Certains champs doivent être corrigés.'): string
{
    $messageFormulaire = erreur_de($erreurs, '_form');
    if ($messageFormulaire === null && $erreurs === []) {
        return '';
    }
    $texte = $messageFormulaire ?? $intro;
    return '<div class="message erreur formulaire-message" role="alert">'
        . '<span class="message-icone" aria-hidden="true">' . icone('alerte', 20) . '</span>'
        . '<span>' . e($texte) . '</span></div>';
}

/* ---------- Petits composants d'interface ---------- */
function jeton(int|string|null $n, bool $gros = false): string
{
    return '<span class="jeton' . ($gros ? ' gros' : '') . '">' . e($n) . ' h</span>';
}

function etat_resa(string $etat): string
{
    [$lib, $cls] = Libelles::ETATS_RESERVATION[$etat] ?? ['Inconnu', 'neutre'];
    return '<span class="etat ' . e($cls) . '">' . e($lib) . '</span>';
}

function etat_offre(array $o): string
{
    if ((int) ($o['masquee'] ?? 0)) {
        return '<span class="etat litige">Masquée</span>';
    }
    [$lib, $cls] = Libelles::ETATS_OFFRE[$o['etat'] ?? ''] ?? ['Inconnu', 'neutre'];
    return '<span class="etat ' . e($cls) . '">' . e($lib) . '</span>';
}

function etoiles(int|float|string|null $note): string
{
    if ($note === null || $note === '') {
        return '<span class="discret">Pas encore noté</span>';
    }
    $n = max(0, min(5, (int) round((float) $note)));
    return '<span class="etoiles" aria-label="' . e($note) . ' sur 5">' . str_repeat('★', $n)
        . '<span class="vides">' . str_repeat('★', 5 - $n) . '</span></span> <b>' . e($note) . '</b>';
}

function carte_offre(array $o, ?string $raison = null, bool $exacte = false): string
{
    $h = '<article class="offre" style="--cat: ' . e($o['couleur'] ?? '#4b3fd1') . '">';
    $h .= '<div class="categorie">' . e($o['categorie'] ?? '') . ' · ' . e($o['competence'] ?? '') . '</div>';
    $h .= '<h3><a href="' . e(url('/offres/' . (int) ($o['id'] ?? 0))) . '">' . e($o['titre'] ?? '') . '</a></h3>';
    if ($raison) {
        $h .= '<span class="raison' . ($exacte ? ' exacte' : '') . '">' . e($raison) . '</span>';
    }
    $h .= '<div class="etiquettes"><span class="etiquette">' . e(Libelles::NIVEAUX[$o['niveau'] ?? ''] ?? '') . '</span>'
        . '<span class="etiquette">' . e(Libelles::MODES[$o['mode'] ?? ''] ?? '') . '</span></div>';
    $h .= '<div class="discret">par ' . e($o['intervenant'] ?? '')
        . (!empty($o['note']) ? ' <span class="etoiles">★</span> ' . e($o['note']) : '') . '</div>';
    $h .= '<div class="bas">' . jeton($o['duree'] ?? null) . '<span class="discret">'
        . (!empty($o['prochain']) ? 'Prochain : ' . e(date_longue($o['prochain'])) . ' à ' . e(heure($o['prochain'])) : 'Aucun créneau libre')
        . '</span></div></article>';
    return $h;
}

/** Valeur d'un tableau échappée, chaîne vide si absente ou non scalaire (formulaires). */
function v(array $t, string $cle): string
{
    $valeur = $t[$cle] ?? '';
    return is_scalar($valeur) || $valeur === null ? e($valeur) : '';
}
