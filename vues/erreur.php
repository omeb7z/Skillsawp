<?php
/** Page d'erreur autonome : aucune dépendance à la base, à la session ou à App. */
$h = static fn(mixed $v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$code = (int) ($code ?? 500);
$titre = (string) ($titre ?? 'Erreur');
$message = (string) ($message ?? 'Une erreur est survenue.');
$reference = isset($reference) && $reference !== null && $reference !== '' ? (string) $reference : null;
$base = (string) ($base ?? '');
$css = (string) ($css ?? ($base . '/css/skillswap-v5.css'));
$accueil = (string) ($accueil ?? ($base . '/' ?: '/'));
?><!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="color-scheme" content="light dark">
  <meta name="theme-color" content="#f4f2ec">
  <title><?= $h($code) ?> — <?= $h($titre) ?> — SkillSwap Campus</title>
  <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='8' fill='%234b3fd1'/%3E%3Cpath d='M9 6h14v2.5l-5.2 7.5 5.2 7.5V26H9v-2.5l5.2-7.5L9 8.5z' fill='none' stroke='%23fff' stroke-width='1.8' stroke-linejoin='round'/%3E%3Cpath d='M12 23.6h8l-4-5z' fill='%23f2b134'/%3E%3C/svg%3E">
  <script>
    (function () {
      try {
        var choix = localStorage.getItem('skillswap-theme');
        if (choix !== 'light' && choix !== 'dark') {
          choix = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }
        document.documentElement.dataset.theme = choix;
        document.documentElement.style.colorScheme = choix;
      } catch (e) {}
    })();
  </script>
  <link rel="stylesheet" href="<?= $h($css) ?>">
</head>
<body class="page-erreur">
  <main class="panneau erreur-page" aria-labelledby="titre-erreur">
    <div class="erreur-marque">
      <svg width="34" height="34" viewBox="0 0 32 32" aria-hidden="true">
        <rect width="32" height="32" rx="8" fill="#4b3fd1"/>
        <path d="M9 6h14v2.5l-5.2 7.5 5.2 7.5V26H9v-2.5l5.2-7.5L9 8.5z" fill="none" stroke="#fff" stroke-width="1.8" stroke-linejoin="round"/>
        <path d="M12 23.6h8l-4-5z" fill="#f2b134"/>
      </svg>
      <span>SkillSwap Campus</span>
    </div>
    <p class="erreur-code"><?= $h($code) ?></p>
    <h1 id="titre-erreur"><?= $h($titre) ?></h1>
    <p><?= $h($message) ?></p>
    <?php if ($reference !== null): ?>
      <p class="erreur-ref">Référence à transmettre : <?= $h($reference) ?></p>
    <?php endif; ?>
    <p style="margin-top:22px"><a class="bouton" href="<?= $h($accueil) ?>">Revenir à l’accueil</a></p>
  </main>
</body>
</html>
