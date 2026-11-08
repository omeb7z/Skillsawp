<?php $maintenant = Horloge::maintenantTxt(App::db()); ?>
<div class="entete">
  <div>
    <h1>Mon planning</h1>
    <p>Les séances acceptées, que vous les animiez ou que vous y participiez. Deux séances ne peuvent pas se chevaucher.</p>
  </div>
</div>
<section class="panneau" aria-label="Séances du planning">
  <?php foreach ($jours as $jour => $seances): ?>
    <section class="jour">
      <div class="date"><b><?= substr($jour, 8, 2) ?></b><?= e(date_longue($jour)) ?></div>
      <div>
        <?php foreach ($seances as $s): ?>
          <a class="seance" style="--cat: <?= e($s['couleur']) ?>" href="<?= e(url('/reservations/' . $s['id'])) ?>">
            <span class="horaire"><?= e(heure($s['debut'])) ?><small>→ <?= e(heure($s['fin'])) ?></small></span>
            <span class="seance-infos"><b><?= e($s['titre']) ?></b> <span class="role <?= e($s['role']) ?>"><?= $s['role'] === 'ANIME' ? "J'anime" : "J'apprends" ?></span><br>
              <span class="discret">avec <?= e($s['autre']) ?> · <?= e(Libelles::MODES[$s['mode']] ?? 'Mode non précisé') ?><?php if ($s['fin'] <= $maintenant && $s['etat'] === 'ACCEPTEE'): ?> · <?= (int) $s['nb_confirmations'] ?>/2 confirmations<?php endif; ?></span></span>
            <?= etat_resa($s['etat']) ?>
          </a>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endforeach; ?>
  <?php if (!$jours): ?>
    <div class="vide">
      <span aria-hidden="true"><?= icone('calendrier', 32) ?></span>
      <p>Votre planning est vide.</p>
      <a class="bouton petit" href="<?= e(url('/catalogue')) ?>">Explorer le catalogue</a>
    </div>
  <?php endif; ?>
</section>
