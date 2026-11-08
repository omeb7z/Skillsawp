<?php
$enAttente = fn(array $l) => count(array_filter($l, fn($d) => $d['etat'] === 'EN_ATTENTE'));
$nbRecues = $enAttente($recues);
$nbEnv = $enAttente($envoyees);
$liste = $onglet === 'recues' ? $recues : $envoyees;
?>
<div class="entete">
  <div>
    <h1>Demandes</h1>
    <p>Les demandes reçues pour vos offres et celles que vous avez envoyées.</p>
  </div>
  <span class="badge-doux"><?= icone('demandes', 16) ?> <?= count($liste) ?> demande(s) dans cette vue</span>
</div>

<nav class="onglets" aria-label="Type de demandes">
  <a href="?onglet=recues" <?= $onglet === 'recues' ? 'aria-current="page"' : '' ?>>Reçues<?= $nbRecues ? " · {$nbRecues} en attente" : '' ?></a>
  <a href="?onglet=envoyees" <?= $onglet === 'envoyees' ? 'aria-current="page"' : '' ?>>Envoyées<?= $nbEnv ? " · {$nbEnv} en attente" : '' ?></a>
</nav>

<?php if ($liste): ?>
  <div class="liste-cartes">
    <?php foreach ($liste as $d): ?>
      <article class="demande-carte">
        <div>
          <div class="meta" style="margin-bottom:4px">
            <?= etat_resa($d['etat']) ?>
            <span><?= e(date_longue($d['debut'])) ?> · <?= e(heure($d['debut'])) ?> – <?= e(heure($d['fin'])) ?></span>
            <?= jeton($d['cout']) ?>
          </div>
          <h3><a href="<?= e(url('/reservations/' . $d['id'])) ?>"><?= e($d['titre']) ?></a></h3>
          <div class="meta">
            <span><?= $onglet === 'recues' ? 'Demandé par' : 'Animé par' ?>
              <a href="<?= e(url('/profil/' . $d['autre_id'])) ?>"><?= e($d['autre']) ?></a></span>
          </div>
          <?php if ($d['message'] && $d['etat'] === 'EN_ATTENTE'): ?><p class="discret" style="margin:8px 0 0">« <?= e($d['message']) ?> »</p><?php endif; ?>
          <?php if ($d['motif_refus'] && $d['etat'] === 'REFUSEE'): ?><p class="discret" style="margin:8px 0 0">Motif : <?= e($d['motif_refus']) ?></p><?php endif; ?>
        </div>
        <div class="actions">
          <?php if ($d['etat'] === 'EN_ATTENTE' && $onglet === 'recues'): ?>
            <form class="enligne" method="post" action="<?= e(url('/reservations/' . $d['id'] . '/accepter')) ?>">
              <?= csrf_champ() ?>
              <input type="hidden" name="retour" value="demandes">
              <button class="bouton petit succes" type="submit">Accepter</button>
            </form>
            <details class="tiroir">
              <summary class="bouton petit danger">Refuser</summary>
              <form method="post" action="<?= e(url('/reservations/' . $d['id'] . '/refuser')) ?>">
                <?= csrf_champ() ?>
                <input type="hidden" name="retour" value="demandes">
                <div class="champ">
                  <label for="m<?= (int) $d['id'] ?>">Motif du refus</label>
                  <input id="m<?= (int) $d['id'] ?>" type="text" name="motif" required minlength="5" placeholder="Indisponible ce jour-là…">
                </div>
                <button class="bouton petit danger" type="submit">Confirmer le refus</button>
              </form>
            </details>
          <?php else: ?>
            <a class="bouton petit second" href="<?= e(url('/reservations/' . $d['id'])) ?>">Ouvrir</a>
          <?php endif; ?>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
<?php else: ?>
  <div class="vide">
    <span aria-hidden="true"><?= icone($onglet === 'recues' ? 'demandes' : 'recherche', 32) ?></span>
    <p><?= $onglet === 'recues' ? 'Personne ne vous a encore sollicité. Publiez une offre pour recevoir des demandes.' : "Vous n’avez envoyé aucune demande." ?></p>
    <a class="bouton petit" href="<?= e(url($onglet === 'recues' ? '/mes-offres' : '/catalogue')) ?>"><?= $onglet === 'recues' ? 'Mes offres' : 'Explorer le catalogue' ?></a>
  </div>
<?php endif;
