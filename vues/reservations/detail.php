<?php
$moi = (int) $utilisateur['id'];
$autre = $jeSuisIntervenant ? $r['beneficiaire'] : $r['intervenant'];
$autreId = $jeSuisIntervenant ? $r['beneficiaire_id'] : $r['intervenant_id'];
$attribuee = in_array($r['etat'], ['ACCEPTEE', 'EN_LITIGE', 'REALISEE'], true);
$action = fn(string $a) => e(url("/reservations/{$r['id']}/$a"));
$faitQqch = false;
?>
<p><a class="retour" href="<?= e(url('/demandes?onglet=' . ($jeSuisIntervenant ? 'recues' : 'envoyees'))) ?>"><?= icone('fleche', 15) ?> Demandes</a></p>

<div class="entete">
  <div>
    <span class="role <?= $jeSuisIntervenant ? 'ANIME' : 'APPREND' ?>"><?= $jeSuisIntervenant ? "J'anime cette séance" : "J'apprends" ?></span>
    <h1 style="margin-top:8px"><?= e($r['titre']) ?></h1>
    <p>Avec <a href="<?= e(url('/profil/' . $autreId)) ?>"><?= e($autre) ?></a> · <?= e(date_longue($r['debut'])) ?>, <?= e(heure($r['debut'])) ?> – <?= e(heure($r['fin'])) ?> · <?= jeton($r['cout']) ?></p>
  </div>
  <?= etat_resa($r['etat']) ?>
</div>

<div class="panneau">
  <ol class="parcours" aria-label="Avancement de la séance" style="padding:0;margin:0">
    <?php foreach ($etapes as [$nom, $statut]): ?><li class="<?= $statut ?>" <?= $statut === 'courant' ? 'aria-current="step"' : '' ?>><?= e($nom) ?></li><?php endforeach; ?>
  </ol>
</div>

