<div class="entete"><div><h1>Catégories et compétences</h1><p>La couleur d'une catégorie marque toutes ses offres dans l'application.</p></div></div>
<div class="cote">
  <div>
    <?php foreach ($categories as $k): $kid = (int) $k['id']; ?>
      <div class="panneau carte-categorie" style="--cat: <?= e($k['couleur']) ?>">
        <div class="entete compact">
          <h2 class="titre-serre"><?= e($k['nom']) ?> <?php if (!(int) $k['active']): ?><span class="etat neutre">Masquée</span><?php endif; ?></h2>
          <form method="post" class="enligne"><?= csrf_champ() ?><input type="hidden" name="action" value="basculer"><input type="hidden" name="id" value="<?= $kid ?>">
            <button class="bouton petit second" type="submit"><?= (int) $k['active'] ? 'Masquer' : 'Réactiver' ?></button></form>
        </div>
        <p class="discret"><?= e($k['description']) ?></p>
        <div class="etiquettes marge-bas-s"><?php foreach ($comps[$kid] ?? [] as $c): ?><span class="etiquette"><?= e($c['nom']) ?></span><?php endforeach; ?></div>
        <div class="actions">
          <form method="post" class="actions"><?= csrf_champ() ?><input type="hidden" name="action" value="competence"><input type="hidden" name="id" value="<?= $kid ?>">
            <input class="saisie-competence" type="text" name="nom" placeholder="Nouvelle compétence" aria-label="Nouvelle compétence pour <?= e($k['nom']) ?>" required>
            <button class="bouton petit" type="submit">Ajouter</button></form>
          <details class="tiroir"><summary class="bouton petit second">Modifier</summary>
            <form method="post"><?= csrf_champ() ?><input type="hidden" name="action" value="modifier"><input type="hidden" name="id" value="<?= $kid ?>">
              <div class="ligne"><input type="text" name="nom" value="<?= e($k['nom']) ?>" aria-label="Nom"><input type="color" name="couleur" value="<?= e($k['couleur']) ?>" aria-label="Couleur"></div>
              <input class="marge-haut-s" type="text" name="description" value="<?= e($k['description']) ?>" aria-label="Description">
              <button class="bouton petit marge-haut-s" type="submit">Enregistrer</button></form></details>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <form class="panneau" method="post"><?= csrf_champ() ?>
    <input type="hidden" name="action" value="creer">
    <h2>Nouvelle catégorie</h2>
    <div class="champ"><label for="nom">Nom</label><input id="nom" type="text" name="nom" required></div>
    <div class="champ"><label for="desc">Description</label><input id="desc" type="text" name="description"></div>
    <div class="champ"><label for="coul">Couleur</label><input id="coul" type="color" name="couleur" value="#2a9d8f" style="height:44px;width:80px"></div>
    <button class="bouton" type="submit">Créer la catégorie</button>
  </form>
</div>
