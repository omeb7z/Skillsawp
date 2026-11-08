<?php $nonLues = count(array_filter($liste, fn($n) => !(int) $n['lue'])); ?>
<div class="entete">
  <div>
    <h1>Notifications</h1>
    <p>Décisions, annulations, confirmations et litiges vous concernant.</p>
  </div>
  <?php if ($nonLues): ?>
    <form method="post" action="<?= e(url('/notifications/lire')) ?>">
      <?= csrf_champ() ?>
      <button class="bouton second" type="submit">Tout marquer comme lu</button>
    </form>
  <?php endif; ?>
</div>
<section class="panneau" aria-labelledby="titre-liste-notifications">
  <h2 id="titre-liste-notifications" class="sr-only">Liste des notifications</h2>
  <?php if ($liste): ?>
    <div class="notifications-liste">
      <?php foreach ($liste as $n): ?>
        <a class="notification-lien<?= (int) $n['lue'] ? '' : ' non-lue' ?>" href="<?= e(url('/notifications/' . $n['id'])) ?>">
          <span class="notification-point" aria-hidden="true"></span>
          <span class="notification-texte"><?= e($n['message']) ?></span>
          <span class="notification-date"><?= e(date_courte($n['cree_le'])) ?></span>
          <span class="sr-only"><?= (int) $n['lue'] ? '' : 'Non lue. ' ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="vide">
      <span aria-hidden="true"><?= icone('cloche', 32) ?></span>
      <p>Rien de nouveau pour l’instant.</p>
    </div>
  <?php endif; ?>
</section>