<div class="cote" style="margin-top:18px">
  <div>
    <!-- Informations pratiques : RG19, réservées aux participants d'une séance attribuée -->
    <div class="panneau">
      <h2>Informations pratiques</h2>
      <?php if ($attribuee): ?>
        <p><b><?= e(Libelles::MODES[$r['mode']] ?? 'Mode non précisé') ?></b> —
          <?php if ($r['mode'] === 'PRESENTIEL'): ?><?= e($r['lieu']) ?><?php else: ?><a href="<?= e($r['lien']) ?>" target="_blank" rel="noopener"><?= e($r['lien']) ?></a><?php endif; ?></p>
        <p class="discret" style="margin:0">Ces informations ne sont visibles que par vous et <?= e($autre) ?>.</p>
      <?php else: ?>
        <p class="discret" style="margin:0">Le lieu ou le lien de réunion s'affichera ici une fois la séance acceptée.</p>
      <?php endif; ?>
      <?php if ($r['message']): ?><p style="margin:12px 0 0"><b>Message de <?= e($r['beneficiaire']) ?> :</b> « <?= e($r['message']) ?> »</p><?php endif; ?>
      <?php if ($r['motif_refus']): ?><p class="message erreur" style="margin:12px 0 0">Motif du refus : <?= e($r['motif_refus']) ?></p><?php endif; ?>
      <?php if ($r['motif_annulation']): ?><p class="message info" style="margin:12px 0 0"><?= e($r['motif_annulation']) ?></p><?php endif; ?>
    </div>

    <?php if ($attribuee): ?>
    <div class="panneau">
      <h2>Confirmation de réalisation</h2>
      <p class="discret">Après l'heure de fin, chacun confirme que la séance a eu lieu. À la seconde confirmation, les <?= (int) $r['cout'] ?> h bloquées passent de <?= e($r['beneficiaire']) ?> à <?= e($r['intervenant']) ?>.</p>
      <div class="sceaux">
        <?php foreach ([[(int) $r['intervenant_id'], $r['intervenant'], 'Intervenant'], [(int) $r['beneficiaire_id'], $r['beneficiaire'], 'Bénéficiaire']] as [$pid, $nom, $role]): ?>
          <div class="sceau <?= isset($confirmations[$pid]) ? 'pose' : '' ?>">
            <small><?= $role ?></small><b><?= e($nom) ?></b>
            <?php if (isset($confirmations[$pid])): ?>✓ a confirmé le <?= e(date_courte($confirmations[$pid])) ?><?php else: ?><span class="discret">pas encore confirmé</span><?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($litige): ?>
      <div class="panneau" style="border-color:var(--danger)">
        <h2><?= $litige['etat'] === 'OUVERT' ? 'Litige en cours' : 'Litige réglé' ?></h2>
        <p><?= $litige['etat'] === 'OUVERT' ? "Les crédits restent bloqués jusqu'à la décision motivée de l'administration."
            : 'Décision : ' . ($litige['decision'] === 'TRANSFERT' ? "transfert intégral à l'intervenant" : 'restitution intégrale au bénéficiaire') . '.' ?></p>
        <a class="bouton second" href="<?= e(url('/litiges/' . $litige['id'])) ?>">Ouvrir le dossier</a>
      </div>
    <?php endif; ?>

    <?php if ($r['etat'] === 'REALISEE'): ?>
      <div class="panneau">
        <h2>Évaluer <?= e($autre) ?></h2>
        <?php if ($monEvaluation): ?>
          <p style="margin:0">Vous avez donné <span class="etoiles"><?= str_repeat('★', (int) $monEvaluation['note']) ?></span> <?= (int) $monEvaluation['note'] ?>/5<?= $monEvaluation['commentaire'] ? ' : « ' . e($monEvaluation['commentaire']) . ' »' : '' ?></p>
        <?php else: ?>
          <form method="post" action="<?= $action('evaluer') ?>"><?= csrf_champ() ?>
            <fieldset class="champ notation" style="border:0;padding:0"><legend style="font-weight:700;margin-bottom:6px">Votre note</legend>
              <div class="choix"><?php for ($n = 1; $n <= 5; $n++): ?><label><input type="radio" name="note" value="<?= $n ?>" required><span><?= str_repeat('★', $n) ?></span></label><?php endfor; ?></div>
              <output class="discret"></output></fieldset>
            <div class="champ"><label for="commentaire">Commentaire <span class="discret">(facultatif)</span></label>
              <textarea id="commentaire" name="commentaire" maxlength="500" placeholder="Clarté, ponctualité, ambiance…"></textarea></div>
            <button class="bouton" type="submit">Publier mon évaluation</button>
          </form>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- Actions possibles selon l'état, le rôle et l'heure -->
  <aside class="panneau">
    <h3>Que pouvez-vous faire ?</h3>
    <?php if ($r['etat'] === 'EN_ATTENTE' && $jeSuisIntervenant): $faitQqch = true; ?>
      <form method="post" action="<?= $action('accepter') ?>"><?= csrf_champ() ?>
        <button class="bouton succes" type="submit" style="width:100%">Accepter la demande</button>
        <p class="discret"><?= (int) $r['cout'] ?> h seront bloquées sur le compte de <?= e($r['beneficiaire']) ?>. Les autres demandes sur ce créneau seront refusées automatiquement.</p></form>
      <form method="post" action="<?= $action('refuser') ?>"><?= csrf_champ() ?>
        <div class="champ"><label for="motif">Motif du refus</label><input id="motif" type="text" name="motif" required minlength="5"></div>
        <button class="bouton danger" type="submit" style="width:100%">Refuser</button></form>
    <?php endif; ?>
    <?php if ($r['etat'] === 'EN_ATTENTE' && !$jeSuisIntervenant): $faitQqch = true; ?>
      <p class="discret">En attente de la réponse de <?= e($r['intervenant']) ?>. Aucun crédit n'est bloqué.</p>
      <form method="post" action="<?= $action('retirer') ?>"><?= csrf_champ() ?>
        <button class="bouton danger" type="submit" style="width:100%" data-confirmer="Retirer votre demande ?">Retirer ma demande</button></form>
    <?php endif; ?>
    <?php if ($r['etat'] === 'ACCEPTEE' && $maintenant < $r['debut']): $faitQqch = true; ?>
      <form method="post" action="<?= $action('annuler') ?>"><?= csrf_champ() ?>
        <div class="champ"><label for="motif-a">Annuler la séance</label><input id="motif-a" type="text" name="motif" placeholder="Motif (facultatif)"></div>
        <button class="bouton danger" type="submit" style="width:100%" data-confirmer="Annuler cette séance ? Les heures bloquées seront libérées.">Annuler la séance</button>
        <p class="discret">Possible jusqu'au début. Les heures bloquées sont rendues et le créneau redevient réservable.</p></form>
    <?php endif; ?>
    <?php if ($r['etat'] === 'ACCEPTEE' && $maintenant >= $r['debut'] && $maintenant < $r['fin']): $faitQqch = true; ?>
      <p>La séance est en cours. Vous pourrez la confirmer à partir de <?= e(heure($r['fin'])) ?>.</p>
    <?php endif; ?>
    <?php if ($r['etat'] === 'ACCEPTEE' && $maintenant >= $r['fin'] && !isset($confirmations[$moi])): $faitQqch = true; ?>
      <form method="post" action="<?= $action('confirmer') ?>"><?= csrf_champ() ?>
        <button class="bouton succes" type="submit" style="width:100%">Confirmer que la séance a eu lieu</button></form>
    <?php endif; ?>
    <?php if ($r['etat'] === 'ACCEPTEE' && isset($confirmations[$moi])): ?>
      <p class="message succes">Vous avez confirmé. Les heures seront transférées dès que <?= e($autre) ?> confirmera aussi.</p>
    <?php endif; ?>
    <?php if ($r['etat'] === 'ACCEPTEE' && $maintenant >= $r['debut']): $faitQqch = true; ?>
      <details class="tiroir" style="margin-top:14px"><summary class="bouton danger" style="width:100%">Signaler un problème</summary>
        <form method="post" action="<?= $action('signaler') ?>"><?= csrf_champ() ?>
          <div class="champ"><label for="motif-s">Nature du problème</label>
            <select id="motif-s" name="motif">
              <?php foreach (['ABSENCE', 'DESACCORD', 'CONFIRMATION_MANQUANTE'] as $m): ?><option value="<?= $m ?>"><?= e(Libelles::MOTIFS_LITIGE[$m]) ?></option><?php endforeach; ?>
            </select></div>
          <div class="champ"><label for="desc">Ce qui s'est passé</label><textarea id="desc" name="description" required minlength="10"></textarea></div>
          <button class="bouton danger" type="submit">Ouvrir un signalement</button>
          <p class="discret">Le transfert est suspendu : l'administration examinera les éléments des deux parties.</p>
        </form></details>
    <?php endif; ?>
    <?php if (!$faitQqch): ?>
      <p class="discret" style="margin:0"><?= match ($r['etat']) {
          'REALISEE' => "Séance terminée et réglée. Merci d'avoir échangé votre temps !",
          'EN_LITIGE' => 'Ajoutez vos éléments dans le dossier du litige.',
          default => 'Cette demande est close.',
      } ?></p>
    <?php endif; ?>
  </aside>
</div>
