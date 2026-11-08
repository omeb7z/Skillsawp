<div class="entete">
  <div>
    <h1>Séances pour moi</h1>
    <p>Choisies d’après ce que vous voulez apprendre. Une correspondance exacte sur la compétence passe avant une simple correspondance de catégorie ; la note de l’intervenant départage les ex æquo.</p>
  </div>
  <a class="bouton second" href="<?= e(url('/profil')) ?>#recherchees">Modifier mes envies</a>
</div>

<?php if ($recherchees): ?>
  <p>Vous cherchez : <?php foreach ($recherchees as $c): ?><span class="etiquette"><?= e($c['nom']) ?></span> <?php endforeach; ?></p>
<?php endif; ?>

<?php if ($suggestions): ?>
  <div class="grille g-auto"><?php foreach ($suggestions as $o) echo carte_offre($o, $o['raison'], $o['exacte']); ?></div>
<?php elseif (!$recherchees): ?>
  <div class="vide">
    <span aria-hidden="true"><?= icone('etincelle', 32) ?></span>
    <h3>Dites-nous ce que vous voulez apprendre</h3>
    <p>Cochez quelques compétences recherchées dans votre profil : les suggestions apparaîtront ici.</p>
    <a class="bouton" href="<?= e(url('/profil')) ?>#recherchees">Compléter mon profil</a>
  </div>
<?php else: ?>
  <div class="vide">
    <span aria-hidden="true"><?= icone('recherche', 32) ?></span>
    <h3>Pas encore de séance correspondante</h3>
    <p>Aucune offre publiée avec un créneau libre ne correspond à vos compétences recherchées.</p>
    <a class="bouton second" href="<?= e(url('/catalogue')) ?>">Parcourir le catalogue</a>
  </div>
<?php endif;
