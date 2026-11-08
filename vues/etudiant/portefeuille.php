<?php
$solde = (new ServiceComptes(App::db()))->portefeuille((int) $utilisateur['id']) ?? ['disponible' => 0, 'bloque' => 0];
$echelle = max((int) $solde['disponible'] + (int) $solde['bloque'], 6);
$hHaut = round($solde['disponible'] / $echelle * 100) * 1.16;
$hBas = round($solde['bloque'] / $echelle * 100) * 1.16;
?>
<div class="entete"><div><h1>Mon portefeuille de temps</h1>
  <p>1 crédit = 1 heure. Le sable du haut est disponible ; celui qui s'écoule est réservé pour des séances acceptées.</p></div></div>

<div class="panneau coffre">
  <svg class="sablier" viewBox="0 0 200 290" role="img"
       aria-label="<?= (int) $solde['disponible'] ?> heures disponibles et <?= (int) $solde['bloque'] ?> heures bloquées">
    <defs>
      <clipPath id="haut"><path d="M28 24 H172 Q172 92 108 140 H92 Q28 92 28 24 Z"/></clipPath>
      <clipPath id="bas"><path d="M92 150 H108 Q172 198 172 266 H28 Q28 198 92 150 Z"/></clipPath>
    </defs>
    <rect x="14" y="8" width="172" height="14" rx="5" fill="var(--encre)"/>
    <rect x="14" y="268" width="172" height="14" rx="5" fill="var(--encre)"/>
    <g clip-path="url(#haut)"><rect class="sable-haut" x="20" y="<?= 140 - $hHaut ?>" width="160" height="<?= $hHaut + 2 ?>" fill="var(--accent)"/></g>
    <g clip-path="url(#bas)"><rect class="sable-bas" x="20" y="<?= 266 - $hBas ?>" width="160" height="<?= $hBas + 2 ?>" fill="var(--accent)"/></g>
    <?php if ($solde['bloque']): ?><line class="filet" x1="100" y1="140" x2="100" y2="<?= 264 - $hBas ?>" stroke="var(--accent)" stroke-width="3" stroke-dasharray="3 3"/><?php endif; ?>
    <path d="M28 24 H172 Q172 92 108 140 V150 Q172 198 172 266 H28 Q28 198 92 150 V140 Q28 92 28 24 Z"
          fill="none" stroke="var(--primaire)" stroke-width="4" stroke-linejoin="round"/>
  </svg>
  <div>
    <div class="compteurs">
      <div class="compteur dispo">Disponible<b><?= (int) $solde['disponible'] ?> h</b><span class="discret">utilisable pour réserver</span></div>
      <div class="compteur">Bloqué<b><?= (int) $solde['bloque'] ?> h</b><span class="discret">réservé pour vos séances acceptées</span></div>
      <div class="compteur">Gagné en animant<b class="plus">+<?= $gagnes ?> h</b></div>
      <div class="compteur">Dépensé en apprenant<b class="moins">−<?= $depenses ?> h</b></div>
    </div>
  </div>
</div>

<div class="panneau">
  <h2>Livret des mouvements</h2>
  <?php if ($mouvements): ?>
  <div class="tableau-wrap">
    <table class="livret">
      <caption class="sr-only">Livret des mouvements d’heures</caption>
      <thead><tr><th>Date</th><th>Type</th><th>Séance concernée</th><th>Quantité</th><th>Disponible après</th></tr></thead>
      <tbody>
      <?php foreach ($mouvements as $m): [$type, $explication] = Libelles::TYPES_MOUVEMENT[$m['type']]; ?>
        <tr>
          <td><?= e(date_courte($m['cree_le'])) ?></td>
          <td><span class="type-mvt"><?= e($type) ?></span><br><small><?= e($explication) ?></small></td>
          <td><?php if ($m['reservation_id']): ?><a href="<?= e(url('/reservations/' . $m['reservation_id'])) ?>"><?= e($m['offre_titre']) ?></a>
              <br><small>séance du <?= e(date_courte($m['seance_debut'])) ?></small><?php else: ?>—<?php endif; ?></td>
          <td>
            <?php if ($m['effet_disponible'] > 0): ?><span class="plus">+<?= (int) $m['quantite'] ?> h</span>
            <?php elseif ($m['effet_disponible'] < 0): ?><span class="moins">−<?= (int) $m['quantite'] ?> h</span>
            <?php else: ?><span class="moins">−<?= (int) $m['quantite'] ?> h</span> <small>(bloqué)</small><?php endif; ?>
          </td>
          <td class="solde"><?= $soldes[$m['id']] ?> h</td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?><div class="vide"><p>Aucun mouvement pour l'instant.</p></div><?php endif; ?>
</div>
