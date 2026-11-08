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
    <header class="accueil-intro">
      <div class="accueil-intro-texte">
        <p class="accueil-suite-surtitre"><?= icone('etincelle', 15) ?> La banque du temps · EST Béni Mellal</p>
        <h2 id="titre-echange">Un échange, simplement</h2>
        <p class="accueil-suite-chapeau">Une heure de votre savoir peut devenir une heure d’anglais, de code ou de dessin. <strong>Vous donnez ce que vous savez, vous recevez ce que vous voulez apprendre.</strong> Pas de paiement, pas de troc compliqué&nbsp;: juste du temps qui circule entre étudiants.</p>
        <p class="accueil-intro-note">Déjà un compte&nbsp;? <a href="<?= e(url('/connexion')) ?>">Retrouver mon espace</a></p>
      </div>
      <div class="accueil-principes" aria-label="Les trois principes de l’échange">
        <div class="accueil-principe">
          <span class="accueil-principe-icone" aria-hidden="true"><?= icone('sablier', 19) ?></span>
          <div><b>1 h = 1 h</b><p>La seule unité de compte, c’est votre temps.</p></div>
        </div>
        <div class="accueil-principe">
          <span class="accueil-principe-icone" aria-hidden="true"><?= icone('check', 19) ?></span>
          <div><b>Zéro paiement</b><p>Pas de tarif, pas d’abonnement, pas de troc compliqué.</p></div>
        </div>
        <div class="accueil-principe">
          <span class="accueil-principe-icone" aria-hidden="true"><?= icone('comptes', 19) ?></span>
          <div><b>Entre étudiants</b><p>Un réseau local et humain, réservé au campus.</p></div>
        </div>
      </div>
    </header>

    <section class="accueil-methode" aria-labelledby="titre-methode">
      <div class="accueil-section-entete">
        <div>
          <p class="accueil-suite-surtitre"><?= icone('etincelle', 15) ?> Comment ça marche</p>
          <h3 id="titre-methode">Proposez, échangez, progressez</h3>
        </div>
        <p>Trois gestes suffisent pour transformer une compétence en nouvelle découverte. Le campus devient votre salle de classe.</p>
      </div>
      <ol class="accueil-etapes">
        <li class="accueil-etape">
          <span class="accueil-etape-num" aria-hidden="true">01</span>
          <span class="accueil-etape-icone" aria-hidden="true"><?= icone('offre', 21) ?></span>
          <h4>Proposez</h4>
          <p>Publiez une compétence et un créneau. Votre offre est visible par les étudiants du campus.</p>
          <span class="accueil-etape-meta">Une offre, un créneau</span>
        </li>
        <li class="accueil-etape">
          <span class="accueil-etape-num" aria-hidden="true">02</span>
          <span class="accueil-etape-icone" aria-hidden="true"><?= icone('demandes', 21) ?></span>
          <h4>Échangez</h4>
          <p>Réservez une heure chez un autre étudiant. La réservation bloque le temps, puis le transfert se confirme après la rencontre.</p>
          <span class="accueil-etape-meta">1 h réservée = 1 h bloquée</span>
        </li>
        <li class="accueil-etape">
          <span class="accueil-etape-num" aria-hidden="true">03</span>
          <span class="accueil-etape-icone" aria-hidden="true"><?= icone('etincelle', 21) ?></span>
          <h4>Progressez</h4>
          <p>Apprenez, échangez à votre tour et faites grandir votre portefeuille de temps.</p>
          <span class="accueil-etape-meta">Le savoir circule</span>
        </li>
      </ol>
    </section>

    <aside class="accueil-bonus" aria-labelledby="titre-bonus">
      <span class="accueil-bonus-icone" aria-hidden="true"><?= icone('etincelle', 24) ?></span>
      <div class="accueil-bonus-texte">
        <p class="accueil-bonus-surtitre">Votre premier échange</p>
        <h3 id="titre-bonus">Chaque nouveau compte reçoit <?= jeton(2) ?> de bienvenue.</h3>
        <p>De quoi réserver votre première heure sans attendre d’avoir donné la vôtre.</p>
      </div>
      <a class="bouton bienvenue-entrer" href="<?= e(url('/inscription')) ?>">Créer mon compte <?= icone('fleche', 17) ?></a>
    </aside>

    <section class="accueil-chiffres" aria-labelledby="titre-chiffres">
      <div class="accueil-chiffres-entete">
        <div>
          <p class="accueil-suite-surtitre"><?= icone('etincelle', 15) ?> Le campus en chiffres</p>
          <h3 id="titre-chiffres">Une communauté qui s’entraide</h3>
        </div>
        <p class="accueil-chiffres-note">Chaque offre, chaque rencontre et chaque heure partagée font avancer le campus.</p>
      </div>
      <div class="accueil-statistiques" role="group" aria-label="SkillSwap Campus en chiffres">
        <div class="accueil-stat">
          <span class="accueil-stat-icone" aria-hidden="true"><?= icone('comptes', 19) ?></span>
          <b><?= (int) ($stats['comptes_actifs'] ?? 0) ?></b>
          <span class="accueil-stat-label">étudiants inscrits</span>
          <small>déjà dans la boucle</small>
        </div>
        <div class="accueil-stat">
          <span class="accueil-stat-icone" aria-hidden="true"><?= icone('offre', 19) ?></span>
          <b><?= (int) ($stats['offres_publiees'] ?? 0) ?></b>
          <span class="accueil-stat-label">offres publiées</span>
          <small>compétences à découvrir</small>
        </div>
        <div class="accueil-stat">
          <span class="accueil-stat-icone" aria-hidden="true"><?= icone('horloge', 19) ?></span>
          <b><?= (int) ($stats['credits_transferes'] ?? 0) ?> h</b>
          <span class="accueil-stat-label">heures échangées</span>
          <small>de savoirs transmis</small>
        </div>
      </div>
    </section>

    <section class="accueil-pratique" aria-labelledby="titre-exemple">
      <div class="accueil-pratique-principal">
        <p class="accueil-suite-surtitre"><?= icone('etincelle', 15) ?> Un échange en pratique</p>
        <h3 id="titre-exemple">Le temps donné devient le temps appris.</h3>
        <p class="accueil-pratique-intro">Sara partage son Python. Amine réserve. Sara utilise ensuite son heure pour apprendre l’anglais avec Inès.</p>
        <div class="accueil-parcours" aria-label="Exemple d’échange entre étudiants">
          <div class="accueil-parcours-ligne">
            <span class="accueil-parcours-num" aria-hidden="true">01</span>
            <span class="avatar" aria-hidden="true">SA</span>
            <div><b>Sara publie</b><p>1 h de Python</p></div>
            <?= jeton(1) ?>
          </div>
          <div class="accueil-parcours-ligne">
            <span class="accueil-parcours-num" aria-hidden="true">02</span>
            <span class="avatar" aria-hidden="true">AM</span>
            <div><b>Amine réserve</b><p>1 h bloquée sur son créneau</p></div>
            <span class="badge-doux">Réservé</span>
          </div>
          <div class="accueil-parcours-ligne">
            <span class="accueil-parcours-num" aria-hidden="true">03</span>
            <span class="avatar" aria-hidden="true">IN</span>
            <div><b>Sara apprend</b><p>1 h d’anglais avec Inès</p></div>
            <?= jeton(1) ?>
          </div>
        </div>
      </div>

      <aside class="accueil-carte" aria-labelledby="titre-rejoindre">
        <p class="accueil-carte-kicker">Prêt à tester ?</p>
        <h3 id="titre-rejoindre">Votre première heure vous attend.</h3>
        <p>Créez votre compte en quelques instants. Recevez 2 h de bienvenue et choisissez votre premier échange.</p>
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
    </section>
  </div>
</section>
