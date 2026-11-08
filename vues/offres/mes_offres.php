<div class="entete">
  <div>
    <h1>Mes offres</h1>
    <p>Brouillon → publiée → archivée. Une offre n’apparaît au catalogue qu’une fois publiée.</p>
  </div>
  <a class="bouton" href="<?= e(url('/offres/nouvelle')) ?>"><?= icone('plus', 17) ?> Proposer une séance</a>
</div>

<?php if ($offres): ?>
  <div class="liste-cartes">
    <?php foreach ($offres as $o): ?>
      <article class="carte-ligne" style="border-left:5px solid <?= e($o['couleur'] ?: 'var(--primaire)') ?>">
        <div class="carte-principal">
          <div class="meta" style="margin-bottom:4px">
            <span class="categorie" style="--cat: <?= e($o['couleur'] ?: 'var(--primaire)') ?>"><?= e($o['categorie'] ?: 'Catégorie à choisir') ?><?= $o['competence'] ? ' · ' . e($o['competence']) : '' ?></span>
            <?= etat_offre($o) ?>
          </div>
          <h3><a href="<?= e(url('/offres/' . $o['id'])) ?>"><?= e($o['titre']) ?></a></h3>
          <div class="meta">
            <?php if ($o['duree']): ?><?= jeton($o['duree']) ?><?php endif; ?>
            <span><?= (int) $o['futurs'] ?> créneau(x) à venir</span>
            <?php if ($o['demandes']): ?><a class="etat attente" href="<?= e(url('/demandes')) ?>"><?= (int) $o['demandes'] ?> demande(s)</a><?php else: ?><span>Aucune demande en attente</span><?php endif; ?>
          </div>
        </div>
        <div class="actions">
          <a class="bouton petit second" href="<?= e(url('/offres/' . $o['id'])) ?>">Ouvrir</a>
          <a class="bouton petit" href="<?= e(url('/offres/' . $o['id'] . '/modifier')) ?>">Modifier</a>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
<?php else: ?>
  <div class="vide">
    <span aria-hidden="true"><?= icone('offre', 32) ?></span>
    <h3>Vous n’avez encore rien proposé</h3>
    <p>Chaque heure animée vous rapporte une heure à dépenser. Que savez-vous expliquer ?</p>
    <a class="bouton" href="<?= e(url('/offres/nouvelle')) ?>">Créer ma première offre</a>
  </div>
<?php endif;
