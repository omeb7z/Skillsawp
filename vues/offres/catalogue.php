<div class="entete">
  <div>
    <h1>Catalogue des séances</h1>
    <p>Offres publiées par les étudiants. Filtrez par compétence, catégorie, niveau, date ou mode.</p>
  </div>
  <a class="bouton second" href="<?= e(url('/suggestions')) ?>"><?= icone('etincelle', 17) ?> Séances pour moi</a>
</div>

<form class="panneau" method="get" role="search" aria-labelledby="titre-filtres">
  <h2 id="titre-filtres" class="sr-only">Filtrer le catalogue</h2>
  <div class="ligne">
    <div class="champ champ-large">
      <label for="q">Compétence ou mot-clé</label>
      <input id="q" type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Python, anglais, prise de parole…">
    </div>
    <div class="champ">
      <label for="categorie">Catégorie</label>
      <select id="categorie" name="categorie">
        <option value="">Toutes</option>
        <?php foreach ($categories as $c): ?><option value="<?= (int) $c['id'] ?>" <?= (int) $f['categorie'] === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['nom']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="champ">
      <label for="niveau">Niveau</label>
      <select id="niveau" name="niveau">
        <option value="">Tous</option>
        <?php foreach (Libelles::NIVEAUX as $k => $l): ?><option value="<?= $k ?>" <?= $f['niveau'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="champ">
      <label for="mode">Mode</label>
      <select id="mode" name="mode">
        <option value="">Tous</option>
        <?php foreach (Libelles::MODES as $k => $l): ?><option value="<?= $k ?>" <?= $f['mode'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="champ">
      <label for="date">Date</label>
      <input id="date" type="date" name="date" value="<?= e($f['date']) ?>">
    </div>
  </div>
  <div class="actions">
    <button class="bouton" type="submit"><?= icone('recherche', 17) ?> Rechercher</button>
    <?php if ($rechercheActive): ?><a class="bouton second" href="<?= e(url('/catalogue')) ?>">Effacer les filtres</a><?php endif; ?>
    <span class="discret" role="status" aria-live="polite"><?= count($resultats) ?> offre(s)</span>
  </div>
</form>

<div class="marge-haut">
  <?php if ($resultats): ?>
    <div class="grille g-auto"><?php foreach ($resultats as $o) echo carte_offre($o); ?></div>
  <?php else: ?>
    <div class="vide">
      <span aria-hidden="true"><?= icone('recherche', 32) ?></span>
      <h3>Aucune offre ne correspond à ces critères</h3>
      <p>Élargissez la recherche (autre date, autre niveau) ou proposez vous-même une séance sur ce sujet.</p>
      <div class="actions" style="justify-content:center">
        <a class="bouton second" href="<?= e(url('/catalogue')) ?>">Voir toutes les offres</a>
        <?php if ($utilisateur['role'] === 'ETUDIANT'): ?><a class="bouton" href="<?= e(url('/offres/nouvelle')) ?>">Proposer une séance</a><?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
</div>
