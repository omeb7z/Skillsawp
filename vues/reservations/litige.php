<?php $estAdmin = $utilisateur['role'] === 'ADMIN'; ?>
<p><a class="retour" href="<?= e(url($estAdmin ? '/admin/litiges' : '/reservations/' . $l['reservation_id'])) ?>"><?= icone('fleche', 15) ?> <?= $estAdmin ? 'Tous les litiges' : 'Retour à la séance' ?></a></p>
<div class="entete">
  <div>
    <h1>Dossier n°<?= (int) $l['id'] ?> : <?= e($l['titre']) ?></h1>
    <p><?= e(Libelles::MOTIFS_LITIGE[$l['motif']]) ?> · ouvert par <?= e($l['auteur']) ?> le <?= e(date_courte($l['ouvert_le'])) ?></p>
  </div>
  <span class="etat <?= $l['etat'] === 'OUVERT' ? 'litige' : 'fait' ?>"><?= $l['etat'] === 'OUVERT' ? 'Ouvert' : 'Réglé' ?></span>
</div>

<div class="cote">
  <div>
    <div class="panneau">
      <h2>Éléments des deux parties</h2>
      <p class="discret">Ce dossier n'est visible que par <?= e($l['intervenant']) ?>, <?= e($l['beneficiaire']) ?> et l'administration.</p>
      <div class="fil">
        <?php foreach ($elements as $el):
            $cls = $el['role'] === 'ADMIN' ? 'adm' : ((int) $el['auteur_id'] === (int) $l['intervenant_id'] ? 'int' : 'ben');
            $qui = ['adm' => 'administration', 'int' => 'intervenant', 'ben' => 'bénéficiaire'][$cls]; ?>
          <div class="element <?= $cls ?>">
            <b><?= e($el['auteur']) ?></b> <small>— <?= $qui ?>, <?= e(date_courte($el['cree_le'])) ?></small>
            <p style="margin:4px 0 0;white-space:pre-line"><?= e($el['texte']) ?></p>
          </div>
        <?php endforeach; ?>
      </div>
      <?php if ($l['etat'] === 'OUVERT'): ?>
        <form method="post" action="<?= e(url('/litiges/' . $l['id'] . '/elements')) ?>" style="margin-top:18px"><?= csrf_champ() ?>
          <div class="champ"><label for="texte"><?= $estAdmin ? 'Note ou question aux parties' : 'Ajouter votre version des faits' ?></label>
            <textarea id="texte" name="texte" required minlength="5"></textarea></div>
          <button class="bouton second" type="submit">Ajouter au dossier</button>
        </form>
      <?php endif; ?>
    </div>
  </div>

  <aside>
    <div class="panneau">
      <h3>Enjeu</h3>
      <p><?= jeton($l['cout'], true) ?> en jeu sur la séance.</p>
      <p class="discret" style="margin:0"><?php if ($l['etat'] === 'OUVERT'): ?>Ces heures restent bloquées sur le compte de <?= e($l['beneficiaire']) ?> jusqu'à la décision.<?php else: ?>La décision a été appliquée : les crédits ne sont plus bloqués.<?php endif; ?><br>
        Séance du <?= e(date_courte($l['debut'])) ?> au <?= e(heure($l['fin'])) ?>.<br>
        Intervenant : <?= e($l['intervenant']) ?><br>Bénéficiaire : <?= e($l['beneficiaire']) ?></p>
    </div>
    <?php if ($l['etat'] === 'REGLE'): ?>
      <div class="panneau decision">
        <h3>Décision définitive</h3>
        <p><b><?= $l['decision'] === 'TRANSFERT' ? 'Transfert intégral à ' . e($l['intervenant']) : 'Restitution intégrale à ' . e($l['beneficiaire']) ?></b></p>
        <p style="white-space:pre-line"><?= e($l['justification']) ?></p>
        <small><?= e($l['admin']) ?>, le <?= e(date_courte($l['regle_le'])) ?></small>
      </div>
    <?php elseif ($estAdmin): ?>
      <form class="panneau decision" method="post" action="<?= e(url('/admin/litiges/' . $l['id'] . '/decider')) ?>"><?= csrf_champ() ?>
        <h3>Décider</h3>
        <p class="discret">Règlement intégral et définitif : aucun partage, aucun second règlement.</p>
        <div class="choix" style="flex-direction:column;margin-bottom:14px">
          <label><input type="radio" name="decision" value="TRANSFERT" required><span>Transférer <?= (int) $l['cout'] ?> h à <?= e($l['intervenant']) ?> (séance réalisée)</span></label>
          <label><input type="radio" name="decision" value="RESTITUTION"><span>Restituer <?= (int) $l['cout'] ?> h à <?= e($l['beneficiaire']) ?> (séance annulée)</span></label>
        </div>
        <div class="champ"><label for="just">Motivation de la décision</label>
          <textarea id="just" name="justification" required minlength="10" placeholder="Éléments retenus, raisons de la décision…"></textarea></div>
        <button class="bouton" type="submit" data-confirmer="Ce règlement est définitif. Confirmer la décision ?">Appliquer la décision</button>
      </form>
    <?php else: ?>
      <div class="panneau"><p class="discret" style="margin:0">L'administration étudie le dossier. Vous serez notifié de sa décision.</p></div>
    <?php endif; ?>
  </aside>
</div>
