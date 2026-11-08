<?php
/** Gabarit commun : navigation applicative ou en-tête public. Variables : $titre, $contenu, $utilisateur. */
$db = App::db();
$chemin = App::cheminDemande();
$pleinEcran = (bool) ($pleinEcran ?? false);
$interfacePublique = !$utilisateur || $pleinEcran;
$solde = ['disponible' => 0, 'bloque' => 0];
$nbNotif = 0;
$estAdmin = false;
if ($utilisateur) {
    $nbNotif = (int) $db->valeur('SELECT COUNT(*) FROM notification WHERE destinataire_id = ? AND lue = 0', [$utilisateur['id']]);
    $estAdmin = $utilisateur['role'] === 'ADMIN';
    if (!$estAdmin) {
        $solde = (new ServiceComptes($db))->portefeuille((int) $utilisateur['id']) ?? $solde;
    }
}
$decalage = Horloge::decalageMinutes($db);

$estActif = static function (string $href) use ($chemin): bool {
    return match ($href) {
        '/admin' => $chemin === '/admin' || $chemin === '/admin/',
        '/tableau-de-bord' => $chemin === '/tableau-de-bord',
        '/demandes' => $chemin === '/demandes' || str_starts_with($chemin, '/reservations/')
            || str_starts_with($chemin, '/litiges/'),
        '/profil' => $chemin === '/profil' || str_starts_with($chemin, '/profil/'),
        '/mes-offres' => $chemin === '/mes-offres' || $chemin === '/offres/nouvelle'
            || preg_match('#^/offres/\d+/modifier$#', $chemin) === 1,
        '/notifications' => $chemin === '/notifications' || str_starts_with($chemin, '/notifications/'),
        default => $chemin === $href || str_starts_with($chemin, rtrim($href, '/') . '/'),
    };
};

$nav = $estAdmin
    ? [
        ['titre' => 'Découvrir', 'liens' => [
            ['/bienvenue', 'Accueil du site', 'accueil'],
        ]],
        ['titre' => 'Administration', 'liens' => [
            ['/admin', 'Tableau de bord', 'admin'],
            ['/admin/comptes', 'Comptes', 'comptes'],
            ['/admin/categories', 'Catégories', 'categories'],
            ['/admin/offres', 'Modération', 'moderation'],
            ['/admin/litiges', 'Litiges', 'litiges'],
        ]],
        ['titre' => 'Mon espace', 'liens' => [
            ['/admin/mot-de-passe', 'Mot de passe', 'cle'],
            ['/notifications', 'Notifications', 'cloche', $nbNotif],
            ['/catalogue', 'Voir le catalogue', 'explorer'],
        ]],
    ]
    : [
        ['titre' => 'Découvrir', 'liens' => [
            ['/bienvenue', 'Accueil du site', 'accueil'],
        ]],
        ['titre' => 'Apprendre', 'liens' => [
            ['/tableau-de-bord', 'Accueil', 'accueil'],
            ['/catalogue', 'Catalogue', 'explorer'],
            ['/suggestions', 'Pour moi', 'etincelle'],
        ]],
        ['titre' => 'Transmettre', 'liens' => [
            ['/mes-offres', 'Mes offres', 'offre'],
        ]],
        ['titre' => 'Mes séances', 'liens' => [
            ['/demandes', 'Demandes', 'demandes'],
            ['/planning', 'Planning', 'calendrier'],
            ['/portefeuille', 'Portefeuille', 'portefeuille'],
            ['/notifications', 'Notifications', 'cloche', $nbNotif],
        ]],
    ];

$navMobile = $estAdmin
    ? [
        ['/admin', 'Admin', 'admin'],
        ['/admin/comptes', 'Comptes', 'comptes'],
        ['/admin/offres', 'Modération', 'moderation'],
        ['/admin/litiges', 'Litiges', 'litiges'],
        ['/admin/categories', 'Catégories', 'categories'],
    ]
    : [
        ['/tableau-de-bord', 'Accueil', 'accueil'],
        ['/catalogue', 'Explorer', 'explorer'],
        ['/demandes', 'Demandes', 'demandes'],
        ['/planning', 'Planning', 'calendrier'],
        ['/profil', 'Profil', 'profil'],
    ];

