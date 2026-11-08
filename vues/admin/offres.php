<div class="entete"><div><h1>Modération des offres</h1><p>Masquer une offre l'ôte du catalogue et annule ses demandes en attente ; ses séances déjà acceptées continuent normalement.</p></div></div>
<nav class="filtres" aria-label="Filtrer les offres">
  <?php foreach (['publiees' => 'Publiées', 'masquees' => 'Masquées', 'toutes' => 'Toutes'] as $k => $lib): ?>
    <a class="bouton <?= $filtre === $k ? '' : 'second' ?>" href="?filtre=<?= $k ?>"<?= $filtre === $k ? ' aria-current="page"' : '' ?>><?= $lib ?></a><?php endforeach; ?>
</nav>
<div class="panneau tableau-wrap">
  <table>
    <caption class="sr-only">Offres soumises à la modération</caption>
    <thead><tr><th>Offre</th><th>Intervenant</th><th>État</th><th>Modération</th></tr></thead>
    <tbody>
    <?php foreach ($offres as $o): ?>
      <tr>
        <td><a href="<?= e(url('/offres/' . $o['id'])) ?>"><b><?= e($o['titre']) ?></b></a><br><small><?= e($o['categorie'] ?: '—') ?> · <?= e($o['competence'] ?: '—') ?></small></td>
        <td><?= e($o['intervenant']) ?></td>
        <td><?= etat_offre($o) ?><?php if ((int) $o['masquee']): ?><br><small><?= e($o['motif_masquage']) ?></small><?php endif; ?></td>
        <td><?php if ((int) $o['masquee']): ?>
            <form method="post" action="<?= e(url('/admin/offres/' . $o['id'] . '/reafficher')) ?>"><?= csrf_champ() ?><button class="bouton petit second" type="submit">Réafficher</button></form>
          <?php else: ?>
            <details class="tiroir"><summary class="bouton petit danger">Masquer</summary>
              <form method="post" action="<?= e(url('/admin/offres/' . $o['id'] . '/masquer')) ?>"><?= csrf_champ() ?>
                <input type="text" name="motif" placeholder="Motif" aria-label="Motif de masquage" required minlength="5">
                <button class="bouton petit danger marge-haut-s" type="submit">Confirmer</button></form></details>
          <?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$offres): ?><tr><td colspan="4" class="discret">Aucune offre dans cette liste.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
