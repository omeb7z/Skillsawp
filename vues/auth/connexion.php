<?php $erreurs = $erreurs ?? []; ?>
<div class="auth-page">
  <section class="auth-recit" aria-labelledby="recit-connexion">
    <div>
      <span class="badge-doux"><?= icone('horloge', 15) ?> Banque du temps du campus</span>
      <h1 id="recit-connexion">Reprenez là où votre temps vous attend.</h1>
      <p>Vos séances, vos demandes et votre portefeuille d’heures sont conservés. Une seule connexion suffit pour animer comme pour apprendre.</p>
    </div>
    <ul class="auth-liste">
      <li><?= icone('check', 17) ?><span><b>Vos heures restent acquises.</b> Elles ne disparaissent jamais entre deux visites.</span></li>
      <li><?= icone('check', 17) ?><span><b>Un même compte, deux rôles.</b> Vous animez une séance, vous en suivez une autre.</span></li>
      <li><?= icone('check', 17) ?><span><b>Zéro paiement.</b> Le temps donné devient le temps reçu.</span></li>
    </ul>
    <span class="etiquette">EST Béni Mellal · SkillSwap Campus</span>
  </section>

  <section class="auth-carte" aria-labelledby="titre-connexion">
    <h1 id="titre-connexion">Connexion</h1>
    <p class="sous-titre">Heureux de vous revoir sur le campus.</p>

    <?php if (isset($_GET['deconnecte'])): ?>
      <div class="messages"><div class="message info" role="status">
        <span class="message-icone" aria-hidden="true"><?= icone('info', 20) ?></span>
        <span>Vous êtes déconnecté. À bientôt !</span>
      </div></div>
    <?php endif; ?>
    <?= resume_erreurs($erreurs) ?>

    <form method="post">
      <?= csrf_champ() ?>
      <div class="champ">
        <label for="email">Adresse électronique</label>
        <input id="email" type="email" name="email" value="<?= e($email) ?>" autocomplete="username"
               maxlength="190" required autofocus<?= attrs_erreur($erreurs, 'email', 'email-erreur') ?>>
        <?= erreur_html($erreurs, 'email', 'email-erreur') ?>
      </div>
      <div class="champ">
        <label for="mdp">Mot de passe</label>
        <input id="mdp" type="password" name="mot_de_passe" autocomplete="current-password"
               required<?= attrs_erreur($erreurs, 'mot_de_passe', 'mdp-erreur') ?>>
        <?= erreur_html($erreurs, 'mot_de_passe', 'mdp-erreur') ?>
      </div>
      <button class="bouton large" type="submit">Se connecter <?= icone('fleche', 18) ?></button>
    </form>
    <p class="auth-alt">Pas encore de compte ? <a href="<?= e(url('/inscription')) ?>">Inscrivez-vous</a> et recevez 2 heures de bienvenue.</p>
  </section>
</div>