$lienNav = static function (array $item) use ($estActif): string {
    [$href, $libelle, $icone] = $item;
    $badge = (int) ($item[3] ?? 0);
    $actif = $estActif($href);
    return '<a class="lien-nav' . ($actif ? ' actif' : '') . '" href="' . e(url($href)) . '"'
        . ($actif ? ' aria-current="page"' : '') . '>'
        . '<span class="lien-nav-icone" aria-hidden="true">' . icone($icone, 19) . '</span>'
        . '<span class="lien-nav-texte">' . e($libelle) . '</span>'
        . ($badge > 0 ? '<span class="pastille" aria-label="' . $badge . ' non lue(s)">' . $badge . '</span>' : '')
        . '</a>';
};
?><!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="SkillSwap Campus — la banque du temps des étudiants de l’EST Béni Mellal. Donnez une heure de votre savoir, recevez une heure pour apprendre.">
  <meta name="color-scheme" content="light dark">
  <meta name="theme-color" content="#f4f2ec" id="meta-theme">
  <meta name="application-name" content="SkillSwap Campus">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="default">
  <meta name="apple-mobile-web-app-title" content="SkillSwap">
  <title><?= e($titre) ?> — SkillSwap Campus</title>
  <script>
    (function () {
      try {
        var cle = 'skillswap-theme';
        var choix = localStorage.getItem(cle);
        if (choix !== 'light' && choix !== 'dark') {
          choix = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }
        document.documentElement.dataset.theme = choix;
        document.documentElement.style.colorScheme = choix;
      } catch (e) {}
    })();
  </script>
  <link rel="manifest" href="<?= e(url('/manifest.webmanifest')) ?>">
  <link rel="apple-touch-icon" href="<?= e(url('/icons/apple-touch-icon.png')) ?>">
  <link rel="icon" type="image/png" sizes="32x32" href="<?= e(url('/icons/favicon-32.png')) ?>">
  <link rel="stylesheet" href="<?= e(url('/css/skillswap-v4.css')) ?>">
  <noscript>
    <style>
      .theme-toggle { display: none !important; }
      @media (max-width: 900px) {
        .barre { position: static; inset: auto; width: auto; transform: none; overflow: visible; box-shadow: none; border-right: 0; }
        .barre-mobile, .voile, .nav-mobile, .fermer-menu { display: none !important; }
        .zone { margin-left: 0; }
        .contenu { padding: 22px 15px 40px; }
      }
    </style>
  </noscript>
  <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='8' fill='%234b3fd1'/%3E%3Cpath d='M9 6h14v2.5l-5.2 7.5 5.2 7.5V26H9v-2.5l5.2-7.5L9 8.5z' fill='none' stroke='%23fff' stroke-width='1.8' stroke-linejoin='round'/%3E%3Cpath d='M12 23.6h8l-4-5z' fill='%23f2b134'/%3E%3C/svg%3E">
