<?php
/** Page d’accueil publique : héros 3D et présentation de la banque du temps. */
$stats = is_array($stats ?? null) ? $stats : [];
$vitrine = is_array($vitrine ?? null) ? $vitrine : [];
?>
<section class="accueil-bienvenue" id="accueil-bienvenue" aria-labelledby="bienvenue-titre" aria-describedby="bienvenue-description">
  <div class="bienvenue-contenu">
    <section class="bienvenue-texte">
      <div class="bienvenue-marque">
        <span class="bienvenue-logo"><?php require __DIR__ . '/../partiels/logo.php'; ?></span>
        <span class="bienvenue-marque-texte">
          <strong>SkillSwap</strong>
          <small>Campus · EST Béni Mellal</small>
        </span>
        <span class="bienvenue-theme"><?= bouton_theme() ?></span>
      </div>
      <p class="bienvenue-surtitre"><?= icone('etincelle', 15) ?> La banque du temps du campus</p>
      <h1 id="bienvenue-titre">Bienvenue sur votre <span>SkillSwap Campus</span></h1>
      <p class="bienvenue-description" id="bienvenue-description">Donnez une heure de ce que vous savez, recevez une heure de ce que vous voulez apprendre. Ici, le temps est la seule monnaie : aucune transaction, seulement des savoirs partagés entre étudiants de l’EST Béni Mellal.</p>
      <div class="bienvenue-actions">
        <a class="bouton bienvenue-entrer" href="<?= e(url('/inscription')) ?>">
          Créer un compte <?= icone('fleche', 18) ?>
        </a>
        <a class="bouton bienvenue-connexion" href="<?= e(url('/connexion')) ?>">
          Se connecter
        </a>
        <a class="bienvenue-passer" href="<?= e(url('/connexion')) ?>"
           title="Aller à la page de connexion">Passer à la connexion</a>
      </div>
      <ul class="bienvenue-preuves" aria-label="Les principes de SkillSwap Campus">
        <li><?= icone('sablier', 17) ?><span><b>1 h = 1 h.</b> Le temps donné vaut le temps reçu.</span></li>
        <li><?= icone('check', 17) ?><span><b>Zéro paiement.</b> Un compte, deux rôles.</span></li>
      </ul>
      <a class="bienvenue-apk" href="<?= e(url('/apk/SkillSwap-Campus.apk')) ?>" download
         aria-label="Télécharger l’application Android SkillSwap Campus au format APK">
        <span class="bienvenue-apk-icone" aria-hidden="true"><?= icone('telecharger', 18) ?></span>
        <span class="bienvenue-apk-texte">
          <b>Télécharger l’application</b>
          <small>Android · APK</small>
        </span>
        <?= icone('fleche', 16) ?>
      </a>
    </section>

    <div class="bienvenue-visuel" aria-hidden="true">
      <div class="bienvenue-scene">
        <span class="bienvenue-halo"></span>
        <span class="bienvenue-socle"></span>
        <span class="bienvenue-anneau bienvenue-anneau-1"></span>
        <span class="bienvenue-anneau bienvenue-anneau-2"></span>
        <span class="bienvenue-anneau bienvenue-anneau-3"></span>
        <span class="bienvenue-bulle bienvenue-bulle-1"></span>
        <span class="bienvenue-bulle bienvenue-bulle-2"></span>
        <span class="bienvenue-bulle bienvenue-bulle-3"></span>

        <div class="bienvenue-centre">
          <?php require __DIR__ . '/../partiels/logo.php'; ?>
          <span class="bienvenue-centre-label">1 h <b>=</b> 1 h</span>
        </div>

        <span class="bienvenue-jeton bienvenue-jeton-1"><?= icone('sablier', 14) ?><b>1 h</b></span>
        <span class="bienvenue-jeton bienvenue-jeton-2"><?= icone('check', 13) ?><b>+2 h</b></span>

        <div class="bienvenue-orbite bienvenue-orbite-1">
          <span class="bienvenue-orbite-pastille pastille-python">Py</span>
          <span class="bienvenue-orbite-texte"><strong>Python</strong><small>1 h de savoir</small></span>
        </div>
        <div class="bienvenue-orbite bienvenue-orbite-2">
          <span class="bienvenue-orbite-pastille pastille-anglais">Aa</span>
          <span class="bienvenue-orbite-texte"><strong>Anglais</strong><small>1 h de savoir</small></span>
        </div>
        <div class="bienvenue-orbite bienvenue-orbite-3">
          <span class="bienvenue-orbite-pastille pastille-dessin">D</span>
          <span class="bienvenue-orbite-texte"><strong>Dessin</strong><small>1 h de savoir</small></span>
        </div>

        <span class="bienvenue-etoile bienvenue-etoile-1">✦</span>
        <span class="bienvenue-etoile bienvenue-etoile-2">✧</span>
        <span class="bienvenue-etoile bienvenue-etoile-3">✦</span>
      </div>
    </div>
  </div>
