<section class="panneau" aria-labelledby="titre-profil-public">
  <div class="profil-entete">
    <span class="avatar grand" aria-hidden="true"><?= e(initiales($p['prenom'] . ' ' . $p['nom'])) ?></span>
    <div>
      <h1 id="titre-profil-public"><?= e($p['prenom'] . ' ' . $p['nom']) ?></h1>
      <p class="discret"><?= e($p['filiere']) ?> · membre depuis le <?= e(date_longue($p['cree_le'])) ?></p>
      <div><?= etoiles($note[0]) ?> <span class="discret">sur <?= $note[1] ?> avis</span></div>
    </div>
    <?php if (!(int) $p['actif']): ?><span class="etat refus">Compte désactivé</span><?php endif; ?>
  </div>
</section>
<?php if ($p['bio']): ?><div class="panneau profil-bio"><p style="margin:0"><?= e($p['bio']) ?></p></div><?php endif; ?>

<div class="grille g2" style="margin-top:18px">
  <?php foreach ([['Peut transmettre', $proposees, 'offre'], ['Souhaite apprendre', $recherchees, 'etincelle']] as [$t, $liste, $nomIcone]): ?>
    <section class="panneau">
      <h2><?= icone($nomIcone, 18) ?> <?= $t ?></h2>
      <div class="etiquettes">
        <?php foreach ($liste as $c): ?><span class="etiquette"><?= e($c['nom']) ?></span><?php endforeach; ?>
        <?php if (!$liste): ?><span class="discret">Non renseigné</span><?php endif; ?>
      </div>
    </section>
  <?php endforeach; ?>
</div>

<?php if ($offres): ?>
  <h2 style="margin-top:26px">Ses offres</h2>
  <div class="grille g-auto"><?php foreach ($offres as $o) echo carte_offre($o); ?></div>
<?php endif; ?>

<h2 style="margin-top:26px">Évaluations reçues</h2>
<section class="panneau fil" aria-label="Évaluations reçues">
  <?php foreach ($evaluations as $ev): ?>
    <div class="element <?= $ev['role'] === 'intervenant' ? 'int' : 'ben' ?>">
      <div><?= etoiles($ev['note']) ?> <span class="discret">— <?= e($ev['auteur']) ?>, en tant que <?= e($ev['role']) ?> pour « <?= e($ev['titre']) ?> », le <?= e(date_longue($ev['cree_le'])) ?></span></div>
      <?php if ($ev['commentaire']): ?><p style="margin:6px 0 0"><?= e($ev['commentaire']) ?></p><?php endif; ?>
    </div>
  <?php endforeach; ?>
  <?php if (!$evaluations): ?><p class="discret" style="margin:0">Aucune évaluation pour l’instant.</p><?php endif; ?>
</section>