</head>
<body class="<?= $interfacePublique ? 'page-publique' : 'page-connectee' ?>" data-base="<?= e(url('/')) ?>">
<a class="saut-contenu" href="#contenu">Aller au contenu principal</a>
<?php if (!$interfacePublique): ?>
<div class="cadre">
  <aside class="barre" id="navigation-principale" aria-label="Navigation de l’application">
    <div class="barre-haut">
      <a class="marque" href="<?= e(url('/')) ?>">
        <?php require __DIR__ . '/partiels/logo.php'; ?>
        <span class="marque-texte"><strong>SkillSwap</strong><small>Campus · banque du temps</small></span>
      </a>
      <button class="bouton-icone fermer-menu" type="button" data-menu-fermer aria-label="Fermer le menu">
        <?= icone('fermer', 22) ?>
      </button>
    </div>

    <?php if (!$estAdmin): ?>
      <a class="solde-barre" href="<?= e(url('/portefeuille')) ?>" aria-label="Portefeuille : <?= (int) $solde['disponible'] ?> heures disponibles">
        <span class="solde-barre-titre"><?= icone('sablier', 17) ?> Temps disponible</span>
        <span class="solde-barre-chiffre"><?= (int) $solde['disponible'] ?><small> h</small></span>
        <span class="solde-barre-detail"><?= $solde['bloque'] ? (int) $solde['bloque'] . ' h réservées' : 'Prêt à être échangé' ?></span>
        <span class="solde-barre-lien">Voir le portefeuille <?= icone('fleche', 15) ?></span>
      </a>
    <?php else: ?>
      <div class="role-barre">
        <span class="role-barre-icone" aria-hidden="true"><?= icone('admin', 22) ?></span>
        <span><strong>Administration</strong><small>Banque du temps du campus</small></span>
      </div>
    <?php endif; ?>

    <nav class="nav-principale" aria-label="Navigation principale">
      <?php foreach ($nav as $section): ?>
        <p class="groupe-nav"><?= e($section['titre']) ?></p>
        <?php foreach ($section['liens'] as $item) echo $lienNav($item); ?>
      <?php endforeach; ?>
    </nav>

    <div class="pied">
      <div class="moi">
        <span class="avatar" aria-hidden="true"><?= e(initiales($utilisateur['prenom'] . ' ' . $utilisateur['nom'])) ?></span>
        <span class="moi-nom"><?= e($utilisateur['prenom'] . ' ' . $utilisateur['nom']) ?>
          <small><?= e($estAdmin ? 'Administration' : ($utilisateur['filiere'] ?: 'Étudiant')) ?></small></span>
      </div>
      <div class="actions-pied">
        <?php if (!$estAdmin): ?>
          <a class="lien-pied" href="<?= e(url('/profil')) ?>"><span aria-hidden="true"><?= icone('reglages', 19) ?></span> Mon profil</a>
        <?php endif; ?>
        <?= bouton_theme(true, 'lien-pied') ?>
        <form method="post" action="<?= e(url('/deconnexion')) ?>">
          <?= csrf_champ() ?>
          <button class="lien-pied" type="submit"><span aria-hidden="true"><?= icone('deconnexion', 19) ?></span> Se déconnecter</button>
        </form>
      </div>
    </div>
  </aside>
  <button class="voile" type="button" data-menu-fermer tabindex="-1" aria-label="Fermer le menu"></button>

  <div class="zone">
    <header class="barre-mobile">
      <button class="bouton-icone" type="button" data-menu-ouvrir aria-expanded="false" aria-controls="navigation-principale">
        <?= icone('menu', 22) ?><span class="sr-only">Ouvrir le menu</span>
      </button>
      <a class="marque-mobile" href="<?= e(url('/')) ?>">
        <?php require __DIR__ . '/partiels/logo.php'; ?>
        <span><strong>SkillSwap</strong><small>Campus</small></span>
      </a>
      <div class="actions-mobile">
        <?php if (!$estAdmin): ?>
          <a class="solde-mini" href="<?= e(url('/portefeuille')) ?>" aria-label="Portefeuille : <?= (int) $solde['disponible'] ?> heures disponibles">
            <?= (int) $solde['disponible'] ?><span> h</span>
          </a>
        <?php endif; ?>
        <a class="bouton-icone" href="<?= e(url('/bienvenue')) ?>" aria-label="Accueil du site" title="Accueil du site">
          <?= icone('accueil', 21) ?>
        </a>
        <a class="bouton-icone badge-bouton" href="<?= e(url('/notifications')) ?>" aria-label="Notifications<?= $nbNotif ? ' : ' . $nbNotif . ' non lue(s)' : '' ?>">
          <?= icone('cloche', 21) ?><?php if ($nbNotif): ?><span class="pastille pastille-absolue"><?= $nbNotif ?></span><?php endif; ?>
        </a>
        <?= bouton_theme() ?>
      </div>
    </header>

    <main class="contenu" id="contenu" tabindex="-1">
      <div class="page">
        <?php if ($decalage): ?>
          <div class="horloge-demo" role="status">
            <span aria-hidden="true"><?= icone('horloge', 18) ?></span>
            <span>Horloge de démonstration : nous sommes le <b><?= e(date_courte(Horloge::maintenantTxt($db))) ?></b>
              (<?= $decalage > 0 ? '+' : '' ?><?= round($decalage / 60, 1) ?> h par rapport à l’heure réelle).</span>
          </div>
        <?php endif; ?>
        <?= messages_flash() ?>
        <?= $contenu ?>
      </div>
    </main>

    <nav class="nav-mobile" aria-label="Navigation rapide">
      <?php foreach ($navMobile as $item): [$href, $libelle, $nomIcone] = $item; $actif = $estActif($href); ?>
        <a class="nav-mobile-lien<?= $actif ? ' actif' : '' ?>" href="<?= e(url($href)) ?>"<?= $actif ? ' aria-current="page"' : '' ?>>
          <span class="nav-mobile-icone" aria-hidden="true"><?= icone($nomIcone, 22) ?></span>
          <span><?= e($libelle) ?></span>
        </a>
      <?php endforeach; ?>
    </nav>
  </div>
</div>
<?php else: ?>
<?php if (!$pleinEcran): ?>
<header class="barre-publique">
  <a class="marque-publique" href="<?= e(url('/')) ?>">
    <?php require __DIR__ . '/partiels/logo.php'; ?>
    <span><strong>SkillSwap</strong><small>Campus · banque du temps</small></span>
  </a>
  <div class="actions-publiques">
    <?= bouton_theme() ?>
    <?php if ($chemin === '/connexion'): ?>
      <a class="bouton second petit" href="<?= e(url('/inscription')) ?>">Créer un compte</a>
    <?php elseif ($chemin === '/inscription'): ?>
      <a class="bouton second petit" href="<?= e(url('/connexion')) ?>">Se connecter</a>
    <?php else: ?>
      <a class="bouton second petit" href="<?= e(url('/connexion')) ?>">Se connecter</a>
      <a class="bouton petit" href="<?= e(url('/inscription')) ?>">Créer un compte</a>
    <?php endif; ?>
  </div>
</header>
<?php endif; ?>
<main class="contenu-public<?= $pleinEcran ? ' contenu-public--bienvenue' : '' ?>" id="contenu" tabindex="-1">
  <div class="page">
    <?= messages_flash() ?>
    <?= $contenu ?>
  </div>
</main>
<?php endif; ?>
<script src="<?= e(url('/js/skillswap.js')) ?>"></script>
</body>
</html>