</section>

<section class="accueil-suite" aria-labelledby="titre-echange">
  <div class="accueil-suite-inner">
    <div class="accueil-suite-entete">
      <div class="accueil-suite-texte">
        <p class="accueil-suite-surtitre"><?= icone('etincelle', 15) ?> Le campus en chiffres</p>
        <h2 id="titre-echange">Un échange, simplement</h2>
        <p class="accueil-suite-chapeau">Donnez une heure de ce que vous savez, recevez une heure de ce que vous voulez apprendre. Le temps est la seule monnaie, et chaque échange reste entre étudiants.</p>
      </div>
      <div class="accueil-statistiques" role="group" aria-label="SkillSwap Campus en chiffres">
        <div><b><?= (int) ($stats['comptes_actifs'] ?? 0) ?></b><span>étudiants inscrits</span></div>
        <div><b><?= (int) ($stats['offres_publiees'] ?? 0) ?></b><span>offres publiées</span></div>
        <div><b><?= (int) ($stats['credits_transferes'] ?? 0) ?> h</b><span>heures échangées</span></div>
      </div>
    </div>

    <div class="accueil-suite-grille">
      <div class="accueil-deroulement">
        <h3>Proposez, échangez, progressez</h3>
        <ol class="accueil-etapes">
          <li><b>Proposez</b> une compétence et un créneau.</li>
          <li><b>Échangez</b> une heure contre une heure.</li>
          <li><b>Progressez</b> sans jamais sortir votre portefeuille.</li>
        </ol>
        <p class="accueil-credit"><?= icone('etincelle', 17) ?><span>Chaque nouveau compte reçoit <?= jeton(2) ?> de bienvenue.</span></p>
      </div>

      <aside class="accueil-carte" aria-labelledby="titre-exemple">
        <h3 id="titre-exemple">Un échange en pratique</h3>
        <p class="discret">Trois temps, aucune transaction : le temps donné devient le temps appris.</p>
        <div class="accueil-echange" aria-label="Exemple d’échange entre étudiants">
          <div class="accueil-echange-ligne">
            <span class="avatar" aria-hidden="true">SA</span>
            <span><b>Sara anime</b><small>1 h de Python</small></span>
            <?= jeton(1) ?>
          </div>
          <div class="accueil-echange-ligne">
            <span class="avatar" aria-hidden="true">AM</span>
            <span><b>Amine réserve</b><small>Et bloque 1 h</small></span>
            <?= jeton(1) ?>
          </div>
          <div class="accueil-echange-ligne">
            <span class="avatar" aria-hidden="true">IN</span>
            <span><b>Sara apprend</b><small>1 h d’anglais avec Inès</small></span>
            <?= jeton(1) ?>
          </div>
        </div>
        <a class="bouton large" href="<?= e(url('/inscription')) ?>">Rejoindre la banque du temps <?= icone('fleche', 18) ?></a>

        <?php if ($vitrine): ?>
          <div class="accueil-vitrine">
            <h3>En ce moment sur le campus</h3>
            <ul class="accueil-vitrine-liste">
              <?php foreach ($vitrine as $o): ?>
                <li><a href="<?= e(url('/connexion?suivant=/offres/' . (int) $o['id'])) ?>">
                  <span><?= e($o['titre']) ?></span><small>par <?= e($o['intervenant']) ?></small>
                </a></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>
      </aside>
    </div>
  </div>
</section>
