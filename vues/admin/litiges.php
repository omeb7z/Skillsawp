<div class="entete"><div><h1>Litiges</h1><p>Chaque litige se règle par une décision motivée : transfert intégral ou restitution intégrale, une seule fois.</p></div></div>
<div class="panneau tableau-wrap">
  <table>
    <caption class="sr-only">Litiges et décisions de règlement</caption>
    <thead><tr><th>Séance</th><th>Parties</th><th>Motif</th><th>Enjeu</th><th>État</th></tr></thead>
    <tbody>
    <?php foreach ($litiges as $l): ?>
      <tr>
        <td><a href="<?= e(url('/litiges/' . $l['id'])) ?>"><b><?= e($l['titre']) ?></b></a><br><small>ouvert le <?= e(date_courte($l['ouvert_le'])) ?> · <?= (int) $l['nb_elements'] ?> élément(s)</small></td>
        <td><?= e($l['intervenant']) ?> <small>(intervenant)</small><br><?= e($l['beneficiaire']) ?> <small>(bénéficiaire)</small></td>
        <td><?= e(Libelles::MOTIFS_LITIGE[$l['motif']]) ?></td>
        <td><?= jeton($l['cout']) ?></td>
        <td><?php if ($l['etat'] === 'OUVERT'): ?><span class="etat litige">À trancher</span>
          <?php else: ?><span class="etat fait"><?= $l['decision'] === 'TRANSFERT' ? 'Transfert' : 'Restitution' ?></span><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$litiges): ?><tr><td colspan="5" class="discret">Aucun litige.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
